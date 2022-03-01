#!/bin/bash
#
# Copyright (C) 2017-2020 Pavel Astakhov (pastakhov@yandex.ru), MIT Licence
# Copyright (C) 2021-2022 Simon Stier (simon.stier@gmx.de), AGPL Licence
#

set -e

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

service ssh start

cd $MW_HOME
rm -f "$MW_VOLUME/LocalSettings.php"

# If there is no LocalSettings.php, create one using maintenance/install.php
if [ ! -e "$MW_VOLUME/LocalSettings.php" ]; then
    echo "There is no LocalSettings.php, create one using maintenance/install.php"

    for x in MW_DB_INSTALLDB_USER MW_DB_INSTALLDB_PASS
    do
        if [ -z "${!x}" ]; then
            echo >&2 "Variable $x must be defined";
            exit 1;
        fi
    done

    wait_database_started $MW_DB_INSTALLDB_USER $MW_DB_INSTALLDB_PASS $MW_DB_USER $MW_DB_PASS

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
        --lang "$MW_SITE_LANG" \
        --pass "$MW_ADMIN_PASS" \
        "$MW_SITE_NAME" \
        "$MW_ADMIN_USER"

    # Append inclusion of DockerSettings.php
    #echo "include_once '$IP/DockerSettings.php';"  >> "$MW_VOLUME/LocalSettings.php"
    cat  "$MW_HOME/CustomSettings.php" >> "$MW_VOLUME/LocalSettings.php" 
    cat  "$MW_HOME/DockerSettings.php" >> "$MW_VOLUME/LocalSettings.php"

fi

if [ ! -e "$MW_HOME/LocalSettings.php" ]; then
    ln -s "$MW_VOLUME/LocalSettings.php" "$MW_HOME/LocalSettings.php"
fi

########## Run maintenance scripts ##########
if [ $MW_AUTOUPDATE == 'true' ]; then
    echo 'Check for the need to run maintenance scripts'
    #wait_database_started "$MW_DB_INSTALLDB_USER" "$MW_DB_INSTALLDB_PASS" "$MW_DB_USER" "$MW_DB_PASS"
    
    #development: always run update.php for SMW
    php maintenance/update.php
    #workaround for https://github.com/SemanticMediaWiki/SemanticMediaWiki/issues/4865
    php /var/www/html/w/extensions/SemanticMediaWiki/maintenance/updateEntityCountMap.php

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
        run_maintenance_script_if_needed 'maintenance_CirrusSearch_updateConfig' "$MW_MAINTENANCE_CIRRUSSEARCH_UPDATECONFIG" 'extensions/CirrusSearch/maintenance/UpdateSearchIndexConfig.php'
        #run_script_if_needed 'maintenance_CirrusSearch_updateConfig' "$MW_MAINTENANCE_CIRRUSSEARCH_UPDATECONFIG" 'extensions/CirrusSearch/maintenance/UpdateSearchIndexConfig.php'

        run_maintenance_script_if_needed 'maintenance_CirrusSearch_forceIndex' "$MW_MAINTENANCE_CIRRUSSEARCH_FORCEINDEX" \
            'extensions/CirrusSearch/maintenance/ForceSearchIndex.php --skipLinks --indexOnSkip' \
            'extensions/CirrusSearch/maintenance/ForceSearchIndex.php –skipParse'

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

# Make sure we're not confused by old, incompletely-shutdown httpd
# context after restarting the container.  httpd won't start correctly
# if it thinks it is already running.

############### Run Apache ###############
rm -rf /run/apache2/*

#apachectl -e info & #run in the background

su -s /bin/bash -c '/mwjobrunner.sh &' www-data  #run in the background as www-data, fixes https://www.mediawiki.org/wiki/Topic:Tn0u0v07qa9cb9v8 
#/mwjobrunner.sh -D FOREGROUND

exec apachectl -e info -D FOREGROUND
