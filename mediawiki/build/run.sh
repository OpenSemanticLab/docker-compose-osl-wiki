#!/bin/bash
#
# Copyright (C) 2017-2020 Pavel Astakhov (pastakhov@yandex.ru), MIT Licence
# Copyright (C) 2021-2022 Simon Stier (simon.stier@gmx.de), AGPL Licence
#

set -e

print_logo ()
{
echo "\
                                   
                                   
              O   C                
              |   |                
              P   I                
             /     \               
            E - N   T              
           / \     /               
          S   M   N                
               \ /                 
          L - - A - - B            
                                   
                                   
         OpenSemanticLab        
                                   
                                   
"
}

wait_database_started ()
{
    if [ -n "$db_started" ]; then
        return 0; # already started
    fi

    echo "Waiting for database to start"
    mysql=( mysql -h db -u$1 -p$2 ) 

    for i in {300..0}; do
        if echo 'SELECT 1' | "${mysql[@]}" &> /dev/null; then
                break
        fi
        echo 'Waiting for database to start...'
        sleep 1
    done
    if [ "$i" = 0 ]; then
        echo >&2 'Could not connect to the database.'
        return 1
    fi
    echo 'Successfully connected to the database.'
    #echo "Enable password auth (for mysql > 8.0): ALTER USER '$3' IDENTIFIED WITH mysql_native_password BY '$4';"
    #if echo "ALTER USER '$3' IDENTIFIED WITH mysql_native_password BY '$4';" | "${mysql[@]}" &> /dev/null; then
    #    echo "Success"
    #else
    #    echo "Error"
    #    return 1
    #fi
    db_started="1"
    return 0
}

