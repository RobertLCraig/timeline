#!/usr/bin/env python3
"""
Family Timeline — photo-import: STEP 3 (caption representatives, local vision).

Reads a harvest report (events_dryrun.json), picks the most significant events,
hydrates ONLY each event's one representative image, and asks the local gemma4
vision model (via WSL2 Ollama) for a factual caption + a constrained category.

Discipline (see README guardrail): the model describes ONLY what is visible. It
must NOT invent location, names, dates, or a trip/journey. The authoritative DATE
comes from the harvest metadata, never the model. GPS coordinates, if present, are
carried as raw metadata — never turned into an asserted place name here.

Cost control: one image hydrated + downscaled per captioned event (not per photo),
and only for the N events you choose — so this stays in the hundreds, not 67k.

Usage (from WSL):
  ./.venv/bin/python caption.py --report out/cr2015/events_dryrun.json --limit 15
  ./.venv/bin/python caption.py --report out/full/events_dryrun.json --limit 50 --min-photos 10
"""
from __future__ import annotations

import argparse
import base64
import io
import json
import os
import sys
import urllib.request
from datetime import datetime

from PIL import Image
import pillow_heif
pillow_heif.register_heif_opener()

OLLAMA = "http://localhost:11434/api/generate"
MODEL = "gemma4:latest"
CATEGORIES = ["Birth", "Move", "Anniversary", "Graduation", "Milestone",
              "Wedding", "Travel", "Career", "Health", "Other"]

PROMPT = (
    "You are labelling a single family photo. Describe ONLY what is visibly in "
    "this image. Do NOT guess the location, place names, people's names, the "
    "date, or any backstory, trip or event that is not literally visible.\n\n"
    "Return STRICT JSON with exactly these keys:\n"
    '  "title": a short factual caption, max 8 words, of what is shown\n'
    '  "description": one plain sentence describing what is visible\n'
    f'  "category": exactly one of {CATEGORIES} (use "Other" if unsure)\n'
    '  "is_meaningful": true if this looks like a real-life moment worth keeping, '
    'false if it is a screenshot, document, meme, artwork, or junk\n'
    "Return only the JSON object, nothing else."
)


def downscale_b64(path: str, max_px: int = 1024) -> str:
    with Image.open(path) as im:
        im = im.convert("RGB")
        im.thumbnail((max_px, max_px))
        buf = io.BytesIO()
        im.save(buf, format="JPEG", quality=85)
    return base64.b64encode(buf.getvalue()).decode()


def caption_image(path: str) -> dict:
    payload = {
        "model": MODEL, "prompt": PROMPT, "images": [downscale_b64(path)],
        "stream": False, "format": "json", "options": {"temperature": 0.1},
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
    return out


def select_events(events: list[dict], limit: int, min_photos: int) -> list[dict]:
    cands = [e for e in events if e.get("junk_ratio", 1) < 0.5
             and e.get("photo_count", 0) >= min_photos
             and e.get("rep_path")]
    # Most-photographed first = most likely to matter.
    cands.sort(key=lambda e: -e["photo_count"])
    return cands[:limit]


def main():
    ap = argparse.ArgumentParser(description="Caption representative photos for harvested events (local vision).")
    ap.add_argument("--report", required=True, help="Path to an events_dryrun.json from harvest.py")
    ap.add_argument("--limit", type=int, default=15, help="How many events to caption (default 15)")
    ap.add_argument("--min-photos", type=int, default=3, help="Skip events with fewer than N photos (default 3)")
    ap.add_argument("--out", default=None, help="Output JSON (default: alongside the report as events_captioned.json)")
    args = ap.parse_args()

    report = json.load(open(args.report))
    chosen = select_events(report["events"], args.limit, args.min_photos)
    print(f"Captioning {len(chosen)} of {len(report['events'])} events "
          f"(min_photos={args.min_photos}) with {MODEL} …", file=sys.stderr)

    enriched = []
    for i, e in enumerate(chosen, 1):
        date = e["start"][:10]            # authoritative date from metadata
        path = e["rep_path"]
        rec = {"date": date, "photo_count": e["photo_count"],
               "confidence": e["confidence"], "gps": e.get("centroid"),
               "rep_path": path, "ai": None}
        if not os.path.exists(path):
            rec["ai"] = {"error": "representative file not found on disk"}
            print(f"  [{i}/{len(chosen)}] {date}  MISSING  {os.path.basename(path)}", file=sys.stderr)
        else:
            try:
                ai = caption_image(path)          # hydrates this one file
                rec["ai"] = ai
                print(f"  [{i}/{len(chosen)}] {date}  ({e['photo_count']}p, {ai.get('_eval_s')}s)  "
                      f"{ai.get('title')}  [{ai.get('category')}]", file=sys.stderr)
            except Exception as ex:
                rec["ai"] = {"error": str(ex)}
                print(f"  [{i}/{len(chosen)}] {date}  ERROR {ex}", file=sys.stderr)
        enriched.append(rec)

    out = args.out or os.path.join(os.path.dirname(args.report), "events_captioned.json")
    json.dump({"source_report": args.report, "model": MODEL, "events": enriched},
              open(out, "w"), indent=2)
    print(f"\nWrote {out}", file=sys.stderr)


if __name__ == "__main__":
    main()
