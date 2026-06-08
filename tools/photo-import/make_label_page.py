#!/usr/bin/env python3
"""Self-contained HTML page for labelling face clusters — MERGE-BY-NAME.

Each cluster's name field is a combobox (dropdown of every name already entered,
plus your partner names pre-loaded). Picking an existing name = "same person" =
merged; a new name joins the dropdown for every other cluster. No person-numbers,
no select-then-merge. Cards sharing a name share a colour. Download → people.json.

Args: <clusters.json> [out.html] [max_clusters] [people.json prefill] [partners.json]
"""
import base64, io, json, os, sys
from PIL import Image
import pillow_heif; pillow_heif.register_heif_opener()

SRC = sys.argv[1] if len(sys.argv) > 1 else "out/cr2015/face_clusters.json"
OUT = sys.argv[2] if len(sys.argv) > 2 else "out/cr2015/label.html"
MAXC = int(sys.argv[3]) if len(sys.argv) > 3 else 80
PREFILL = sys.argv[4] if len(sys.argv) > 4 else None
PARTNERS = sys.argv[5] if len(sys.argv) > 5 else os.path.join(os.path.dirname(__file__), "partners.json")
SAVE_URL = sys.argv[6] if len(sys.argv) > 6 else ""   # if set, page POSTs labels here instead of downloading
CROPS, TH = 6, 96

pf = {}
if PREFILL:
    try:
        for p in json.load(open(PREFILL))["people"]:
            # resolve old merge_into prefills to the lead's name so they pre-fill by name
            pf[p["person"]] = p
    except Exception:
        pass
# old-style merges: give the merged card its lead's name so it shows grouped
if pf:
    for p in list(pf.values()):
        mi = p.get("merge_into")
        if mi and mi in pf and not p.get("name"):
            p["name"] = pf[mi].get("name", "")
            p["relationship"] = pf[mi].get("relationship", "unset")

partner_names = []
try:
    partner_names = [p["name"] for p in json.load(open(PARTNERS)).get("partners", [])]
except Exception:
    pass

# Pre-load the name dropdown with EVERY person already known across all years
# (from the registry) + all partners, so cross-year names stay consistent — you
# pick "Arthur Craig" the same way in 2017 as in 2024.
registry_names = []
try:
    reg = json.load(open(os.path.join(os.path.dirname(__file__), "registry.json")))
    registry_names = [p["name"] for p in reg.get("people", [])]
except Exception:
    pass
known_names = sorted(set(partner_names) | set(registry_names))

RELS = ["unset", "me", "partner", "family", "friend", "other", "ignore"]
REL_LABEL = {"unset": "— relationship —", "other": "other / acquaintance",
             "ignore": "ignore (bystander / not a person)"}


def crop_b64(path, bbox, pad=0.3):
    with Image.open(path) as im:
        im = im.convert("RGB")
    x1, y1, x2, y2 = bbox; bw, bh = x2-x1, y2-y1
    c = im.crop((max(0,int(x1-bw*pad)), max(0,int(y1-bh*pad)),
                 min(im.width,int(x2+bw*pad)), min(im.height,int(y2+bh*pad))))
    c.thumbnail((TH, TH)); buf = io.BytesIO(); c.save(buf, "JPEG", quality=80)
    return base64.b64encode(buf.getvalue()).decode()


data = json.load(open(SRC))
clusters = [c for c in data["clusters"] if c["n"] >= 2][:MAXC]

# Full-photo previews for the lightbox: click a face crop → see the whole original
# photo it came from. Deduped by source path; HEIC decoded by Pillow so it renders
# in the browser; embedded so the page is self-contained (no online-only issues).
_full_cache, FULLS = {}, []
def full_id(path):
    if path in _full_cache:
        return _full_cache[path]
    uri = ""
    try:
        with Image.open(path) as im:
            im = im.convert("RGB"); im.thumbnail((1100, 1100))
            b = io.BytesIO(); im.save(b, "JPEG", quality=78)
            uri = "data:image/jpeg;base64," + base64.b64encode(b.getvalue()).decode()
    except Exception:
        uri = ""
    i = len(FULLS); FULLS.append(uri); _full_cache[path] = i
    return i

