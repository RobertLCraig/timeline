# photo-import — build Family Timeline events from your photo library

A local, **privacy-preserving** pipeline that turns a folder of photos into
candidate Family Timeline events. Everything runs on this machine — EXIF parsing
locally, and (later) captioning via your **WSL2 Ollama** vision model
(`gemma4:latest`, which reports `vision` capability). No photo ever leaves the box.

## The core principle

> **EXIF tells you *when*. The AI only tells you *what*.**

A vision model cannot reliably date a photo — so we never ask it to. The exact
date and GPS live in EXIF. We use those to **cluster** thousands of photos into a
few hundred candidate events *first*, deterministically, so the optional AI step
only has to caption **one representative photo per event** instead of all of them.

```
STEP 1  harvest   date/GPS/title from SIDECARS + filenames   ← harvest.py  (done)
STEP 2  cluster   group records by time + location gap        ← harvest.py  (done)
STEP 3  caption   several photos/event → gemma4 vision        ← caption_event.py (done)
STEP 4  geocode   GPS → place name (offline)                  ← caption_event.py (done, reverse_geocoder)
STEP 5  post      idempotent create/update event via PAT      ← post.py     (done)
```

The pipeline runs **end-to-end**: harvest → caption → post. Step 4 (reverse
geocoding for place-aware, human-confirmed titles) is the only optional piece
left. Run the stages in order; each writes a file the next consumes:

```
harvest.py → out/<set>/events_dryrun.json
caption.py → out/<set>/events_captioned.json   (reads events_dryrun.json)
post.py    → posts to a group                  (reads events_captioned.json)
```

### caption_event.py (step 3 — event-level, preferred)
A single frame gets *described* but mislabels the *event* ("lakeside view" for a
whole day at the Lakes). `caption_event.py` instead feeds gemma4 **several photos
sampled across the day** (`sample_paths`) plus the facts it may state — date, photo
count, and the **offline reverse-geocoded place** from GPS (`reverse_geocoder`) —
and asks for an event-level title. e.g. *"lakeside view with hills" → "Family day
trip to Penrith, England" [Travel]*.

**Anti-hallucination rules (hard-won):** the model only uses the EXACT place text
given; it must not substitute a region/landmark (Penrith → "Lake District") and,
crucially, when NO GPS place is given it must name NO place at all — otherwise it
invents proper nouns (it produced a fictional "Green Trenthon Manor" before the
rules were tightened). It also won't assert an occasion unless clearly visible.
Needs the GPU (gemma4 ~10 GB VRAM); `caption.py` is the simpler 1-photo variant.

```bash
./.venv/bin/python caption_event.py --report out/cr2015/events_dryrun.json --limit 15 --min-photos 8
```

### faces.py (who-axis — identity, not guessing)
The vision LLM can't tell *who* is in a photo, so the who-axis category was a guess.
`faces.py` runs **InsightFace / ArcFace** locally to detect + embed every face, then
clusters the faceprints into "Person 1, 2, …" (no names). Output is a person sheet
(`person_sheet.png`) of face crops grouped by cluster — you label the recurring
people once, and the who-axis (Family/Couple/Social/Solo, knowns vs bystanders)
becomes a fact. All on-device; faceprints never leave the machine.

```bash
./.venv/bin/python faces.py --report out/cr2015/events_dryrun.json --limit 15 --min-photos 8 --device gpu
```

**GPU setup (one-time, non-obvious):** InsightFace runs on onnxruntime. For CUDA on
the RTX 5080 (Blackwell), install the GPU runtime + CUDA libs via pip wheels — no
system CUDA needed:
```bash
./.venv/bin/pip uninstall -y onnxruntime
./.venv/bin/pip install onnxruntime-gpu nvidia-cudnn-cu12 nvidia-cublas-cu12 \
    nvidia-cufft-cu12 nvidia-curand-cu12 nvidia-cuda-runtime-cu12 nvidia-cuda-nvrtc-cu12
```
The catch: onnxruntime won't find `libcublasLt.so.12` from the pip packages on its
own and silently falls back to CPU. The fix is **`onnxruntime.preload_dlls()`**
before any session is created (faces.py does this when `--device gpu`). Verify with
`Applied providers: ['CUDAExecutionProvider', ...]` in the output.

