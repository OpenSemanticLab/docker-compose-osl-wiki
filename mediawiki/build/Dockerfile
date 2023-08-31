FROM ubuntu:22.04 AS base

LABEL maintainers="simon.stier@gmx.de"

ENV MW_VERSION=REL1_39 \
    MW_HOME=/var/www/html/w \
    MW_VOLUME=/mediawiki \
    WWW_USER=www-data \
    WWW_GROUP=www-data \
    APACHE_LOG_DIR=/var/log/apache2 \
    PHP_VERSION=8.1 \
    DEBIAN_FRONTEND=noninteractive

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
        php$PHP_VERSION-pgsql \
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
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer

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
        -b $MW_VERSION-dev \
        https://github.com/OpenSemanticLab/mediawiki.git \
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
    ## create empty config
    && touch composer.local.json && echo "{}" >> composer.local.json \
    ## allow plugin
    && COMPOSER=composer.local.json composer config --no-plugins allow-plugins.wikimedia/composer-merge-plugin true \
    ## alternative way to allow plugin
    #&& jq '.config."allow-plugins" += {"wikimedia/composer-merge-plugin": true}' < composer.local.json  > tmp.$$.json && mv tmp.$$.json composer.local.json \
    && cd ..

##### Skins ####
RUN set -x; \
    cd $MW_HOME/skins \
    ## bundled
    #&& git clone --depth 1 -b $MW_VERSION https://gerrit.wikimedia.org/r/mediawiki/skins/Vector \
    #&& git clone --depth 1 -b $MW_VERSION https://gerrit.wikimedia.org/r/mediawiki/skins/MonoBook \
    #&& git clone --depth 1 -b $MW_VERSION https://gerrit.wikimedia.org/r/mediawiki/skins/Timeless \
    #&& git clone --depth 1 -b $MW_VERSION https://gerrit.wikimedia.org/r/mediawiki/skins/MinervaNeue \
    ## non-bundled
    && git clone --depth 1 -b $MW_VERSION https://gerrit.wikimedia.org/r/mediawiki/skins/Modern \
    && git clone --depth 1 https://github.com/jthingelstad/foreground \
    && git clone --depth 1 -b osw https://github.com/OpenSemanticLab/mediawiki-skins-Citizen Citizen \
    && cd $MW_HOME/extensions \
    && git clone --depth 1 -b $MW_VERSION https://gerrit.wikimedia.org/r/p/mediawiki/extensions/MobileFrontend \
    && cd $MW_HOME \
    && COMPOSER=composer.local.json composer require --no-update mediawiki/bootstrap-components:~5.0 \
    && COMPOSER=composer.local.json composer require --no-update mediawiki/chameleon-skin:~4.0 \
    && composer update  --no-dev --prefer-source

##### Non-bundled Core Extensions ####
RUN set -x; \
    cd $MW_HOME/extensions \
    && git clone --depth 1 -b $MW_VERSION https://github.com/wikimedia/mediawiki-extensions-Variables.git Variables \
    && git clone --depth 1 -b $MW_VERSION https://github.com/wikimedia/mediawiki-extensions-MyVariables.git MyVariables \
    && git clone --depth 1 -b $MW_VERSION https://github.com/wikimedia/mediawiki-extensions-Arrays.git Arrays \
    && git clone --depth 1  https://github.com/WikibaseSolutions/WSArrays.git WSArrays \
    && git clone --depth 1 -b $MW_VERSION https://github.com/wikimedia/mediawiki-extensions-Loops.git Loops \
    && git clone --depth 1 -b master https://github.com/wikimedia/mediawiki-extensions-ApprovedRevs ApprovedRevs \
    && git clone --depth 1 -b $MW_VERSION https://gerrit.wikimedia.org/r/p/mediawiki/extensions/UserMerge.git \
    && git clone --depth 1 -b $MW_VERSION https://gerrit.wikimedia.org/r/p/mediawiki/extensions/Echo \
    && git clone --depth 1 -b $MW_VERSION https://gerrit.wikimedia.org/r/p/mediawiki/extensions/Thanks \
    && git clone --depth 1 -b $MW_VERSION https://gerrit.wikimedia.org/r/p/mediawiki/extensions/BetaFeatures \
    && git clone --depth 1 -b $MW_VERSION https://gerrit.wikimedia.org/r/p/mediawiki/extensions/CookieWarning \
    && git clone --depth 1 -b $MW_VERSION https://gerrit.wikimedia.org/r/p/mediawiki/extensions/CleanChanges \
    && git clone --depth 1 -b $MW_VERSION https://gerrit.wikimedia.org/r/p/mediawiki/extensions/UniversalLanguageSelector \
    && git clone --depth 1 -b $MW_VERSION https://github.com/wikimedia/mediawiki-extensions-HitCounters HitCounters \
    && cd $MW_HOME \
    && COMPOSER=composer.local.json composer config --no-plugins allow-plugins.composer/installers true \
    && COMPOSER=composer.local.json composer require --no-update mediawiki/hit-counters \
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

