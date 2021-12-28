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
        python-pygments \
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

##### MediaWiki Core setup
RUN set -x; \
    mkdir -p $MW_HOME \
    && git clone \
        --depth 1 \
        -b $MW_VERSION \
        https://gerrit.wikimedia.org/r/p/mediawiki/core.git \
        $MW_HOME \
    && cd $MW_HOME \
    && git submodule update --init --recursive \
    && composer install --no-dev \
    && chown -R $WWW_USER:$WWW_GROUP images \
    && chown -R $WWW_USER:$WWW_GROUP cache 
    #\
    #&& cd $MW_HOME/skins \
    #&& git clone --depth 1 -b $MW_VERSION https://gerrit.wikimedia.org/r/mediawiki/skins/Vector
    
##### ElasticSearch extensions
RUN set -x; \
    cd $MW_HOME/extensions \
    && git clone --depth 1 -b $MW_VERSION https://gerrit.wikimedia.org/r/p/mediawiki/extensions/CirrusSearch \
    && git clone --depth 1 -b $MW_VERSION https://gerrit.wikimedia.org/r/p/mediawiki/extensions/Elastica \
    && cd Elastica \
    && composer install --no-dev \
    && cd ..

##### SEMANTIC MEDIAWIKI
RUN set -x; \
    cd $MW_HOME \
    && COMPOSER=composer.local.json composer require --no-update mediawiki/semantic-media-wiki:3.2.3 \
    ## Apply 
    && cd $MW_HOME \
    && composer update  --no-dev --prefer-source

#### Dev Tools
ARG SSH_PASS
RUN set -x; \
    apt-get -y update \
    && apt-get -y install openssh-server
RUN useradd -m -s /bin/bash -g $WWW_GROUP -p "$(openssl passwd -1 $SSH_PASS)" sshuser
EXPOSE 22

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

#COPY favicon.ico $MW_HOME/favicon.ico
#COPY logo.png $MW_HOME/logo.png
COPY CustomSettings.php $MW_HOME/CustomSettings.php

#dir for public files, e. g. main page images
COPY pub $MW_HOME/pub

CMD ["/run.sh"]

VOLUME ["$MW_HOME/images", "$MW_VOLUME"]
