#!/usr/bin/env python3
"""
Family Timeline — photo-import: METADATA HARVEST (sidecar-first, no downloads).

Built for a library that is a Google Takeout export living in OneDrive as
ONLINE-ONLY placeholders. The hard-won lesson from the first scan: reading image
bytes to get EXIF forces OneDrive to hydrate (download) every file — ~200 GB for
67k images. We refuse to do that.

Instead we get the *when / where / what* for free from:
  1. Google Takeout SIDECAR JSON  (photoTakenTime, geoData, title, description)
  2. the FILENAME pattern          (e.g. 20220111_102739, IMG_20231101_..., PXL_, WA)
  3. (last resort, opt-in only)     EXIF via --allow-hydrate
  4. (final fallback)               file mtime  — low confidence

A sidecar is ~600 bytes, so a full-library harvest costs ~15 MB of reads, not
200 GB. No image is ever opened. Nothing to clean up afterwards.

It also DEDUPES Takeout's cross-album copies (the same photo exported into every
album it belonged to) by (taken-time, title) — this is the "merge back together"
step — and clusters the survivors into candidate timeline events by time + GPS gap.

STILL a dry run: no AI, no posting, no image fetch. Outputs JSON + Markdown.

Usage (from WSL):
  ./.venv/bin/python harvest.py --root "/mnt/c/Users/r/OneDrive/Pictures"
  ./.venv/bin/python harvest.py --root <dir> --limit 4000        # quick sample
"""

from __future__ import annotations

import argparse
import json
import math
import os
import re
import sys
import time
from concurrent.futures import ThreadPoolExecutor
from dataclasses import dataclass, field
from datetime import datetime, timezone
from pathlib import Path

IMAGE_EXTS = {".jpg", ".jpeg", ".png", ".heic", ".heif", ".tif", ".tiff", ".webp", ".gif"}
VIDEO_EXTS = {".mov", ".mp4", ".m4v", ".avi", ".3gp"}

# ── Junk signals (kept but flagged, not silently dropped) ─────────────────────
JUNK_NAME = re.compile(
    r"(?i)(screenshot|screen[ _-]?shot|whatsapp|dall[·e]|dalle|sticker|meme|"
    r"qimg|fullview|-icon\.|^capture\.|^untitled|^received_|^fb_img|download)"
)
# A description that is basically a URL/domain = a web download, not a life moment.
JUNK_DESC = re.compile(r"(?i)^\s*(https?://|www\.|[a-z0-9.-]+\.(com|net|org|io|co)\b)")
# Whole albums that are not life events (e.g. Takeout's "Screenshots 1" = 1388 events).
JUNK_ALBUM = re.compile(r"(?i)(screenshot|saved|downloads?|whatsapp|memes?|food.?porn|wallpaper)")

# ── Filename date patterns (zero I/O — no hydration) ──────────────────────────
# Each returns a datetime or None. Ordered most-specific first.
_FN_PATTERNS = [
    # 20220111_102739  /  IMG_20231101_115412  /  PXL_20210101_120000123
    (re.compile(r"(?<!\d)(20\d{2})(\d{2})(\d{2})[_-](\d{2})(\d{2})(\d{2})"),
     lambda m: _mk(*m.groups())),
    # IMG-20210205-WA0001  /  20210205 (date only)
    (re.compile(r"(?<!\d)(20\d{2})(\d{2})(\d{2})(?!\d)"),
     lambda m: _mk(*m.groups(), "00", "00", "00")),
    # Screenshot 2020-11-12 015634  /  2015-07-15
    (re.compile(r"(?<!\d)(20\d{2})-(\d{2})-(\d{2})(?:[ _](\d{2})(\d{2})(\d{2}))?"),
     lambda m: _mk(*(m.groups()[i] or d for i, d in enumerate(["", "", "", "00", "00", "00"])))),
]


def _mk(y, mo, d, h, mi, s) -> datetime | None:
    try:
        dt = datetime(int(y), int(mo), int(d), int(h), int(mi), int(s))
        if 2000 <= dt.year <= datetime.now().year + 1:
            return dt
        return None
    except ValueError:
        return None


def date_from_filename(name: str) -> datetime | None:
    stem = os.path.splitext(name)[0]
    for rx, fn in _FN_PATTERNS:
        m = rx.search(stem)
        if m:
            dt = fn(m)
            if dt:
                return dt
    return None


# ── Record ────────────────────────────────────────────────────────────────────
@dataclass
class Rec:
    image_name: str            # the photo this metadata describes (may not be on disk)
    album: str                 # parent folder (Takeout album / "Camera Roll/<year>")
    taken: str | None          # ISO datetime
    source: str                # sidecar | filename | mtime
    lat: float | None
    lon: float | None
    title: str | None
    description: str | None
    junk: bool = False
    image_path: str | None = None   # set if the actual image file was seen on disk

    @property
    def dt(self) -> datetime | None:
        return datetime.fromisoformat(self.taken) if self.taken else None