##### Flow extension #####
RUN set -x; \
    cd $MW_HOME/extensions \
    && git clone --depth 1 -b $MW_VERSION https://gerrit.wikimedia.org/r/p/mediawiki/extensions/Flow \
    && cd Flow \
    #patch for REL_1.35 with parsoid zeroconfig https://phabricator.wikimedia.org/T260648#6645078
    #see also: https://www.mediawiki.org/wiki/Topic:X8mv19b4va26u8tz
    && git fetch https://gerrit.wikimedia.org/r/mediawiki/extensions/Flow refs/changes/58/622258/14 && git format-patch -1 --stdout FETCH_HEAD \
    && composer install --no-dev \
    && cd ..

FROM base as extensions

#### Data ####
RUN set -x; \
    cd $MW_HOME \
    #ExternalData (with optional mongodb support)
    && apt-get -y update && apt-get -y install php$PHP_VERSION-mongodb && phpenmod mongodb \
    ## allow plugin
    && COMPOSER=composer.local.json composer config --no-plugins allow-plugins.composer/installers true \
    && COMPOSER=composer.local.json composer require --no-update mongodb/mongodb "^1.6.0" \
    && git clone --depth 1 -b dev-REL1_35 https://github.com/simontaurus/mediawiki-extensions-ExternalData.git extensions/ExternalData \
    && jq '.extra."merge-plugin".include += ["extensions/ExternalData/composer.json"]' < composer.local.json  > tmp.$$.json && mv tmp.$$.json composer.local.json \
    #https://www.mediawiki.org/wiki/Extension:External_Data/Local_programs
    && apt-get -y install graphviz \
    && cd $MW_HOME/extensions \
    && git clone --depth 1 https://github.com/wikimedia/mediawiki-extensions-UrlGetParameters.git UrlGetParameters \
    && git clone --depth 1 -b $MW_VERSION https://github.com/wikimedia/mediawiki-extensions-Push.git Push \
    && git clone --depth 1 -b 2.0.1 https://github.com/BorderCloud/PushAll.git PushAll \
    && cd $MW_HOME \
    ## Extension:Sync
    # && COMPOSER=composer.local.json composer require --no-update "nischayn22/mediawiki-api:dev-master" \
    && git clone --depth 1 https://github.com/nischayn22/Sync.git extensions/Sync \
    && jq '.extra."merge-plugin".include += ["extensions/Sync/composer.json"]' < composer.local.json  > tmp.$$.json && mv tmp.$$.json composer.local.json \
    ## LinkedWiki
    && git clone --depth 1 https://github.com/wikimedia/mediawiki-extensions-LinkedWiki.git extensions/LinkedWiki \
    && apt-get update -y && apt-get install --no-install-recommends -y -o Dpkg::Options::="--force-confold" -y nodejs npm \
    && cd extensions/LinkedWiki && npm install --production && cd $MW_HOME \
    && jq '.extra."merge-plugin".include += ["extensions/LinkedWiki/composer.json"]' < composer.local.json  > tmp.$$.json && mv tmp.$$.json composer.local.json \
    ## WSSlots
    && git clone --depth 1 https://github.com/OpenSemanticLab/WSSlots.git extensions/WSSlots \
    && jq '.extra."merge-plugin".include += ["extensions/WSSlots/composer.json"]' < composer.local.json  > tmp.$$.json && mv tmp.$$.json composer.local.json \
    ## PageExchange
    && git clone --depth 1 -b dev https://github.com/OpenSemanticLab/mediawiki-extensions-PageExchange extensions/PageExchange \
    ## Update composer
    && composer update  --no-dev --prefer-source \
    && cd ..

