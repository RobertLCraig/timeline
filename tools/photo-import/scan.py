#!/usr/bin/env python3
"""
Family Timeline — photo-import prototype: STEP 1 (scan) + STEP 2 (cluster).

Dry run only. Reads a photo library, extracts EXIF date + GPS for every image,
then groups the photos into candidate "timeline events" using a deterministic
time + location gap heuristic. NO AI and NO posting happen here — the point is
to validate that the *when* (and rough *where*) is trustworthy before any vision
model or API call is involved.

Why deterministic first: a vision model cannot reliably tell you *when* a photo
was taken. The date/GPS in EXIF is exact and free; clustering on it turns
thousands of photos into a few hundred candidate events, so the later (optional)
captioning step only has to look at one representative photo per event.

Outputs (next to this script, under ./out/):
  - events_dryrun.json   machine-readable clusters (for the later post step)
  - events_dryrun.md     human-readable report to eyeball

Usage (from WSL, where Ollama/photos live):
  ./.venv/bin/python scan.py --root "/mnt/c/Users/r/OneDrive/Pictures"
  ./.venv/bin/python scan.py --root <dir> --limit 500        # quick sample
  ./.venv/bin/python scan.py --root <dir> --time-gap-hours 8 --geo-gap-km 50
"""

from __future__ import annotations

import argparse
import json
import math
import os
import re
import sys
from dataclasses import dataclass, asdict, field
from datetime import datetime, timedelta
from pathlib import Path

from PIL import Image, ExifTags
import pillow_heif

# Let Pillow open HEIC/HEIF (iPhone default format).
pillow_heif.register_heif_opener()

IMAGE_EXTS = {".jpg", ".jpeg", ".png", ".heic", ".heif", ".tif", ".tiff", ".webp"}
VIDEO_EXTS = {".mov", ".mp4", ".m4v", ".avi", ".3gp"}  # counted but not parsed (yet)

# Filenames that are almost never a real-life event: screenshots, social saves,
# AI art, web-download hashes, profile thumbnails. Used by --skip-junk. The dry
# run showed these are what pollutes the timeline (and they rarely carry EXIF).
JUNK_PATTERNS = re.compile(
    r"(?i)("
    r"screenshot|screen[ _-]?shot|whatsapp|dall[·e]|dalle|"
    r"^img_\d{10,}|qimg|fullview|_n\.jpg$|-icon\.|sticker|meme|"
    r"^capture\.|^untitled|download|^received_|^fb_img"
    r")"
)

# EXIF tag-name -> id, resolved once.
_TAG = {v: k for k, v in ExifTags.TAGS.items()}
_GPS_TAG = {v: k for k, v in ExifTags.GPSTAGS.items()}


@dataclass
class Photo:
    path: str
    taken: str | None          # ISO datetime string, or None
    date_source: str           # "exif" | "mtime"
    lat: float | None
    lon: float | None

    @property
    def dt(self) -> datetime | None:
        return datetime.fromisoformat(self.taken) if self.taken else None


# ── EXIF extraction ──────────────────────────────────────────────────────────

def _to_float(rational) -> float:
    """EXIF rationals come back as IFDRational / (num, den) / float."""
    try:
        return float(rational)
    except (TypeError, ValueError):
        try:
            return rational[0] / rational[1]
        except Exception:
            return 0.0


def _gps_to_decimal(dms, ref) -> float | None:
    if not dms or len(dms) != 3:
        return None
    deg, minutes, sec = (_to_float(x) for x in dms)
    val = deg + minutes / 60.0 + sec / 3600.0
    if ref in ("S", "W"):
        val = -val
    return round(val, 6)


def _parse_exif_dt(raw: str) -> datetime | None:
    # EXIF date format is "YYYY:MM:DD HH:MM:SS"; sometimes with subsec/offset junk.
    raw = raw.strip().split(".")[0]
    for fmt in ("%Y:%m:%d %H:%M:%S", "%Y:%m:%d %H:%M", "%Y-%m-%d %H:%M:%S"):
        try:
            return datetime.strptime(raw[:19], fmt)
        except ValueError:
            continue
    return None


def read_photo(path: Path) -> Photo:
    taken: datetime | None = None
    lat = lon = None
    try:
        with Image.open(path) as img:
            exif = img.getexif()
            if exif:
                # Date: prefer DateTimeOriginal, then DateTimeDigitized, then DateTime.
                ifd = exif.get_ifd(_TAG.get("ExifOffset", 0x8769)) or {}
                for key in ("DateTimeOriginal", "DateTimeDigitized"):
                    tid = _TAG.get(key)
                    if tid and ifd.get(tid):
                        taken = _parse_exif_dt(str(ifd[tid]))
                        if taken:
                            break
                if not taken and exif.get(_TAG.get("DateTime")):
                    taken = _parse_exif_dt(str(exif[_TAG["DateTime"]]))

                # GPS
                gps = exif.get_ifd(_TAG.get("GPSInfo", 0x8825)) or {}
                if gps:
                    lat = _gps_to_decimal(
                        gps.get(_GPS_TAG.get("GPSLatitude")),
                        gps.get(_GPS_TAG.get("GPSLatitudeRef")),
                    )
                    lon = _gps_to_decimal(
                        gps.get(_GPS_TAG.get("GPSLongitude")),
                        gps.get(_GPS_TAG.get("GPSLongitudeRef")),
                    )
    except Exception:
        pass  # unreadable / corrupt header -> falls through to mtime

    if taken:
        return Photo(str(path), taken.isoformat(), "exif", lat, lon)

    # Fallback: file modified time. Lower confidence — flagged via date_source.
    mtime = datetime.fromtimestamp(path.stat().st_mtime)
    return Photo(str(path), mtime.isoformat(), "mtime", lat, lon)


