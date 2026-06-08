#!/usr/bin/env python3
"""Build a contact sheet: one row per event showing the photos the captioner
actually saw (sample_paths), labelled with date / title / category, so the
who-axis classification can be eyeballed against the real images."""
import json, os, sys
from PIL import Image, ImageDraw, ImageFont
import pillow_heif; pillow_heif.register_heif_opener()

CAP = sys.argv[1] if len(sys.argv) > 1 else "out/cr2015/events_captioned.json"
DRY = sys.argv[2] if len(sys.argv) > 2 else "out/cr2015/events_dryrun.json"
OUT = sys.argv[3] if len(sys.argv) > 3 else "out/cr2015/contact_sheet.png"

THUMB = 200; GAP = 6; LABEL_W = 300; FONT_PATH = "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"
try:
    f_big = ImageFont.truetype(FONT_PATH, 18); f_sm = ImageFont.truetype(FONT_PATH, 14)
except Exception:
    f_big = f_sm = ImageFont.load_default()

cap = json.load(open(CAP))["events"]
dry = {e["start"][:10]: e for e in json.load(open(DRY))["events"]}

def thumb(path):
    try:
        im = Image.open(path).convert("RGB"); im.thumbnail((THUMB, THUMB)); return im
    except Exception:
        ph = Image.new("RGB", (THUMB, THUMB), (40, 40, 40))
        ImageDraw.Draw(ph).text((10, 90), "missing", fill=(150, 150, 150), font=f_sm); return ph

rows = []
for e in cap:
    date = e["date"]; ai = e.get("ai") or {}
    paths = (dry.get(date, {}).get("sample_paths") or [e.get("rep_path")])[:5]
    thumbs = [thumb(p) for p in paths if p]
    rows.append((date, ai.get("title", ""), ai.get("category", ""), e.get("photo_count"), thumbs))

row_h = THUMB + GAP
W = LABEL_W + 5 * (THUMB + GAP) + GAP
H = row_h * len(rows) + GAP
sheet = Image.new("RGB", (W, H), (18, 18, 20))
d = ImageDraw.Draw(sheet)

for i, (date, title, cat, n, thumbs) in enumerate(rows):
    y = GAP + i * row_h
    d.text((10, y + 8), date, fill=(255, 255, 255), font=f_big)
    d.text((10, y + 34), f"[{cat}]", fill=(120, 220, 140), font=f_big)
    # wrap title
    words = (title or "").split(); line = ""; ty = y + 64
    for w in words:
        if len(line + " " + w) > 32:
            d.text((10, ty), line, fill=(200, 200, 205), font=f_sm); ty += 18; line = w
        else:
            line = (line + " " + w).strip()
    d.text((10, ty), line, fill=(200, 200, 205), font=f_sm)
    d.text((10, y + THUMB - 16), f"{n} photos", fill=(120, 120, 128), font=f_sm)
    for j, t in enumerate(thumbs):
        x = LABEL_W + j * (THUMB + GAP)
        sheet.paste(t, (x, y + (THUMB - t.height) // 2))

sheet.save(OUT, quality=88)
print(OUT, f"{W}x{H}")