def _f(v) -> float | None:
    try:
        v = float(v)
        return v if v != 0.0 else None   # Takeout uses 0.0 to mean "no GPS"
    except (TypeError, ValueError):
        return None


def parse_sidecar(path: Path, album: str) -> Rec | None:
    try:
        d = json.loads(path.read_text(encoding="utf-8", errors="replace"))
    except Exception:
        return None
    if not isinstance(d, dict):
        return None
    ts = (d.get("photoTakenTime") or d.get("creationTime") or {}).get("timestamp")
    taken = None
    if ts:
        try:
            taken = datetime.fromtimestamp(int(ts), tz=timezone.utc).replace(tzinfo=None).isoformat()
        except (ValueError, OSError):
            taken = None
    geo = d.get("geoData") or {}
    title = d.get("title") or None
    desc = (d.get("description") or "").strip() or None
    # The image name the sidecar describes: strip ".json"/".supplemental-metadata.json".
    img_name = re.sub(r"(?i)\.supplemental-metadata\.json$|\.json$", "", path.name)
    junk = bool((title and JUNK_NAME.search(title)) or (desc and JUNK_DESC.search(desc))
                or JUNK_ALBUM.search(album))
    # The image the sidecar describes sits beside it; record the path so a later
    # captioner can hydrate exactly this file. (Not verified on disk here — no I/O.)
    img_path = str(path.parent / img_name)
    return Rec(img_name, album, taken, "sidecar",
               _f(geo.get("latitude")), _f(geo.get("longitude")),
               title, desc, junk, img_path)


# ── Walk ──────────────────────────────────────────────────────────────────────
def harvest(root: Path, limit: int | None, allow_hydrate: bool, workers: int):
    recs: dict[str, Rec] = {}     # key -> Rec (dedup)
    images: list[tuple[str, str, Path]] = []   # (name, album, path)
    sidecar_paths: list[tuple[Path, str]] = []  # (path, album)
    n_video = n_other = 0

    def album_of(dirpath: str) -> str:
        rel = os.path.relpath(dirpath, root)
        return "." if rel == "." else rel.replace(os.sep, "/")

    # Walk once, reading NOTHING — just classify by name (no hydration).
    for dirpath, _dirs, files in os.walk(root):
        album = album_of(dirpath)
        for name in files:
            low = name.lower()
            if low.endswith(".json"):
                sidecar_paths.append((Path(dirpath) / name, album))
                continue
            ext = os.path.splitext(low)[1]
            if ext in VIDEO_EXTS:
                n_video += 1
                continue
            if ext not in IMAGE_EXTS:
                n_other += 1
                continue
            images.append((name, album, Path(dirpath) / name))
            if limit and len(images) >= limit:
                break
        if limit and len(images) >= limit:
            break

    n_side = len(sidecar_paths)
    n_img = len(images)

    # Pass 1: parse sidecars in parallel (online-only reads are I/O-bound, so a
    # thread pool collapses the per-file OneDrive round-trip latency).
    print(f"  parsing {n_side} sidecars with {workers} threads …", file=sys.stderr)
    t0 = time.time()
    done = n_side_merged = 0
    with ThreadPoolExecutor(max_workers=workers) as ex:
        for rec in ex.map(lambda pa: parse_sidecar(pa[0], pa[1]), sidecar_paths, chunksize=16):
            done += 1
            if done % 2000 == 0:
                print(f"    …{done}/{n_side} sidecars ({(time.time()-t0):.0f}s)", file=sys.stderr)
            if rec and rec.dt:
                key = f"{rec.taken}|{(rec.title or rec.image_name).lower()}"
                if key in recs:
                    n_side_merged += 1   # true cross-album / duplicate copy
                else:
                    recs[key] = rec

    # Pass 2: images with no sidecar metadata -> try filename date, then mtime.
    have = {(r.image_name.lower(), r.album) for r in recs.values()}
    have_names = {r.image_name.lower() for r in recs.values()}
    n_fn = n_mtime = 0
    for name, album, path in images:
        if name.lower() in have_names:        # covered by a sidecar already
            continue
        dt = date_from_filename(name)
        src = "filename"
        if not dt and allow_hydrate:
            dt = _exif_date(path)             # opt-in: hydrates this one file
            src = "exif" if dt else src
        if not dt:
            try:
                dt = datetime.fromtimestamp(path.stat().st_mtime)
                src = "mtime"
            except OSError:
                continue
        if src == "filename":
            n_fn += 1
        elif src == "mtime":
            n_mtime += 1
        junk = bool(JUNK_NAME.search(name) or JUNK_ALBUM.search(album))
        key = f"{dt.isoformat()}|{name.lower()}"
        recs.setdefault(key, Rec(name, album, dt.isoformat(), src,
                                 None, None, None, None, junk, str(path)))

    stats = {
        "sidecars_seen": n_side,
        "images_seen": n_img,
        "videos_seen": n_video,
        "unique_records": len(recs),
        "dedup_removed": n_side_merged,  # cross-album/duplicate sidecar copies merged
        "from_sidecar": sum(1 for r in recs.values() if r.source == "sidecar"),
        "from_filename": n_fn,
        "from_mtime": n_mtime,
        "with_gps": sum(1 for r in recs.values() if r.lat is not None),
        "flagged_junk": sum(1 for r in recs.values() if r.junk),
    }
    return list(recs.values()), stats


