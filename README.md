# docker-compose-smw-35

## Install
```
git clone https://github.com/simontaurus/docker-compose-smw-35 docker-compose-smw
cd docker-compose-smw
chown -R www-data:www-data mediawiki/data
nano .env
docker-compose build
docker-compose up
```

## Maintenance

missing semantic properties after backup restore
```
php /var/www/html/w/extensions/SemanticMediaWiki/maintenance/rebuildData.php
```

no search results after backup restore
```
php /var/www/html/w/extensions/CirrusSearch/maintenance/ForceSearchIndex.php
```

incorrect link labels (page name instead of display name) after template changes or large imports
```
php /var/www/html/w/maintenance/refreshLinks.php
```

missing thumbnails for tif images
```
php /var/www/html/w/maintenance/refreshImageMetadata.php --force
```

## Backup
```
mkdir backup
docker-compose exec db /bin/bash -c 'mysqldump --all-databases -uroot -p"$MYSQL_ROOT_PASSWORD" 2>/dev/null | gzip | base64 -w 0' | base64 -d > backup/db_backup_$(date +"%Y%m%d_%H%M%S").sql.gz
tar -zcf backup/file_backup_$(date +"%Y%m%d_%H%M%S").tar mediawiki/data
```

## Restore
cleanup old data
```
rm -r mediawiki/data
rm -r mysql/data
rm -r blazegraph/data
```
import
```
zcat backup/db_backup_<date>.sql.gz | docker exec -i docker-compose-osl-wiki_db_1 sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD"'
tar -xf backup/file_backup_<date>.tar
chown -R www-data:www-data mediawiki/data
```

## DEV

### Version Control
check for modificated extensions
```
cd /var/www/html/w/extensions/
find . -maxdepth 1 -mindepth 1 -type d -exec sh -c '(echo {} && cd {} && git status -s && echo)' \;
```

### Debug
create debug file
touch /var/www/html/w/my-custom-debug.log
chown www-data:www-data /var/www/html/w/my-custom-debug.log

in LocalSettings.php:
```
$wgDebugLogFile = "/var/www/html/w/my-custom-debug.log";
```
in PHP source code:
```
wfDebug( "\n[tag] some debug message: $somevar.\n" );
```
remove and recreate logfile
```
rm /var/www/html/w/my-custom-debug.log && touch /var/www/html/w/my-custom-debug.log && chown www-data:www-data /var/www/html/w/my-custom-debug.log
```

copy files
```
docker cp mediawiki/config/pub/* osl-wiki_mediawiki_1:/var/www/html/w/pub/
```
