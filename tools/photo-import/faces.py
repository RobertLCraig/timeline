#!/usr/bin/env python3
"""
Family Timeline — photo-import: FACE stage (detect → embed → cluster).

The vision LLM can't tell WHO is in a photo, so the who-axis category (Family /
Couple / Social / Solo) was just a guess. This runs a dedicated local face model
(InsightFace / ArcFace) over the event sample photos, embeds every face, and
clusters the embeddings into "Person 1, Person 2, …" — with NO names yet.

Output is a person sheet (face crops grouped by cluster) so a human can label the
recurring people once. Those labels later turn "who is in this day" from a guess
into a fact (known-family vs unknown/bystander → the real who-axis).

Everything is on-device; faceprints never leave the machine.

Usage (from WSL):
  ./.venv/bin/python faces.py --report out/cr2015/events_dryrun.json --limit 15 --min-photos 8
  ./.venv/bin/python faces.py --report <file> --threshold 0.6
"""
from __future__ import annotations

import argparse, json, os, sys
import numpy as np
from PIL import Image, ImageDraw, ImageFont
import pillow_heif; pillow_heif.register_heif_opener()


def load_bgr(path: str):
    """Load any image (incl. HEIC) as a BGR uint8 array for InsightFace."""
    with Image.open(path) as im:
        rgb = np.array(im.convert("RGB"))
    return rgb[:, :, ::-1].copy()   # RGB -> BGR


def collect_paths(report: str, limit: int, min_photos: int):
    evs = json.load(open(report))["events"]
    evs = [e for e in evs if e.get("junk_ratio", 1) < 0.5 and e.get("photo_count", 0) >= min_photos]
    evs.sort(key=lambda e: -e["photo_count"])
    seen, paths = set(), []
    for e in evs[:limit]:
        for p in (e.get("sample_paths") or [e.get("rep_path")]):
            if p and p not in seen:
                seen.add(p); paths.append(p)
    return paths


