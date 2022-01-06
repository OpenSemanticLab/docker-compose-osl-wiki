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
#local time zone
$wgLocaltimezone = getenv( 'MW_TIME_ZONE' );

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
$wgLogos = [
	'1.5x' => "$wgScriptPath/logo.png"	// path to 1.5x version
];

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
wfLoadExtension( 'VEForAll' );
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
#$wgVisualEditorEnableWikitext = true;
#$wgDefaultUserOptions['visualeditor-newwikitext'] = 1;
//Whether to enable the visual diff function on the history special page. 
$wgVisualEditorEnableDiffPage = true;

wfLoadExtension( 'Math' );
#$wgMathValidModes[] = 'mathml';
#$wgDefaultUserOptions['math'] = 'mathml';
#$wgMathMathMLUrl = getenv( 'MATHOID_SERVER' ); //  IP of Mathoid server. RestBase is still required (only possible for public wikis)
//use local cli. disable speech (config.prod.yaml) may improve performance
$wgMathoidCli = ['/usr/local/nodejs/mathoid/node_modules/mathoid/cli.js', '-c', '/usr/local/nodejs/mathoid/node_modules/mathoid/config.dev.yaml'];
// Raise MediaWiki's memory limit to 1.2G for mathoid.
$wgMaxShellMemory = 1228800;
#wfLoadExtension( 'CodeMirror' );

############ Multimedia & Editors ############
## File formats
wfLoadExtension( 'NativeSvgHandler' );
## Visual Editor
## Other Editors
wfLoadExtension( 'DrawioEditor' );
$wgDrawioEditorBackendUrl =  getenv( 'DRAWIO_SERVER' );


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

$wgUseImageMagick = true;
$wgImageMagickConvertCommand = "/usr/bin/convert";

## access images over img_auth.php
$wgUploadPath = "$wgScriptPath/img_auth.php";


####################### Bundled extensions #########################
wfLoadExtension( 'CategoryTree' );
wfLoadExtension( 'Cite' );
wfLoadExtension( 'CiteThisPage' );
wfLoadExtension( 'CodeEditor' );
$wgDefaultUserOptions['usebetatoolbar'] = 1; // user option provided by WikiEditor extension
#wfLoadExtension( 'ConfirmEdit' ); //not needed for private wiki
#wfLoadExtension( 'ApprovedRevs' ); //not bundled
wfLoadExtension( 'Gadgets' );
wfLoadExtension( 'ImageMap' );
wfLoadExtension( 'InputBox' );
wfLoadExtension( 'Interwiki' );
$wgGroupPermissions['sysop']['interwiki'] = true; // To grant sysops permissions to edit interwiki data
#$wgEnableScaryTranscluding = true; //To enable transclusion from other sites
#wfLoadExtension( 'LocalisationUpdate' );
#$wgLocalisationUpdateDirectory = "$IP/cache";
wfLoadExtension( 'MultimediaViewer' );
wfLoadExtension( 'Nuke' );
#wfLoadExtension( 'OATHAuth' );
wfLoadExtension( 'PageImages' );
wfLoadExtension( 'ParserFunctions' );
$wgPFEnableStringFunctions = true;
#require_once( "$IP/extensions/Arrays/Arrays.php" ); //not bundled
#wfLoadExtension( 'WSArrays' );  //not bundled
#wfLoadExtension( 'Loops' );  //not bundled
wfLoadExtension( 'PdfHandler' );
wfLoadExtension( 'Poem' );
wfLoadExtension( 'Renameuser' );
wfLoadExtension( 'ReplaceText' );
$wgGroupPermissions['bureaucrat']['replacetext'] = true;
wfLoadExtension( 'Scribunto' );
$wgScribuntoDefaultEngine = 'luastandalone';
$wgScribuntoUseGeSHi = true;
$wgScribuntoUseCodeEditor = true;
wfLoadExtension( 'SecureLinkFixer' );
#wfLoadExtension( 'SpamBlacklist' ); //not needed for private wiki
wfLoadExtension( 'SyntaxHighlight_GeSHi' );
#$wgPygmentizePath = '/usr/bin/pygmentize';
wfLoadExtension( 'TemplateData' );
wfLoadExtension( 'TextExtracts' );
wfLoadExtension( 'TitleBlacklist' );
wfLoadExtension( 'WikiEditor' );

########### External Data ###############
wfLoadExtension( 'ExternalData' );
$wgExternalDataSources['graphviz'] = [
   'name'              => 'GraphViz',
   'program url'       => 'https://graphviz.org/',
   'version command'   => null,
   'command'           => 'dot -K$layout$ -Tsvg',
   'params'            => [ 'layout' => 'dot' ],
   'param filters'     => [ 'layout' => '/^(dot|neato|twopi|circo|fdp|osage|patchwork|sfdp)$/' ],
   'input'             => 'dot',
   'preprocess'        => 'EDConnectorExe::wikilinks4dot',
   'postprocess'       => 'EDConnectorExe::innerXML',
   'min cache seconds' => 30 * 24 * 60 * 60,
   'tag'               => 'graphviz'
];

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

$smwgShowFactbox = SMW_FACTBOX_NONEMPTY; #Show factboxes only if they have some content 

wfLoadExtension( 'SemanticResultFormats' );
$srfgFormats[] = 'process';


########### Flow (AFTER SMW!!!) ###############
# https://www.mediawiki.org/wiki/Extension:Flow
$flowNamespaces = getenv( 'MW_FLOW_NAMESPACES' );
if ( $flowNamespaces ) {
    wfLoadExtension( 'Flow' );
    $wgFlowContentFormat = 'html';
    foreach ( explode( ',', $flowNamespaces ) as $ns ) {
        $wgNamespaceContentModels[ constant( $ns ) ] = 'flow-board';
    }
}
