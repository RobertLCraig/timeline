#!/usr/bin/env python3
"""
Family Timeline — photo-import: STEP 3b (EVENT-level captioning).

The per-image captioner (caption.py) describes one frame well but mislabels the
*event* — "lakeside view" for what was a whole day at the Lakes. This version
gives gemma4 more to work with:
  - SEVERAL photos sampled across the day (harvest.py's sample_paths), not one;
  - the FACTS it's allowed to state: the date, the photo count, and the
    reverse-geocoded PLACE from GPS (offline, no network).
…and asks for a grounded EVENT title — what the day involved — while forbidding
invented occasions, names, trips, or any place/detail not given or visible.

Output schema matches caption.py (events_captioned.json) so post.py is unchanged.

Usage (from WSL):
  ./.venv/bin/python caption_event.py --report out/cr2015/events_dryrun.json --limit 15 --min-photos 8
"""
from __future__ import annotations

import argparse
import base64
import io
import json
import os
import sys
import urllib.request

from PIL import Image
import pillow_heif
pillow_heif.register_heif_opener()

OLLAMA = "http://localhost:11434/api/generate"
MODEL = "gemma4:latest"
# Milestones (when clearly visible) + the who-axis (Family/Couple/Social/Solo) +
# Places (no people). The who-axis is the everyday default; see prompt priority.
CATEGORIES = ["Birth", "Move", "Anniversary", "Graduation", "Milestone",
              "Wedding", "Travel", "Career", "Health",
              "Family", "Couple", "Social", "Solo", "Places", "Other"]


# 2-letter country codes → readable names (extend as new regions appear).
COUNTRY = {
    "US": "United States", "GB": "United Kingdom", "PH": "Philippines",
    "SG": "Singapore", "FR": "France", "ES": "Spain", "IT": "Italy",
    "DE": "Germany", "IE": "Ireland", "NL": "Netherlands", "PT": "Portugal",
    "GR": "Greece", "BE": "Belgium", "CH": "Switzerland", "AT": "Austria",
}


def _fmt_place(hit) -> str:
    """Readable 'Town, Region[, Country]'. The raw cc ('GB') reads like a code in a
    title, so map it to a name — and drop it entirely for UK home nations, whose
    admin1 (England/Wales/Scotland) already implies the country."""
    name, admin1, cc = hit.get("name"), hit.get("admin1"), hit.get("cc")
    parts = [name, admin1]
    if cc and cc != "GB":
        parts.append(COUNTRY.get(cc, cc))
    return ", ".join(p for p in parts if p)


def place_lookup(events):
    """Reverse-geocode each event's centroid to a readable place (offline).
    Returns a dict id(event)->place string. Loaded lazily; one batch search."""
    import reverse_geocoder as rg
    coords, idx = [], []
    for i, e in enumerate(events):
        c = e.get("centroid")
        if c and -90 <= c[0] <= 90 and -180 <= c[1] <= 180:
            coords.append((c[0], c[1])); idx.append(i)
    if not coords:
        return {}
    hits = rg.search(coords)   # offline k-d tree over a world cities DB
    return {j: _fmt_place(hit) for j, hit in zip(idx, hits)}


def downscale_b64(path: str, max_px: int = 768) -> str:
    with Image.open(path) as im:
        im = im.convert("RGB")
        im.thumbnail((max_px, max_px))
        buf = io.BytesIO()
        im.save(buf, format="JPEG", quality=82)
    return base64.b64encode(buf.getvalue()).decode()


