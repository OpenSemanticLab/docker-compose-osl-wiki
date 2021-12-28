# Protect against web entry
if ( !defined( 'MEDIAWIKI' ) ) {
    exit;
}

#Debug Settings
if ( getenv( 'MW_SHOW_EXCEPTION_DETAILS' ) === 'true' ) {
    error_reporting( -1 );
    ini_set( 'display_errors', 1 );
    $wgShowExceptionDetails = true;
}

########################### Core Settings ##########################
# Site language code, should be one of the list in ./languages/Names.php
$wgLanguageCode = getenv( 'MW_SITE_LANG' );

## The protocol and server name to use in fully-qualified URLs => set in Custom settings
$wgServer = getenv( 'MW_SITE_SERVER' );

# The name of the site. This is the name of the site as displayed throughout the site.
$wgSitename  = getenv( 'MW_SITE_NAME' );

# Default skin: you can change the default skin. Use the internal symbolic
# names, ie 'standard', 'nostalgia', 'cologneblue', 'monobook', 'vector', 'chameleon':
$wgDefaultSkin = getenv( 'MW_DEFAULT_SKIN' );

# InstantCommons allows wiki to use images from http://commons.wikimedia.org
$wgUseInstantCommons  = getenv( 'MW_USE_INSTANT_COMMONS' );

# Name used for the project namespace. The name of the meta namespace (also known as the project namespace), used for pages regarding the wiki itself.
$wgMetaNamespace = 'Site';
$wgMetaNamespaceTalk = 'Site_talk';

# The relative URL path to the logo.  Make sure you change this from the default,
# or else you'll overwrite your logo when you upgrade!
$wgLogo = "$wgScriptPath/logo.png";

# The URL of the site favicon (the small icon displayed next to a URL in the address bar of a browser)
$wgFavicon = "$wgScriptPath/favicon.ico";

##### Short URLs
## https://www.mediawiki.org/wiki/Manual:Short_URL
$wgArticlePath = '/wiki/$1';
## Also see mediawiki.conf

##### Improve performance
# https://www.mediawiki.org/wiki/Manual:$wgMainCacheType
switch ( getenv( 'MW_MAIN_CACHE_TYPE' ) ) {
    case 'CACHE_ACCEL':
        # APC has several problems in latest versions of MediaWiki and extensions, for example:
        # https://www.mediawiki.org/wiki/Extension:Flow#.22Exception_Caught:_CAS_is_not_implemented_in_Xyz.22
        $wgMainCacheType = CACHE_ACCEL;
        $wgSessionCacheType = CACHE_DB; #This may cause problems when CACHE_ACCEL is used
        break;
    case 'CACHE_DB':
        $wgMainCacheType = CACHE_DB;
        break;
    case 'CACHE_DB':
        $wgMainCacheType = CACHE_DB;
        break;
    case 'CACHE_ANYTHING':
        $wgMainCacheType = CACHE_ANYTHING;
        break;
    case 'CACHE_MEMCACHED':
        # Use Memcached, see https://www.mediawiki.org/wiki/Memcached
        $wgMainCacheType = CACHE_MEMCACHED;
        $wgParserCacheType = CACHE_MEMCACHED; # optional
        $wgMessageCacheType = CACHE_MEMCACHED; # optional
        $wgMemCachedServers = explode( ',', getenv( 'MW_MEMCACHED_SERVERS' ) );
        $wgSessionsInObjectCache = true; # optional
        $wgSessionCacheType = CACHE_MEMCACHED; # optional
        break;
    default:
        $wgMainCacheType = CACHE_NONE;
}

########################### Search ############################
wfLoadExtension( 'Elastica' );
wfLoadExtension( 'CirrusSearch' );
$wgCirrusSearchServers =  explode( ',', getenv( 'MW_CIRRUS_SEARCH_SERVERS' ) );
$wgSearchType = 'CirrusSearch';

########################### VisualEditor ###########################
    wfLoadExtension( 'VisualEditor' );
    // Enable by default for everybody
    $wgDefaultUserOptions['visualeditor-enable'] = 1;
    // Use Visual editor in PageForms
    #wfLoadExtension( 'VEForAll' );
    // Optional: Set VisualEditor as the default for anonymous users
    // otherwise they will have to switch to VE
    $wgDefaultUserOptions['visualeditor-editor'] = "visualeditor";
    // Don't allow users to disable it
    $wgHiddenPrefs[] = 'visualeditor-enable';
    // OPTIONAL: Enable VisualEditor's experimental code features
    $wgDefaultUserOptions['visualeditor-enable-experimental'] = 1;    
    //Whether to allow users to enable the section editing. 
    #$wgVisualEditorEnableVisualSectionEditing = true;
    //Whether to enable VisualEditor for every new account. 
    $wgVisualEditorAutoAccountEnable = true;
    //Whether to enable the wikitext source mode inside VisualEditor. 
    $wgVisualEditorEnableWikitext = true;
    $wgDefaultUserOptions['visualeditor-newwikitext'] = 1;
    //Whether to enable the visual diff function on the history special page. 
    $wgVisualEditorEnableDiffPage = true;

############### Private Wiki ######################################
$wgGroupPermissions['*']['createaccount'] = false;
$wgGroupPermissions['*']['edit'] = false;
$wgGroupPermissions['*']['read'] = false;
$wgGroupPermissions['user']['writeapi'] = true;
$wgWhitelistRead[] = 'Main Page';
$wgWhitelistRead[] = 'Site:About';
$wgWhitelistRead[] = 'Site:Privacy_policy';
$wgWhitelistRead[] = 'Site:General_disclaimer';
$wgWhitelistRead[] = 'Site:Terms_of_Service'; #redirect to Project:Privacy_policy needed
$wgAllowExternalImages = true; #to use images on public main page
$wgAllowImageTag = true;


############## Uploads #####################
$wgEnableUploads  = getenv( 'MW_ENABLE_UPLOADS' );
$wgGroupPermissions['user']['reupload'] = true;

$wgFileExtensions = array( 'png', 'gif', 'jpg', 'jpeg', 'doc',
    'xls', 'csv', 'txt', 'mpp', 'pdf', 'ppt', 'tiff', 'bmp', 'docx', 'xlsx',
    'pptx', 'ps', 'odt', 'ods', 'odp', 'odg', 'svg', 'mp4', 'mp3'
);

## access images over img_auth.php
$wgUploadPath = "$wgScriptPath/img_auth.php";


########### Semantic Mediawiki ###############
#strip protocol from MW_SITE_SERVER
enableSemantics( preg_replace( "#^[^:/.]*[:/]+#i", "", getenv( 'MW_SITE_SERVER' ) ) );
$smwgDefaultStore = 'SMWSparqlStore';
$smwgSparqlRepositoryConnector = 'blazegraph';

$smwgSparqlEndpoint["query"] = 'http://graphdb:9999/blazegraph/namespace/kb/sparql';
$smwgSparqlEndpoint["update"] = 'http://graphdb:9999/blazegraph/namespace/kb/sparql';
$smwgSparqlEndpoint["data"] = '';

# Optional name of default graph
$smwgSparqlDefaultGraph = getenv( 'MW_SITE_SERVER' ) . '/id/';
# Namespace for export
$smwgNamespace =  getenv( 'MW_SITE_SERVER' ) . '/id/';
#needs rebuild: php /var/www/html/w/extensions/SemanticMediaWiki/maintenance/rebuildData.php

########### Flow (AFTER SMW!!!) ###############
#if ( $flowNamespaces ) {
#    $wgFlowSearchServers = $wgCirrusSearchServers;
#}
