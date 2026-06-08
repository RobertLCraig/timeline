#!/usr/bin/env python3
"""
Family Timeline — photo-import: ROSTER → who-axis (face identity → category).

Combines the face clusters (faces.py) with your labels (people.json) to work out,
for each event, WHO is actually present — then sets the category from that fact
instead of the vision model's guess:

  - any known family member present      → Family
  - exactly you + your partner, no others → Couple
  - only one known person (e.g. just you) → Solo
  - friends/others or a crowd of unknowns → Social
  - no faces at all                       → Places

Names live only in people.json — they are NOT written to the timeline (the
category is). Re-writes the categories in events_captioned.json so post.py picks
them up on the next (idempotent) run.

Usage:
  ./.venv/bin/python roster.py --report out/cr2015/events_dryrun.json \
      --clusters out/cr2015/face_clusters.json --people out/cr2015/people.json \
      --captioned out/cr2015/events_captioned.json
"""
from __future__ import annotations
import argparse, json, os, sys


# Higher = wins when the same NAME is labelled with conflicting relationships
# across its clusters (e.g. Vy tagged both "partner" and "friend" → partner).
REL_PRIORITY = ["unset", "ignore", "other", "friend", "family", "partner", "me"]

# Vision-detected milestones that outrank the face-based who-axis (the occasion
# matters more than who's in frame). Travel is intentionally NOT here — the title
# already carries the place, so a family trip should read as Family.
MILESTONES = {"Birth", "Move", "Anniversary", "Graduation", "Wedding", "Career", "Health", "Milestone"}


