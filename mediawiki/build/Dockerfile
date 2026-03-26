FROM debian:12.8 AS base

LABEL maintainers="simon.stier@gmx.de"

ENV MW_VERSION=REL1_43 \
    MW_HOME=/var/www/html/w \
    MW_VOLUME=/mediawiki \
    WWW_USER=www-data \
    WWW_GROUP=www-data \
    APACHE_LOG_DIR=/var/log/apache2 \
    PHP_VERSION=8.1 \
    DEBIAN_FRONTEND=noninteractive \
    # allow plugins to run as root/super user
    COMPOSER_ALLOW_SUPERUSER=1 

# System setup
RUN set x; \
	apt-get clean \
	&& apt-get update \
	&& apt-get install -y aptitude \
	&& aptitude -y upgrade \
	&& aptitude install -y \
	git \
	inotify-tools \
	apache2 \
	software-properties-common \
	gpg \
	apt-transport-https \
	ca-certificates \
	wget \
	lsb-release \
	imagemagick  \
	librsvg2-bin \
	python3-pygments \
	msmtp \
	msmtp-mta \
	patch \
	vim \
	mc \
	ffmpeg \
	curl \
	iputils-ping \
	unzip \
	gnupg \
	default-mysql-client \
	rsync \
	lynx \
	poppler-utils \
	&& wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg \
	&& echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" | tee /etc/apt/sources.list.d/php.list \
	&& aptitude update \
	&& aptitude install -y \
	php$PHP_VERSION \
	php$PHP_VERSION-cli \
	php$PHP_VERSION-gd \
	php$PHP_VERSION-mbstring \
	php$PHP_VERSION-xml \
	php$PHP_VERSION-mysql \
    php$PHP_VERSION-pgsql \
	php$PHP_VERSION-intl \
	php$PHP_VERSION-opcache \
	php$PHP_VERSION-apcu \
	php$PHP_VERSION-redis \
	php$PHP_VERSION-curl \
	php$PHP_VERSION-zip \
    php$PHP_VERSION-memcached \
	#php$PHP_VERSION-fpm \
	php$PHP_VERSION-yaml \
	php$PHP_VERSION-ldap \
	#libapache2-mod-fcgid \
    libapache2-mod-php$PHP_VERSION