def crop_face(bgr, bbox, pad=0.25, size=112):
    h, w = bgr.shape[:2]
    x1, y1, x2, y2 = bbox
    bw, bh = x2 - x1, y2 - y1
    x1 = max(0, int(x1 - bw * pad)); y1 = max(0, int(y1 - bh * pad))
    x2 = min(w, int(x2 + bw * pad)); y2 = min(h, int(y2 + bh * pad))
    crop = bgr[y1:y2, x1:x2][:, :, ::-1]              # BGR -> RGB
    im = Image.fromarray(crop).convert("RGB")
    im.thumbnail((size, size))
    sq = Image.new("RGB", (size, size), (30, 30, 34))
    sq.paste(im, ((size - im.width) // 2, (size - im.height) // 2))
    return sq


def main():
    ap = argparse.ArgumentParser(description="Detect, embed and cluster faces in event sample photos.")
    ap.add_argument("--report", required=True)
    ap.add_argument("--limit", type=int, default=15)
    ap.add_argument("--min-photos", type=int, default=8)
    ap.add_argument("--threshold", type=float, default=0.6, help="Cosine-distance threshold for same person (lower = stricter)")
    ap.add_argument("--min-score", type=float, default=0.55, help="Drop face detections below this confidence")
    ap.add_argument("--device", choices=["gpu", "cpu"], default="gpu", help="Run InsightFace on GPU (CUDA) or CPU")
    ap.add_argument("--out-dir", default="out/cr2015")
    args = ap.parse_args()

    import onnxruntime
    # Load the pip-installed CUDA/cuDNN libs (libcublasLt etc.) so the CUDA EP can
    # initialise — without this onnxruntime silently falls back to CPU.
    if args.device == "gpu":
        try:
            onnxruntime.preload_dlls()
        except Exception as e:
            print(f"preload_dlls failed ({e}); CUDA may fall back to CPU", file=sys.stderr)
    from insightface.app import FaceAnalysis
    from sklearn.cluster import AgglomerativeClustering

    avail = onnxruntime.get_available_providers()
    print(f"onnxruntime providers available: {avail}", file=sys.stderr)
    if args.device == "gpu" and "CUDAExecutionProvider" in avail:
        providers, ctx = ["CUDAExecutionProvider", "CPUExecutionProvider"], 0
        print("→ using GPU (CUDAExecutionProvider)", file=sys.stderr)
    else:
        if args.device == "gpu":
            print("→ CUDA not available, falling back to CPU", file=sys.stderr)
        providers, ctx = ["CPUExecutionProvider"], -1

    paths = collect_paths(args.report, args.limit, args.min_photos)
    print(f"Scanning {len(paths)} sample photos for faces …", file=sys.stderr)

    app = FaceAnalysis(name="buffalo_l", providers=providers)
    app.prepare(ctx_id=ctx, det_size=(640, 640))

    faces = []   # {emb, path, bbox, crop}
    for i, p in enumerate(paths):
        try:
            bgr = load_bgr(p)
        except Exception:
            continue
        for f in app.get(bgr):
            if f.det_score < args.min_score:
                continue
            bw = f.bbox[2] - f.bbox[0]
            if bw < 40:                       # tiny background faces — skip
                continue
            faces.append({"emb": f.normed_embedding, "path": p,
                          "bbox": [float(x) for x in f.bbox],
                          "score": float(f.det_score),
                          "crop": crop_face(bgr, f.bbox)})
        if (i + 1) % 20 == 0:
            print(f"  …{i+1}/{len(paths)} photos, {len(faces)} faces so far", file=sys.stderr)

    print(f"Detected {len(faces)} usable faces across {len(paths)} photos.", file=sys.stderr)
    if len(faces) < 2:
        sys.exit("Too few faces to cluster.")

    embs = np.vstack([f["emb"] for f in faces])
    labels = AgglomerativeClustering(
        n_clusters=None, metric="cosine", linkage="average",
        distance_threshold=args.threshold).fit_predict(embs)

    clusters = {}
    for lab, f in zip(labels, faces):
        clusters.setdefault(int(lab), []).append(f)
    # recurring people first (biggest clusters); singletons are likely one-off bystanders
    ordered = sorted(clusters.items(), key=lambda kv: -len(kv[1]))

    def centroid_emb(fl):
        m = np.mean(np.vstack([f["emb"] for f in fl]), axis=0)
        m = m / (np.linalg.norm(m) + 1e-9)          # re-normalise the mean faceprint
        return [round(float(x), 5) for x in m]

    os.makedirs(args.out_dir, exist_ok=True)
    json.dump({"n_faces": len(faces), "n_clusters": len(clusters),
               "clusters": [{"person": idx + 1, "n": len(fl),
                             "centroid_emb": centroid_emb(fl),   # faceprint for the global registry
                             "faces": [{"path": f["path"], "bbox": f["bbox"], "score": f["score"]} for f in fl]}
                            for idx, (_, fl) in enumerate(ordered)]},
              open(os.path.join(args.out_dir, "face_clusters.json"), "w"), indent=2)

    # ── person sheet ──────────────────────────────────────────────────────────
    THUMB, GAP, LABEL_W, PERROW = 112, 6, 150, 12
    try:
        font = ImageFont.truetype("/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf", 16)
        small = ImageFont.truetype("/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf", 12)
    except Exception:
        font = small = ImageFont.load_default()

    recurring = [(i, fl) for i, (_, fl) in enumerate(ordered) if len(fl) >= 2]
    singles = [fl[0] for _, fl in ordered if len(fl) == 1]

    rows = []   # (label1, label2, list_of_crops)
    for idx, fl in recurring:
        rows.append((f"Person {idx+1}", f"{len(fl)} faces", [f["crop"] for f in fl][:PERROW]))
    if singles:
        rows.append(("One-offs", f"{len(singles)} singletons", [f["crop"] for f in singles][:PERROW]))

    W = LABEL_W + PERROW * (THUMB + GAP) + GAP
    H = GAP + len(rows) * (THUMB + GAP)
    sheet = Image.new("RGB", (W, H), (18, 18, 20))
    d = ImageDraw.Draw(sheet)
    for r, (l1, l2, crops) in enumerate(rows):
        y = GAP + r * (THUMB + GAP)
        d.text((10, y + 8), l1, fill=(255, 255, 255), font=font)
        d.text((10, y + 30), l2, fill=(150, 200, 160), font=small)
        for j, c in enumerate(crops):
            sheet.paste(c, (LABEL_W + j * (THUMB + GAP), y))
    out_png = os.path.join(args.out_dir, "person_sheet.png")
    sheet.save(out_png, quality=88)

    print(f"\n{len(recurring)} recurring people (>=2 faces), {len(singles)} one-offs.", file=sys.stderr)
    print(f"  sheet: {out_png} ({W}x{H})", file=sys.stderr)


if __name__ == "__main__":
    main()