### Global face registry — label each person ONCE
Re-labelling the same family every year is the redundancy to kill. The registry
stores each person's faceprint(s) so future years auto-recognise them.

- `faces.py` writes a `centroid_emb` (faceprint) per cluster.
- `build_registry.py` aggregates labelled clusters across years → `registry.json`
  (name → relationship → faceprints; accumulates more faceprints per person over time).
- `match_registry.py` matches a new year's clusters to the registry by cosine
  similarity and writes a **prefill `people.json`** — which is exactly the label
  page's prefill input.

**New-year workflow (mostly pre-labelled):**
```
harvest (slice year) → prewarm → faces.py (centroids)
  → match_registry.py face_clusters.json registry.json 0.55 prefill.json
  → make_label_page.py clusters.html 80 prefill.json partners.json   # arrives pre-named
  → (label only NEW faces) → roster.py → post.py
  → build_registry.py registry.json  …all years…                    # fold the new labels back in
```

Proven across a 9-year gap (registry from 2015 → matched 2024): adults recognised
strongly (Robert/Chee/Francis ~0.87–0.89; Daniel/William/Ah Khum 0.6–0.73), new
people correctly flagged unknown. **Caveat:** children who aged a lot don't match
across long gaps (ArcFace drift) — they need a re-label, which then seeds their
faceprint for subsequent years. Tune the match threshold (~0.5 lenient, ~0.6 strict).

### Operational gotchas (learned the hard way)
- **Verify categories against the API, not the roster output.** `post.py`'s
  `CATEGORIES` must list EVERY category the roster can emit (the who-axis
  Family/Couple/Social/Solo/Places **plus** the milestones) — otherwise
  `canonical_category()` silently collapses unknown names to "Other", and the
  roster's correct categories never reach the timeline. A bulk run looked fine in
  the roster logs while posting 146 events as "Other".
- **The API throttles event writes to 60/min/token.** Re-posting many events
  across years in one burst gets 429'd partway. Space bulk re-posts (~1 year per
  minute) or add a delay.

### post.py (step 5)
Posts captioned events with a stable `import_hash` (`sha1(date|rep_filename)`), so
re-runs UPDATE rather than duplicate (relies on the app's idempotency feature,
deployed 2026-06-06). Events are posted **image-less** (title + date) by default to
respect server storage, and default to `visibility=private` + `social=private`.
Non-meaningful / un-captioned events are skipped unless `--include-all`.

```bash
# Preview, then post into a PRIVATE sandbox group first:
./.venv/bin/python post.py --captioned out/cr2015/events_captioned.json \
    --group <sandbox-slug> --dry-run
./.venv/bin/python post.py --captioned out/cr2015/events_captioned.json --group <sandbox-slug>
```

Verified end-to-end on production into a throwaway group: 12 events posted, a second
run updated all 12 (0 duplicates), all private and category-constrained.

## Two tools — use `harvest.py`

| Tool | When | Reads images? |
|------|------|---------------|
| **`harvest.py`** (primary) | Library is a **Google Takeout export** and/or lives in **OneDrive as online-only** files. | **No** — metadata only. |
| `scan.py` (fallback) | Library is **fully local** with reliable EXIF and no sidecars. | Yes (EXIF). |

**Rob's `OneDrive/Pictures` is the first case** (94k files, 99.5% online-only,
25k Takeout sidecars), so use `harvest.py`. `scan.py` would hydrate ~200 GB.