def _exif_date(path: Path) -> datetime | None:
    """Opt-in EXIF read (hydrates the file). Imported lazily so Pillow isn't
    required unless --allow-hydrate is used."""
    try:
        from PIL import Image, ExifTags
        import pillow_heif
        pillow_heif.register_heif_opener()
        tagmap = {v: k for k, v in ExifTags.TAGS.items()}
        with Image.open(path) as img:
            exif = img.getexif()
            ifd = exif.get_ifd(0x8769) or {}
            for key in ("DateTimeOriginal", "DateTimeDigitized"):
                raw = ifd.get(tagmap.get(key))
                if raw:
                    try:
                        return datetime.strptime(str(raw)[:19], "%Y:%m:%d %H:%M:%S")
                    except ValueError:
                        pass
    except Exception:
        return None
    return None


# ── Cluster (time + geo gap) ─────────────────────────────────────────────────
def haversine_km(a: Rec, b: Rec) -> float | None:
    if None in (a.lat, a.lon, b.lat, b.lon):
        return None
    r = 6371.0
    p1, p2 = math.radians(a.lat), math.radians(b.lat)
    dphi, dlmb = math.radians(b.lat - a.lat), math.radians(b.lon - a.lon)
    h = math.sin(dphi / 2) ** 2 + math.cos(p1) * math.cos(p2) * math.sin(dlmb / 2) ** 2
    return 2 * r * math.asin(math.sqrt(h))


