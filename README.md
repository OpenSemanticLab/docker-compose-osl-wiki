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

## DEV
check for modificated extensions
```
cd /var/www/html/w/extensions/
find . -maxdepth 1 -mindepth 1 -type d -exec sh -c '(echo {} && cd {} && git status -s && echo)' \;
```
