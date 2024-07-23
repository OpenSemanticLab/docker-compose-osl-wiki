# OSL Docker image
This repos is currently used to build the [OSL Mediawiki Docker Image](https://hub.docker.com/r/opensemanticlab/osl-mw). If you just want to use the image, go to https://github.com/OpenSemanticLab/osl-mw-docker-compose. Both repos will soon be merged.

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

### Optional Extensions
- wfLoadExtension( 'Widgets' );
- wfLoadExtension( 'TwitterTag' ); # Not GDPR conform!
- wfLoadExtension( 'WebDAV' ); # Allows access to uploaded files via WebDAV (e. g. directly with MS Word)
- wfLoadExtension( 'RdfExport' ); # exposes an DCAT catalog at `/api.php?action=catalog&format=json&rdf_format=turtle` and allows OWL ontology export (use only in public instances, requires SPARQL-Store)

### SMW Store
Currently the default is blazegraph as SPARQL-Store. Since blazegraph is no longer maintained we are transitioning to use Apache Jena Fuseki.
To switch to Fuseke, add the following settings to your CustomSettings.php file:
```php
$smwgSparqlRepositoryConnector = 'fuseki';
$smwgSparqlEndpoint["query"] = 'http://fuseki:3030/ds/sparql';
$smwgSparqlEndpoint["update"] = 'http://fuseki:3030/ds/update';
```

and run the stack with
```bash
docker compose --profile fuseki up
```

Note: A full data rebuild is required to populate the new store.

to run include sparklis SPARQL editor, run
```bash
docker compose --profile fuseki --profile sparklis up
```
or
```bash
COMPOSE_PROFILES=fuseki,sparklis docker compose up
```

If you do not need a SPARQL endpoint, you can switch to SMWElasticStore by reusing the elasticsearch container:
```php
$smwgDefaultStore = 'SMWElasticStore';
$smwgElasticsearchEndpoints = [
    [
        'host' => 'elasticsearch',
        'port' => 9200,
        'scheme' => 'http'
    ]
];
```

Note: Switch store types requires to re-setup the store.
```bash
php /var/www/html/w/extensions/SemanticMediaWiki/maintenance/setupStore.php
php /var/www/html/w/extensions/SemanticMediaWiki/maintenance/rebuildElasticIndex.php
php /var/www/html/w/extensions/SemanticMediaWiki/maintenance/rebuildData.php
```

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
docker compose down -v && sudo rm -r mediawiki/data && sudo rm -r blazegraph/data && sudo rm -r mysql/data
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

### Push with tag
triggers CI/CD workflow and pushes image with tags to docker registry (see also [stackoverflow: push-git-commits-tags-simultaneously](https://stackoverflow.com/questions/3745135/push-git-commits-tags-simultaneously/57842917#57842917) )
```
git tag <tag>
git push --atomic origin main --tags
```

### Testing
Note: You may have to wait 15 - 30 min for all page packages to be installed on the first run

Pull reqired images (see `./tests/codecept/browsers.json`) from docker registry before running `codeceptjs`:

manually
```sh
docker pull selenoid/video-recorder:latest-release;
docker pull selenoid/firefox:latest;
...
```
automated by parsing `./tests/codecept/browsers.json` (replace `docker run --rm -i imega/jq` with `jq` if installed on your host), see [docs](https://aerokube.com/selenoid/latest/#_syncing_browser_images_from_existing_file)
```sh
docker pull selenoid/video-recorder:latest-release && cat ./tests/codecept/browsers.json | docker run --rm -i imega/jq -r '..|.image?|strings' | xargs -I{} docker pull {}
```

Note: use kiosk mode for demo video recording

Run all tests with a single browser
```sh
docker compose run --rm codeceptjs
```

Run only test with tag `@<tag>` a single browser
```sh
docker compose run --rm codeceptjs codeceptjs run --grep "@<tag>"
```

Run only test with without `@<tag>` a single browser
```sh
docker compose run --rm codeceptjs codeceptjs run --grep "@<tag>" --invert
```

Run multi-browser tests
```sh
docker compose run --rm codeceptjs codeceptjs run-multiple --all
```

More options: https://codecept.io/commands/


You can follow the test execution on selenoid-ui at "http://localhost:8080".
Run with autopause to interact with the browser in a state where test have failed

1. codeceptjs: container name
2. codeceptjs: shell command inside container

```sh
docker compose run --rm codeceptjs codeceptjs run -p pauseOnFail
```

### Create Testcases

Create a new file ./tests/codecept/tests/<name>_test.js

Follow the existing examples or https://codecept.io/tutorial/

To find XPath expressions and test them in the browser:
https://stackoverflow.com/questions/41857614/how-to-find-xpath-of-an-element-in-firefox-inspector

To compare / assert values: https://github.com/SitamJana/codeceptjs-chai
