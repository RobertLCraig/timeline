#!/usr/bin/env python3
"""De-risk step 3: confirm gemma4 vision works via Ollama, with a no-narration prompt.

Takes one image path, downscales it (so we send ~1024px, not a 4 MB original),
base64-encodes it, and asks gemma4 to describe ONLY what is visible — no guessing
location, names, dates, or events. Prints the raw model JSON.

Usage: ./.venv/bin/python test_vision.py "<image path>"
"""
import base64
import io
import json
import sys
import urllib.request

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
    "date, or any backstory or event that is not literally visible.\n\n"
    "Return STRICT JSON with exactly these keys:\n"
    '  "title": a short factual caption, max 8 words, of what is shown\n'
    '  "description": one plain sentence describing what is visible\n'
    f'  "category": exactly one of {CATEGORIES} (use "Other" if unsure)\n'
    '  "is_meaningful": true if this looks like a real-life moment worth keeping, '
    'false if it is a screenshot, document, meme, or junk\n'
    "Return only the JSON object, nothing else."
)


def downscale_b64(path: str, max_px: int = 1024) -> str:
    with Image.open(path) as im:
        im = im.convert("RGB")
        im.thumbnail((max_px, max_px))
        buf = io.BytesIO()
        im.save(buf, format="JPEG", quality=85)
    return base64.b64encode(buf.getvalue()).decode()


def caption(path: str) -> dict:
    payload = {
        "model": MODEL,
        "prompt": PROMPT,
        "images": [downscale_b64(path)],
        "stream": False,
        "format": "json",
        "options": {"temperature": 0.1},
    }
    req = urllib.request.Request(
        OLLAMA, data=json.dumps(payload).encode(),
        headers={"Content-Type": "application/json"})
    with urllib.request.urlopen(req, timeout=300) as r:
        body = json.loads(r.read())
    return body


if __name__ == "__main__":
    path = sys.argv[1]
    print(f"image: {path}", file=sys.stderr)
    body = caption(path)
    print("=== model 'response' field ===")
    print(body.get("response", "").strip())
    print("\n=== timing ===")
    for k in ("total_duration", "load_duration", "eval_count", "eval_duration"):
        if k in body:
            v = body[k]
            print(f"  {k}: {v/1e9:.1f}s" if "duration" in k else f"  {k}: {v}")