# ── Clustering (STEP 2) ──────────────────────────────────────────────────────

def haversine_km(a: Photo, b: Photo) -> float | None:
    if None in (a.lat, a.lon, b.lat, b.lon):
        return None
    r = 6371.0
    p1, p2 = math.radians(a.lat), math.radians(b.lat)
    dphi = math.radians(b.lat - a.lat)
    dlmb = math.radians(b.lon - a.lon)
    h = math.sin(dphi / 2) ** 2 + math.cos(p1) * math.cos(p2) * math.sin(dlmb / 2) ** 2
    return 2 * r * math.asin(math.sqrt(h))


@dataclass
class Cluster:
    photos: list[Photo] = field(default_factory=list)

    def start(self) -> datetime:
        return min(p.dt for p in self.photos)

    def end(self) -> datetime:
        return max(p.dt for p in self.photos)

    def representative(self) -> Photo:
        # Middle photo chronologically — usually more "into" the event than the first.
        ordered = sorted(self.photos, key=lambda p: p.dt)
        return ordered[len(ordered) // 2]

    def centroid(self) -> tuple[float, float] | None:
        pts = [(p.lat, p.lon) for p in self.photos if p.lat is not None]
        if not pts:
            return None
        return (round(sum(x for x, _ in pts) / len(pts), 5),
                round(sum(y for _, y in pts) / len(pts), 5))

    def confidence(self) -> str:
        exif_dates = sum(1 for p in self.photos if p.date_source == "exif")
        has_gps = any(p.lat is not None for p in self.photos)
        if exif_dates == len(self.photos) and has_gps:
            return "high"
        if exif_dates >= len(self.photos) / 2:
            return "medium"
        return "low"  # mostly mtime-derived dates — review before trusting


def cluster_photos(photos: list[Photo], time_gap_h: float, geo_gap_km: float) -> list[Cluster]:
    """Session clustering: walk photos in time order, break into a new event when
    the time gap to the previous photo exceeds time_gap_h, OR both photos have GPS
    and they're more than geo_gap_km apart (a clear location jump on the same day)."""
    ordered = sorted((p for p in photos if p.dt), key=lambda p: p.dt)
    clusters: list[Cluster] = []
    cur = Cluster()
    for p in ordered:
        if not cur.photos:
            cur.photos.append(p)
            continue
        prev = cur.photos[-1]
        gap_h = (p.dt - prev.dt).total_seconds() / 3600.0
        dist = haversine_km(prev, p)
        if gap_h > time_gap_h or (dist is not None and dist > geo_gap_km):
            clusters.append(cur)
            cur = Cluster([p])
        else:
            cur.photos.append(p)
    if cur.photos:
        clusters.append(cur)
    return clusters


# ── Scan + report ────────────────────────────────────────────────────────────

def scan_dir(root: Path, limit: int | None, skip_junk: bool):
    photos: list[Photo] = []
    n_seen = n_video = n_other = n_junk = 0
    for dirpath, _dirs, files in os.walk(root):
        for name in files:
            ext = os.path.splitext(name)[1].lower()
            if ext in VIDEO_EXTS:
                n_video += 1
                continue
            if ext not in IMAGE_EXTS:
                n_other += 1
                continue
            if skip_junk and JUNK_PATTERNS.search(name):
                n_junk += 1
                continue
            n_seen += 1
            photos.append(read_photo(Path(dirpath) / name))
            if n_seen % 250 == 0:
                print(f"  …scanned {n_seen} images", file=sys.stderr)
            if limit and n_seen >= limit:
                return photos, n_seen, n_video, n_other, n_junk
    return photos, n_seen, n_video, n_other, n_junk


def write_reports(out_dir: Path, root: Path, clusters: list[Cluster], stats: dict):
    out_dir.mkdir(parents=True, exist_ok=True)

    data = {
        "root": str(root),
        "generated": datetime.now().isoformat(timespec="seconds"),
        "stats": stats,
        "events": [],
    }
    for c in clusters:
        rep = c.representative()
        data["events"].append({
            "start": c.start().isoformat(),
            "end": c.end().isoformat(),
            "photo_count": len(c.photos),
            "confidence": c.confidence(),
            "centroid": c.centroid(),
            "representative": rep.path,
            "photos": [p.path for p in sorted(c.photos, key=lambda p: p.dt)],
        })
    (out_dir / "events_dryrun.json").write_text(json.dumps(data, indent=2), encoding="utf-8")

    # Human-readable
    lines = [
        f"# Photo-import dry run\n",
        f"**Library:** `{root}`  ",
        f"**Generated:** {data['generated']}\n",
        "## Scan summary\n",
        f"- Images scanned: **{stats['images']}**",
        f"- With real EXIF date: **{stats['with_exif_date']}** "
        f"({stats['with_exif_date'] * 100 // max(stats['images'], 1)}%)",
        f"- With GPS: **{stats['with_gps']}** "
        f"({stats['with_gps'] * 100 // max(stats['images'], 1)}%)",
        f"- Fell back to file-time (lower confidence): **{stats['mtime_fallback']}**",
        f"- Videos found (not parsed): {stats['videos']}",
        f"- Junk filenames skipped (--skip-junk): {stats.get('junk_skipped', 0)}",
        f"- Dropped (no EXIF date, --exif-only): {stats.get('dropped_mtime_only', 0)}",
        f"- Date range: {stats['date_min']} → {stats['date_max']}",
        f"- **Candidate events (clusters): {len(clusters)}**\n",
        "## Candidate events\n",
        "| # | Date | Span | Photos | Conf | Location | Representative |",
        "|---|------|------|--------|------|----------|----------------|",
    ]
    for i, c in enumerate(clusters, 1):
        s, e = c.start(), c.end()
        span = "single" if s.date() == e.date() else f"{(e - s).days + 1}d"
        loc = "—"
        cen = c.centroid()
        if cen:
            loc = f"{cen[0]:.3f},{cen[1]:.3f}"
        rep = os.path.basename(c.representative().path)
        date_lbl = s.strftime("%Y-%m-%d") if span == "single" else f"{s:%Y-%m-%d}…{e:%m-%d}"
        lines.append(
            f"| {i} | {date_lbl} | {span} | {len(c.photos)} | {c.confidence()} | {loc} | `{rep}` |"
        )
    (out_dir / "events_dryrun.md").write_text("\n".join(lines), encoding="utf-8")


def main():
    ap = argparse.ArgumentParser(description="Scan a photo library and propose timeline-event clusters (dry run).")
    ap.add_argument("--root", required=True, help="Photo library root (e.g. /mnt/c/Users/r/OneDrive/Pictures)")
    ap.add_argument("--time-gap-hours", type=float, default=8.0, help="New event when time gap exceeds this (default 8h)")
    ap.add_argument("--geo-gap-km", type=float, default=50.0, help="New event when GPS jump exceeds this (default 50km)")
    ap.add_argument("--min-cluster-size", type=int, default=1, help="Drop clusters with fewer than N photos (default 1)")
    ap.add_argument("--exif-only", action="store_true", help="Keep only photos with a real EXIF date (drop file-time fallbacks — removes most screenshots/downloads)")
    ap.add_argument("--skip-junk", action="store_true", help="Skip files whose name matches the junk pattern (screenshots, WhatsApp, DALL·E, etc.)")
    ap.add_argument("--limit", type=int, default=None, help="Stop after N images (quick sample run)")
    ap.add_argument("--out", default=str(Path(__file__).parent / "out"), help="Output dir")
    args = ap.parse_args()

    root = Path(args.root)
    if not root.is_dir():
        sys.exit(f"Not a directory: {root}")

    print(f"Scanning {root} …", file=sys.stderr)
    photos, n_img, n_video, n_other, n_junk = scan_dir(root, args.limit, args.skip_junk)

    n_dropped_mtime = 0
    if args.exif_only:
        before = len(photos)
        photos = [p for p in photos if p.date_source == "exif"]
        n_dropped_mtime = before - len(photos)

    clusters = cluster_photos(photos, args.time_gap_hours, args.geo_gap_km)
    if args.min_cluster_size > 1:
        clusters = [c for c in clusters if len(c.photos) >= args.min_cluster_size]

    dated = [p for p in photos if p.dt]
    stats = {
        "images": n_img,
        "with_exif_date": sum(1 for p in photos if p.date_source == "exif"),
        "with_gps": sum(1 for p in photos if p.lat is not None),
        "mtime_fallback": sum(1 for p in photos if p.date_source == "mtime"),
        "videos": n_video,
        "non_image_files": n_other,
        "junk_skipped": n_junk,
        "exif_only": args.exif_only,
        "dropped_mtime_only": n_dropped_mtime,
        "date_min": min(p.taken for p in dated)[:10] if dated else "—",
        "date_max": max(p.taken for p in dated)[:10] if dated else "—",
        "time_gap_hours": args.time_gap_hours,
        "geo_gap_km": args.geo_gap_km,
    }

    write_reports(Path(args.out), root, clusters, stats)

    print(f"\nDone. {n_img} images → {len(clusters)} candidate events.", file=sys.stderr)
    print(f"  EXIF dates: {stats['with_exif_date']}  |  GPS: {stats['with_gps']}  |  mtime fallback: {stats['mtime_fallback']}", file=sys.stderr)
    print(f"  Report: {Path(args.out) / 'events_dryrun.md'}", file=sys.stderr)


if __name__ == "__main__":
    main()