blocks = []
for c in clusters:
    imgs = ""
    for f in c["faces"][:CROPS]:
        try:
            fid = full_id(f["path"])
            imgs += (f'<img src="data:image/jpeg;base64,{crop_b64(f["path"], f["bbox"])}" '
                     f'data-full="{fid}" onclick="showFull(event,{fid})">')
        except Exception:
            pass
    pid = c["person"]; saved = pf.get(pid, {})
    name_val = saved.get("name") or ""
    cur_rel = saved.get("relationship", "unset") or "unset"
    until_val = saved.get("until") or ""
    then_rel = saved.get("then") or "unset"
    opts = "".join(f'<option value="{r}"{" selected" if r==cur_rel else ""}>{REL_LABEL.get(r,r)}</option>' for r in RELS)
    then_opts = "".join(f'<option value="{r}"{" selected" if r==then_rel else ""}>{REL_LABEL.get(r,r)}</option>' for r in RELS)
    blocks.append(f'''<div class="person" data-person="{pid}">
      <div class="hd">#{pid} <span>{c['n']} faces</span></div>
      <div class="faces">{imgs}</div>
      <div class="fields">
        <input class="name" list="roster" autocomplete="off" autocapitalize="off" spellcheck="false" data-1p-ignore data-lpignore="true" placeholder="who is this? (pick or type)" value="{name_val}" oninput="onName(this)">
        <select class="rel" onchange="syncName(this)">{opts}</select>
        <div class="when"><span>until</span><input class="until" type="month" autocomplete="off" data-1p-ignore data-lpignore="true" value="{until_val}"><span>then</span><select class="then">{then_opts}</select></div>
      </div>
    </div>''')

STYLE = """
 body{background:#141416;color:#e6e6ea;font:15px/1.4 system-ui,Segoe UI,sans-serif;margin:0;padding:24px}
 h1{font-size:20px;margin:.2em 0}.sub{color:#9aa;margin-bottom:14px;max-width:840px}
 .bar{position:sticky;top:0;background:#141416ee;backdrop-filter:blur(4px);padding:12px 0;border-bottom:1px solid #2a2a30;margin-bottom:16px;z-index:9;display:flex;gap:12px;align-items:center;flex-wrap:wrap}
 button.primary{background:#22c55e;color:#06210f;border:0;border-radius:8px;padding:10px 18px;font-weight:700;font-size:15px;cursor:pointer}
 .btn{text-decoration:none;border-radius:8px;padding:10px 14px;font-weight:700;font-size:14px}
 .btn.dash{background:#334155;color:#e6e6ea}
 .hint{color:#9aa;font-size:13px}
 .person{position:relative;display:flex;gap:16px;align-items:center;padding:10px 10px 10px 14px;border:1px solid #26262c;border-left:6px solid #26262c;border-radius:10px;margin-bottom:10px;background:#1a1a1e}
 .hd{width:90px;font-weight:600;color:#bbb}.hd span{display:block;color:#8a8;font-weight:400;font-size:12px}
 .faces img{width:96px;height:96px;object-fit:cover;border-radius:6px;margin-right:4px;cursor:zoom-in}
 #lb{display:none;position:fixed;inset:0;background:#000e;z-index:99;align-items:center;justify-content:center;cursor:zoom-out}
 #lb img{max-width:94vw;max-height:94vh;border-radius:8px;box-shadow:0 10px 50px #000}
 #lb .tip{position:fixed;top:14px;left:0;right:0;text-align:center;color:#9aa;font-size:13px}
 .fields{margin-left:auto;display:flex;flex-direction:column;gap:6px;min-width:300px}
 input,select{background:#101013;color:#e6e6ea;border:1px solid #33333a;border-radius:6px;padding:8px}
 .name{font-size:15px}
 .when{display:flex;align-items:center;gap:5px;font-size:12px;color:#9aa}.when input,.when select{padding:5px;font-size:12px}
 .person.partner .name{border-color:#ec4899}
"""