##### SEMANTIC MEDIAWIKI
# versions form https://github.com/CanastaWiki/Canasta/blob/master/_sources/configs/composer.canasta.json
RUN set -x; \
    cd $MW_HOME \
    && COMPOSER=composer.local.json composer require --no-update mediawiki/semantic-media-wiki:dev-master \
    && COMPOSER=composer.local.json composer require --no-update mediawiki/semantic-result-formats:4.0.2 \
    && COMPOSER=composer.local.json composer require --no-update mediawiki/mermaid:~3.1 \
    && COMPOSER=composer.local.json composer require --no-update professional-wiki/modern-timeline:~1.0 \
    && COMPOSER=composer.local.json composer require --no-update mediawiki/semantic-extra-special-properties:3.0.1 \
    && COMPOSER=composer.local.json composer require --no-update mediawiki/semantic-compound-queries:~2.2 \
    # && COMPOSER=composer.local.json composer require --no-update mediawiki/semantic-interlanguage-links:2.1 \ #not compatible with SMW > 4.0
    # && COMPOSER=composer.local.json composer require --no-update mediawiki/semantic-breadcrumb-links:2.1.x-dev \ #installation problem with composer
    # && COMPOSER=composer.local.json composer require --no-update mediawiki/maps:10.0.0 \
    && COMPOSER=composer.local.json composer require --no-update mediawiki/semantic-forms-select:4.0.x-dev \
    && COMPOSER=composer.local.json composer require --no-update mediawiki/semantic-scribunto:~2.2 \
    ## SRF
    && cd $MW_HOME/extensions \
    #&& git clone --depth 1 -b use-vizjs-renderer https://github.com/simontaurus/SemanticResultFormats.git SemanticResultFormats \
    #&& git clone --depth 1 -b REL1.35_use-vizjs-renderer https://github.com/simontaurus/SemanticResultFormats.git \
    #&& git clone --depth 1 https://github.com/SemanticMediaWiki/Mermaid.git Mermaid \
    #&& git clone --depth 1 https://github.com/SemanticMediaWiki/SemanticExtraSpecialProperties.git \
    #&& git clone --depth 1 -b dev-$MW_VERSION https://github.com/simontaurus/SemanticFormsSelect.git \
    #&& git clone --depth 1 -b semantic_reference_template https://github.com/simontaurus/SemanticCite.git \ #needs update
    && git clone -b dev https://github.com/OpenSemanticLab/mediawiki-extensions-SemanticACL.git SemanticACL \
    #&& git clone --depth 1 -b master https://github.com/wikimedia/mediawiki-extensions-SemanticACL.git SemanticACL \
    && git clone -b dev https://github.com/OpenSemanticLab/Maps \
    ## SemanticActions
    && git clone --depth 1 -b dev_en https://github.com/OpenSemanticLab/SemanticActions.git \
    && git clone --depth 1 https://github.com/enterprisemediawiki/PageImporter.git \
    && cd $MW_HOME \
    #&& jq '.extra."merge-plugin".include += ["extensions/Mermaid/composer.json"]' < composer.local.json  > tmp.$$.json && mv tmp.$$.json composer.local.json \
    #&& jq '.extra."merge-plugin".include += ["extensions/SemanticResultFormats/composer.json"]' < composer.local.json  > tmp.$$.json && mv tmp.$$.json composer.local.json \
    #&& jq '.extra."merge-plugin".include += ["extensions/SemanticCite/composer.json"]' < composer.local.json  > tmp.$$.json && mv tmp.$$.json composer.local.json \
    && jq '.extra."merge-plugin".include += ["extensions/Maps/composer.json"]' < composer.local.json  > tmp.$$.json && mv tmp.$$.json composer.local.json \
    ## Apply 
    && cd $MW_HOME \
    && composer update  --no-dev --prefer-source --optimize-autoloader \
    #https://www.semantic-mediawiki.org/wiki/Help:Setup_information_file
    && touch extensions/SemanticMediaWiki/.smw.json \
    && chown $WWW_USER:$WWW_GROUP extensions/SemanticMediaWiki/.smw.json