# Install additional packages
RUN set -x; \
	aptitude update \
	&& aptitude install -y \
    nano \
    jq \
    php-pear \
    && update-alternatives --set php /usr/bin/php$PHP_VERSION \
    && sed -i "$ s|\-n||g" /usr/bin/pecl \
    && pear install mail net_smtp \
    && aptitude clean \
    && rm -rf /var/lib/apt/lists/* \
    && rm -rf /var/cache/apt/archives/*

# Post install configuration
# ToDo: https://github.com/CanastaWiki/CanastaBase/blob/master/_sources/configs/php-fpm-www.conf#L397
RUN set -x; \
	# Remove default config
	rm -rf /etc/apache2/sites-enabled/* \
	&& rm -rf /etc/apache2/sites-available/* \
	# && rm -rf /var/www/html \
    && rm /var/www/html/index.html \
	# Enable rewrite module
    && a2enmod rewrite \
    && a2enmod remoteip
	# enabling mpm_event and php-fpm
	#&& a2dismod mpm_prefork \
	#&& a2enconf php8.1-fpm \
	#&& a2enmod mpm_event \
	#&& a2enmod proxy_fcgi

# Composer
RUN set -x; \
	curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer self-update 2.1.3

# logs should go to stdout / stderr
RUN set -ex \
    && ln -sfT /dev/stderr "$APACHE_LOG_DIR/error.log" \
    && ln -sfT /dev/stdout "$APACHE_LOG_DIR/access.log" \
    && ln -sfT /dev/stdout "$APACHE_LOG_DIR/other_vhosts_access.log"

##### Patches for MediaWiki core
COPY patches/ /tmp/patches/

##### MediaWiki Core setup
RUN set -x; \
    mkdir -p $MW_HOME \
    && git clone --depth 1 -b $MW_VERSION https://github.com/mediawiki/mediawiki.git $MW_HOME \
    && cd $MW_HOME \
    ## Bundled extensions
    && git submodule update --init --recursive \
    && composer install --no-dev \
    && chown -R $WWW_USER:$WWW_GROUP images \
    && chown -R $WWW_USER:$WWW_GROUP cache \
    ## Patch UploadBase.php to support configurable SVG namespaces and data URIs
    ## Allows DrawIO and other tools to upload SVGs with xhtml namespace and embedded SVG images
    && cd includes/upload && patch -p1 < /tmp/patches/UploadBase-svg-upload.patch && cd $MW_HOME \
    ## for PdfHandler (bundled)
    && apt-get update && apt-get install -y --no-install-recommends poppler-utils ghostscript \
    ## for SyntaxHighlight (bundled), alternativ: apt-get install python-pygments & set $wgPygmentizePath = '/usr/bin/pygmentize';
    && chmod a+x  $MW_HOME/extensions/SyntaxHighlight_GeSHi/pygments/pygmentize \
    ## create empty config
    && touch composer.local.json && echo "{}" >> composer.local.json \
    ## allow plugin
    # && COMPOSER=composer.local.json composer config --no-plugins allow-plugins.wikimedia/composer-merge-plugin true \
    ## alternative way to allow plugin
    #&& jq '.config."allow-plugins" += {"wikimedia/composer-merge-plugin": true}' < composer.local.json  > tmp.$$.json && mv tmp.$$.json composer.local.json \
    ## handle bundled extensions that required composer install
    && jq '.extra."merge-plugin".include += ["extensions/OATHAuth/composer.json"]' < composer.local.json  > tmp.$$.json && mv tmp.$$.json composer.local.json \
    && composer update --no-dev \
    && cd ..

##### Skins ####
RUN set -x; \
    cd $MW_HOME/skins \
    && git clone --depth 1 -b osw-REL1_43 https://github.com/OpenSemanticLab/mediawiki-skins-Citizen Citizen \
    # && git clone --depth 1 -b https://github.com/StarCitizenTools/mediawiki-skins-Citizen Citizen \
    && cd $MW_HOME \
    && COMPOSER=composer.local.json composer require --no-update mediawiki/bootstrap-components:~5.0 \
    && composer update  --no-dev --prefer-source

##### Non-bundled Core Extensions ####
RUN set -x; \
    cd $MW_HOME/extensions \
    && git clone --depth 1 -b $MW_VERSION https://github.com/wikimedia/mediawiki-extensions-MyVariables.git MyVariables \
    && git clone --depth 1 -b master https://github.com/wikimedia/mediawiki-extensions-ApprovedRevs ApprovedRevs \
    && git clone --depth 1 -b $MW_VERSION https://gerrit.wikimedia.org/r/p/mediawiki/extensions/UserMerge.git \
    && git clone --depth 1 -b $MW_VERSION https://gerrit.wikimedia.org/r/p/mediawiki/extensions/BetaFeatures \
    && git clone --depth 1 -b $MW_VERSION https://gerrit.wikimedia.org/r/p/mediawiki/extensions/CookieWarning \
    && git clone --depth 1 -b $MW_VERSION https://github.com/wikimedia/mediawiki-extensions-HitCounters HitCounters \
    && cd $MW_HOME \
    #&& COMPOSER=composer.local.json composer config --no-plugins allow-plugins.composer/installers true \
    && composer update  --no-dev --prefer-source \
    && cd ..
    
##### ElasticSearch extensions
RUN set -x; \
    cd $MW_HOME/extensions \
    && git clone --depth 1 -b $MW_VERSION https://gerrit.wikimedia.org/r/p/mediawiki/extensions/CirrusSearch \
    && git clone --depth 1 -b $MW_VERSION https://gerrit.wikimedia.org/r/p/mediawiki/extensions/Elastica \
    && cd Elastica \
    && composer install --no-dev \
    && cd ..

##### Discussion extensions #####
RUN set -x; \
    cd $MW_HOME \
    && git clone --depth 1 -b $MW_VERSION https://github.com/wikimedia/mediawiki-extensions-CommentStreams extensions/CommentStreams \
    && jq '.extra."merge-plugin".include += ["extensions/CommentStreams/composer.json"]' < composer.local.json  > tmp.$$.json && mv tmp.$$.json composer.local.json \
    && composer update  --no-dev --prefer-source

FROM base as extensions

#### Data ####
RUN set -x; \
    cd $MW_HOME \
    && cd $MW_HOME/extensions \
    && git clone --depth 1 https://github.com/wikimedia/mediawiki-extensions-UrlGetParameters.git UrlGetParameters \
    && cd $MW_HOME \
    ## WSSlots
    && git clone --depth 1 https://github.com/Open-CSP/WSSlots.git extensions/WSSlots \
    && jq '.extra."merge-plugin".include += ["extensions/WSSlots/composer.json"]' < composer.local.json  > tmp.$$.json && mv tmp.$$.json composer.local.json \
    ## PageExchange
    && git clone --depth 1 -b dev https://github.com/OpenSemanticLab/mediawiki-extensions-PageExchange extensions/PageExchange \
    ## ExternalData
    && git clone --depth 1 -b $MW_VERSION https://github.com/wikimedia/mediawiki-extensions-ExternalData.git extensions/ExternalData \
    && jq '.extra."merge-plugin".include += ["extensions/ExternalData/composer.json"]' < composer.local.json  > tmp.$$.json && mv tmp.$$.json composer.local.json \
    ## Update composer
    && composer update  --no-dev --prefer-source \
    && cd ..

##### SEMANTIC MEDIAWIKI
# versions form https://github.com/CanastaWiki/Canasta/blob/master/_sources/configs/composer.canasta.json
# https://raw.githubusercontent.com/CanastaWiki/RecommendedRevisions/1df2c155b67658d999f96cc7f6e5408ab8f56243/1.43.yaml
RUN set -x; \
    cd $MW_HOME \
    && COMPOSER=composer.local.json composer require --no-update mediawiki/semantic-media-wiki:^6.0.0 \
    # SRF is installed via git clone below (OSL fork), not via composer
    && COMPOSER=composer.local.json composer require --no-update mediawiki/mermaid:~3.1 \
    && COMPOSER=composer.local.json composer require --no-update professional-wiki/modern-timeline:~1.0 \
    && COMPOSER=composer.local.json composer require --no-update mediawiki/semantic-compound-queries:^3.0.0 \
    && COMPOSER=composer.local.json composer require --no-update mediawiki/maps:~12.0 \
    && COMPOSER=composer.local.json composer require --no-update mediawiki/semantic-scribunto:^2.3.2 \
    && COMPOSER=composer.local.json composer require --no-update mediawiki/semantic-extra-special-properties:~4.0 \
    ## SRF (git clone from OSL fork, register with composer for dependency resolution)
    && cd $MW_HOME/extensions \
    && git clone --depth 1 -b dev-5.1.0 https://github.com/OpenSemanticLab/SemanticResultFormats.git SemanticResultFormats \
    && cd $MW_HOME \
    && jq '.extra."merge-plugin".include += ["extensions/SemanticResultFormats/composer.json"]' < composer.local.json > tmp.$$.json && mv tmp.$$.json composer.local.json \
    && cd $MW_HOME/extensions \
    && git clone --depth 1 -b master https://github.com/wikimedia/mediawiki-extensions-PageForms.git PageForms \
    && git clone -b dev-MW1.43 https://github.com/OpenSemanticLab/mediawiki-extensions-SemanticACL.git SemanticACL \
    && git clone --depth 1 https://gerrit.wikimedia.org/r/mediawiki/extensions/Lockdown \
    && cd $MW_HOME \
    && composer update  --no-dev --prefer-source --optimize-autoloader
    # fix: videos in gallery format not shown
    # && sed -i -e "s/find( 'a.image' )/find( 'a.image' ).add( 'video>a' )/g" $MW_HOME/extensions/SemanticResultFormats/formats/gallery/resources/ext.srf.gallery.redirect.js \
    # fix: create temp table only of not existing yet, see https://github.com/SemanticMediaWiki/SemanticMediaWiki/issues/5714
    # && sed -i -e 's/"CREATE TEMPORARY TABLE "/"CREATE TEMPORARY TABLE IF NOT EXISTS "/g' $MW_HOME/extensions/SemanticMediaWiki/src/SQLStore/TableBuilder/TemporaryTableBuilder.php

#### Scribunto / Lua ####
RUN set -x; \
    cd $MW_HOME/extensions \
    # install lua from distro to support all platforms
    && apt-get update &&  apt-get install -y --no-install-recommends lua5.1

##### Multimedia and Editor extension
RUN set -x; \
    cd $MW_HOME/extensions \
    ## File formats
    && git clone --depth 1 https://github.com/wikimedia/mediawiki-extensions-NativeSvgHandler.git NativeSvgHandler \
    && git clone --depth 1 -b $MW_VERSION https://github.com/wikimedia/mediawiki-extensions-PagedTiffHandler PagedTiffHandler \
    #  for PdfHandler (bundled)
    && apt-get update &&  apt-get install -y --no-install-recommends poppler-utils ghostscript \
    && git clone --depth 1 https://github.com/WolfgangFahl/PDFEmbed.git PDFEmbed \
    # Videos
    && git clone --depth 1 -b $MW_VERSION https://gerrit.wikimedia.org/r/mediawiki/extensions/TimedMediaHandler.git \
    && apt-get update && apt-get install -y --no-install-recommends ffmpeg \
    && cd $MW_HOME && jq '.extra."merge-plugin".include += ["extensions/TimedMediaHandler/composer.json"]' < composer.local.json  > tmp.$$.json && mv tmp.$$.json composer.local.json && cd $MW_HOME/extensions \
    && git clone --depth 1 -b $MW_VERSION https://github.com/StarCitizenWiki/mediawiki-extensions-EmbedVideo EmbedVideo \
    # 3D Files commit hash MW1.39 compatible
    && git clone --depth 1 https://github.com/dolfinus/3DAlloy.git 3DAlloy \
    && git clone --depth 1 -b $MW_VERSION https://github.com/wikimedia/mediawiki-extensions-VEForAll.git VEForAll \
    && git clone --depth 1 -b $MW_VERSION https://github.com/wikimedia/mediawiki-extensions-CodeMirror.git CodeMirror \
    && git clone --depth 1 -b dev https://github.com/OpenSemanticLab/WikiMarkdown.git WikiMarkdown \
    && cd $MW_HOME && jq '.extra."merge-plugin".include += ["extensions/WikiMarkdown/composer.json"]' < composer.local.json  > tmp.$$.json && mv tmp.$$.json composer.local.json && cd $MW_HOME/extensions \
    ## Other Editors
    #&& git clone --depth 1 -b $MW_VERSION https://github.com/wikimedia/mediawiki-extensions-DrawioEditor.git DrawioEditor \
    && git clone --depth 1 -b dev https://github.com/OpenSemanticLab/mediawiki-extensions-DrawioEditor DrawioEditor \
    #  apply patch for private wikis, see: https://phabricator.wikimedia.org/T271347
    # && sed -i -e "s/static \$validNamespaces = \[/static \$validNamespaces = \['http:\/\/www.w3.org\/1999\/xhtml',/g" $MW_HOME/includes/upload/UploadBase.php \
    # && sed -i -e "s/datastr = datastr.replace( expr, '\"http:\/\/www.w3.org\/2000\/svg\"' );/\/\/datastr = datastr.replace( expr, '\"http:\/\/www.w3.org\/2000\/svg\"' ); /g" $MW_HOME/extensions/DrawioEditor/resources/ext.drawioeditor.js \
    # WebDAV
    && git clone --depth 1 https://github.com/OpenSemanticWorld/mediawiki-extensions-WebDAV.git WebDAV \
    && cd $MW_HOME \
    && ln -s extensions/WebDAV/entrypoints/webdav.php webdav.php \
    && jq '.extra."merge-plugin".include += ["extensions/WebDAV/composer.json"]' < composer.local.json  > tmp.$$.json && mv tmp.$$.json composer.local.json \
    && composer update  --no-dev --prefer-source

##### UI extensions
RUN set -x; \
    cd $MW_HOME \
    #&& COMPOSER=composer.local.json composer require --no-update "mediawiki/simple-batch-upload:1.8.2" \
    && git clone -b mobile-camera-upload https://github.com/OpenSemanticLab/mediawiki-extensions-SimpleBatchUpload.git extensions/SimpleBatchUpload \
    #&& jq '.extra."merge-plugin".include += ["extensions/SimpleBatchUpload/composer.json"]' < composer.local.json  > tmp.$$.json && mv tmp.$$.json composer.local.json \
    #&& composer update  --no-dev --prefer-source \
    && cd $MW_HOME/extensions \
    && git clone --depth 1 https://github.com/debtcompliance/TreeAndMenu TreeAndMenu \
    #&& git clone --depth 1 -b $MW_VERSION https://gerrit.wikimedia.org/r/mediawiki/extensions/DisplayTitle DisplayTitle \
    && git clone --depth 1 -b REL1_43-dev https://github.com/OpenSemanticLab/mediawiki-extensions-DisplayTitle.git DisplayTitle \
    ## Iframes
    && git clone --depth 1 https://github.com/sigbertklinke/Iframe Iframe \
    ## Reveal Slides
    && git clone --depth 1 https://github.com/OpenSemanticLab/mediawiki-extensions-Reveal.git Reveal \
    && cd ..

##### Auth extensions ####
RUN set -x; \
    cd $MW_HOME/extensions \
    #OAUTH
    && cd $MW_HOME/extensions \
    ## Manual Account request and confirmation
    && git clone --depth 1 -b $MW_VERSION https://gerrit.wikimedia.org/r/mediawiki/extensions/ConfirmAccount.git \
    && cd $MW_HOME \
    ## Wiki as auth provider for other services (e.g. jupyterhub)
    && git clone --depth 1 -b $MW_VERSION https://github.com/wikimedia/mediawiki-extensions-OAuth.git extensions/OAuth \
    && jq '.extra."merge-plugin".include += ["extensions/OAuth/composer.json"]' < composer.local.json  > tmp.$$.json && mv tmp.$$.json composer.local.json \
    ## Account management e. g. via Keycloak
    && git clone --depth 1 -b 7.5.0 https://github.com/wikimedia/mediawiki-extensions-PluggableAuth.git extensions/PluggableAuth \
    && git clone --depth 1 -b 8.3.0 https://github.com/wikimedia/mediawiki-extensions-OpenIDConnect.git extensions/OpenIDConnect \
    ##patched version allowing custom name attributes
    #&& git clone --depth 1 -b dev https://github.com/OpenSemanticLab/mediawiki-extensions-OpenIDConnect.git extensions/OpenIDConnect \
    && jq '.extra."merge-plugin".include += ["extensions/OpenIDConnect/composer.json"]' < composer.local.json  > tmp.$$.json && mv tmp.$$.json composer.local.json \
    #display realname beside userids
    && git clone --depth 1 https://github.com/ofbeaton/mediawiki-realnames.git extensions/Realnames \
    && composer update --no-dev \
    && cd ..

FROM extensions as custom

#### TEMP ####
RUN set -x; \
    cd $MW_HOME/extensions \
    && git clone https://github.com/simontaurus/WellplateEditor.git \
    && git clone -b no-storage-prompt https://github.com/OpenSemanticLab/mediawiki-extensions-SvgEditor.git SvgEditor \
    && git clone https://github.com/OpenSemanticLab/mediawiki-extensions-MwJson.git MwJson \
    && git clone https://github.com/OpenSemanticLab/mediawiki-extensions-SpreadsheetEditor.git SpreadsheetEditor \
    && git clone https://github.com/OpenSemanticLab/mediawiki-extensions-ChemEditor.git ChemEditor \
    && git clone -b refactoring https://github.com/OpenSemanticLab/mediawiki-extensions-InteractiveSemanticGraph InteractiveSemanticGraph \
    && git clone https://github.com/OpenSemanticLab/mediawiki-extensions-InteractiveSemanticGraph2 InteractiveSemanticGraph2 \
    && git clone https://github.com/OpenSemanticLab/mediawiki-extensions-SciFileHandler SciFileHandler \    
    && git clone https://github.com/OpenSemanticLab/mediawiki-extensions-OpenSemanticLab.git OpenSemanticLab \
    && git clone --depth 1 https://github.com/OpenSemanticLab/mediawiki-extensions-SemanticProperties.git SemanticProperties \
    && git clone --depth 1 https://github.com/OpenSemanticWorld/mediawiki-extensions-Chatbot.git Chatbot \
    && git clone --depth 1 https://github.com/OpenSemanticLab/mediawiki-extensions-FileApi.git FileApi \
    && git clone --depth 1 https://github.com/OpenSemanticLab/mediawiki-extensions-ApiGateway.git ApiGateway \
    && cd $MW_HOME \
    && git clone --depth 1 https://github.com/opensemanticworld/mediawiki-extensions-RdfExport.git extensions/RdfExport \
    && jq '.extra."merge-plugin".include += ["extensions/RdfExport/composer.json"]' < composer.local.json  > tmp.$$.json && mv tmp.$$.json composer.local.json \
    && composer update  --no-dev --prefer-source \
    && cd ..

#### Run maintenance sripts
# Increase value for run maintenance script before web service started
ENV MW_AUTOUPDATE=true \
    MW_MAINTENANCE_UPDATE=8 \
    MW_MAINTENANCE_CIRRUSSEARCH_UPDATECONFIG=1 \
    MW_MAINTENANCE_CIRRUSSEARCH_FORCEINDEX=1 \
    MW_MAINTENANCE_ULS_INDEXER=1 \
    #MW_SCRIPT_CLDR_REBUILD=1 \
    MW_SITE_NAME=My\ MediaWiki\ Site \
    MW_SITE_LANG=en \
    MW_DEFAULT_SKIN=vector \
    MW_MAIN_CACHE_TYPE=CACHE_ACCEL \
    MW_REST_DOMAIN=localhost \
    MW_REST_RESTBASE_PROXY_PATH=/api/rest_ \
    MW_REST_RESTBASE_PORT=7231 \
    MW_SHOW_EXCEPTION_DETAILS=true \
    PHP_LOG_ERRORS=On \
    PHP_ERROR_REPORTING=E_ALL
    
EXPOSE 80

COPY php.ini /etc/php/$PHP_VERSION/apache2/conf.d/mediawiki.ini

COPY mediawiki.conf /etc/apache2/sites-available/mediawiki.conf
RUN set -x; ln -s /etc/apache2/sites-available/mediawiki.conf /etc/apache2/sites-enabled/mediawiki.conf

COPY run.sh /run.sh
RUN chmod -v +x /run.sh

COPY mwjobrunner.sh /mwjobrunner.sh
RUN chmod -v +x /mwjobrunner.sh
COPY mwmaintenance.sh /mwmaintenance.sh
RUN chmod -v +x /mwmaintenance.sh
COPY mwsitemapbuilder.sh /mwsitemapbuilder.sh
RUN chmod -v +x /mwsitemapbuilder.sh

COPY DockerSettings.php $MW_HOME/DockerSettings.php

COPY favicon.ico $MW_HOME/favicon.ico
COPY logo.* $MW_HOME/

#dir for public files, e. g. main page images
COPY pub $MW_HOME/pub

#dir for certs
COPY ca-certificates /usr/local/share/ca-certificates
RUN update-ca-certificates

ENV MW_HOME=/var/www/html/w \
    MW_VOLUME=/mediawiki

CMD ["/run.sh"]

VOLUME ["$MW_HOME/images", "$MW_VOLUME"]