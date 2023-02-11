#!/bin/bash
# Put the MediaWiki installation path on the line below
MW_INSTALL_PATH=/var/www/html/w
BUILDMAP=$MW_INSTALL_PATH/maintenance/generateSitemap.php
echo Starting sitemap builder service...
if [ ! -d "$MW_INSTALL_PATH/sitemap" ]; then
	echo "sitemap dir not existing, create it"
	mkdir "$MW_INSTALL_PATH/sitemap"
fi
# Wait a minute after the server starts up to give other processes time to get started
sleep 60
echo Started.
while true; do
	# This will create a sitemap index. See https://www.mediawiki.org/wiki/Manual:GenerateSitemap.php
	php $BUILDMAP --memory-limit=50M --fspath=$MW_INSTALL_PATH/sitemap/ --urlpath=/w/sitemap/ --server="$1" --compress=yes --skip-redirects
	if [ ! -L "$MW_INSTALL_PATH/sitemap.xml" ]; then
		ln -s $MW_INSTALL_PATH/sitemap/sitemap-index-mediawiki.xml $MW_INSTALL_PATH/sitemap.xml
	fi
	# Wait some seconds to let the CPU do other things, like handling web requests, etc
	echo Waiting for 1m...
	sleep 1m
done