#### Scribunto / Lua ####
RUN set -x; \
    cd $MW_HOME/extensions \
    && git clone --depth 1 https://github.com/Liquipedia/VariablesLua.git \
    && git clone --depth 1 https://github.com/wikimedia/mediawiki-extensions-Capiunto.git Capiunto

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
    && cd TimedMediaHandler && composer install --no-dev && cd .. \
    && git clone --depth 1 https://github.com/StarCitizenWiki/mediawiki-extensions-EmbedVideo EmbedVideo \
    ## Visual Editor
    # && git clone --depth 1 -b $MW_VERSION https://gerrit.wikimedia.org/r/mediawiki/extensions/Math.git \ # bundled in REL1_39
    && apt-get update -y && apt-get install --no-install-recommends -y -o Dpkg::Options::="--force-confold" -y nodejs npm librsvg2-dev build-essential \
    && mkdir -p /usr/local/nodejs/mathoid/ && cd /usr/local/nodejs/mathoid/ \
    && npm install mathoid \
    #  https://github.com/wikimedia/mathoid/commit/8aa71deeb28d4b039d421bee011342aaf5cc124a
    && npm install commander@6.2.1 \
    && cd $MW_HOME/extensions \
    && git clone --depth 1 -b $MW_VERSION https://github.com/wikimedia/mediawiki-extensions-VEForAll.git VEForAll \
    && git clone --depth 1 -b $MW_VERSION https://github.com/wikimedia/mediawiki-extensions-CodeMirror.git CodeMirror \
    && git clone --depth 1 -b dev https://github.com/OpenSemanticLab/WikiMarkdown.git WikiMarkdown \
    && cd $MW_HOME && jq '.extra."merge-plugin".include += ["extensions/WikiMarkdown/composer.json"]' < composer.local.json  > tmp.$$.json && mv tmp.$$.json composer.local.json && cd $MW_HOME/extensions \
    ## Other Editors
    #&& git clone --depth 1 -b $MW_VERSION https://github.com/wikimedia/mediawiki-extensions-DrawioEditor.git DrawioEditor \
    && git clone --depth 1 -b dev https://github.com/OpenSemanticLab/mediawiki-extensions-DrawioEditor DrawioEditor \
    #  apply patch for private wikis, see: https://phabricator.wikimedia.org/T271347
    && sed -i -e "s/static \$validNamespaces = \[/static \$validNamespaces = \['http:\/\/www.w3.org\/1999\/xhtml',/g" $MW_HOME/includes/upload/UploadBase.php \
    && sed -i -e "s/datastr = datastr.replace( expr, '\"http:\/\/www.w3.org\/2000\/svg\"' );/\/\/datastr = datastr.replace( expr, '\"http:\/\/www.w3.org\/2000\/svg\"' ); /g" $MW_HOME/extensions/DrawioEditor/resources/ext.drawioeditor.js \
    && cd $MW_HOME \
    && composer update  --no-dev --prefer-source

