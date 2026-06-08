#!/usr/bin/env bash
# Process several years end-to-end, unattended. Family is auto-named from the
# registry; the partner + any new faces are left for a quick human pass on the
# generated label page. Run from the photo-import dir.
set -u
cd "$(dirname "$0")"
PY=./.venv/bin/python
SLUG=photo-import-sandbox-LHCveq
FULL=out/full/events_dryrun.json

for YR in "$@"; do
  echo "################ YEAR $YR ################"
  mkdir -p "out/$YR"
  $PY -c "import json; f=json.load(open('$FULL')); ev=[e for e in f['events'] if e['start'][:4]=='$YR']; json.dump({'root':f['root'],'generated':f['generated'],'stats':{},'events':ev}, open('out/$YR/events_dryrun.json','w')); print('$YR events:', len(ev))"
  echo "-- prewarm --";       $PY prehydrate.py "out/$YR/events_dryrun.json" 40
  echo "-- faces (GPU) --";   $PY faces.py --report "out/$YR/events_dryrun.json" --limit 500 --min-photos 1 --device gpu --out-dir "out/$YR" 2>&1 | grep -iE "Detected|recurring"
  echo "-- match registry --";$PY match_registry.py "out/$YR/face_clusters.json" registry.json 0.55 "out/$YR/people.json" 2>&1 | tail -1
  echo "-- caption --";       $PY caption_event.py --report "out/$YR/events_dryrun.json" --limit 80 --min-photos 1 --out "out/$YR/events_captioned.json" 2>&1 | tail -1
  echo "-- roster --";        $PY roster.py --report "out/$YR/events_dryrun.json" --clusters "out/$YR/face_clusters.json" --people "out/$YR/people.json" --captioned "out/$YR/events_captioned.json" 2>&1 | tail -3
  echo "-- post --";          $PY post.py --captioned "out/$YR/events_captioned.json" --group "$SLUG" --prune 2>&1 | tail -1
  echo "-- label page --";    $PY make_label_page.py "out/$YR/face_clusters.json" "out/$YR/label.html" 80 "out/$YR/people.json" partners.json 2>&1 | tail -1
  echo "################ YEAR $YR DONE ################"
done
echo "ALL YEARS DONE"
