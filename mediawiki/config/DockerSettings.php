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
wfLoadSkin( 'Modern' );
wfLoadSkin( 'MinervaNeue' );
wfLoadExtension( 'Bootstrap' );
wfLoadSkin( 'chameleon' );
wfLoadSkin( 'foreground' );
$wgDefaultSkin = getenv( 'MW_DEFAULT_SKIN' );
wfLoadExtension( 'MobileFrontend' );
$wgMFAutodetectMobileView = false;
#$wgMFDefaultSkinClass = 'SkinMinerva';

# InstantCommons allows wiki to use images from http://commons.wikimedia.org
$wgUseInstantCommons  = getenv( 'MW_USE_INSTANT_COMMONS' );

# Name used for the project namespace. The name of the meta namespace (also known as the project namespace), used for pages regarding the wiki itself.
#$wgMetaNamespace = 'Site'; #just an alias. does not work at all of canonical namespace 'project' is created / used by an extension
#$wgMetaNamespaceTalk = 'Site_talk';

# The relative URL path to the logo.  Make sure you change this from the default,
# or else you'll overwrite your logo when you upgrade!
# logos should actually have different sizes, see https://www.mediawiki.org/wiki/Manual:$wgLogos
$wgLogos = [
        '1x' => "$wgScriptPath/logo.png",
        '1.5x' => "$wgScriptPath/logo.png",
        '2x' => "$wgScriptPath/logo.png",
        'svg' => "$wgScriptPath/logo.svg"
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

//manual fetch a property from the db and index it (does not work as expected)
/*$wgHooks['CirrusSearchBuildDocumentParse'][] = function( \Elastica\Document $doc, Title $title, Content $content, ParserOutput $parserOutput ) {
        //fetch displaytitle from db
        $dbr = wfGetDB( DB_REPLICA );
        $displayTitle = $dbr->selectField(
                'page_props',
                'pp_value',
                array( 'pp_propname' => 'displaytitle', 'pp_page' => $title->getArticleId() ),
                __METHOD__
        );
        if ( $displayTitle === null || trim($displayTitle) === '' )$doc_title = $title->getText();
        else $doc_title = $displayTitle . ' ' . $title->getText();
        //echo $doc_title . "\n";
        //store displaytitle as title in elastic search document
        $doc->set( 'display_title', $doc_title );
        //$doc->set( 'title', $doc_title );
};*/
//$wgCirrusSearchPrefixSearchStartsWithAnyWord = true; //Is it ok if the prefix starts on any word in the title or just the first word?
//only in recent version of cirrus MW>1.35
#$wgCirrusSearchCustomPageFields = [
#    'display_title' => 'short_text'
#];

//Register display_title as index
$wgHooks['SearchIndexFields'][] = function( array &$fields, SearchEngine $engine ) {
        #$engine->Xtes();
        if ( !( $engine instanceof CirrusSearch\CirrusSearch ) ) {
                return;
        }
        $fields['display_title'] = $engine->makeSearchFieldMapping(  'display_title', 'short_text' );
};

//rank display_title higher than title
$wgCirrusSearchWeights = [
        'title' => 20,
        'display_title' => 50,
        'redirect' => 15,
        'category' => 8,
        'heading' => 5,
        'opening_text' => 3,
        'text' => 1,
        'auxiliary_text' => 0.5,
        'file_text' => 0.5,
];
$wgCirrusSearchPrefixWeights = [
        'title' => 10,
        'display_title' => 30,
        'redirect' => 1,
        'title_asciifolding' => 7,
        'redirect_asciifolding' => 0.7,
];

//rebuild index with
/*
php /var/www/html/w/extensions/CirrusSearch/maintenance/UpdateSearchIndexConfig.php --startOver
php /var/www/html/w/extensions/CirrusSearch/maintenance/ForceSearchIndex.php
php /var/www/html/w/maintenance/runJobs.php
*/

//alternative SMWSearch
#$wgSearchType = 'SMWSearch';
#$smwgFallbackSearchType = function() {
#       return new CirrusSearch\CirrusSearch();
#};
// The two next parameters are recommended to highlight excerpts
#$smwgElasticsearchConfig['query']['highlight.fragment']['type'] = 'plain'; // or 'unified' or 'fvh'
#$smwgElasticsearchConfig['indexer']['raw.text'] = true;

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
$wgVisualEditorEnableVisualSectionEditing = true;
//Whether to enable VisualEditor for every new account. 
$wgVisualEditorAutoAccountEnable = true;
//Whether to enable the wikitext source mode inside VisualEditor. 
$wgVisualEditorEnableWikitext = true;
$wgDefaultUserOptions['visualeditor-newwikitext'] = 1;
//Whether to enable the visual diff function on the history special page. 
$wgVisualEditorEnableDiffPage = true;

wfLoadExtension( 'Math' );
#$wgMathValidModes[] = 'mathml';
#$wgDefaultUserOptions['math'] = 'mathml';
//use local cli. disable speech (config.prod.yaml) may improve performance
$wgMathoidCli = ['/usr/local/nodejs/mathoid/node_modules/mathoid/cli.js', '-c', '/usr/local/nodejs/mathoid/node_modules/mathoid/config.dev.yaml'];
// Raise MediaWiki's memory limit to 2*1.2G for mathoid.
$wgMaxShellMemory = 2*1228800;
wfLoadExtension( 'CodeMirror' );

############ Multimedia & Editors ############
## File formats
wfLoadExtension( 'NativeSvgHandler' );
wfLoadExtension( 'PagedTiffHandler' );
## Visual Editor
## Other Editors
wfLoadExtension( 'DrawioEditor' );
$wgDrawioEditorBackendUrl =  getenv( 'DRAWIO_SERVER' );
#wfLoadExtension( 'CognitiveProcessDesigner' );
wfLoadExtension( 'TimedMediaHandler' );
$wgFFmpegLocation = '/usr/bin/ffmpeg'; // Most common ffmpeg path on Linux
#$wgMaxShellMemory *= 4; //already increased by Extension:Math

######################### Page Forms ###################
wfLoadExtension( 'PageForms' );
#bsgPermissionConfig["multipageedit"] = ["type" => "global", "roles" => ["editor"]];
#$bsgPermissionConfig["viewedittab"] = ["type" => "global", "roles" => ["editor"]];
$wgPageFormsUseDisplayTitle = true;
$wgPageFormsSimpleUpload = true; #skip upload form
$smwgNamespacesWithSemanticLinks[106] = true; #PF_NS_FORM

############### Private Wiki ######################################
$wgGroupPermissions['*']['createaccount'] = false;
$wgGroupPermissions['*']['edit'] = false;
$wgGroupPermissions['*']['read'] = false;
$wgGroupPermissions['user']['writeapi'] = true;
$wgWhitelistRead[] = 'Main Page';
$wgWhitelistRead[] = 'Project:About';
$wgWhitelistRead[] = 'Project:Privacy_policy';
$wgWhitelistRead[] = 'Project:General_disclaimer';
$wgWhitelistRead[] = 'Project:Terms_of_Service'; #redirect to Project:Privacy_policy needed
$wgAllowExternalImages = true; #to use images on public main page
$wgAllowImageTag = true;
## access images over img_auth.php
$wgUploadPath = "$wgScriptPath/img_auth.php";


####################### Semantic Access Control ####################
wfLoadExtension( 'SemanticACL' );
###Partial Public Wiki ##
## https://github.com/simontaurus/mediawiki-extensions-SemanticACL/tree/feature_default_policy_only_users #
#$wgGroupPermissions['*']['read'] = true;
##cp /extensions/SemanticACL/img_auth_patched.php img_auth.php
#$wgImgAuthForceAuth = true; #force user validation also in 'public' wiki
#$wgPublicPagesCategory = 'PublicPages';
#$wgPublicImagesCategory = 'PublicFiles';
$wgGroupPermissions['user']['view-non-categorized-pages'] = true;
$wgGroupPermissions['user']['view-non-categorized-media'] = true;
#flow-bot is active during semantic data build (??) - therefore we need to grant him all rights
$wgGroupPermissions['flow-bot']['sacl-exempt'] = true;
$wgGroupPermissions['flow-bot']['view-non-categorized-pages'] = true;
$wgGroupPermissions['flow-bot']['view-non-categorized-media'] = true;
#in case of all pages default restricted we need explicite read permission for oauth
$wgWhitelistRead[] = 'Special:UserLogin';
$wgWhitelistRead[] = 'Special:RequestAccount';
$wgWhitelistRead[] = "Special:OAuth/initiate";
$wgWhitelistRead[] = "Special:OAuth/authorize";
$wgWhitelistRead[] = "Special:OAuth/token";
$wgWhitelistRead[] = "Special:OAuth/authenticate";
$wgWhitelistRead[] = "Special:OAuth/identify";

############## Uploads #####################
$wgEnableUploads  = getenv( 'MW_ENABLE_UPLOADS' );
$wgGroupPermissions['user']['reupload'] = true;
$wgGroupPermissions['user']['upload_by_url'] = true;
$wgAllowCopyUploads = true;
$wgCopyUploadsFromSpecialUpload = true;
$wgUploadSizeWarning = 2147483647; 
$wgMaxUploadSize = 2147483647; //allow max 2GB uploads

$wgFileExtensions = array( 'png', 'gif', 'jpg', 'jpeg', 'doc',
    'xls', 'csv', 'txt', 'json', 'mpp', 'pdf', 'ppt', 'tif', 'tiff', 'bmp', 'docx', 'xlsx',
    'pptx', 'ps', 'odt', 'ods', 'odp', 'odg', 'svg', 'mp4', 'mp3'
);

$wgUseImageMagick = true;
$wgImageMagickConvertCommand = "/usr/bin/convert";
$wgMaxImageArea = 200e6; //Creates thumbnails of images up to 100 Megapixels
$wgMaxShellFileSize = 102400*10;

####################### Bundled extensions #########################
wfLoadExtension( 'CategoryTree' );
wfLoadExtension( 'Cite' );
wfLoadExtension( 'CiteThisPage' );
wfLoadExtension( 'CodeEditor' );
$wgDefaultUserOptions['usebetatoolbar'] = 1; // user option provided by WikiEditor extension
#wfLoadExtension( 'ConfirmEdit' ); //not needed for private wiki
wfLoadExtension( 'Gadgets' );
wfLoadExtension( 'ImageMap' );
wfLoadExtension( 'InputBox' );
wfLoadExtension( 'Interwiki' );
$wgGroupPermissions['sysop']['interwiki'] = true; // To grant sysops permissions to edit interwiki data
#$wgEnableScaryTranscluding = true; //To enable transclusion from other sites
#wfLoadExtension( 'LocalisationUpdate' );
#$wgLocalisationUpdateDirectory = "$IP/cache";
wfLoadExtension( 'MultimediaViewer' );
$wgMediaViewerEnableByDefault = false; //to enable direct download of files
wfLoadExtension( 'Nuke' );
#wfLoadExtension( 'OATHAuth' );
wfLoadExtension( 'PageImages' );
wfLoadExtension( 'ParserFunctions' );
$wgPFEnableStringFunctions = true;
wfLoadExtension( 'PdfHandler' );
wfLoadExtension( 'Poem' );
wfLoadExtension( 'Renameuser' );
wfLoadExtension( 'ReplaceText' );
$wgGroupPermissions['bureaucrat']['replacetext'] = true;
wfLoadExtension( 'SecureLinkFixer' );
#wfLoadExtension( 'SpamBlacklist' ); //not needed for private wiki
wfLoadExtension( 'SyntaxHighlight_GeSHi' );
#$wgPygmentizePath = '/usr/bin/pygmentize';
wfLoadExtension( 'TemplateData' );
wfLoadExtension( 'TextExtracts' );
wfLoadExtension( 'TitleBlacklist' );
wfLoadExtension( 'WikiEditor' );


##### Non-bundled Core Extensions ####
wfLoadExtension( 'Variables' ); #requirement for SemanticActions
wfLoadExtension( 'MyVariables' ); #additional variables like USERLANGUAGECODE 
wfLoadExtension( 'Arrays' );
wfLoadExtension( 'WSArrays' );  
wfLoadExtension( 'Loops' );
#wfLoadExtension( 'ApprovedRevs' );
wfLoadExtension( 'UserMerge' ); //to merge and delete users
// By default nobody can use this function, enable for bureaucrat?
$wgGroupPermissions['bureaucrat']['usermerge'] = true;
wfLoadExtension( 'Thanks' );
wfLoadExtension( 'Echo' );
wfLoadExtension( 'BetaFeatures' );
wfLoadExtension( 'CookieWarning' );
$wgCookieWarningEnabled = true;
$wgCookieWarningMoreUrl = '/wiki/Project:Privacy_policy#Cookies';
#wfLoadExtension( 'CleanChanges' ); //no effect visible - already included
#$wgCCTrailerFilter = true;
#$wgCCUserFilter = false;
#$wgDefaultUserOptions['usenewrc'] = 1;
wfLoadExtension( 'UniversalLanguageSelector' );
wfLoadExtension( 'PDFEmbed' );
$wgPdfEmbed['width'] = 800; // Default width for the PDF object container.
$wgPdfEmbed['height'] = 1090; // Default height for the PDF object container.
$wgGroupPermissions['user']['embed_pdf'] = true; //Allow user the usage of the pdf tag (default

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

########## Linked Wiki ############
wfLoadExtension( 'LinkedWiki' );
#$wgLinkedWikiOSMAccessToken = ""; // => CustomSettings.php
#$wgLinkedWikiConfigSPARQLServices = .. // => CustomSettings.php
$wgHooks['BeforePageDisplay'][] = function( OutputPage &$out, Skin &$skin ) {
  $out->addInlineStyle("#ca-linkedwiki-purge { display: none;}"); #hide second "Purge" button next to "Refresh"
};

wfLoadExtension( 'UrlGetParameters' );
#require_once("$IP/extensions/UrlGetParameters/UrlGetParameters.php");

wfLoadExtension( 'PushAll' );
$egPushAllAttachedNamespaces[] = "Data";
$egPushAllAttachedNamespaces[] = "Discussion";
#wfLoadExtension( 'Push' );
#wfLoadExtension( 'Sync' ); #private config needed, breaks VE
#require_once("$IP/extensions/Sync/Sync.php");


########### Semantic Mediawiki ###############
#strip protocol from MW_SITE_SERVER
enableSemantics( preg_replace( "#^[^:/.]*[:/]+#i", "", getenv( 'MW_SITE_SERVER' ) ) );

#$smwgChangePropagationProtection = false; #temp fix to restore locked pages
$smwgQMaxSize = 50; #increase max query conditions, default 12
$smwgQMaxDepth = 20; #increase property chain query limit, default 4
$maxRecursionDepth = 5; #increase limit of nested templates in query results, default 2

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

#$smwgShowFactbox = SMW_FACTBOX_NONEMPTY; #Show factboxes only if they have some content
$smwgShowFactbox = SMW_FACTBOX_SHOWN; #Enable the factbox to be always shown - has no effect?

wfLoadExtension( 'SemanticResultFormats' );
$srfgFormats[] = 'graph';
$srfgFormats[] = 'process';
wfLoadExtension( 'Mermaid' );
$mermaidgDefaultTheme = 'dark';
$srfgFormats[] = 'gantt';
wfLoadExtension( 'ModernTimeline' );
wfLoadExtension( 'Maps' );
$egMapsDefaultService = 'leaflet';

wfLoadExtension( 'SemanticFormsSelect' );
wfLoadExtension( 'SemanticExtraSpecialProperties' );
wfLoadExtension( 'SemanticCompoundQueries' );
#wfLoadExtension( 'SemanticCite' );
##$GLOBALS['wgGroupPermissions']['user']['sci-metasearch'] = false;
wfLoadExtension( 'SemanticInterlanguageLinks' );
wfLoadExtension('PageImporter'); #import templates and forms for SemanticActions
#run once: php extensions/PageImporter/importPages.php
wfLoadExtension('SemanticActions');
$egSemanticActionsAssigneeValuesFrom = "User";

#Enable Semantic NS
$smwgNamespacesWithSemanticLinks[NS_MAIN] = true;
$smwgNamespacesWithSemanticLinks[NS_USER] = true;
$smwgNamespacesWithSemanticLinks[NS_PROJECT] = true;
$smwgNamespacesWithSemanticLinks[NS_FILE] = true;
$smwgNamespacesWithSemanticLinks[NS_TEMPLATE] = true;
$smwgNamespacesWithSemanticLinks[NS_HELP] = true;
$smwgNamespacesWithSemanticLinks[NS_CATEGORY] = true;
$smwgNamespacesWithSemanticLinks[SMW_NS_PROPERTY] = true;
$smwgNamespacesWithSemanticLinks[SMW_NS_SCHEMA] = true;
$smwgNamespacesWithSemanticLinks[SMW_NS_CONCEPT] = true;
$smwgNamespacesWithSemanticLinks[690] = true; #Action
$smwgNamespacesWithSemanticLinks[692] = true; #Label


############# Slots ############
wfLoadExtension( 'WSSlots' );
$wgWSSlotsDefaultSlotRoleLayout = [ 
	"display" => "none",
	"region" => "center",
	"placement" => "append"
];
$wgWSSlotsDefinedSlots = [
    "jsonschema"      => ["content_model" => "json", "slot_role_layout" => [ "region" => "footer", "display" => "details"]],
    "jsondata"        => ["content_model" => "json", "slot_role_layout" => [ "region" => "footer", "display" => "details"]],,
    "schema_template" => ["content_model" => "text", "slot_role_layout" => [ "display" => "none"]],
    "data_template"   => ["content_model" => "wikitext", "slot_role_layout" => [ "display" => "none"]],
    "header_template" => ["content_model" => "wikitext", "slot_role_layout" => [ "display" => "none"]],
    "footer_template" => ["content_model" => "wikitext", "slot_role_layout" => [ "display" => "none"]],
    "header" => [
        "content_model" => "wikitext",
        "slot_role_layout" => [
            "display" => "plain",
            "region" => "header",
            "placement" => "prepend"
        ]
    ],
    "footer" => [
        "content_model" => "wikitext",
        "slot_role_layout" => [
            "display" => "plain",
            "region" => "footer",
            "placement" => "prepend"
        ]
    ],
];
$wgWSSlotsSemanticSlots = [ "data_template", "header" ];
$wgWSSlotsDoPurge = true;
$wgWSSlotsOverrideActions = false;

############# Scribunto #############
wfLoadExtension( 'Scribunto' ); //bundled
$wgScribuntoDefaultEngine = 'luastandalone';
$wgScribuntoUseGeSHi = true;
$wgScribuntoUseCodeEditor = true;
wfLoadExtension( 'SemanticScribunto' );
wfLoadExtension( 'Capiunto' );
wfLoadExtension( 'VariablesLua' );


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

######################## UI  #############################
$wgNamespacesWithSubpages[NS_MAIN] = true;
wfLoadExtension( 'SemanticBreadcrumbLinks' );
$wgNamespacesWithSubpages[NS_TEMPLATE] = true; //NS Template
$smwgNamespacesWithSemanticLinks[NS_TEMPLATE] = true; //Needed for Subpage Navbar
wfLoadExtension( 'JSBreadCrumbs' );
wfLoadExtension( 'TreeAndMenu' );
wfLoadExtension( 'DisplayTitle' );
$wgAllowDisplayTitle = true;
$wgRestrictDisplayTitle = false;
wfLoadExtension( 'HeaderTabs' );
wfLoadExtension( 'MagicNoCache' );
wfLoadExtension( 'SimpleBatchUpload' );
wfLoadExtension( 'UploadWizard' );
#GuidedTours
wfLoadExtension( 'EventStreamConfig' );
wfLoadExtension( 'EventLogging' );
$wgEventLoggingBaseUri = '/beacon/event';
$wgEventLoggingSchemaApiUri = $wgServer . '/w/api.php';
$wgEventLoggingDBname = $wgDBname;
#wfLoadExtension( 'GuidedTour' );
#wfLoadExtension( 'GettingStarted' );
#wfLoadExtension( 'Iframe' );
wfLoadExtension( 'Reveal' );


####################### Auth ####################
## Manual Account request and confirmation
#require_once "$IP/extensions/ConfirmAccount/ConfirmAccount.php";
wfLoadExtension( 'ConfirmAccount' );

## Wiki as auth provider for other services (e.g. jupyterhub)
wfLoadExtension( 'OAuth' );
$wgGroupPermissions['sysop']['mwoauthproposeconsumer'] = true;
$wgGroupPermissions['sysop']['mwoauthupdateownconsumer'] = true;
$wgGroupPermissions['sysop']['mwoauthmanageconsumer'] = true;
$wgGroupPermissions['sysop']['mwoauthsuppress'] = true;
$wgGroupPermissions['sysop']['mwoauthviewsuppressed'] = true;
$wgGroupPermissions['sysop']['mwoauthviewprivate'] = true;
$wgGroupPermissions['sysop']['mwoauthmanagemygrants'] = true;
$wgWhitelistRead[] = "Special:OAuth";
$wgMWOAuthSecureTokenTransfer = false; #redirect loop bug

## Account management e. g. via Keycloak
#wfLoadExtension( 'PluggableAuth' );
$wgPluggableAuth_EnableAutoLogin = false; #Should login occur automatically when a user visits the wiki? 
$wgPluggableAuth_EnableLocalLogin = true; #Should user also be presented with username/password fields on the login page to allow local password-based login to the wiki? 
$wgPluggableAuth_EnableLocalProperties = true; #If true, users can edit their email address and real name on the wiki.
#$wgPluggableAuth_ButtonLabelMessage = "Msg"; #If set, the name of a message that will be used for the label of the login button on the Special:UserLogin form
$wgPluggableAuth_ButtonLabel = "Login"; #If $wgPluggableAuth_ButtonLabelMessage is not set and $wgPluggableAuth_ButtonLabel is set to a string value, this string value will be used as the label of the login button on the Special:UserLogin form.
#wfLoadExtension( 'OpenIDConnect' );
#$wgGroupPermissions['*']['createaccount'] = true; #for PluggableAuth
#$wgGroupPermissions['*']['autcreateaccount'] = true; #for PluggableAuth
#$wgHooks['BeforePageDisplay'][] = function( OutputPage &$out, Skin &$skin ) {
#  $out->addInlineStyle("#pt-createaccount { display: none;}"); #hides enables misleading "Create Account" link
#};
#wfLoadExtension( 'Realnames' );

####################### Custom Extensions ####################
wfLoadExtension( 'MwJson' );
wfLoadExtension( 'OpenSemanticLab' );
$wgExtraSignatureNamespaces = [7100]; #allow signatures in NS LabNote
wfLoadExtension( 'SemanticProperties' );
wfLoadExtension( 'WellplateEditor' );
wfLoadExtension( 'SvgEditor' );
wfLoadExtension( 'SpreadsheetEditor' );
wfLoadExtension( 'ChemEditor' );
wfLoadExtension( 'InteractiveSemanticGraph' );

####################### Custom Content #####################
wfLoadExtension( 'PageExchange' );
$wgPageExchangeFileDirectories[] = 'https://raw.githubusercontent.com/OpenSemanticLab/PagePackages/main/package_index.txt';