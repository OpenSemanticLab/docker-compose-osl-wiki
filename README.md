# docker-compose-smw-35

## Install
```bash
git clone https://github.com/OpenSemanticLab/docker-compose-osl-wiki
cd docker-compose-osl-wiki
chown -R www-data:www-data mediawiki/data
nano .env
docker-compose build
docker-compose up
```

## Config

### MediaWiki

MediaWiki's core config file LocalSettings.php is created dynamically on every container by merging
- InstallSettings.php
- DockerSettings.php
- CustomSettings.php

InstallSettings.php is created by running maintenance/install.php with parameters defined in .env on the first run.
To recreate this file after change settings in .env set
```yaml
        environment:
            - MW_REINSTALL=true
```

DockerSettings.php is copied from mediawiki/config/DockerSettings.php into the container during build.

CustomSettings.php can be mounted to the container (optional)

```yaml
        volumes:
            - ./mediawiki/config/CustomSettings.php:/var/www/html/w/CustomSettings.php
```

To modify LocalSettings.php without restarting the container, copy the merged file and mount it, this will skip the dynamical creation:
```bash
docker cp -L osl-mw-dev-test_mediawiki_1:/var/www/html/w/LocalSettings.php mediawiki/config/LocalSettings.php
```
in docker-compose.yaml:
```yaml
        volumes:
            - ./mediawiki/config/LocalSettings.php:/var/www/html/w/LocalSettings.php
```

### ReverseProxy
```bash
sudo cp misc/reverse_proxy_nginx.conf /etc/nginx/sites-enabled/default
sudo nano /etc/nginx/sites-enabled/default
```
-> set domain and cert paths

## First Steps

### Important page content
If your instance is public, make sure to add a privacy policy to `/wiki/Site:Privacy_policy` and legal informations to `/wiki/Site:General_disclaimer`.
You may also create a single page with all necessary informations and point with a redirect from other pages to it: `#REDIRECT [[Site:General_disclaimer]]`

### Email service
If you don't have an email server yet (optional, but necessary for notification and password resets, etc.), you can use [docker-mailserver](https://github.com/docker-mailserver/docker-mailserver)

## Maintenance

missing semantic properties after backup restore
```bash
php /var/www/html/w/extensions/SemanticMediaWiki/maintenance/rebuildData.php
```

no search results after backup restore
```bash
php /var/www/html/w/extensions/CirrusSearch/maintenance/ForceSearchIndex.php
```

incorrect link labels (page name instead of display name) after template changes or large imports
```bash
php /var/www/html/w/maintenance/refreshLinks.php
```

missing thumbnails for tif images
```bash
php /var/www/html/w/maintenance/refreshImageMetadata.php --force
```

## Backup
```bash
mkdir backup
docker-compose exec db /bin/bash -c 'mysqldump --all-databases -uroot -p"$MYSQL_ROOT_PASSWORD" 2>/dev/null | gzip | base64 -w 0' | base64 -d > backup/db_backup_$(date +"%Y%m%d_%H%M%S").sql.gz
tar -zcf backup/file_backup_$(date +"%Y%m%d_%H%M%S").tar mediawiki/data
```

## Restore
cleanup old data
```bash
rm -r mediawiki/data
rm -r mysql/data
rm -r blazegraph/data
```
import
```bash
zcat backup/db_backup_<date>.sql.gz | docker exec -i docker-compose-osl-wiki_db_1 sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD"'
tar -xf backup/file_backup_<date>.tar
chown -R www-data:www-data mediawiki/data
```

## DEV

### Version Control
check for modificated extensions
```bash
cd /var/www/html/w/extensions/
find . -maxdepth 1 -mindepth 1 -type d -exec sh -c '(echo {} && cd {} && git status -s && echo)' \;
```

### Debug
create debug file
```bash
touch /var/www/html/w/my-custom-debug.log
chown www-data:www-data /var/www/html/w/my-custom-debug.log
```

in LocalSettings.php:
```php
$wgDebugLogFile = "/var/www/html/w/my-custom-debug.log";
```
in PHP source code:
```php
wfDebug( "\n[tag] some debug message: $somevar.\n" );
```
remove and recreate logfile
```bash
rm /var/www/html/w/my-custom-debug.log && touch /var/www/html/w/my-custom-debug.log && chown www-data:www-data /var/www/html/w/my-custom-debug.log
```

copy files
```bash
docker cp mediawiki/config/pub/* osl-wiki_mediawiki_1:/var/www/html/w/pub/
```
