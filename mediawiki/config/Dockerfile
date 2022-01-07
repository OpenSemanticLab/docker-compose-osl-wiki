FROM ubuntu:20.04

MAINTAINER simon.stier@gmx.de

ENV MW_VERSION=REL1_35 \
    MW_HOME=/var/www/html/w \
    MW_VOLUME=/mediawiki \
    WWW_USER=www-data \
    WWW_GROUP=www-data \
    APACHE_LOG_DIR=/var/log/apache2 \
    PHP_VERSION=7.4

# Install requered packages
RUN set -x; \
    apt-get update \
    && export LANG=C.UTF-8 \
    && apt-get install -y software-properties-common \
    && add-apt-repository -y ppa:ondrej/php \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        apache2 \
        libapache2-mod-php$PHP_VERSION \
        php$PHP_VERSION \
        php$PHP_VERSION-mysql \
        php$PHP_VERSION-cli \
        php$PHP_VERSION-gd \
        php$PHP_VERSION-curl \
        php$PHP_VERSION-mbstring \
        php$PHP_VERSION-xml \
        php-xml \
        php$PHP_VERSION-apcu \
        php$PHP_VERSION-intl \
        php$PHP_VERSION-zip \
        php$PHP_VERSION-memcached \
        php-pear \
        imagemagick \
        netcat \
        git \
        #composer \
        unzip \
        mysql-client \
        wget \
        nano \
        curl \
        jq \
    && update-alternatives --set php /usr/bin/php$PHP_VERSION \
    && sed -i "$ s|\-n||g" /usr/bin/pecl \
    && pear install mail net_smtp \
    && rm -rf /var/lib/apt/lists/* \
    && rm -rf /var/cache/apt/archives/* \
    && a2enmod rewrite \
    && a2enmod remoteip \
    && rm /var/www/html/index.html \
    && rm -rf /etc/apache2/sites-enabled/* \
    && wget -qO composer-setup.php https://getcomposer.org/installer \
    && php composer-setup.php --1 --install-dir=/usr/local/bin --filename=composer

# logs should go to stdout / stderr
RUN set -ex \
    && ln -sfT /dev/stderr "$APACHE_LOG_DIR/error.log" \
    && ln -sfT /dev/stdout "$APACHE_LOG_DIR/access.log" \
    && ln -sfT /dev/stdout "$APACHE_LOG_DIR/other_vhosts_access.log"

#### Dev Tools
ARG SSH_PASS
RUN set -x; \
    apt-get -y update \
    && apt-get -y install openssh-server
RUN useradd -m -s /bin/bash -g $WWW_GROUP -p "$(openssl passwd -1 $SSH_PASS)" sshuser
EXPOSE 22

##### MediaWiki Core setup
RUN set -x; \
    mkdir -p $MW_HOME \
    && git clone \
        --depth 1 \
        -b $MW_VERSION \
        https://gerrit.wikimedia.org/r/p/mediawiki/core.git \
        $MW_HOME \
    && cd $MW_HOME \
    ## Bundled extensions
    && git submodule update --init --recursive \
    && composer install --no-dev \
    && chown -R $WWW_USER:$WWW_GROUP images \
    && chown -R $WWW_USER:$WWW_GROUP cache \
    ## for PdfHandler (bundled)
    && apt-get update && apt-get install -y --no-install-recommends poppler-utils ghostscript \
    ## for SyntaxHighlight (bundled), alternativ: apt-get install python-pygments & set $wgPygmentizePath = '/usr/bin/pygmentize';
    && chmod a+x  $MW_HOME/extensions/SyntaxHighlight_GeSHi/pygments/pygmentize \
    #&& cd $MW_HOME/skins \
    #&& git clone --depth 1 -b $MW_VERSION https://gerrit.wikimedia.org/r/mediawiki/skins/Vector \
    && cd ..

##### Non-bundled Core Extensions ####
RUN set -x; \
    cd $MW_HOME/extensions \
    && git clone --depth 1 -b $MW_VERSION https://github.com/wikimedia/mediawiki-extensions-Variables.git Variables \
    && git clone --depth 1 -b $MW_VERSION https://github.com/wikimedia/mediawiki-extensions-Arrays.git Arrays \
    && git clone --depth 1  https://github.com/WikibaseSolutions/WSArrays.git WSArrays \
    && git clone --depth 1  https://github.com/wikimedia/mediawiki-extensions-Loops.git Loops \
    && git clone --depth 1  https://github.com/simontaurus/mediawiki-extensions-ApprovedRevs.git ApprovedRevs \
    && git clone --depth 1 -b $MW_VERSION https://gerrit.wikimedia.org/r/p/mediawiki/extensions/Echo \
    && git clone --depth 1 -b $MW_VERSION https://gerrit.wikimedia.org/r/p/mediawiki/extensions/Thanks \
    && git clone --depth 1 -b $MW_VERSION https://gerrit.wikimedia.org/r/p/mediawiki/extensions/BetaFeatures \
    && git clone --depth 1 -b $MW_VERSION https://gerrit.wikimedia.org/r/p/mediawiki/extensions/CookieWarning \
    && git clone --depth 1 -b $MW_VERSION https://gerrit.wikimedia.org/r/p/mediawiki/extensions/CleanChanges \
    && git clone --depth 1 -b $MW_VERSION https://gerrit.wikimedia.org/r/p/mediawiki/extensions/UniversalLanguageSelector \
    && cd ..
    
##### ElasticSearch extensions
RUN set -x; \
    cd $MW_HOME/extensions \
    && git clone --depth 1 -b $MW_VERSION https://gerrit.wikimedia.org/r/p/mediawiki/extensions/CirrusSearch \
    && git clone --depth 1 -b $MW_VERSION https://gerrit.wikimedia.org/r/p/mediawiki/extensions/Elastica \
    && cd Elastica \
    && composer install --no-dev \
    && cd ..

##### Flow extension #####
RUN set -x; \
    cd $MW_HOME/extensions \
    && git clone --depth 1 -b $MW_VERSION https://gerrit.wikimedia.org/r/p/mediawiki/extensions/Flow \
    && cd Flow \
    && composer install --no-dev \
    && cd ..

#### Data ####
RUN set -x; \
    cd $MW_HOME/extensions \
    && git clone --depth 1 -b dev-REL1_35 https://github.com/simontaurus/mediawiki-extensions-ExternalData.git ExternalData \
    #https://www.mediawiki.org/wiki/Extension:External_Data/Local_programs
    && apt-get -y install graphviz

##### SEMANTIC MEDIAWIKI
RUN set -x; \
    cd $MW_HOME \
    && COMPOSER=composer.local.json composer require --no-update mediawiki/semantic-media-wiki:3.2.3 \
    #&& COMPOSER=composer.local.json composer require --no-update mediawiki/semantic-result-formats:3.2 \
    #SRF
    && cd $MW_HOME/extensions \
    #&& git clone --depth 1 -b use-vizjs-renderer https://github.com/simontaurus/SemanticResultFormats.git SemanticResultFormats \
    && git clone --depth 1 -b REL1.35_use-vizjs-renderer https://github.com/simontaurus/SemanticResultFormats.git \
    && git clone --depth 1 https://github.com/SemanticMediaWiki/Mermaid.git Mermaid \
    && cd $MW_HOME \
    && jq '.extra."merge-plugin".include += ["extensions/Mermaid/composer.json"]' < composer.local.json  > tmp.$$.json && mv tmp.$$.json composer.local.json \
    && jq '.extra."merge-plugin".include += ["extensions/SemanticResultFormats/composer.json"]' < composer.local.json  > tmp.$$.json && mv tmp.$$.json composer.local.json \
    ## Apply 
    && cd $MW_HOME \
    && composer update  --no-dev --prefer-source \
    #https://www.semantic-mediawiki.org/wiki/Help:Setup_information_file
    && touch extensions/SemanticMediaWiki/.smw.json \
    && chown $WWW_USER:$WWW_GROUP extensions/SemanticMediaWiki/.smw.json

#### Scribunto / Lua ####
RUN set -x; \
    cd $MW_HOME/extensions \
    && git clone --depth 1 https://github.com/Liquipedia/VariablesLua.git \
    && git clone --depth 1 https://github.com/wikimedia/mediawiki-extensions-Capiunto.git Capiunto \
    && cd $MW_HOME \
    && COMPOSER=composer.local.json composer require --no-update mediawiki/semantic-scribunto:~2.1 \
    && composer update  --no-dev --prefer-source \
    && cd ..

##### Multimedia and Editor extension
RUN set -x; \
    cd $MW_HOME/extensions \
    ## File formats
    && git clone --depth 1 https://github.com/wikimedia/mediawiki-extensions-NativeSvgHandler.git NativeSvgHandler \
    #for PdfHandler (bundled)
    && apt-get update &&  apt-get install -y --no-install-recommends poppler-utils ghostscript \
    ## Visual Editor
    && git clone --depth 1 -b $MW_VERSION https://gerrit.wikimedia.org/r/mediawiki/extensions/Math.git \
    && apt-get update -y && apt-get install --no-install-recommends -y -o Dpkg::Options::="--force-confold" -y nodejs npm librsvg2-dev build-essential \
    && mkdir -p /usr/local/nodejs/mathoid/ && cd /usr/local/nodejs/mathoid/ \
    && npm install mathoid \
    ## https://github.com/wikimedia/mathoid/commit/8aa71deeb28d4b039d421bee011342aaf5cc124a
    && npm install commander@6.2.1 \
    && cd $MW_HOME/extensions \
    && git clone --depth 1 -b $MW_VERSION https://github.com/wikimedia/mediawiki-extensions-VEForAll.git VEForAll \
    && git clone --depth 1 -b $MW_VERSION https://github.com/wikimedia/mediawiki-extensions-CodeMirror.git CodeMirror \
    ## Other Editors
    && git clone --depth 1 -b $MW_VERSION https://github.com/wikimedia/mediawiki-extensions-DrawioEditor.git DrawioEditor \
    && cd .. 

##### UI extensions
RUN set -x; \
    cd $MW_HOME \
    && COMPOSER=composer.local.json composer require --no-update mediawiki/sub-page-list "~1.5" \
    #&& COMPOSER=composer.local.json composer require --no-update hallowelt/cognitiveprocessdesigner "dev-REL1_31" \
    && git clone --depth 1 -b $MW_VERSION https://github.com/wikimedia/mediawiki-extensions-CognitiveProcessDesigner.git extensions/CognitiveProcessDesigner \
    && jq '.extra."merge-plugin".include += ["extensions/CognitiveProcessDesigner/composer.json"]' < composer.local.json  > tmp.$$.json && mv tmp.$$.json composer.local.json \
    && composer update  --no-dev --prefer-source \
    && cd $MW_HOME/extensions \
    && git clone --depth 1 -b $MW_VERSION https://github.com/wikimedia/mediawiki-extensions-JSBreadCrumbs.git JSBreadCrumbs \
    && git clone --depth 1 -b $MW_VERSION https://github.com/wikimedia/mediawiki-extensions-DisplayTitle.git DisplayTitle \
    && git clone --depth 1 -b dev-$MW_VERSION  https://github.com/simontaurus/mediawiki-extensions-PageForms.git PageForms \
    #&& git clone --depth 1 -b $MW_VERSION https://github.com/wikimedia/mediawiki-extensions-PageForms.git PageForms \
    && cd ..

#### TEMP ####
RUN set -x; \
    cd $MW_HOME/extensions \
    && cd $MW_HOME \
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

COPY DockerSettings.php $MW_HOME/DockerSettings.php

COPY favicon.ico $MW_HOME/favicon.ico
COPY logo.png $MW_HOME/logo.png
COPY CustomSettings.php $MW_HOME/CustomSettings.php

#dir for public files, e. g. main page images
COPY pub $MW_HOME/pub

CMD ["/run.sh"]

VOLUME ["$MW_HOME/images", "$MW_VOLUME"]
