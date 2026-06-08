#!/usr/bin/env python3
"""Render the top-N face clusters (the people who recur most) into one sheet for
labelling. Reads face_clusters.json from faces.py."""
import json, sys
from PIL import Image, ImageDraw, ImageFont
import pillow_heif; pillow_heif.register_heif_opener()

SRC = sys.argv[1] if len(sys.argv) > 1 else "out/cr2015/face_clusters.json"
OUT = sys.argv[2] if len(sys.argv) > 2 else "out/cr2015/top_roster.png"
TOPN = int(sys.argv[3]) if len(sys.argv) > 3 else 24
THUMB, GAP, LABEL_W, PERROW = 112, 6, 170, 14

try:
    font = ImageFont.truetype("/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf", 18)
    small = ImageFont.truetype("/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf", 13)
except Exception:
    font = small = ImageFont.load_default()

data = json.load(open(SRC))
clusters = [c for c in data["clusters"] if c["n"] >= 2][:TOPN]

def crop(path, bbox, pad=0.3):
    with Image.open(path) as im:
        im = im.convert("RGB")
    x1, y1, x2, y2 = bbox; bw, bh = x2 - x1, y2 - y1
    box = (max(0, int(x1 - bw*pad)), max(0, int(y1 - bh*pad)),
           min(im.width, int(x2 + bw*pad)), min(im.height, int(y2 + bh*pad)))
    c = im.crop(box); c.thumbnail((THUMB, THUMB))
    sq = Image.new("RGB", (THUMB, THUMB), (30, 30, 34))
    sq.paste(c, ((THUMB - c.width)//2, (THUMB - c.height)//2))
    return sq

W = LABEL_W + PERROW*(THUMB+GAP) + GAP
H = GAP + len(clusters)*(THUMB+GAP)
sheet = Image.new("RGB", (W, H), (18, 18, 20))
d = ImageDraw.Draw(sheet)
for r, c in enumerate(clusters):
    y = GAP + r*(THUMB+GAP)
    d.text((10, y+10), f"Person {c['person']}", fill=(255,255,255), font=font)
    d.text((10, y+34), f"{c['n']} faces", fill=(150,200,160), font=small)
    for j, f in enumerate(c["faces"][:PERROW]):
        try:
            sheet.paste(crop(f["path"], f["bbox"]), (LABEL_W + j*(THUMB+GAP), y))
        except Exception:
            pass
sheet.save(OUT, quality=88)
print(OUT, f"{W}x{H}", f"top {len(clusters)} clusters")
