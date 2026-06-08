#!/usr/bin/env python3
"""
Family Timeline — photo-import: STEP 5 (post events, idempotent).

Consumes a CAPTIONED report (events_captioned.json from caption.py) and posts each
event to a Family Timeline group via the REST API, using `import_hash` so the run
is idempotent — re-running updates the matching event (HTTP 200) instead of
duplicating (201). Built on the migration deployed 2026-06-06.

Design choices (see README):
  - DATE is authoritative (from harvest metadata); the AI only supplies the title,
    description and category. No location/trip is asserted.
  - Events are posted IMAGE-LESS by default (title + date), to respect the limited
    server storage — pass --upload-images to attach a downscaled hero later.
  - First runs should target a NEW PRIVATE sandbox group; events default to
    visibility=private + social_visibility=private (creator-only).
  - Non-meaningful / un-captionable events are skipped unless --include-all.

Token: read from the secrets file (shell-style TOKEN="..."/BASE="..."), never printed.

Usage (from WSL):
  ./.venv/bin/python post.py --captioned out/cr2015/events_captioned.json \
      --group photo-import-sandbox-XXXX --dry-run
  ./.venv/bin/python post.py --captioned <file> --group <slug>          # really post
"""
from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import sys
import time
import urllib.request

SECRETS = "/mnt/c/Dev/_secrets/timeline.enhanceify.txt"
# Must include the who-axis categories (Family/Couple/Social/Solo/Places) the
# roster assigns — otherwise canonical_category() collapses them to "Other".
CATEGORIES = ["Birth", "Move", "Anniversary", "Graduation", "Milestone",
              "Wedding", "Travel", "Career", "Health",
              "Family", "Couple", "Social", "Solo", "Places", "Other"]
_CAT_LOWER = {c.lower(): c for c in CATEGORIES}


def load_secrets(path: str) -> dict:
    """Parse shell-style assignments, tolerating a UTF-8 BOM and CRLF."""
    out = {}
    with open(path, "rb") as fh:
        raw = fh.read().decode("utf-8-sig", errors="replace")
    for line in raw.splitlines():
        m = re.match(r'\s*(\w+)\s*=\s*"?([^"\r]*)"?\s*$', line)
        if m:
            out[m.group(1)] = m.group(2)
    return out


def import_hash_for(ev: dict) -> str:
    """Stable per-cluster key: date + the representative filename. Survives
    re-runs as long as the cluster's identity doesn't change."""
    base = os.path.basename(ev.get("rep_path") or "")
    return hashlib.sha1(f"{ev['date']}|{base}".encode()).hexdigest()  # 40 chars


def canonical_category(name: str | None) -> str:
    return _CAT_LOWER.get((name or "").strip().lower(), "Other")


def _req(method, url, token, payload=None):
    req = urllib.request.Request(
        url, data=json.dumps(payload).encode() if payload is not None else None,
        headers={"Authorization": f"Bearer {token}", "Accept": "application/json",
                 "Content-Type": "application/json"}, method=method)
    try:
        with urllib.request.urlopen(req, timeout=60) as r:
            return r.status, json.loads(r.read() or b"{}")
    except urllib.error.HTTPError as e:
        return e.code, json.loads(e.read() or b"{}")


def post_event(base, token, slug, payload):
    return _req("POST", f"{base}/groups/{slug}/events", token, payload)


def hash_to_id(base, token, slug):
    """Map existing events' import_hash -> id (for pruning previously-posted noise)."""
    out = {}
    page = 1
    while True:
        code, body = _req("GET", f"{base}/groups/{slug}/events?per_page=200&page={page}", token)
        rows = body.get("data") if isinstance(body, dict) else None
        if not rows:
            break
        for e in rows:
            if e.get("import_hash"):
                out[e["import_hash"]] = e["id"]
        if not body.get("next_page_url"):
            break
        page += 1
    return out


def main():
    ap = argparse.ArgumentParser(description="Post captioned events to a Family Timeline group (idempotent).")
    ap.add_argument("--captioned", required=True, help="events_captioned.json from caption.py")
    ap.add_argument("--group", required=True, help="Target group slug (use a private sandbox first)")
    ap.add_argument("--include-all", action="store_true", help="Post even events flagged not-meaningful or without a caption")
    ap.add_argument("--visibility", default="private", choices=["public", "members", "private"])
    ap.add_argument("--social", default="private", choices=["family", "close_friends", "friends", "acquaintances", "public", "private"])
    ap.add_argument("--dry-run", action="store_true", help="Show what would be posted, send nothing")
    ap.add_argument("--prune", action="store_true", help="Delete already-posted events that are now skipped as noise")
    ap.add_argument("--rate", type=float, default=1.1, help="Seconds between writes (stay under the 60/min throttle)")
    ap.add_argument("--secrets", default=SECRETS)
    args = ap.parse_args()

    data = json.load(open(args.captioned))
    events = data["events"]

    sec = load_secrets(args.secrets)
    base = sec.get("BASE") or "https://timeline.enhanceify.co.uk/api"
    token = sec.get("TOKEN")
    if not token and not args.dry_run:
        sys.exit("TOKEN not found in secrets file.")

    existing = hash_to_id(base, token, args.group) if (args.prune and not args.dry_run) else {}

    created = updated = skipped = failed = pruned = 0
    for ev in events:
        ai = ev.get("ai") or {}
        # Skip junk (not meaningful) AND everyday noise (not noteworthy).
        noise = ai.get("error") or ai.get("title") is None \
            or ai.get("is_meaningful") is False or ai.get("noteworthy_final") is False
        if not args.include_all and noise:
            skipped += 1
            # if this noise event was posted before, delete it
            h = import_hash_for(ev)
            if args.prune and h in existing and not args.dry_run:
                code, _ = _req("DELETE", f"{base}/groups/{args.group}/events/{existing[h]}", token)
                if code in (200, 204):
                    pruned += 1
                    time.sleep(args.rate)
            continue

        title = (ai.get("title") or f"Photos from {ev['date']}")[:200]
        desc = (ai.get("description") or "")
        if ev.get("photo_count"):
            desc = (desc + f"\n\n({ev['photo_count']} photos from this day.)").strip()
        payload = {
            "title": title,
            "event_date": ev["date"],
            "description": desc[:5000],
            "category": canonical_category(ai.get("category")),
            "visibility": args.visibility,
            "social_visibility": args.social,
            "import_hash": import_hash_for(ev),
        }

        if args.dry_run:
            print(f"  DRY  {ev['date']}  [{payload['category']:9}]  {title}")
            continue

        code, body = post_event(base, token, args.group, payload)
        if code == 201:
            created += 1; tag = "CREATED"
        elif code == 200:
            updated += 1; tag = "UPDATED"
        else:
            failed += 1; tag = f"FAIL {code} {body.get('message','')}"
        print(f"  {tag:8} {ev['date']}  {title[:46]}", file=sys.stderr)
        time.sleep(args.rate)   # stay under the 60 writes/min throttle

    print(f"\nDone. created={created} updated={updated} skipped={skipped} "
          f"pruned={pruned} failed={failed}{' (dry run)' if args.dry_run else ''}", file=sys.stderr)


if __name__ == "__main__":
    main()