### Why harvest doesn't download anything
- **Date/GPS/title/description** come from the Takeout sidecar JSON
  (`<image>.jpg.json`) — `photoTakenTime`, `geoData`, `title`, `description`.
- For images **without** a sidecar, the date is parsed from the **filename**
  (`20220111_102739`, `IMG_20231101_…`, `PXL_…`, `…-WA0001`, `Screenshot 2020-11-12 …`).
- It also **dedupes Takeout's cross-album copies** (same photo exported into every
  album) on `(taken-time, title)` — this is the "merge back together" step.
- EXIF (which *does* download the file) is opt-in only via `--allow-hydrate`.

> **OneDrive cold-read note:** the sidecars are tiny but online-only, so the first
> harvest pays ~3.5 s/file of OneDrive hydration latency over the WSL→drvfs→cloud
> path → **~30 min one-time** for 25k sidecars (~15 MB on disk). Re-runs are
> instant because the JSONs are then local. `--workers` parallelises the reads.

## Setup (WSL2 Ubuntu)

```bash
cd /mnt/c/Dev/timeline/tools/photo-import
python3 -m venv .venv
./.venv/bin/pip install Pillow pillow-heif    # Pillow only needed for scan.py / --allow-hydrate
```

## Usage

```bash
# PRIMARY — harvest the whole library, no downloads:
./.venv/bin/python harvest.py --root "/mnt/c/Users/r/OneDrive/Pictures" \
    --workers 48 --out ./out/full

# Fallback for a fully-local, EXIF-reliable library:
./.venv/bin/python scan.py --root <dir> --exif-only --skip-junk --out ./out/full
```

Key `harvest.py` flags:

| Flag | Effect |
|------|--------|
| `--workers N` | Threads for parallel sidecar reads (default 48). Collapses OneDrive latency. |
| `--time-gap-hours N` | New event when the time gap exceeds N hours (default 8). |
| `--geo-gap-km N` | New event when GPS jumps more than N km (default 50). |
| `--allow-hydrate` | **Opt-in:** read EXIF (downloads the file) for images with no sidecar and no filename date. Off by default. |
| `--limit N` | Stop after N image files (sampling). |

## Output

Written to `--out` (default `./out/`):

- **`events_dryrun.md`** — human-readable: scan summary + a table of candidate events
  (date, span, photo count, confidence, GPS centroid, representative file).
- **`events_dryrun.json`** — machine-readable clusters; the input the later
  post step will consume.

Each event carries a **confidence**: `high` (all EXIF-dated + has GPS), `medium`,
or `low` (mostly file-time fallback — review before trusting).

## What the first dry run taught us

On a 300-image sample of `OneDrive/Pictures`:
- ~46% had real EXIF dates; those clustered into clean, GPS-tagged day-events
  (e.g. a continuous Jan-2022 run at `51.416,-0.739`).
- ~54% had **no** EXIF and were almost all non-events: screenshots, WhatsApp
  saves, DALL·E art, memes, profile pics. One folder of 122 Instagram screenshots
  shared a single copy-date and collapsed into one bogus "event".
- **Takeaway:** `--exif-only` is the difference between a real timeline and noise.

## Next phase (steps 3–5) — design decisions

These resolve the real-world concerns the library surfaced. Every step stays
on-device; nothing but the finished events (and a few hero images) leaves the box.

**GUARDRAIL — a cluster is a dated SESSION, not a trip. Do not narrate.**
The harvester produces day/session clusters from timestamps. It does **not** know
about trips, and neither does the AI. Hard rules, learned from getting this wrong:
- **Never merge clusters by location.** The same place (e.g. Snowdonia) appears
  across many separate trips over years. Same GPS ≠ same occasion.
- **Never sum or aggregate across clusters** to describe "an event." Each cluster
  stands alone.
- **A centroid is only meaningful for a low-spread, stationary cluster.** For a
  travelling day it is a nonsense midpoint — averaging a DC→LA drive lands you in
  Kansas, where nobody was. If a cluster's GPS spread is large, show a *range* or
  "in transit", never a single averaged "location".