def _median(xs):
    xs = sorted(xs)
    return xs[len(xs) // 2] if xs else None


def km_between(a, b):
    """Great-circle km between two (lat, lon) pairs."""
    import math
    r = 6371.0
    p1, p2 = math.radians(a[0]), math.radians(b[0])
    dphi, dlmb = math.radians(b[0] - a[0]), math.radians(b[1] - a[1])
    h = math.sin(dphi / 2) ** 2 + math.cos(p1) * math.cos(p2) * math.sin(dlmb / 2) ** 2
    return 2 * r * math.asin(math.sqrt(h))


def is_highlight(cat, photo_count, n_known, n_people, away):
    """Highlights-only keep rule: trips (away from home), larger gatherings,
    couple days, and milestones — dropping everyday family-at-home."""
    if cat in MILESTONES or cat in ("Couple", "Travel"):
        return True
    if away:                                    # any day notably away from home is a trip
        return True
    if cat == "Social":                         # friends / a gathering / an outing
        return n_people >= 2 or photo_count >= 8
    if cat == "Family":                         # a gathering (3+ people) or a busy day
        return n_people >= 3 or photo_count >= 18
    if cat == "Solo":
        return photo_count >= 18
    if cat == "Places":                         # a scenic outing (not a stray landscape)
        return photo_count >= 12
    return False                                # Other at home → drop


def resolve_identities(people: list[dict]) -> dict[int, dict]:
    """person# -> identity. Identity is keyed by NAME (clusters that share a name
    are the same person — that's how merge-by-name works), with a single relationship
    per name resolved by priority, and any until/then carried over. Legacy
    merge_into is still honoured."""
    by = {p["person"]: p for p in people}
    def root(pid, seen=None):
        seen = seen or set()
        p = by.get(pid)
        if not p or p.get("merge_into") in (None, "", pid) or p["merge_into"] in seen:
            return pid
        seen.add(pid)
        return root(p["merge_into"], seen)

    # 1) per-cluster raw (name + its own relationship + any date bounds)
    raw = {}
    for p in people:
        canon = by.get(root(p["person"]), p)
        rel = (canon.get("relationship") or "unset")
        if rel == "unset":
            rel = p.get("relationship") or "unset"
        raw[p["person"]] = {
            "name": canon.get("name") or p.get("name") or f"Person {p['person']}",
            "rel": rel,
            "until": canon.get("until") or p.get("until"),
            "then": canon.get("then") or p.get("then"),
        }

    # 2) normalise to ONE relationship + date bounds per name
    name_rel, name_meta = {}, {}
    for d in raw.values():
        n = d["name"]
        if REL_PRIORITY.index(d["rel"]) > REL_PRIORITY.index(name_rel.get(n, "unset")):
            name_rel[n] = d["rel"]
        if d["until"] and d["then"] and n not in name_meta:
            name_meta[n] = (d["until"], d["then"])

    out = {}
    for pid, d in raw.items():
        u, t = name_meta.get(d["name"], (None, None))
        out[pid] = {"name": d["name"], "relationship": name_rel.get(d["name"], "unset"),
                    "until": u, "then": t}
    return out


def _name_matches(identity_name: str, partner_name: str) -> bool:
    a, b = identity_name.lower().strip(), partner_name.lower().strip()
    return a == b or a.startswith(b + " ") or b.startswith(a + " ")


def partner_window(name: str, partners: list[dict]) -> dict | None:
    """First partner whose name matches the identity (e.g. 'Vy' ~ 'Vy Lieu')."""
    for p in partners:
        if _name_matches(name, p["name"]):
            return p
    return None


def relationship_at(identity: dict, date: str, partners: list[dict]) -> str:
    """Relationship in force at `date` (YYYY-MM-DD).

    Partners are resolved from the partner timeline by NAME: inside [from..until]
    (inclusive years) they're 'partner', outside they're 'friend' — so an ex who
    appears in a later photo is correctly Social, not Couple. Falls back to the
    person's own (optionally until/then-bounded) relationship label."""
    yr = date[:4]
    pw = partner_window(identity.get("name", ""), partners)
    if pw:
        in_window = yr >= pw["from"] and (pw["until"] is None or yr <= pw["until"])
        return "partner" if in_window else "friend"
    until, then = identity.get("until"), identity.get("then")
    if until and then and date >= until:
        return then
    return identity["relationship"]


def categorize(rels: list[str], n_known: int, n_unknown: int, n_faces: int) -> str:
    has = lambda r: r in rels
    if n_faces == 0:
        return "Places"
    distinct = n_known + n_unknown
    if has("partner") and not has("family") and distinct <= 2 and n_unknown == 0:
        return "Couple"
    if has("family"):
        return "Family"
    if distinct == 1 and n_unknown == 0:
        return "Solo"                      # one known person alone (often "me")
    if has("friend") or has("other") or n_unknown >= 1:
        return "Social"
    return "Family"                        # knowns present, nothing else fits


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--report", required=True)
    ap.add_argument("--clusters", required=True)
    ap.add_argument("--people", required=True)
    ap.add_argument("--captioned", required=True)
    ap.add_argument("--partners", default=os.path.join(os.path.dirname(__file__), "partners.json"),
                    help="Partner timeline for relationship-at-date resolution")
    ap.add_argument("--highlights", action="store_true",
                    help="Keep only highlights (trips, gatherings, couples, milestones) — drop everyday family-at-home")
    args = ap.parse_args()

    # Match captioned events back to report events by the unique representative
    # photo (a date alone is ambiguous — a day can have several events). Keep a
    # date fallback for older captioned files without rep_path.
    report_events = json.load(open(args.report))["events"]
    # "home" ≈ the median of all geotagged days; days far from it are trips.
    gps_pts = [e["centroid"] for e in report_events
               if e.get("centroid") and None not in e["centroid"]]
    home = (_median([p[0] for p in gps_pts]), _median([p[1] for p in gps_pts])) if gps_pts else None
    by_rep = {e["rep_path"]: e for e in report_events if e.get("rep_path")}
    by_date = {}
    for e in report_events:
        by_date.setdefault(e["start"][:10], e)
    clusters = json.load(open(args.clusters))["clusters"]
    people = json.load(open(args.people))["people"]
    ident = resolve_identities(people)
    partners = []
    if os.path.exists(args.partners):
        partners = json.load(open(args.partners)).get("partners", [])

    # path -> set of person-cluster ids present in that photo
    path_people: dict[str, set] = {}
    for c in clusters:
        for f in c["faces"]:
            path_people.setdefault(f["path"], set()).add(c["person"])

    cap = json.load(open(args.captioned))
    rows = []
    for e in cap["events"]:
        date = e["date"]
        src = by_rep.get(e.get("rep_path")) or by_date.get(date) or {}
        paths = src.get("sample_paths") or ([e.get("rep_path")] if e.get("rep_path") else [])
        present = set()
        n_faces = 0
        for p in paths:
            ids = path_people.get(p, set())
            n_faces += len(ids)
            present |= ids

        names, rels, n_unknown = [], [], 0
        for pid in present:
            who = ident.get(pid)
            if not who:
                n_unknown += 1                         # unlabelled cluster = unknown person
                continue
            rel = relationship_at(who, date, partners)  # relationship AS OF this event's date
            if rel == "ignore":
                continue                               # explicitly ignored cluster
            names.append(who["name"]); rels.append(rel)
        # de-dup names from merged clusters
        names = sorted(set(names))
        ai = e.setdefault("ai", {})
        # Preserve the vision model's ORIGINAL category once, so re-running the
        # roster is idempotent (it must never derive from its own prior output).
        vision_cat = ai.get("vision_category", ai.get("category"))
        ai["vision_category"] = vision_cat

        # The who-axis only applies when there are FACES to classify. With no
        # people, the event is about WHAT not WHO — keep the vision model's content
        # category (Move/Health/Places/Other/Travel…). With faces: a clearly-visible
        # milestone still outranks the who-axis.
        if n_faces == 0:
            new_cat = vision_cat
        else:
            new_cat = vision_cat if vision_cat in MILESTONES else categorize(rels, len(names), n_unknown, n_faces)
        ai["category"] = new_cat
        old = vision_cat
        e["ai"]["people"] = names
        e["ai"]["face_based"] = True

        # KEEP decision. Two modes:
        #  - default: drop only everyday LOGISTICS noise (admin/shopping/chores) —
        #    keep anything with recognised people or a real occasion.
        #  - --highlights: keep only standout days (trips, gatherings, couples,
        #    milestones), dropping ordinary family-at-home.
        gps = src.get("centroid")
        away = bool(home and gps and None not in gps and km_between(gps, home) > 60)
        if args.highlights:
            ai["noteworthy_final"] = is_highlight(new_cat, e.get("photo_count", 0),
                                                  len(names), len(names) + n_unknown, away)
        else:
            ai["noteworthy_final"] = bool(
                len(names) >= 1
                or new_cat in {"Wedding", "Graduation", "Birth", "Anniversary", "Move", "Travel"}
                or ai.get("noteworthy") is True
            )
        rows.append((date, old, new_cat, names, n_unknown, n_faces))

    json.dump(cap, open(args.captioned, "w"), indent=2)

    print(f"{'date':10}  {'old':8} → {'new':8}  faces  who", file=sys.stderr)
    for date, old, new, names, nu, nf in rows:
        who = ", ".join(names) + (f" +{nu} unknown" if nu else "") or ("— none —" if nf == 0 else f"{nu} unknown")
        print(f"{date}  {str(old):8} → {str(new):8}  {nf:>4}   {who}", file=sys.stderr)


if __name__ == "__main__":
    main()