@dataclass
class Cluster:
    recs: list[Rec] = field(default_factory=list)

    def start(self): return min(r.dt for r in self.recs)
    def end(self):   return max(r.dt for r in self.recs)

    def centroid(self):
        pts = [(r.lat, r.lon) for r in self.recs if r.lat is not None]
        if not pts:
            return None
        return (round(sum(x for x, _ in pts) / len(pts), 5),
                round(sum(y for _, y in pts) / len(pts), 5))

    def confidence(self):
        sc = sum(1 for r in self.recs if r.source == "sidecar")
        gps = any(r.lat is not None for r in self.recs)
        if sc and gps:
            return "high"
        if sc or all(r.source in ("sidecar", "filename") for r in self.recs):
            return "medium"
        return "low"

    def junk_ratio(self):
        return sum(1 for r in self.recs if r.junk) / max(len(self.recs), 1)

    def representative(self):
        ordered = sorted(self.recs, key=lambda r: r.dt)
        return ordered[len(ordered) // 2]

    def sample_paths(self, n: int = 5):
        """Up to n image paths spread evenly across the day, so an event-level
        captioner can see the arc of the day, not just one frame. Non-junk first."""
        ordered = sorted([r for r in self.recs if r.image_path and not r.junk],
                         key=lambda r: r.dt)
        if not ordered:
            return []
        if len(ordered) <= n:
            return [r.image_path for r in ordered]
        step = (len(ordered) - 1) / (n - 1)
        return [ordered[round(i * step)].image_path for i in range(n)]


def cluster(recs: list[Rec], time_gap_h: float, geo_gap_km: float) -> list[Cluster]:
    ordered = sorted((r for r in recs if r.dt), key=lambda r: r.dt)
    out: list[Cluster] = []
    cur = Cluster()
    for r in ordered:
        if not cur.recs:
            cur.recs.append(r); continue
        prev = cur.recs[-1]
        gap_h = (r.dt - prev.dt).total_seconds() / 3600.0
        dist = haversine_km(prev, r)
        if gap_h > time_gap_h or (dist is not None and dist > geo_gap_km):
            out.append(cur); cur = Cluster([r])
        else:
            cur.recs.append(r)
    if cur.recs:
        out.append(cur)
    return out


# ── Report ────────────────────────────────────────────────────────────────────
def write_reports(out_dir: Path, root: Path, clusters: list[Cluster], stats: dict):
    out_dir.mkdir(parents=True, exist_ok=True)
    data = {"root": str(root), "generated": datetime.now().isoformat(timespec="seconds"),
            "stats": stats, "events": []}
    for c in clusters:
        rep = c.representative()
        data["events"].append({
            "start": c.start().isoformat(), "end": c.end().isoformat(),
            "photo_count": len(c.recs), "confidence": c.confidence(),
            "junk_ratio": round(c.junk_ratio(), 2), "centroid": c.centroid(),
            "albums": sorted({r.album for r in c.recs})[:5],
            "rep_title": rep.title or rep.image_name,
            "rep_description": rep.description,
            "rep_path": rep.image_path,     # actual file to hydrate for captioning
            "rep_album": rep.album,
            "sample_paths": c.sample_paths(5),   # a few photos across the day for event-level captioning
        })
    (out_dir / "events_dryrun.json").write_text(json.dumps(data, indent=2), encoding="utf-8")

    dated = [r for c in clusters for r in c.recs]
    dmin = min((r.taken for r in dated), default="—")
    dmax = max((r.taken for r in dated), default="—")
    real = [c for c in clusters if c.junk_ratio() < 0.5]
    lines = [
        "# Photo-import harvest (sidecar-first, no downloads)\n",
        f"**Library:** `{root}`  ", f"**Generated:** {data['generated']}\n",
        "## Summary\n",
        f"- Sidecar JSONs read: **{stats['sidecars_seen']}**",
        f"- Image files indexed (not opened): **{stats['images_seen']}**",
        f"- Videos: {stats['videos_seen']}",
        f"- **Unique records after dedup: {stats['unique_records']}** "
        f"(~{stats['dedup_removed']} cross-album/duplicate copies merged)",
        f"- Date from sidecar: {stats['from_sidecar']} | from filename: {stats['from_filename']} | from mtime: {stats['from_mtime']}",
        f"- With GPS: {stats['with_gps']}",
        f"- Flagged junk (screenshots/downloads/etc): {stats['flagged_junk']}",
        f"- Date range: {dmin[:10]} → {dmax[:10]}",
        f"- **Candidate events: {len(clusters)}**  (of which {len(real)} are mostly-real, junk_ratio < 0.5)\n",
        "## Candidate events (real-looking first 120)\n",
        "| # | Date | Span | Photos | Conf | Junk | Location | Title / album |",
        "|---|------|------|--------|------|------|----------|---------------|",
    ]
    shown = sorted(real, key=lambda c: c.start())
    for i, c in enumerate(shown[:120], 1):
        s, e = c.start(), c.end()
        span = "single" if s.date() == e.date() else f"{(e - s).days + 1}d"
        cen = c.centroid()
        loc = f"{cen[0]:.3f},{cen[1]:.3f}" if cen else "—"
        date_lbl = s.strftime("%Y-%m-%d") if span == "single" else f"{s:%Y-%m-%d}…{e:%m-%d}"
        rep = c.representative()
        label = (rep.title or rep.image_name)[:40]
        lines.append(f"| {i} | {date_lbl} | {span} | {len(c.recs)} | {c.confidence()} | "
                     f"{c.junk_ratio():.0%} | {loc} | {label} |")
    (out_dir / "events_dryrun.md").write_text("\n".join(lines), encoding="utf-8")


def main():
    ap = argparse.ArgumentParser(description="Harvest Takeout/photo metadata into candidate timeline events (no downloads).")
    ap.add_argument("--root", required=True)
    ap.add_argument("--time-gap-hours", type=float, default=8.0)
    ap.add_argument("--geo-gap-km", type=float, default=50.0)
    ap.add_argument("--limit", type=int, default=None, help="Stop after N image files (sampling)")
    ap.add_argument("--allow-hydrate", action="store_true",
                    help="Permit EXIF reads (downloads online-only files) for images with no sidecar AND no filename date. OFF by default.")
    ap.add_argument("--workers", type=int, default=48, help="Threads for parallel sidecar reads (default 48)")
    ap.add_argument("--out", default=str(Path(__file__).parent / "out"))
    args = ap.parse_args()

    root = Path(args.root)
    if not root.is_dir():
        sys.exit(f"Not a directory: {root}")

    print(f"Harvesting metadata from {root} (no image downloads) …", file=sys.stderr)
    recs, stats = harvest(root, args.limit, args.allow_hydrate, args.workers)
    clusters = cluster(recs, args.time_gap_hours, args.geo_gap_km)
    write_reports(Path(args.out), root, clusters, stats)

    print(f"\nDone. {stats['unique_records']} unique records → {len(clusters)} candidate events.", file=sys.stderr)
    print(f"  sidecar:{stats['from_sidecar']} filename:{stats['from_filename']} mtime:{stats['from_mtime']} "
          f"gps:{stats['with_gps']} junk:{stats['flagged_junk']}", file=sys.stderr)
    print(f"  Report: {Path(args.out) / 'events_dryrun.md'}", file=sys.stderr)


if __name__ == "__main__":
    main()