wait_elasticsearch_started ()
{
    if [ -n "$es_started" ]; then
        return 0; # already started
    fi

    echo 'Waiting for elasticsearch to start'
    for i in {300..0}; do
        result=0
        output=$(wget --timeout=1 -q -O - http://elasticsearch:9200/_cat/health) || result=$?
        if [[ "$result" = 0 && "`echo $output|awk '{ print $4 }'`" = "green" ]]; then
            break
        fi
        if [ "$result" = 0 ]; then
            echo "Waiting for elasticsearch health status changed from [`echo $output|awk '{ print $4 }'`] to [green]..."
        else
            echo 'Waiting for elasticsearch to start...'
        fi
        sleep 1
    done
    if [ "$i" = 0 ]; then
        echo >&2 'Could not connect to the elasticsearch'
        echo "$output"
        retirn 1
    fi
    echo 'Elasticsearch started successfully'
    es_started="1"
    return 0
}

run_maintenance_script_if_needed () {
    if [ -f "$MW_VOLUME/$1.info" ]; then
        update_info="$(cat "$MW_VOLUME/$1.info" 2>/dev/null)"
    else
        update_info=""
    fi

    if [[ "$update_info" != "$2" && -n "$2" && "${2: -1}" != '-' ]]; then
        wait_database_started "$MW_DB_INSTALLDB_USER" "$MW_DB_INSTALLDB_PASS" "$MW_DB_USER" "$MW_DB_PASS"
        if [[ "$1" == *CirrusSearch* ]]; then wait_elasticsearch_started; fi 

        i=3
        while [ -n "${!i}" ]
        do
            if [ ! -f "`echo "${!i}" | awk '{print $1}'`" ]; then
                echo >&2 "Maintenance script does not exit: ${!i}"
                return 0;
            fi
            echo "Run maintenance script: ${!i}"
            runuser -c "php ${!i}" -s /bin/bash $WWW_USER
            i=$(( $i + 1 ))
        done

        echo "Successful updated: $2"
        echo "$2" > "$MW_VOLUME/$1.info"
    else
        echo "$1 is up to date: $2."
    fi
}

run_script_if_needed () {
    if [ -f "$MW_VOLUME/$1.info" ]; then
        update_info="$(cat "$MW_VOLUME/$1.info" 2>/dev/null)"
    else
        update_info=""
    fi

    if [[ "$update_info" != "$2" && -n "$2" && "${2: -1}" != '-' ]]; then
        wait_database_started "$MW_DB_INSTALLDB_USER" "$MW_DB_INSTALLDB_PASS" "$MW_DB_USER" "$MW_DB_PASS"
        if [[ "$1" == *CirrusSearch* ]]; then wait_elasticsearch_started; fi 
        echo "Run script: $3"
        eval $3

        cd $MW_HOME

        echo "Successful updated: $2"
        echo "$2" > "$MW_VOLUME/$1.info"
    else
        echo "$1 is skipped: $2."
    fi
}

cd $MW_HOME

########## Create Temp Dir #########
if [ ! -d "$MW_HOME/images/temp" ]; then
	echo " temp dir not existing, create it"
	mkdir "$MW_HOME/images/temp"
    chown www-data:www-data "$MW_HOME/images/temp"
fi

########## Generate robots.txt ##########
echo "Generating robots.txt"
cat > "$MW_HOME/robots.txt" << 'ROBOTS'
# robots.txt for OpenSemanticLab MediaWiki
# Generated at container startup

User-agent: *
Allow: /w/resources
Allow: /w/skins
Allow: /w/extensions
Allow: /w/images
Allow: /w/sitemap
Allow: /w/api.php?action=mobileview
Allow: /w/load.php
Disallow: /w/
Disallow: /Special:
Disallow: /Special%3A
Disallow: /wiki/Special:
Disallow: /wiki/Special%3A
Disallow: /MediaWiki:
Disallow: /MediaWiki%3A
Disallow: /wiki/MediaWiki:
Disallow: /wiki/MediaWiki%3A

# Block aggressive crawlers
User-agent: AhrefsBot
Disallow: /

User-agent: SemrushBot
Disallow: /

User-agent: MJ12bot
Disallow: /

User-agent: DotBot
Disallow: /

ROBOTS
# Append sitemap reference only if sitemap building is enabled
if [ "$MW_AUTOBUILD_SITEMAP" == 'true' ]; then
    echo "Sitemap: ${MW_SITE_SERVER}/sitemap.xml" >> "$MW_HOME/robots.txt"
fi

########## Create LocalSettings ##########

# If LocalSettings was not mounted: File does not exist (first run) or is a symlink (after first run) 
if [ ! -e "$MW_HOME/LocalSettings.php" ] || [ -L "$MW_HOME/LocalSettings.php" ]; then

    echo "There is no LocalSettings.php, create one"

    # If there is no LocalSettings.php, create one using maintenance/install.php
    if [ ! -e "$MW_HOME/InstallSettings.php" ] || [ "$MW_REINSTALL" == 'true' ]; then

        echo "There is no InstallSettings.php or reinstall was forced, create one using maintenance/install.php"

        for x in MW_DB_INSTALLDB_USER MW_DB_INSTALLDB_PASS
        do
            if [ -z "${!x}" ]; then
                echo >&2 "Variable $x must be defined";
                exit 1;
            fi
        done

        wait_database_started $MW_DB_INSTALLDB_USER $MW_DB_INSTALLDB_PASS $MW_DB_USER $MW_DB_PASS

        # Back up updatelog before install.php runs.
        # install.php marks all migrations as done ("Prevent running unneeded updates")
        # even on existing databases where those migrations never actually ran.
        # We restore the original updatelog afterwards so update.php can run them properly.
        mysql -h db -u$MW_DB_INSTALLDB_USER -p$MW_DB_INSTALLDB_PASS $MW_DB_NAME \
            -e "SELECT ul_key, ul_value FROM updatelog" > /tmp/updatelog_backup.tsv 2>/dev/null || true

        # remove previous created file, otherwise install.php will fail
        rm -f "$MW_VOLUME/LocalSettings.php"

        # remove symlink if already created
        if [ -e "$MW_HOME/LocalSettings.php" ]; then
            rm -f "$MW_HOME/LocalSettings.php"
        fi

        # install.php may return non-zero on existing databases due to non-fatal
        # GRANT errors (Error 1410). We check for actual success by verifying
        # that LocalSettings.php was created.
        install_result=0
        php maintenance/install.php \
            --confpath "$MW_VOLUME" \
            --dbserver "db" \
            --dbtype "mysql" \
            --dbname "$MW_DB_NAME" \
            --dbuser "$MW_DB_USER" \
            --dbpass "$MW_DB_PASS" \
            --installdbuser "$MW_DB_INSTALLDB_USER" \
            --installdbpass "$MW_DB_INSTALLDB_PASS" \
            --server "$MW_SITE_SERVER" \
            --scriptpath "/w" \
            --lang "en" \
            --pass "$MW_ADMIN_PASS" \
            "$MW_SITE_NAME" \
            "$MW_ADMIN_USER" || install_result=$?

        if [ ! -e "$MW_VOLUME/LocalSettings.php" ]; then
            echo >&2 "install.php failed: LocalSettings.php was not created (exit code $install_result)"
            exit 1
        fi
        if [ $install_result -ne 0 ]; then
            echo "Warning: install.php exited with code $install_result but LocalSettings.php was created, continuing"
        fi

        # Restore updatelog if it existed before install.php ran (= existing DB upgrade)
        if [ -s /tmp/updatelog_backup.tsv ]; then
            echo "Restoring updatelog to pre-install state so update.php can run pending migrations"
            mysql -h db -u$MW_DB_INSTALLDB_USER -p$MW_DB_INSTALLDB_PASS $MW_DB_NAME \
                -e "DELETE FROM updatelog" 2>/dev/null
            # Re-import the original rows (skip TSV header line)
            tail -n +2 /tmp/updatelog_backup.tsv | while IFS=$'\t' read -r key value; do
                if [ "$value" = "NULL" ] || [ -z "$value" ]; then
                    mysql -h db -u$MW_DB_INSTALLDB_USER -p$MW_DB_INSTALLDB_PASS $MW_DB_NAME \
                        -e "INSERT IGNORE INTO updatelog (ul_key) VALUES ('$(echo "$key" | sed "s/'/''/g")')" 2>/dev/null
                else
                    mysql -h db -u$MW_DB_INSTALLDB_USER -p$MW_DB_INSTALLDB_PASS $MW_DB_NAME \
                        -e "INSERT IGNORE INTO updatelog (ul_key, ul_value) VALUES ('$(echo "$key" | sed "s/'/''/g")', '$(echo "$value" | sed "s/'/''/g")')" 2>/dev/null
                fi
            done
            rm -f /tmp/updatelog_backup.tsv
        fi

        # copy the freshly created LocalSettings.php to InstallSettings.php
        cp "$MW_VOLUME/LocalSettings.php" "$MW_HOME/InstallSettings.php"

    fi

    ln -s "$MW_HOME/InstallSettings.php" "$MW_HOME/LocalSettings.php"

    # Append inclusion of DockerSettings.php - unfortunately this leads to strange errors
    #echo "require_once 'DockerSettings.php';"  >> "$MW_HOME/LocalSettings.php"

    # merge DockerSettings.php to LocalSettings.php if existing
    if [ -e "$MW_HOME/DockerSettings.php" ]; then
        echo "Append DockerSettings.php"
        cat  "$MW_HOME/DockerSettings.php" >> "$MW_HOME/LocalSettings.php"
    fi

    # merge CustomSettings.php to LocalSettings.php if existing
    if [ -e "$MW_HOME/CustomSettings.php" ]; then
        echo "Append CustomSettings.php"
        cat  "$MW_HOME/CustomSettings.php" >> "$MW_HOME/LocalSettings.php"
    fi

