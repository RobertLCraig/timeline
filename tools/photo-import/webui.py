#!/usr/bin/env python3
"""
Family Timeline — photo-import control panel.

A small local web app that ties the whole workflow together so you never touch
the shell or download/copy people.json again:

  - Dashboard of every year: status, event count, the partner for that year, and
    the next thing to do — with natural year-by-year progression.
  - Click a year → its label page (family pre-named from the registry); name the
    partner / new faces and hit "Save & apply" → the server runs roster + post +
    registry refresh and the events land on the timeline. No download.
  - Years not processed yet have a "Process" button that runs the full pipeline
    (slice → prewarm → faces → match → caption → roster → post) in the background.

Run:  ./.venv/bin/python webui.py   →   open http://localhost:5000
"""
import json, os, subprocess, threading, collections, html
from flask import Flask, request, jsonify, send_file, redirect

BASE = os.path.dirname(os.path.abspath(__file__))
PY = os.path.join(BASE, ".venv", "bin", "python")
FULL = os.path.join(BASE, "out", "full", "events_dryrun.json")
PARTNERS = os.path.join(BASE, "partners.json")
REGISTRY = os.path.join(BASE, "registry.json")
SLUG = "photo-import-sandbox-LHCveq"
TIMELINE_URL = "https://timeline.enhanceify.co.uk/g/" + SLUG

app = Flask(__name__)
jobs = {}   # yr -> {"running":bool, "log":str}


def ydir(yr):
    return os.path.join(BASE, "out", "cr2015" if yr == "2015" else yr)


def years():
    ev = json.load(open(FULL))["events"]
    c = collections.Counter(e["start"][:4] for e in ev if e["start"][:4].isdigit())
    return dict(sorted(c.items()))


def partners_for(yr):
    P = json.load(open(PARTNERS))["partners"]
    return [p["name"] for p in P if p["from"] <= yr and (p["until"] is None or yr <= p["until"])]


def _name_match(a, b):
    a, b = a.lower().strip(), b.lower().strip()
    return a == b or a.startswith(b + " ") or b.startswith(a + " ")


def status(yr):
    d = ydir(yr)
    processed = os.path.exists(os.path.join(d, "face_clusters.json"))
    pjson = os.path.join(d, "people.json")
    people = json.load(open(pjson))["people"] if os.path.exists(pjson) else []
    n_named = len([p for p in people if (p.get("name") or "").strip()])
    parts = partners_for(yr)
    labelled_names = [(p.get("name") or "") for p in people]
    part_done = [pn for pn in parts if any(_name_match(ln, pn) for ln in labelled_names)]
    part_todo = [pn for pn in parts if pn not in part_done]
    posted = os.path.exists(os.path.join(d, "events_captioned.json"))
    if jobs.get(yr, {}).get("running"):
        state, todo = "processing", "running the pipeline…"
    elif not processed:
        state, todo = "new", "Process this year"
    elif part_todo:
        state, todo = "label", "Label partner: " + ", ".join(part_todo)
    else:
        state, todo = "done", "Review / re-label"
    return dict(yr=yr, processed=processed, posted=posted, n_named=n_named,
                partners=parts, part_todo=part_todo, state=state, todo=todo)


# ── Pipeline runners ─────────────────────────────────────────────────────────
def run_process(yr):
    jobs[yr] = {"running": True, "log": ""}
    try:
        p = subprocess.run(["bash", os.path.join(BASE, "process_years.sh"), yr],
                           cwd=BASE, capture_output=True, text=True, timeout=3600)
        jobs[yr] = {"running": False, "log": (p.stdout + p.stderr)[-4000:]}
    except Exception as e:
        jobs[yr] = {"running": False, "log": f"ERROR: {e}"}


