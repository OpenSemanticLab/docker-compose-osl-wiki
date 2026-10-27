#!/bin/bash
# Put the MediaWiki installation path on the line below
MW_INSTALL_PATH=/var/www/html/w

# MediaWiki keeps links current through LinksUpdate on edit and the job queue.
# refreshLinks.php is a repair tool for drift, so it runs as an occasional
# safety net rather than continuously. A full pass re-parses every page, and on
# a semantic wiki each page also writes to the triplestore and queues a search
# index update, so it competes with web requests for the database, the search
# backend and the triplestore.
#
# Note that --touched-only does not usefully shrink the pass here: Semantic
# MediaWiki touches an annotated page again right after the link update, so
# nearly every page still qualifies.
#
# MW_REFRESHLINKS_DAY   weekday to run on, "off" disables the pass
# MW_REFRESHLINKS_HOUR  hour of day to start, local time
DAY="${MW_REFRESHLINKS_DAY:-Sunday}"
HOUR="${MW_REFRESHLINKS_HOUR:-3}"

echo Starting maintenance service...

if [ -z "$DAY" ] || [ "$DAY" == 'off' ]; then
	echo "refreshLinks is disabled, nothing to schedule."
	exit 0
fi

# Always the same weekday and hour, recomputed before every pass. Sleeping for
# a fixed span after the previous pass instead would drift by however long that
# pass took, and walk out of the quiet hours within a few weeks.
next_run() {
	if [ "$(date +%A)" == "$DAY" ]; then
		local today
		today=$(date -d "today ${HOUR}:00" +%s 2>/dev/null)
		if [ -n "$today" ] && [ "$today" -gt "$(date +%s)" ]; then
			echo "$today"
			return
		fi
	fi
	date -d "next $DAY ${HOUR}:00" +%s 2>/dev/null
}

if [ -z "$(next_run)" ]; then
	echo "Cannot schedule refreshLinks for '$DAY' at '$HOUR'. Check MW_REFRESHLINKS_DAY and MW_REFRESHLINKS_HOUR."
	exit 1
fi

while true; do
	target=$(next_run)
	now=$(date +%s)
	echo "Waiting until $(date -d "@$target") for the next pass..."
	sleep $((target - now))

	# nice and ionice so the pass yields to web requests and the job runner.
	nice -n 19 ionice -c3 php "$MW_INSTALL_PATH/maintenance/refreshLinks.php"
done
