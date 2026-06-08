#!/usr/bin/env python3
"""Parallel-prewarm the sample photos of a harvest report so a later pass reads
them warm. OneDrive cold reads are I/O-bound, so threads collapse the latency —
we just read the bytes (triggering hydration) and discard them (memory-light)."""
import json, sys
from concurrent.futures import ThreadPoolExecutor

report = sys.argv[1] if len(sys.argv) > 1 else "out/cr2015/events_dryrun.json"
workers = int(sys.argv[2]) if len(sys.argv) > 2 else 32

evs = json.load(open(report))["events"]
seen, paths = set(), []
for e in evs:
    for p in (e.get("sample_paths") or [e.get("rep_path")]):
        if p and p not in seen:
            seen.add(p); paths.append(p)

print(f"prewarming {len(paths)} photos with {workers} threads …", file=sys.stderr)

def rd(p):
    try:
        with open(p, "rb") as f:
            f.read()
        return 1
    except Exception:
        return 0

with ThreadPoolExecutor(max_workers=workers) as ex:
    n = sum(ex.map(rd, paths))
print(f"warmed {n}/{len(paths)}", file=sys.stderr)
