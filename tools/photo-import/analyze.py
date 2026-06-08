import json
from collections import Counter

d = json.load(open("out/full/events_dryrun.json"))
ev = d["events"]
yr = Counter(e["start"][:4] for e in ev)
print("events per year:")
for y in sorted(yr):
    tag = "  <-- pre-1995, likely bogus epoch date" if y < "1995" else ""
    print("  %s: %4d%s" % (y, yr[y], tag))

real = [e for e in ev if e["junk_ratio"] < 0.5]
gps = [e for e in real if e["centroid"]]
big = [e for e in real if e["photo_count"] >= 20]
print("\nmostly-real: %d | with GPS: %d | >=20 photos: %d" % (len(real), len(gps), len(big)))

print("\nbiggest GPS-tagged events (likely trips/occasions):")
for e in sorted(gps, key=lambda x: -x["photo_count"])[:12]:
    c = e["centroid"]
    print("  %s  %4d photos  %.3f,%.3f  %s" % (e["start"][:10], e["photo_count"], c[0], c[1], e["rep_title"][:38]))
