#!/usr/bin/env python3
"""Match a year's face clusters against the global registry, auto-recognising
known people. Writes a prefill people.json so the label page arrives mostly
filled — you only name NEW faces.

Match = cosine similarity of the cluster's faceprint to a registered person's
best faceprint (ArcFace embeddings are L2-normalised, so cosine = dot product).

Usage:
  ./.venv/bin/python match_registry.py out/2024/face_clusters.json registry.json 0.5 out/2024/prefill.json
"""
import json, sys
import numpy as np


def main():
    clusters = json.load(open(sys.argv[1]))["clusters"]
    reg = json.load(open(sys.argv[2]))["people"]
    thr = float(sys.argv[3]) if len(sys.argv) > 3 else 0.5
    out = sys.argv[4] if len(sys.argv) > 4 else None

    R = [(p["name"], p["relationship"], np.array(p["embeddings"], dtype=np.float32)) for p in reg if p["embeddings"]]
    prefill, recog = [], 0
    for c in sorted(clusters, key=lambda x: -x["n"]):
        if not c.get("centroid_emb"):
            continue
        v = np.array(c["centroid_emb"], dtype=np.float32)
        best_name, best_rel, best_sim = None, None, -1.0
        for name, rel, E in R:
            s = float((E @ v).max())
            if s > best_sim:
                best_sim, best_name, best_rel = s, name, rel
        if best_sim >= thr:
            recog += 1
            prefill.append({"person": c["person"], "name": best_name, "relationship": best_rel,
                            "until": None, "then": None, "merge_into": None})
            print(f"  #{c['person']:>3} ({c['n']:>2} faces) → {best_name:24} [{best_rel:8}] sim={best_sim:.2f}")
        else:
            print(f"  #{c['person']:>3} ({c['n']:>2} faces) → (new / unknown)        best={best_sim:.2f}")

    if out:
        json.dump({"people": prefill}, open(out, "w"), indent=2)
    print(f"\nauto-recognised {recog}/{sum(1 for c in clusters if c.get('centroid_emb'))} clusters "
          f"(threshold {thr})" + (f" → {out}" if out else ""))


if __name__ == "__main__":
    main()