SCRIPT = """
const PARTNERS = __PARTNERS__;   // partner names (lowercased) from the timeline
function cards(){ return [...document.querySelectorAll('.person')]; }
function norm(s){ return (s||'').trim().toLowerCase(); }
function isPartner(name){ const n=norm(name); return PARTNERS.some(p => n===p || n.startsWith(p+' ') || p.startsWith(n+' ') && n); }
function color(name){ let h=0; for(const ch of norm(name)) h=(h*31+ch.charCodeAt(0))%360; return `hsl(${h} 65% 55%)`; }

function refreshRoster(){
  const names = new Set();
  cards().forEach(c => { const v=c.querySelector('.name').value.trim(); if(v) names.add(v); });
  __PARTNER_DISPLAY__.forEach(n => names.add(n));
  const dl = document.getElementById('roster');
  dl.innerHTML = [...names].sort().map(n => `<option value="${n.replace(/"/g,'&quot;')}">`).join('');
}
function relFor(name){   // existing relationship already chosen for this name elsewhere
  const n=norm(name);
  for(const c of cards()){ if(norm(c.querySelector('.name').value)===n){ const r=c.querySelector('.rel').value; if(r!=='unset') return r; } }
  return null;
}
function onName(inp){
  const card = inp.closest('.person'), name = inp.value.trim();
  card.style.borderLeftColor = name ? color(name) : '#26262c';
  card.classList.toggle('partner', isPartner(name));
  const rel = card.querySelector('.rel');
  // relationship is a property of the NAME: partners auto-tag; otherwise inherit
  // whatever this name already resolves to elsewhere (one relationship per name).
  if(isPartner(name)) rel.value='partner';
  else { const ex=relFor(name); if(ex) rel.value=ex; }
  refreshRoster();
}
function syncName(sel){   // changing a relationship updates EVERY card with the same name
  const card=sel.closest('.person'), n=norm(card.querySelector('.name').value);
  if(!n) return;
  cards().forEach(c => { if(norm(c.querySelector('.name').value)===n) c.querySelector('.rel').value=sel.value; });
}
function buildPeople(){
  return cards().map(c => {
    const name=c.querySelector('.name').value.trim(), rel=c.querySelector('.rel').value;
    const until=c.querySelector('.until').value, then=c.querySelector('.then').value;
    return { person:+c.dataset.person, name, relationship:rel,
             until:until||null, then:(until&&then!=='unset')?then:null, merge_into:null };
  }).filter(x => x.name || x.relationship!=='unset');
}
function save(){
  const blob=new Blob([JSON.stringify({people:buildPeople()},null,2)],{type:'application/json'});
  const a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download='people.json';a.click();
}
const SAVE_URL="__SAVE_URL__";
function saveServer(){
  const btn=document.getElementById('saveBtn'), res=document.getElementById('result');
  btn.disabled=true; btn.textContent='⏳ saving & posting…'; res.textContent='';
  fetch(SAVE_URL, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({people:buildPeople()})})
    .then(r=>r.json())
    .then(d=>{ btn.disabled=false; btn.textContent='💾 Save & apply'; res.innerHTML='✓ '+(d.message||'done')+' &nbsp; <a href="/" style="color:#7dd3fc">back to dashboard</a>'; })
    .catch(e=>{ btn.disabled=false; btn.textContent='💾 Save & apply'; res.textContent='error: '+e; });
}
function init(){ cards().forEach(c => onName(c.querySelector('.name'))); refreshRoster(); }
const FULL = __FULLS__;   // full-photo previews, indexed by data-full id
function showFull(e, id){
  e.stopPropagation();
  const lb = document.getElementById('lb');
  document.getElementById('lbimg').src = FULL[id] || '';
  lb.style.display = 'flex';
}
"""

HTML = """<!doctype html><html><head><meta charset="utf-8"><title>Face labelling — Family Timeline</title>
<style>__STYLE__</style></head><body>
<datalist id="roster"></datalist>
<div id="lb" onclick="this.style.display='none'"><div class="tip">click anywhere to close</div><img id="lbimg"></div>
<div class="bar">__DASH_LINK__<button class="primary" id="saveBtn" onclick="__SAVE_ACTION__">__SAVE_LABEL__</button>
  <span id="result" class="hint"></span>
  <span class="hint">Name each person worth tagging — <b>pick from the dropdown</b> to reuse a name (that merges the clusters), or type a new one. Partners are pre-loaded and auto-tagged. Leave the rest blank.</span>
</div>
<h1>Who is who — 2015 roster</h1>
<div class="sub">Same person split across rows? Just give them the <b>same name</b> (the dropdown shows everyone you've named so far) — matching names share a colour and merge automatically. Relationship auto-fills when you reuse a name; partners come from your timeline.</div>
__BLOCKS__
<script>__SCRIPT__</script><script>init()</script>
</body></html>"""

script = (SCRIPT
          .replace("__PARTNERS__", json.dumps([n.lower() for n in partner_names]))
          .replace("__PARTNER_DISPLAY__", json.dumps(known_names))
          .replace("__FULLS__", json.dumps(FULLS))
          .replace("__SAVE_URL__", SAVE_URL))
html = (HTML.replace("__STYLE__", STYLE).replace("__SCRIPT__", script)
            .replace("__BLOCKS__", "\n".join(blocks))
            .replace("__SAVE_ACTION__", "saveServer()" if SAVE_URL else "save()")
            .replace("__SAVE_LABEL__", "💾 Save &amp; apply" if SAVE_URL else "⬇ Download people.json")
            .replace("__DASH_LINK__", '<a class="btn dash" href="/">← Dashboard</a>' if SAVE_URL else ''))
open(OUT, "w", encoding="utf-8").write(html)
print(OUT, f"{len(clusters)} clusters, {len(partner_names)} partners pre-loaded")