def apply_labels(yr, people):
    """Save people.json, then roster + post + refresh registry."""
    d = ydir(yr)
    json.dump({"people": people}, open(os.path.join(d, "people.json"), "w"), indent=2)
    rep = os.path.join(d, "events_dryrun.json")
    cl = os.path.join(d, "face_clusters.json")
    pe = os.path.join(d, "people.json")
    cap = os.path.join(d, "events_captioned.json")
    out = []
    r = subprocess.run([PY, "roster.py", "--report", rep, "--clusters", cl, "--people", pe, "--captioned", cap],
                       cwd=BASE, capture_output=True, text=True)
    p = subprocess.run([PY, "post.py", "--captioned", cap, "--group", SLUG],
                       cwd=BASE, capture_output=True, text=True)
    out.append(p.stdout.strip().splitlines()[-1] if p.stdout.strip() else "")
    # refresh registry across all labelled years
    pairs = []
    for y in years():
        dd = ydir(y)
        if os.path.exists(os.path.join(dd, "people.json")) and os.path.exists(os.path.join(dd, "face_clusters.json")):
            pairs += [os.path.join(dd, "people.json"), os.path.join(dd, "face_clusters.json")]
    if pairs:
        subprocess.run([PY, "build_registry.py", REGISTRY] + pairs, cwd=BASE, capture_output=True, text=True)
    return " | ".join(x for x in out if x)


# ── Routes ───────────────────────────────────────────────────────────────────
@app.get("/")
def dashboard():
    yc = years()
    rows = ""
    next_year = None
    for yr, n in yc.items():
        s = status(yr)
        if next_year is None and s["state"] in ("new", "label"):
            next_year = yr
        badge = {"new": "#64748b", "processing": "#f59e0b", "label": "#22c55e", "done": "#3b82f6"}[s["state"]]
        parts = ", ".join(s["partners"]) or "—"
        if s["state"] == "new":
            action = f'<button onclick="proc(\'{yr}\')">▶ Process</button>'
        elif s["state"] == "processing":
            action = '<span class="muted">…</span>'
        else:
            action = f'<a class="btn" href="/label/{yr}">🏷 Label / review</a>'
        rows += (f'<tr id="row-{yr}"><td class="yr">{yr}</td><td>{n}</td>'
                 f'<td><span class="dot" style="background:{badge}"></span>{html.escape(s["todo"])}</td>'
                 f'<td>{html.escape(parts)}</td><td>{action}</td></tr>')
    nxt = f'<p class="next">Next up: <b>{next_year}</b> — {html.escape(status(next_year)["todo"])}</p>' if next_year else '<p class="next">🎉 All years done.</p>'
    return PAGE.replace("__ROWS__", rows).replace("__NEXT__", nxt).replace("__TL__", TIMELINE_URL)


def gen_label(yr):
    """(Re)generate the label page with a save-to-server button. Slow for big
    years (embeds full-photo previews), so cached and pre-generated."""
    d = ydir(yr)
    cl = os.path.join(d, "face_clusters.json")
    if not os.path.exists(cl):
        return None
    pre = os.path.join(d, "people.json")
    out_html = os.path.join(d, "label.html")
    subprocess.run([PY, "make_label_page.py", cl, out_html, "80",
                    pre if os.path.exists(pre) else "", PARTNERS, f"/save/{yr}"],
                   cwd=BASE, capture_output=True, text=True)
    return out_html


def stale(yr):
    d = ydir(yr)
    h, p = os.path.join(d, "label.html"), os.path.join(d, "people.json")
    if not os.path.exists(h):
        return True
    if os.path.exists(p) and os.path.getmtime(p) > os.path.getmtime(h):
        return True
    # registry grew (new people to offer in the dropdown) → refresh the page
    if os.path.exists(REGISTRY) and os.path.getmtime(REGISTRY) > os.path.getmtime(h):
        return True
    # an old page generated without the save-to-server button must be regenerated
    try:
        with open(h, encoding="utf-8") as f:
            return "saveServer()" not in f.read(20000)
    except Exception:
        return True