fi

########## Run maintenance scripts ##########
if [ "$MW_AUTOUPDATE" == 'true' ]; then
    echo 'Check for the need to run maintenance scripts'
    #wait_database_started "$MW_DB_INSTALLDB_USER" "$MW_DB_INSTALLDB_PASS" "$MW_DB_USER" "$MW_DB_PASS"
    
    #development: always run update.php for SMW
    php maintenance/update.php
    # Fix ownership of SMW config file (created by root during update.php, needed by www-data at runtime)
    chown $WWW_USER:$WWW_USER $MW_HOME/extensions/SemanticMediaWiki/.smw.json 2>/dev/null || true
    #workaround for https://github.com/SemanticMediaWiki/SemanticMediaWiki/issues/4865 => fixed
    # php /var/www/html/w/extensions/SemanticMediaWiki/maintenance/updateEntityCountMap.php

    # Refresh MySQL index statistics after update.php migrations.
    # install.php + update.php can create/populate tables (e.g. slots, content) without
    # updating InnoDB statistics, causing the query optimizer to choose full table scans.
    echo "Analyzing key database tables..."
    mysql -h db -u$MW_DB_INSTALLDB_USER -p$MW_DB_INSTALLDB_PASS $MW_DB_NAME \
        -e "ANALYZE TABLE revision, slots, content, page, text, actor, comment, job;" 2>/dev/null || true

    ### maintenance/update.php
    run_maintenance_script_if_needed 'maintenance_update' "$MW_VERSION-$MW_MAINTENANCE_UPDATE" 'maintenance/update.php --quick'
    #run_script_if_needed 'maintenance_update' "$MW_VERSION-$MW_MAINTENANCE_UPDATE" 'maintenance/update.php --quick'

    ### images
    run_maintenance_script_if_needed 'maintenance_refreshImageMetadata' "$MW_VERSION-$MW_MAINTENANCE_UPDATE"  'maintenance/refreshImageMetadata.php -f'
    #run_script_if_needed 'maintenance_refreshImageMetadata' "$MW_VERSION-$MW_MAINTENANCE_UPDATE"  'maintenance/refreshImageMetadata.php -f'    
    run_maintenance_script_if_needed 'maintenance_rebuildImages' "$MW_VERSION-$MW_MAINTENANCE_UPDATE" 'maintenance/rebuildImages.php'
    #run_script_if_needed 'maintenance_rebuildImages' "$MW_VERSION-$MW_MAINTENANCE_UPDATE" 'maintenance/rebuildImages.php'



    ### CirrusSearch
    if [ "$MW_SEARCH_TYPE" == 'CirrusSearch' ]; then

        #always update config due to changes in LocalSettings.php
        php extensions/CirrusSearch/maintenance/UpdateSearchIndexConfig.php

        #run_maintenance_script_if_needed 'maintenance_CirrusSearch_updateConfig' "$MW_MAINTENANCE_CIRRUSSEARCH_UPDATECONFIG" 'extensions/CirrusSearch/maintenance/UpdateSearchIndexConfig.php'
        #run_script_if_needed 'maintenance_CirrusSearch_updateConfig' "$MW_MAINTENANCE_CIRRUSSEARCH_UPDATECONFIG" 'extensions/CirrusSearch/maintenance/UpdateSearchIndexConfig.php'

        run_maintenance_script_if_needed 'maintenance_CirrusSearch_forceIndex' "$MW_VERSION-$MW_MAINTENANCE_UPDATE-${MW_MAINTENANCE_CIRRUSSEARCH_FORCEINDEX:-0}" \
            'extensions/CirrusSearch/maintenance/ForceSearchIndex.php --skipLinks --indexOnSkip' \
            'extensions/CirrusSearch/maintenance/ForceSearchIndex.php --skipParse'

        #run_script_if_needed 'maintenance_CirrusSearch_forceIndex' "$MW_MAINTENANCE_CIRRUSSEARCH_FORCEINDEX" \
        #    'extensions/CirrusSearch/maintenance/ForceSearchIndex.php --skipLinks --indexOnSkip' \
        #    'extensions/CirrusSearch/maintenance/ForceSearchIndex.php –skipParse'

    fi

    ### cldr extension
    if [ -n "$MW_SCRIPT_CLDR_REBUILD" ]; then
    run_script_if_needed 'script_cldr_rebuild' "$MW_VERSION-$MW_SCRIPT_CLDR_REBUILD" \
        'set -x; cd $MW_HOME/extensions/cldr && wget -q http://www.unicode.org/Public/cldr/latest/core.zip && unzip -q core.zip -d core && php rebuild.php && set +x;'

        if [ -n "$MW_MAINTENANCE_ULS_INDEXER" ]; then
            ### UniversalLanguageSelector extension
            run_maintenance_script_if_needed 'maintenance_ULS_indexer' "$MW_VERSION-$MW_SCRIPT_CLDR_REBUILD-$MW_MAINTENANCE_ULS_INDEXER" \
                'extensions/UniversalLanguageSelector/data/LanguageNameIndexer.php'
            #run_maintenance_script_if_needed 'maintenance_ULS_indexer' "$MW_VERSION-$MW_SCRIPT_CLDR_REBUILD-$MW_MAINTENANCE_ULS_INDEXER" \
            #    'extensions/UniversalLanguageSelector/data/LanguageNameIndexer.php'

        fi
    fi

    ### Flow extension
    if [ -n "$MW_FLOW_NAMESPACES" ]; then
        # https://www.mediawiki.org/wiki/Extension:Flow#Enabling_or_disabling_Flow
        run_maintenance_script_if_needed 'maintenance_populateContentModel' "$MW_FLOW_NAMESPACES" \
            'maintenance/populateContentModel.php --ns=all --table=revision' \
            'maintenance/populateContentModel.php --ns=all --table=archive' \
            'maintenance/populateContentModel.php --ns=all --table=page'
        #run_script_if_needed 'maintenance_populateContentModel' "$MW_FLOW_NAMESPACES" \
        #    'maintenance/populateContentModel.php --ns=all --table=revision' \
        #    'maintenance/populateContentModel.php --ns=all --table=archive' \
        #    'maintenance/populateContentModel.php --ns=all --table=page'


