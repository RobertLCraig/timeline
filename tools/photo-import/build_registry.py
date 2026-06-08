#!/usr/bin/env python3
"""Build/refresh a GLOBAL face registry from labelled years, so people are
recognised across years and only have to be named once.

Each labelled cluster contributes its faceprint (centroid_emb from faces.py) to
that person's entry. A person accumulates faceprints across years (different ages/
angles → more robust matching). Output: registry.json.

Usage:
  ./.venv/bin/python build_registry.py registry.json \
      out/cr2015/people.json out/cr2015/face_clusters.json \
      out/2024/people.json   out/2024/face_clusters.json
"""
import json, os, sys

REL_PRIORITY = ["unset", "ignore", "other", "friend", "family", "partner", "me"]


def main():
    out = sys.argv[1]
    pairs = sys.argv[2:]
    if pairs == ["--all"]:                      # auto-discover every labelled year
        import glob
        base = os.path.dirname(os.path.abspath(__file__))
        pairs = []
        for d in sorted(glob.glob(os.path.join(base, "out", "*"))):
            p, c = os.path.join(d, "people.json"), os.path.join(d, "face_clusters.json")
            if os.path.exists(p) and os.path.exists(c):
                pairs += [p, c]
    reg = {}   # name -> {relationship, embeddings, until, then, years}
    for i in range(0, len(pairs) - 1, 2):
        people = json.load(open(pairs[i]))["people"]
        clusters = {c["person"]: c for c in json.load(open(pairs[i + 1]))["clusters"]}
        for p in people:
            name = (p.get("name") or "").strip()
            rel = p.get("relationship") or "unset"
            if not name or rel == "ignore":
                continue
            c = clusters.get(p["person"])
            if not c or not c.get("centroid_emb"):
                continue
            e = reg.setdefault(name, {"relationship": "unset", "embeddings": [], "until": None, "then": None})
            e["embeddings"].append(c["centroid_emb"])
            if REL_PRIORITY.index(rel) > REL_PRIORITY.index(e["relationship"]):
                e["relationship"] = rel
            if p.get("until") and p.get("then"):
                e["until"], e["then"] = p["until"], p["then"]

    registry = {"people": [{"name": n, "relationship": v["relationship"],
                            "until": v["until"], "then": v["then"],
                            "n_faceprints": len(v["embeddings"]), "embeddings": v["embeddings"]}
                           for n, v in sorted(reg.items())]}
    json.dump(registry, open(out, "w"), indent=2)
    print(f"registry: {len(reg)} people, "
          f"{sum(len(v['embeddings']) for v in reg.values())} faceprints → {out}")
    for n, v in sorted(reg.items()):
        print(f"  {n:24} [{v['relationship']:8}] {len(v['embeddings'])} faceprints")


if __name__ == "__main__":
    main()