@app.get("/label/<yr>")
def label(yr):
    d = ydir(yr)
    if not os.path.exists(os.path.join(d, "face_clusters.json")):
        return redirect("/")
    out_html = os.path.join(d, "label.html")
    if not os.path.exists(out_html):
        gen_label(yr)                       # first time: generate synchronously
    elif stale(yr):
        threading.Thread(target=gen_label, args=(yr,), daemon=True).start()  # refresh for next time
    return send_file(out_html)


@app.post("/save/<yr>")
def save(yr):
    people = (request.get_json(force=True) or {}).get("people", [])
    msg = apply_labels(yr, people)
    threading.Thread(target=gen_label, args=(yr,), daemon=True).start()  # refresh page for next visit
    return jsonify(message=f"Saved & posted {yr}. {msg}")


@app.post("/process/<yr>")
def process(yr):
    if not jobs.get(yr, {}).get("running"):
        threading.Thread(target=run_process, args=(yr,), daemon=True).start()
    return jsonify(ok=True)


@app.get("/status/<yr>")
def stat(yr):
    j = jobs.get(yr, {})
    return jsonify(running=j.get("running", False), state=status(yr)["state"], log=j.get("log", "")[-1500:])


PAGE = """<!doctype html><html><head><meta charset="utf-8"><title>Photo-import control panel</title>
<style>
 body{background:#0f0f12;color:#e6e6ea;font:15px/1.5 system-ui,Segoe UI,sans-serif;margin:0;padding:28px;max-width:920px}
 h1{font-size:22px}.next{background:#1a1a20;border:1px solid #2a2a33;border-radius:10px;padding:12px 16px}
 a{color:#7dd3fc}.muted{color:#888}
 table{width:100%;border-collapse:collapse;margin-top:14px}
 th,td{text-align:left;padding:10px 12px;border-bottom:1px solid #23232b}
 th{color:#9aa;font-weight:600;font-size:13px}.yr{font-weight:700;font-size:16px}
 .dot{display:inline-block;width:9px;height:9px;border-radius:50%;margin-right:8px}
 button,.btn{background:#22c55e;color:#06210f;border:0;border-radius:7px;padding:7px 13px;font-weight:700;font-size:14px;cursor:pointer;text-decoration:none}
 button:hover,.btn:hover{filter:brightness(1.1)}
 .top{display:flex;justify-content:space-between;align-items:center}
</style></head><body>
<div class="top"><h1>📸 Photo-import — control panel</h1><a class="btn" href="__TL__" target="_blank">Open timeline ↗</a></div>
__NEXT__
<table><thead><tr><th>Year</th><th>Events</th><th>Status / to do</th><th>Partner(s)</th><th></th></tr></thead>
<tbody>__ROWS__</tbody></table>
<p class="muted" style="margin-top:18px">Family is recognised automatically from the registry as you go — you only ever name the new partner and any new faces, then "Save &amp; apply".</p>
<script>
function proc(yr){
  if(!confirm('Process '+yr+'? This pulls photos + runs the pipeline (~15 min).'))return;
  fetch('/process/'+yr,{method:'POST'});
  const cell=document.querySelector('#row-'+yr+' td:nth-child(3)');
  cell.innerHTML='<span class="dot" style="background:#f59e0b"></span>processing…';
  const t=setInterval(()=>fetch('/status/'+yr).then(r=>r.json()).then(d=>{
    if(!d.running){clearInterval(t); location.reload();}
  }),5000);
}
</script>
</body></html>"""

def pregen_all():
    for yr in years():
        if os.path.exists(os.path.join(ydir(yr), "face_clusters.json")) and stale(yr):
            gen_label(yr)


if __name__ == "__main__":
    port = int(os.environ.get("PORT", "5050"))
    threading.Thread(target=pregen_all, daemon=True).start()   # warm the label-page cache
    app.run(host="127.0.0.1", port=port, threaded=True)
