#!/usr/bin/env bash
# Overnight: (1) re-caption existing years with the noise filter and prune the
# junk already posted; (2) process every remaining year end-to-end (recent first,
# so the most relevant arrive first). Resilient — a failure in one year doesn't
# stop the rest. Existing years keep their human labels (people.json untouched);
# only new years get fresh registry-matched labels.
set -u
cd "$(dirname "$0")"
PY=./.venv/bin/python
SLUG=photo-import-sandbox-LHCveq

EXISTING="2015 2017 2018 2019 2022 2023 2024"
NEW="2025 2026 2021 2020 2016 2014 2013 2012 2011 2010 2009 2008 2007 2006 2005 2004 2003 2002 2001 2000"

echo "################ PART 1: clean existing years (noise filter + prune) ################"
for YR in $EXISTING; do
  D="out/$YR"; [ "$YR" = "2015" ] && D="out/cr2015"
  echo "==== re-caption + prune $YR ===="
  $PY caption_event.py --report "$D/events_dryrun.json" --limit 80 --min-photos 1 --out "$D/events_captioned.json" 2>&1 | tail -1
  $PY roster.py --report "$D/events_dryrun.json" --clusters "$D/face_clusters.json" --people "$D/people.json" --captioned "$D/events_captioned.json" 2>&1 | tail -1
  $PY post.py --captioned "$D/events_captioned.json" --group "$SLUG" --prune 2>&1 | tail -1
done

echo "################ PART 2: process remaining years (recent first) ################"
bash process_years.sh $NEW

echo "################ ALL OVERNIGHT WORK DONE ################"
