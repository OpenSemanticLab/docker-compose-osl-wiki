#!/bin/bash
# Put the MediaWiki installation path on the line below
MW_INSTALL_PATH=/var/www/html/w
echo Starting maintenance service...

# Wait 30m after the server starts up to give other processes time to get started
sleep 30m
echo Started.
while true; do
	# This will 
	php $MW_INSTALL_PATH/maintenance/refreshLinks.php

	# Wait some seconds to let the CPU do other things, like handling web requests, etc
	echo Waiting for 6h...
	sleep 6h
done