# https://phabricator.wikimedia.org/T172369
#        if [ "$MW_SEARCH_TYPE" == 'CirrusSearch' ]; then
#            # see https://www.mediawiki.org/wiki/Flow/Architecture/Search
#            run_maintenance_script_if_needed 'maintenance_FlowSearchConfig_CirrusSearch' "$MW_MAINTENANCE_CIRRUSSEARCH_UPDATECONFIG" \
#                'extensions/Flow/maintenance/FlowSearchConfig.php'
#        fi
    fi
fi

########## Install certs ##########
if [ "$MW_AUTOINSTALL_CA_CERTS" == 'true' ]; then
    update-ca-certificates
fi

########## Import pages ##########
if [ "$MW_AUTOIMPORT_PAGES" == 'true' ]; then
    #php /var/www/html/w/extensions/PageImporter/importPages.php 
    #php /var/www/html/w/extensions/PageExchange/maintenance/maintainPackage.php --packageid org.open-semantic-lab.core --update
    while IFS=';' read -ra PACKAGES; do #split package list by ';'
        for i in "${PACKAGES[@]}"; do #interate over packages
            php /var/www/html/w/extensions/PageExchange/maintenance/maintainPackage.php --packageid "$i" --update
        done
    done <<< "$MW_PAGE_PACKAGES"
fi

# Make sure we're not confused by old, incompletely-shutdown httpd
# context after restarting the container.  httpd won't start correctly
# if it thinks it is already running.

############### Run Apache ###############
rm -rf /run/apache2/*

# Set ServerName from MW_SITE_SERVER to suppress AH00558 warning
SERVER_HOST=$(echo "$MW_SITE_SERVER" | sed 's|https\?://||; s|/.*||; s|:.*||')
echo "ServerName ${SERVER_HOST}" > /etc/apache2/conf-available/servername.conf
a2enconf servername > /dev/null 2>&1

#apachectl -e info & #run in the background

su -s /bin/bash -c '/mwjobrunner.sh &' www-data  #run in the background as www-data, fixes https://www.mediawiki.org/wiki/Topic:Tn0u0v07qa9cb9v8 
su -s /bin/bash -c '/mwmaintenance.sh &' www-data 
if [ "$MW_AUTOBUILD_SITEMAP" == 'true' ]; then
    su -s /bin/bash -c '/mwsitemapbuilder.sh "$MW_SITE_SERVER" &'  #updates the sitemap in the background
fi

print_logo
exec apachectl -e info -D FOREGROUND