def build_prompt(date: str, count: int, place: str | None) -> str:
    facts = [f"- Date: {date}", f"- Total photos from this day: {count}"]
    if place:
        facts.append(f"- GPS location resolves to: {place}")
    return (
        "These photos were all taken on the SAME day by one family. Look across ALL "
        "of them together and label the day as a single timeline event.\n\n"
        "Facts you may use (and only these):\n" + "\n".join(facts) + "\n\n"
        "Write a grounded EVENT label — what this day appears to have involved — "
        "suitable for a family timeline.\n"
        "STRICT RULES — read carefully:\n"
        "1. Base it ONLY on what is visible across the photos plus the facts above.\n"
        "2. PLACE NAMES: you may use the EXACT place text in the facts, verbatim. Do "
        "NOT substitute a region, landmark, lake, or nearby place for it (e.g. do not "
        "turn 'Penrith' into 'the Lake District').\n"
        "3. If NO GPS location is given above, you MUST NOT name ANY place, town, "
        "venue, building, manor, park, or landmark. Describe the setting only "
        "generically (e.g. 'a garden', 'indoors', 'by a lake', 'a city street').\n"
        "4. Output NO proper noun (no invented names of people, places, or venues) "
        "that does not appear in the facts above.\n"
        "5. Do NOT assert an occasion (birthday/wedding/graduation/anniversary) "
        "unless it is clearly, unambiguously visible. When unsure, stay general.\n\n"
        "Return STRICT JSON with exactly:\n"
        '  "title": a specific, VARIED event label, max 10 words. Do NOT start with '
        '"Family" or "Family day" — lead with the place or the main activity '
        '(e.g. "Day trip to Penrith", "Lakeside afternoon", "Lunch out in Cranleigh").\n'
        '  "description": one factual sentence about the day\n'
        f'  "category": exactly one of {CATEGORIES}, chosen by this priority:\n'
        "       (a) a clearly visible life MILESTONE → Wedding, Graduation, Birth, "
        "Anniversary, Move, Career, Health, Milestone;\n"
        "       (b) otherwise classify by WHO is in the photos → Family (relatives / "
        "mixed generations together), Couple (mainly two adults together), Social (a "
        "wider group of friends or a party), Solo (one person);\n"
        "       (c) NO people visible (scenery, places, food, objects) → Places;\n"
        "       (d) the title already states the place, so use Travel only for a clear "
        "trip where the people focus is unclear; use Other only if nothing fits.\n"
        '  "is_meaningful": true if a real-life day worth keeping, false if screenshots/'
        'documents/memes/junk\n'
        '  "noteworthy": true if this is a moment worth REMEMBERING on a family timeline '
        '(people together, a gathering, an outing/trip, a celebration, a milestone, a '
        'notable place); false if it is everyday LOGISTICS or admin nobody looks back on '
        '— shopping, product/packaging photos, medicines or supplies, paperwork, receipts, '
        'screenshots, chores, a photo taken just to record information.\n'
        "Return only the JSON object."
    )


def caption_event(paths: list[str], date: str, count: int, place: str | None) -> dict:
    imgs = []
    for p in paths:
        if p and os.path.exists(p):
            try:
                imgs.append(downscale_b64(p))
            except Exception:
                pass
    if not imgs:
        return {"error": "no usable images"}
    payload = {
        "model": MODEL, "prompt": build_prompt(date, count, place),
        "images": imgs, "stream": False, "format": "json",
        "options": {"temperature": 0.1},
    }
    req = urllib.request.Request(OLLAMA, data=json.dumps(payload).encode(),
                                headers={"Content-Type": "application/json"})
    with urllib.request.urlopen(req, timeout=300) as r:
        body = json.loads(r.read())
    raw = (body.get("response") or "").strip()
    try:
        out = json.loads(raw)
    except json.JSONDecodeError:
        out = {"title": None, "description": raw[:200], "category": "Other",
               "is_meaningful": None, "_parse_error": True}
    out["_eval_s"] = round(body.get("eval_duration", 0) / 1e9, 1)
    out["_n_images"] = len(imgs)
    if place:
        out["place"] = place
    return out


def select(events, limit, min_photos):
    cands = [(i, e) for i, e in enumerate(events)
             if e.get("junk_ratio", 1) < 0.5 and e.get("photo_count", 0) >= min_photos
             and (e.get("sample_paths") or e.get("rep_path"))]
    cands.sort(key=lambda t: -t[1]["photo_count"])
    return cands[:limit]


def main():
    ap = argparse.ArgumentParser(description="Event-level captioning (multi-photo + geocoded place).")
    ap.add_argument("--report", required=True)
    ap.add_argument("--limit", type=int, default=15)
    ap.add_argument("--min-photos", type=int, default=3)
    ap.add_argument("--out", default=None)
    args = ap.parse_args()

    report = json.load(open(args.report))
    events = report["events"]
    chosen = select(events, args.limit, args.min_photos)
    places = place_lookup([e for _, e in chosen])
    # remap place keys (place_lookup indexed the chosen sublist)
    place_by_event = {chosen[k][0]: v for k, v in places.items()}

    print(f"Event-captioning {len(chosen)} events with {MODEL} (multi-photo + place) …", file=sys.stderr)
    enriched = []
    for sub_i, (orig_i, e) in enumerate(chosen):
        date = e["start"][:10]
        place = place_by_event.get(orig_i)
        paths = e.get("sample_paths") or [e.get("rep_path")]
        ai = caption_event(paths, date, e["photo_count"], place)
        enriched.append({"date": date, "photo_count": e["photo_count"],
                         "confidence": e["confidence"], "gps": e.get("centroid"),
                         "rep_path": e.get("rep_path"), "ai": ai})
        loc = f" @ {place}" if place else ""
        print(f"  {date}  ({ai.get('_n_images','?')}img{loc})  {ai.get('title')}  [{ai.get('category')}]",
              file=sys.stderr)

    out = args.out or os.path.join(os.path.dirname(args.report), "events_captioned.json")
    json.dump({"source_report": args.report, "model": MODEL, "events": enriched},
              open(out, "w"), indent=2)
    print(f"\nWrote {out}", file=sys.stderr)


if __name__ == "__main__":
    main()
