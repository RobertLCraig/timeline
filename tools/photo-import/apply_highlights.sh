#!/usr/bin/env bash
# Re-decide every year's events in HIGHLIGHTS mode (trips, gatherings, couples,
# milestones — drop everyday family-at-home) and prune the rest from the timeline.
# Roster-only (no GPU) — just re-classifies keep/drop and posts the difference.
set -u
cd "$(dirname "$0")"
PY=./.venv/bin/python
SLUG=photo-import-sandbox-LHCveq

for YR in 2000 2001 2002 2003 2004 2005 2006 2007 2008 2009 2010 2011 2012 2013 2014 \
          2015 2016 2017 2018 2019 2020 2021 2022 2023 2024 2025; do
  D="out/$YR"; [ "$YR" = "2015" ] && D="out/cr2015"
  [ -f "$D/events_captioned.json" ] || continue
  echo "==== highlights $YR ===="
  $PY roster.py --report "$D/events_dryrun.json" --clusters "$D/face_clusters.json" \
      --people "$D/people.json" --captioned "$D/events_captioned.json" --highlights 2>&1 | grep -i error
  $PY post.py --captioned "$D/events_captioned.json" --group "$SLUG" --prune --rate 1.4 2>&1 | tail -1
  sleep 3
done
echo "################ HIGHLIGHTS APPLIED ################"
