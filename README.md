# docker-compose-smw-35

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
