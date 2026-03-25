# docker-compose-osl-wiki
Docker Compose for Mediawiki + OpenSemanticLab

## Deploy

### Hardware Requirements

Minimal Setup
- 4 CPUs ( > 2 GHz)
- 4GB RAM
- 50 GB HDD

Recommended:
- 8 CPUs,
- 8 GB RAM
- 100 GB SSD

OS: Any OS with support for Docker, e.g. Ubuntu in its current LTS version (24.04.3)

### Prerequisites

Required
- [Docker](https://docs.docker.com/engine/install/)

Recommended to follow instructions:
- [Git](https://git-scm.com/book/en/v2/Getting-Started-Installing-Git)

Optional SSL Termination
- [Nginx](https://docs.nginx.com/nginx/admin-guide/installing-nginx/installing-nginx-open-source/) for SSL Termination + [Certbot](https://certbot.eff.org/instructions) to create SSL/TSL certs with [Let's Encrypt](https://letsencrypt.org)
- or (recommended) [caddy-docker-proxy](https://github.com/opensemanticworld/caddy-docker-proxy) as integrated service, example config see `docker-compose.caddy.example.override.yml`


### Clone

Clone & init the repo

```bash
git clone https://github.com/OpenSemanticLab/docker-compose-osl-wiki
cd docker-compose-osl-wiki
sudo chown -R www-data:www-data mediawiki/data
```


### Config

Copy .env.example to .env and CustomSettings.php.example to CustomSettings.php
```
cp .env.example .env
cp mediawiki/config/CustomSettings.example.php mediawiki/config/CustomSettings.php
```

Set the config parameters in .env

Example:
```env
COMPOSE_PROJECT_NAME=osl-1 # unique project name (change when running multiple instances)
MW_IMAGE_TAG=main # Docker image tag, see table below
MW_HOST_PORT=8081 # the port mediawiki exposes on the host (localhost only)
MW_SITE_SERVER=http://localhost:8081 # the public URL of your wiki
MW_SITE_NAME=Wiki # the name of your instance
MW_SITE_LANG=en # the site language
MW_TIME_ZONE=Europe/Berlin # your time zone
MW_ADMIN_PASS=change_me123 # the password of the 'Admin' account
MW_DB_PASS=change_me123 # the db password of the user 'mediawiki'
# the packages to install (multi-line)
MW_PAGE_PACKAGES="
world.opensemantic.core;
world.opensemantic.base;
world.opensemantic.demo.common;
"
MW_AUTOIMPORT_PAGES=true # if true, packages are installed / updated at start
MW_AUTOBUILD_SITEMAP=false # if true, the sitemap is periodically built

MYSQL_ROOT_PASSWORD=change_me123 # the password of the 'root' account
```

**Available image tags (`MW_IMAGE_TAG`):**

| Tag | MediaWiki | Description |
|-----|-----------|-------------|
| `main` | REL1_43 | Latest stable build (default) |
| `latest` | REL1_43 | Alias for main |
| `v2.x.x` (e.g. `v2.0.0-alpha.1`) | REL1_43 | Versioned releases for MW 1.43 |
| `v1.x.x` (e.g. `v1.0.0-beta.23`) | REL1_39 | Versioned releases for MW 1.39 |
| `v0.x.x` (e.g. `v0.8.0`) | REL1_35 | Legacy releases for MW 1.35 |

> [!NOTE]
> By default, **no ports are exposed** on the host. Services communicate over the internal Docker network.
> Use a reverse proxy (nginx/caddy) to expose mediawiki publicly, or use the local override for development:
> ```bash
> cp docker-compose.local.example.override.yml docker-compose.override.yml
> ```
> This exposes mediawiki (:8081), mysql (:3307), blazegraph (:9999), and drawio (:8082) on the host.

You can customize the stack further with `docker-compose.override.yml`. Example overrides:

**Expose ports for local development** — see `docker-compose.local.example.override.yml`

**Add caddy as reverse proxy** — see `docker-compose.caddy.example.override.yml`

**Mount custom volumes** (logos, extensions):
```yaml
services:
    mediawiki:
        volumes:
            - ./mediawiki/config/logo.png:/var/www/html/w/logo.png
            - ./mediawiki/config/logo.svg:/var/www/html/w/logo.svg
            - ./mediawiki/extensions/MyCustomExtension:/var/www/html/w/extensions/MyCustomExtension
```


### Run

```bash
docker compose up
```

Depending on the size of the packages defined in `MW_PAGE_PACKAGES` it will take some time to install them in the background.

You can now login (e. g. at http://localhost:8081/wiki/Main_Page) with user 'Admin' and the `MW_ADMIN_PASS` you set in the .env file.

### Settings

You can add or overwrite mediawiki settings by editing `mediawiki/config/CustomSettings.php`.

You need to re-run `docker compose up` to apply them

#### Public Instance
To make your instance public readable add:
```php
####### Make it public ########
$wgGroupPermissions['*']['read'] = true;
```

#### Permission management
Default settings allow only members of the `sysadmin` group to delete pages.
```php
# allow every user to delete pages
$wgGroupPermissions['user']['delete'] = true;
```
Please note that deleted pages are still available in the pages archive.

Further settings and custom groups can also be defined, see [Manual:User_rights](https://www.mediawiki.org/wiki/Manual:User_rights). Example:
```php
// revoke edit-right for standard users
$wgGroupPermissions['user']['edit'] = false;
$wgGroupPermissions['sysop']['edit'] = true;

$wgGroupPermissions['active-user'] = $wgGroupPermissions['user'];
// grant edit-right and delete-right for custom-group
$wgGroupPermissions['custom-group']['edit'] = true;
$wgGroupPermissions['custom-group']['delete'] = true;
```

Pages installed via packages are default only editable for sysadmin. Custom schemas in the namespace `Category` can be further restricted by
```php
// create schema-edit right
$wgAvailableRights[] = 'schema-edit';
// grant it to custom-group and sysop
$wgGroupPermissions['custom-group']['schema-edit'] = true;
$wgGroupPermissions['sysop']['schema-edit'] = true;

// restrict the creation of new categories outside installed packages
$wgNamespaceProtection[NS_CATEGORY] = ['schema-edit'];
```

#### Additional content packages
Please note: Content packages defined by MW_PAGE_PACKAGES will be install automatically.
Optional packages listed [here](https://github.com/OpenSemanticLab/PagePackages/blob/main/package_index.txt) can be installed under `<your wiki domain>/wiki/Special:Packages`. Package sources are hosted [here](https://github.com/orgs/OpenSemanticWorld-Packages/repositories).
To add additional optional packages, add
```php
$wgPageExchangePackageFiles[] = 'packages.json url';
```
e. g.
```
$wgPageExchangePackageFiles[] = 'https://raw.githubusercontent.com/OpenSemanticWorld-Packages/world.opensemantic.meta.docs/main/packages.json';
```
to `mediawiki/config/CustomSettings.php`

In order to add multiple packages that are listed in an index file, add it to the config as follows:
```php
$wgPageExchangeFileDirectories[] = 'https://raw.githubusercontent.com/<MyOrg>/PagePackages/refs/heads/main/package_index.txt';
```

For private repos generate a Github private repo access token with permission "Content" (read)
```php
$wgPageExchangeGitHubAccessToken = [
    '<MyOrg>' => 'github_pat_...', # org-level
    '<MyOrg>/'<repo>' => 'github_pat_...', # repo-level
];
```

In all cases additional packages are now __available__ for installation. Use `<your wiki domain>/wiki/Special:Packages` or the API to actually install them (more information see [Extension:Page_Exchange](https://www.mediawiki.org/wiki/Extension:Page_Exchange)).

#### Allow additional file uploads
Insecure in public instances!

Example:
```php
$additionalFileExtensions = [ 'py', 'exe' ];
$wgFileExtensions = array_merge( $wgFileExtensions, $additionalFileExtensions );
$wgProhibitedFileExtensions = array_diff( $wgProhibitedFileExtensions, $additionalFileExtensions );
$wgMimeTypeExclusions = array_diff( $wgMimeTypeExclusions, [ 'application/x-msdownload' ]); # for .exe

# allow any upload - insecure in public instances!
# $wgStrictFileExtensions = false;
# $wgCheckFileExtensions = false;
# $wgVerifyMimeType = false;
```

#### Important page content
If your instance is public, make sure to add a privacy policy to `/wiki/Site:Privacy_policy` and legal informations to `/wiki/Site:General_disclaimer`.
You may also create a single page with all necessary informations and point with a redirect from other pages to it: `#REDIRECT [[Site:General_disclaimer]]`

#### Email service
If you don't have an email server yet (optional, but necessary for notification and password resets, etc.), you can use [docker-mailserver](https://github.com/docker-mailserver/docker-mailserver)

#### Optional Extensions
The following extensions are bundled but **not enabled by default**. Enable them by adding the corresponding line to `mediawiki/config/CustomSettings.php`:

```php
# Authentication
wfLoadExtension( 'OATHAuth' );          # Two-factor authentication (see Two-Factor-Authentication section)
wfLoadExtension( 'PluggableAuth' );     # Pluggable authentication framework
wfLoadExtension( 'OpenIDConnect' );     # OpenID Connect login (e.g. via Keycloak)
wfLoadExtension( 'Realnames' );         # Display real names beside user IDs
wfLoadExtension( 'ConfirmAccount' );    # Requires approval for new account requests

# Content & Moderation
wfLoadExtension( 'ApprovedRevs' );      # Allows setting approved revisions of pages
wfLoadExtension( 'CommentStreams' );    # Discussion comments on pages
wfLoadExtension( 'Lockdown' );          # Restrict namespace access per group
wfLoadExtension( 'HitCounters' );       # Page view counters
wfLoadExtension( 'UrlGetParameters' );  # Access URL parameters in wiki pages
wfLoadExtension( 'AbuseFilter' );       # Automated abuse detection and prevention

# Anti-Spam (for public instances)
wfLoadExtension( 'ConfirmEdit' );       # CAPTCHA for edits
wfLoadExtension( 'SpamBlacklist' );     # Block known spam URLs

# Semantic Web
wfLoadExtension( 'SemanticExtraSpecialProperties' ); # Exposes extra properties (creator, approved status, etc.)

# UI & Display
wfLoadExtension( 'Iframe' );            # Embed external content via iframes (see Iframes section)
wfLoadExtension( 'PagedTiffHandler' );  # Multi-page TIFF file support
wfLoadExtension( 'InteractiveSemanticGraph2' ); # Interactive graph visualization (v2)

# Data & Export
wfLoadExtension( 'WebDAV' );            # Access uploaded files via WebDAV (e.g. directly with MS Word/Excel)
wfLoadExtension( 'RdfExport' );         # DCAT catalog and OWL ontology export (public instances only, requires SPARQL store)
wfLoadExtension( 'Chatbot' );           # AI chatbot integration
wfLoadExtension( 'ApiGateway' );        # API gateway for external service integration
```

#### Example configurations

See `mediawiki/config/CustomSettings.php` for a working example. Below are some common configurations:

**CommentStreams** — enable discussion threads on pages:
```php
wfLoadExtension( 'CommentStreams' );
$wgCommentStreamsEnableVoting = true;
$wgCommentStreamsAllowedNamespaces = [
    NS_MAIN, NS_USER, NS_FILE, NS_CATEGORY,
    7000, // Item
];
$wgCommentStreamsInitiallyCollapsedNamespaces = $wgCommentStreamsAllowedNamespaces;
```

**ApprovedRevs + SemanticExtraSpecialProperties** — track approved revisions and page metadata:
```php
wfLoadExtension( 'ApprovedRevs' );
$egApprovedRevsShowApproveLatest = true;
$egApprovedRevsShowNotApprovedMessage = true;
wfLoadExtension( 'SemanticExtraSpecialProperties' );
$sespgEnabledPropertyList = [
    '_EUSER',         // Last editor
    '_CUSER',         // Page creator
    '_APPROVED',      // Approved revision
    '_APPROVEDBY',    // Approved by user
    '_APPROVEDDATE',  // Approval date
    '_APPROVEDSTATUS', // Approval status
];
```

**WebDAV** — access uploaded files directly with MS Office / LibreOffice:
```php
wfLoadExtension( 'WebDAV' );
```

**Account management via OpenID Connect** (e.g. Keycloak, ORCID):
```php
wfLoadExtension( 'PluggableAuth' );
$wgPluggableAuth_EnableAutoLogin = false;       // auto-login on visit
$wgPluggableAuth_EnableLocalLogin = true;       // allow local password login
$wgPluggableAuth_EnableLocalProperties = true;  // allow users to edit email/realname
wfLoadExtension( 'OpenIDConnect' );
$wgGroupPermissions['*']['autocreateaccount'] = true; // required for PluggableAuth
$wgHooks['BeforePageDisplay'][] = function( OutputPage &$out, Skin &$skin ) {
    $out->addInlineStyle("#pt-createaccount { display: none;}"); // hide misleading "Create Account" link
};
wfLoadExtension( 'Realnames' ); // display real names instead of OIDC subject IDs

// Example: ORCID as identity provider
// See https://github.com/ORCID/ORCID-Source/blob/development/orcid-web/ORCID_AUTH_WITH_OPENID_CONNECT.md
$wgPluggableAuth_Config['Login with your ORCID Account'] = [
    'plugin' => 'OpenIDConnect',
    'data' => [
        'providerURL' => 'https://orcid.org',
        'clientID' => 'APP-...',
        'clientsecret' => '...',
        'scope' => ['openid'],
        'preferred_username' => 'sub'
    ]
];

// Optional: restrict login to specific users
/*
$wgHooks['PluggableAuthUserAuthorization'][] = function( MediaWiki\User\UserIdentity $user, bool &$authorized ) {
    $validUsernames = [
        '0000-0003-0410-3616' // Simon Stier
    ];
    $authorized = in_array($user->getName(), $validUsernames);
};
*/
```

To debug authentication issues, add to `CustomSettings.php`:
```php
$wgDebugLogGroups['PluggableAuth'] = '/tmp/pluggableauth.log';
$wgDebugLogGroups['OpenID Connect'] = '/tmp/oidc.log';
```
Then check with: `docker compose exec mediawiki cat /tmp/oidc.log`

**QLever SPARQL store** — use [QLever](https://github.com/ad-freiburg/qlever) instead of Blazegraph:
```php
$smwgSparqlRepositoryConnector = 'sparql11';
$smwgSparqlEndpoint["query"] = 'http://qlever:7001';
$smwgSparqlEndpoint["update"] = 'http://qlever:7001';
$smwgSparqlEndpoint["data"] = '';
```
Run with: `docker compose --profile qlever up`

#### DrawIO SVG uploads with embedded images

DrawIO diagrams may contain embedded SVG images (mostly from the built in icon library) using `data:image/svg+xml` URIs. MediaWiki blocks these by default as a security measure (embedded SVGs could contain scripts). If you need this feature, add to `mediawiki/config/CustomSettings.php`:

```php
$wgAllowSvgDataUriInSvg = true;
```

> **Warning**: This reduces upload security. Only enable this on trusted instances where all uploaders are authenticated.

### ReverseProxy
```bash
sudo cp misc/reverse_proxy_nginx.conf /etc/nginx/sites-enabled/default
sudo nano /etc/nginx/sites-enabled/default
```
-> set domain and cert paths

### Iframes

[Extension:Iframe](https://www.mediawiki.org/wiki/Extension:Iframe) enabled. To do so, add the following to your `CustomSettings.php`
```php
wfLoadExtension( 'Iframe' );
$wgIframe['width'] = "100%"; # example for a default setting
$wgIframe['server']['example'] = [ 'scheme' => 'https', 'domain' => 'example.com' ];
$wgIframe['server']['dashboard'] = [ 'scheme' => 'https', 'domain' => 'user:password@example.com' ]; # example for basic auth
$wgIframe['server']['localhost:20200'] = [ 'scheme' => 'http', 'domain' => 'localhost:20200' ]; # to allow users to test a local running webapp
```

To make use of the whitelisted domains, e.g. as `https://subdomain.example.com/example/page&hl=e`, add the following to any wiki page or template:
```mediawiki
<iframe key="example" level="subdomain" path="example/page&hl=en" />
```

### Two-Factor-Authentication
```php
# 2FA, see https://www.mediawiki.org/wiki/Extension:OATHAuth
wfLoadExtension( 'OATHAuth' );
$wgGroupPermissions['user']['oathauth-enable'] = true;
# $wgOATHRequiredForGroups = ['user']; # this will enforce 2FA but can only be applied in private wikis after every user activated it
# make sure to persist $wgSecretKey between updates, otherwise user need to re-register
$wgSecretKey = "...";
```

### SMW Store
Currently the default is blazegraph as SPARQL-Store. Since blazegraph is no longer maintained we are transitioning to use Apache Jena Fuseki.
To switch to Fuseki, add the following settings to your CustomSettings.php file:
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

### Mediawiki
Run the following commands inside the mediawiki container if you run in one of the following problems

- missing semantic properties after backup restore
```bash
php /var/www/html/w/extensions/SemanticMediaWiki/maintenance/rebuildData.php
```

- no search results after backup restore
```bash
php /var/www/html/w/extensions/CirrusSearch/maintenance/ForceSearchIndex.php
```

- incorrect link labels (page name instead of display name) after template changes or large imports
```bash
php /var/www/html/w/maintenance/refreshLinks.php
```

- missing thumbnails for tif images
```bash
php /var/www/html/w/maintenance/refreshImageMetadata.php --force
```

- Error when deleting a file
`Error deleting file: Could not create directory "metastore/local-backend/local-deleted/v1/"`

Fix the permission on the host
```bash
sudo chown -R www-data:www-data mediawiki/data
```

### MySQL
Large mysql binlog files (see https://askubuntu.com/questions/1322041/how-to-solve-increasing-size-of-mysql-binlog-files-problem)

List files
```bash
docker compose exec db /bin/bash -c 'exec echo "SHOW BINARY LOGS;" | mysql -uroot -p"$MYSQL_ROOT_PASSWORD"'
```

Delete files
```bash
docker compose exec db /bin/bash -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD"'
mysql> PURGE BINARY LOGS TO 'binlog.000123';
```

### Docker
Docker log file size is unlimited in the default settings, see
https://stackoverflow.com/questions/42510002/docker-how-to-clear-the-logs-properly-for-a-docker-container

To inspect the file size, run
```bash
du -sh --  /var/lib/docker/containers/*/*-json.log
```

To reset those file (remove all content), run
```bash
truncate -s 0 /var/lib/docker/containers/**/*-json.log
```

To change the setting, adapt `/etc/docker/daemon.json`
```json
{
  "log-driver": "json-file",
  "log-opts": {
    "max-size": "1g",
    "max-file": "1"
  }
}
```

## Backup
```bash
mkdir backup
docker compose exec db /bin/bash -c 'mysqldump --all-databases -uroot -p"$MYSQL_ROOT_PASSWORD" 2>/dev/null | gzip | base64 -w 0' | base64 -d > backup/db_backup_$(date +"%Y%m%d_%H%M%S").sql.gz
tar -zcf backup/file_backup_$(date +"%Y%m%d_%H%M%S").tar mediawiki/data
```


## Reset

To reset your instance and destroy all data run

```bash
docker compose down -v
sudo rm -R mysql/data/* && sudo rm -R blazegraph/data/* && sudo rm -R mediawiki/data/*
docker compose up
```
This is also required if you change the database passwords after the first run.

## Restore

Reset your instance first, then import your backup
```bash
zcat backup/db_backup_<date>.sql.gz | docker compose exec -T db sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD"'
tar -xf backup/file_backup_<date>.tar
chown -R www-data:www-data mediawiki/data
```

## Development

### Building the image locally

To build the MediaWiki image locally instead of pulling the pre-built one:
```bash
docker compose build
docker compose up
```

### Build Multi-Architecture Image

```bash
cd /mediawiki/build
docker buildx build --platform=linux/amd64,linux/arm64 --push -t docker.io/opensemanticlab/osl-mw:main-arm64 .
```

### Config internals

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
docker compose cp mediawiki:/var/www/html/w/LocalSettings.php mediawiki/config/LocalSettings.php
```
in docker-compose.yml:
```yaml
        volumes:
            - ./mediawiki/config/LocalSettings.php:/var/www/html/w/LocalSettings.php
```

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
docker compose cp mediawiki/config/pub/. mediawiki:/var/www/html/w/pub/
```

backup extensions
```bash
docker compose exec -T mediawiki tar -czf - -C /var/www/html/w/extensions/ . > backup/extensions_backup_$(date +"%Y%m%d_%H%M%S").tar
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