- **GPS is sparse and fragmentary** (~17% of events). The few geotagged photos do
  NOT define a journey's extent — a coast-to-coast road trip can surface as two
  random inland points. Do not infer route, scope, or destination from them.
- **The vision model describes the PHOTO + its date — it does not invent a journey.**
  Any trip/place name is at most a *low-confidence suggestion the human confirms*,
  never an asserted fact in a title or description.
- **Trip/"chapter" grouping (spanning multiple day-clusters) is a separate,
  explicitly human-in-the-loop step.** Propose, show the evidence, let Rob confirm
  or split. Never auto-create trip-level events.

**Storage discipline (the destination is Hostinger shared hosting — not unlimited).**
Default to **dateline events with no uploaded image**: title + date + place is
enough to record "a thing happened that day." Upload a hero image only when it
earns its place (milestone, faces, a genuinely memorable scene — the vision model
scores this), and downscale it first. For rich days, link the Google album as
`album_url` instead of uploading many files. Target: a few hundred uploaded
images, not 67,000.

**Duplicates / near-matches — two tiers, two stages.**
1. *Exact / cross-album* (Takeout copies the same photo into every album): merged
   during harvest on `(taken-time, title)`. Free, no pixels. (Done in `harvest.py`.)
2. *Near-visual & bursts* (re-compressed, lightly edited, 10 shots in 8 s):
   perceptual hash (pHash, Hamming distance). Needs pixels, so it runs only at the
   selective-upload stage on the few candidates per event — never the whole library.

**Categories — constrain, don't invent.** The vision model is handed the app's 10
categories (Birth, Move, Anniversary, Graduation, Milestone, Wedding, Travel,
Career, Health, Other) and must pick one or `Other`. It may **not** create
categories per-event (sprawl). New categories only via a separate, human-gated
review: if many `Other` events cluster on a recurring theme, propose it; on a yes,
create it once via the `create_category` tool.

**Caption.** POST the representative image (base64) to Ollama
`http://localhost:11434/api/generate`, `model: gemma4:latest` (reports `vision`),
asking for a short title + 1-sentence description + a category from the list
above. One call per cluster, not per photo.

**Geocode.** Offline reverse geocoder (e.g. `reverse_geocoder`) GPS → place name; no cloud.

**Post.** Use the RUNBOOK's REST path: token at `C:\Dev\_secrets\timeline.enhanceify.txt`
(`events:write`), bulk NDJSON loop. **First runs target a NEW private test group at
lowest visibility** so AI titles are reviewed before anything is family-facing.
Transcode HEIC→JPEG before any `POST /api/upload` (upload validation rejects HEIC).

**Idempotency — DONE in the app.** The `events` table now has a nullable
`import_hash` with a composite unique index `(group_id, import_hash)`. Posting (REST
`POST /api/groups/{slug}/events` or the MCP `post_timeline_event` tool) with an
`import_hash` is idempotent: a repeat hash UPDATES the matching event (HTTP 200)
instead of duplicating (HTTP 201 on first create), guarded by the same ownership
rule as an edit. Covered by `tests/Feature/ImportHashTest.php`.
The importer should set `import_hash` to a stable per-cluster key, e.g.
`sha1(earliest_photoTakenTime + "|" + sorted_rep_filename)` — stable across re-runs
as long as the cluster's identity doesn't change. Max 64 chars.

**OneDrive space hygiene.** Harvest reads only sidecars (~15 MB). The later
selective-upload step hydrates one image, uses it, then re-evicts it
(`attrib +U` / online-only) so net local disk stays flat. Longer term, Microsoft
Graph's `photo`/`location` facets expose taken-date + GPS with **zero** hydration —
the proper fix if the ~30-min cold sidecar read becomes a recurring annoyance.
