#!/bin/bash
# Put the MediaWiki installation path on the line below
MW_INSTALL_PATH=/var/www/html/w
SMW_MAINTENANCE="$MW_INSTALL_PATH/extensions/SemanticMediaWiki/maintenance"

# Semantic MediaWiki keeps its data current through the job queue, so this is
# not the rebuild that follows an upgrade, which run.sh handles once per
# version. This pass exists because the SPARQLStore is a secondary store fed by
# jobs: an abandoned job silently drops an update and the triplestore drifts
# away from the SQL store with nothing reporting it.
#
# It runs with --shallow-update, which skips entities that still match the last
# known revision date, so it costs a fraction of a full rebuild. Use
# rebuildData.php without that flag by hand when a full pass is really wanted.
#
# MW_SMW_REBUILD_DAY    day of month to run on, 1 to 28, "off" disables
# MW_SMW_REBUILD_HOUR   hour of day to start, local time
# MW_SMW_REBUILD_DELAY  milliseconds to wait after each entity, see run.sh
DAY="${MW_SMW_REBUILD_DAY:-1}"
HOUR="${MW_SMW_REBUILD_HOUR:-1}"

DELAY=""
if [ -n "$MW_SMW_REBUILD_DELAY" ]; then
	DELAY="--d $MW_SMW_REBUILD_DELAY"
fi

echo Starting SMW maintenance service...

if [ -z "$DAY" ] || [ "$DAY" == 'off' ]; then
	echo "SMW shallow rebuild is disabled, nothing to schedule."
	exit 0
fi
if ! [ "$DAY" -ge 1 ] 2>/dev/null || ! [ "$DAY" -le 28 ]; then
	echo "MW_SMW_REBUILD_DAY must be between 1 and 28, got '$DAY'."
	exit 1
fi

# Same day of month and hour every time, recomputed before every pass. Sleeping
# a fixed span after the previous pass instead would drift by however long that
# pass took. Days past 28 are refused so the date never skips a short month.
next_run() {
	local now target month
	now=$(date +%s)
	target=$(date -d "$(date +%Y-%m)-$(printf '%02d' "$DAY") ${HOUR}:00" +%s 2>/dev/null)
	if [ -n "$target" ] && [ "$target" -gt "$now" ]; then
		echo "$target"
		return
	fi
	# First of this month plus one month, so the result never lands on the 31st
	# of a month that has no 31st.
	month=$(date -d "$(date +%Y-%m-01) +1 month" +%Y-%m 2>/dev/null)
	date -d "${month}-$(printf '%02d' "$DAY") ${HOUR}:00" +%s 2>/dev/null
}

if [ -z "$(next_run)" ]; then
	echo "Cannot schedule the SMW shallow rebuild for day '$DAY' at '$HOUR'."
	exit 1
fi

SMW_REBUILD_MARKER="$MW_VOLUME/maintenance_smw_rebuildData.info"

while true; do
	target=$(next_run)
	now=$(date +%s)
	echo "Waiting until $(date -d "@$target") for the next SMW shallow rebuild..."
	sleep $((target - now))

	# Skip while the once per upgrade rebuild from run.sh is still outstanding.
	# That one has to finish first, and running both at once only adds load.
	if [ -n "$MW_SMW_VERSION" ] \
	   && [ "$(cat "$SMW_REBUILD_MARKER" 2>/dev/null)" != "$MW_SMW_VERSION" ]; then
		echo "The rebuild for $MW_SMW_VERSION has not completed yet, skipping this pass."
		continue
	fi

	echo "Starting SMW shallow rebuild..."
	# nice and ionice so the pass yields to web requests and the job runner.
	if nice -n 19 ionice -c3 php "$SMW_MAINTENANCE/rebuildData.php" --shallow-update $DELAY; then
		echo "SMW shallow rebuild finished"
	else
		echo "SMW shallow rebuild failed, it will be retried on the next pass"
	fi
done