##### UI extensions
RUN set -x; \
    cd $MW_HOME \
    && COMPOSER=composer.local.json composer require --no-update mediawiki/sub-page-list "2.0.2" \
    #&& COMPOSER=composer.local.json composer require --no-update hallowelt/cognitiveprocessdesigner "dev-REL1_31" \
    && git clone --depth 1 -b $MW_VERSION https://github.com/wikimedia/mediawiki-extensions-CognitiveProcessDesigner.git extensions/CognitiveProcessDesigner \
    && jq '.extra."merge-plugin".include += ["extensions/CognitiveProcessDesigner/composer.json"]' < composer.local.json  > tmp.$$.json && mv tmp.$$.json composer.local.json \
    #&& COMPOSER=composer.local.json composer require --no-update "mediawiki/simple-batch-upload:1.8.2" \
    && git clone -b mobile-camera-upload https://github.com/OpenSemanticLab/mediawiki-extensions-SimpleBatchUpload.git extensions/SimpleBatchUpload \
    && jq '.extra."merge-plugin".include += ["extensions/SimpleBatchUpload/composer.json"]' < composer.local.json  > tmp.$$.json && mv tmp.$$.json composer.local.json \
    && git clone --depth 1 -b $MW_VERSION https://gerrit.wikimedia.org/r/mediawiki/extensions/Widgets.git extensions/Widgets \
    && chown $WWW_USER:$WWW_GROUP extensions/Widgets/compiled_templates \
    && jq '.extra."merge-plugin".include += ["extensions/Widgets/composer.json"]' < composer.local.json  > tmp.$$.json && mv tmp.$$.json composer.local.json \
    && composer update  --no-dev --prefer-source \
    && cd $MW_HOME/extensions \
    && git clone --depth 1 -b $MW_VERSION https://github.com/wikimedia/mediawiki-extensions-JSBreadCrumbs.git JSBreadCrumbs \
    && git clone --depth 1 https://gitlab.com/organicdesign/TreeAndMenu.git TreeAndMenu \
    && git clone --depth 1 -b REL1_35-dev https://github.com/OpenSemanticLab/mediawiki-extensions-DisplayTitle.git DisplayTitle \
    #&& git clone --depth 1 -b dev-$MW_VERSION  https://github.com/simontaurus/mediawiki-extensions-PageForms.git PageForms \
    ## installed with composer by SemanticFormsSelect
    # && git clone --depth 1 -b $MW_VERSION https://github.com/wikimedia/mediawiki-extensions-PageForms.git PageForms \ 
    && git clone --depth 1 -b $MW_VERSION https://github.com/wikimedia/mediawiki-extensions-MagicNoCache.git MagicNoCache \
    && git clone --depth 1 https://github.com/wikimedia/mediawiki-extensions-HeaderTabs.git HeaderTabs \ 
    && git clone --depth 1 -b $MW_VERSION https://github.com/wikimedia/mediawiki-extensions-UploadWizard.git UploadWizard \
    ## Guilded Tours
    && cd $MW_HOME \
    && git clone --depth 1 -b $MW_VERSION https://github.com/wikimedia/mediawiki-extensions-EventStreamConfig.git extensions/EventStreamConfig \
    && git clone --depth 1 -b $MW_VERSION https://github.com/wikimedia/mediawiki-extensions-EventLogging.git extensions/EventLogging \
    && git clone --depth 1 https://github.com/wikimedia/mediawiki-extensions-GuidedTour.git  extensions/GuidedTour \
    && git clone --depth 1 -b $MW_VERSION https://github.com/wikimedia/mediawiki-extensions-GettingStarted.git extensions/GettingStarted \
    ## Iframes
    && git clone --depth 1 https://github.com/sigbertklinke/Iframe extensions/Iframe \
    ## Reveal Slides
    && git clone --depth 1 https://github.com/OpenSemanticLab/mediawiki-extensions-Reveal.git extensions/Reveal \
    ## TwitterTag: adds <twitter /> tag to allow embedding Twitter content on pages. Not GDPR conform!
    && git clone --depth 1 https://github.com/wikimedia/mediawiki-extensions-TwitterTag.git extensions/TwitterTag \
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
    && git clone --depth 1 -b 6.3 https://github.com/wikimedia/mediawiki-extensions-PluggableAuth.git extensions/PluggableAuth \
    && git clone --depth 1 -b 6.2 https://github.com/wikimedia/mediawiki-extensions-OpenIDConnect.git extensions/OpenIDConnect \
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
    && git clone --depth 1 https://github.com/simontaurus/PageTools.git \
    && git clone https://github.com/simontaurus/WellplateEditor.git \
    && git clone -b no-storage-prompt https://github.com/OpenSemanticLab/mediawiki-extensions-SvgEditor.git SvgEditor \
    && git clone https://github.com/OpenSemanticLab/mediawiki-extensions-MwJson.git MwJson \
    && git clone https://github.com/OpenSemanticLab/mediawiki-extensions-SpreadsheetEditor.git SpreadsheetEditor \
    && git clone https://github.com/OpenSemanticLab/mediawiki-extensions-ChemEditor.git ChemEditor \
    && git clone -b refactoring https://github.com/OpenSemanticLab/mediawiki-extensions-InteractiveSemanticGraph InteractiveSemanticGraph \
    && git clone https://github.com/OpenSemanticLab/mediawiki-extensions-InteractiveSemanticGraph2 InteractiveSemanticGraph2 \
    && git clone https://github.com/OpenSemanticLab/mediawiki-extensions-HdfHandler HdfHandler \    
    && git clone -b dev https://github.com/OpenSemanticLab/mediawiki-extensions-OpenSemanticLab.git OpenSemanticLab \
    && git clone --depth 1 https://github.com/OpenSemanticLab/mediawiki-extensions-SemanticProperties.git SemanticProperties \
    ## patch VisualEditor
    ## && rm -r VisualEditor && git clone -b REL1_35-dev https://github.com/simontaurus/mediawiki-extensions-VisualEditor.git VisualEditor && cd VisualEditor && git submodule update --init && cd .. \
    #&& cd VisualEditor && git remote add fork https://github.com/simontaurus/mediawiki-extensions-VisualEditor.git && git fetch fork && git switch -c REL1_35-dev fork/REL1_35-dev && cd .. \
    #manual patch of external submodule VisualEditor (lib/ve)
    #&& sed -i -e "s/surface.getModel().setLinearSelection( startNode.getOuterRange() );/if (startNode.length != 0) surface.getModel().setLinearSelection( startNode.getOuterRange() );/g" VisualEditor/lib/ve/src/ce/keydownhandlers/ve.ce.LinearDeleteKeyDownHandler.js \
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
