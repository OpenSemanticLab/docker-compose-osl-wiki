#!/bin/bash
# Put the MediaWiki installation path on the line below
MW_INSTALL_PATH=/var/www/html/w
SMW_MAINTENANCE="$MW_INSTALL_PATH/extensions/SemanticMediaWiki/maintenance"

# Semantic MediaWiki keeps its data current through the job queue, so this is
# not the rebuild that follows an upgrade, which run.sh handles once per
# version. This pass exists because the SPARQLStore is a secondary store fed by
# jobs: SPARQLStore::doDataUpdate writes the SQL store first and the triplestore
# second, so a failed or abandoned job leaves the triplestore behind while the
# SQL store already looks current.
#
# That is why this runs --force-update. --shallow-update and --revision-mode
# both decide what to skip by comparing the SQL store's own modification date
# against the page, which is exactly the value that is already correct when the
# triplestore write was the half that failed, so they skip the drifted pages.
# A full pass is the only thing that repairs this, and it takes hours. The wiki
# stays available throughout.
#
# MW_SMW_REBUILD_DAY    day of month to run on, 1 to 28, "off" disables
# MW_SMW_REBUILD_HOUR   hour of day to start, local time
# MW_SMW_REBUILD_DELAY  milliseconds to wait after each entity, see run.sh
DAY="${MW_SMW_REBUILD_DAY:-1}"
HOUR="${MW_SMW_REBUILD_HOUR:-1}"

echo Starting SMW maintenance service...

if [ -z "$DAY" ] || [ "$DAY" == 'off' ]; then
	echo "SMW rebuild is disabled, nothing to schedule."
	exit 0
fi
# 10# so that a zero padded day is read as decimal and not as octal.
if ! DAY=$((10#$DAY)) 2>/dev/null || [ "$DAY" -lt 1 ] || [ "$DAY" -gt 28 ]; then
	echo "MW_SMW_REBUILD_DAY must be between 1 and 28, got '$MW_SMW_REBUILD_DAY'."
	exit 1
fi
if ! [ "$HOUR" -ge 0 ] 2>/dev/null || [ "$HOUR" -gt 23 ]; then
	echo "MW_SMW_REBUILD_HOUR must be between 0 and 23, got '$HOUR'."
	exit 1
fi

DELAY=""
if [ -n "$MW_SMW_REBUILD_DELAY" ]; then
	if ! [ "$MW_SMW_REBUILD_DELAY" -ge 0 ] 2>/dev/null; then
		echo "MW_SMW_REBUILD_DELAY must be a number of milliseconds, got '$MW_SMW_REBUILD_DELAY'."
		exit 1
	fi
	DELAY="--d $MW_SMW_REBUILD_DELAY"
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
	echo "Cannot schedule the SMW rebuild for day '$DAY' at hour '$HOUR'."
	exit 1
fi

SMW_REBUILD_MARKER="$MW_VOLUME/maintenance_smw_rebuildData.info"

while true; do
	target=$(next_run)
	now=$(date +%s)
	if [ -z "$target" ] || [ "$target" -le "$now" ]; then
		echo "Could not work out the next run, retrying in an hour."
		sleep 1h
		continue
	fi
	echo "Waiting until $(date -d "@$target") for the next SMW rebuild..."
	sleep $((target - now))

	# Skip while the once per upgrade rebuild from run.sh is still outstanding,
	# so the two do not run at once. Only when run.sh is actually going to run
	# it: with MW_AUTOUPDATE off nothing ever writes the marker, and waiting for
	# it would disable this pass for the life of the container.
	if [ "$MW_AUTOUPDATE" == 'true' ] && [ -n "$MW_SMW_VERSION" ] \
	   && [ "$(cat "$SMW_REBUILD_MARKER" 2>/dev/null)" != "$MW_SMW_VERSION" ]; then
		echo "The rebuild for $MW_SMW_VERSION has not completed yet, skipping this pass."
		continue
	fi

	echo "Starting SMW rebuild..."
	# nice and ionice so the pass yields to web requests and the job runner.
	# --ignore-exceptions so one unparsable page does not abort the whole pass.
	if nice -n 19 ionice -c3 php "$SMW_MAINTENANCE/rebuildData.php" \
	        --force-update --ignore-exceptions $DELAY; then
		echo "SMW rebuild finished"
	else
		echo "SMW rebuild failed, it will be retried on the next pass"
	fi
done
