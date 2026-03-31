# Protect against web entry
if ( !defined( 'MEDIAWIKI' ) ) {
    exit;
}

#Debug Settings
if ( (getenv( 'MW_SHOW_EXCEPTION_DETAILS', true ) ?: getenv( 'MW_SHOW_EXCEPTION_DETAILS' )) === 'true' ) {
#    error_reporting( -1 );
#    ini_set( 'display_errors', 1 );
    $wgShowExceptionDetails = true;
}

########################### Core Settings ##########################

# Increase PHP memory limit (default 128/150MB is too low for SMW + CirrusSearch indexing)
ini_set( 'memory_limit', '512M' );

# Robot / crawler policy — add noindex meta tags as second layer (robots.txt is first)
$wgDefaultRobotPolicy = 'index,follow';
$wgNamespaceRobotPolicies = [
    NS_SPECIAL => 'noindex,nofollow',
    NS_TALK => 'noindex,nofollow',
    NS_USER => 'noindex,nofollow',
    NS_USER_TALK => 'noindex,nofollow',
];

#local time zone
# we have to use "UTC" here, otherwise SMW stores time values with reference to the local time zone
# see also: https://www.semantic-mediawiki.org/wiki/Help:Type_Date
$wgLocaltimezone = "UTC";
#$wgLocaltimezone = getenv( 'MW_TIME_ZONE', true ) ?: getenv( 'MW_TIME_ZONE' );
# instead we store the timezone param as default user timezone setting
# the offset is dynamically calculated, e.g. 'ZoneInfo|120|Europe/Berlin';
/*date_default_timezone_set('UTC');
$wgDefaultUserOptions['timecorrection'] = 'ZoneInfo|' 
    . timezone_offset_get(
    new DateTimeZone( getenv( 'MW_TIME_ZONE', true ) ?: getenv( 'MW_TIME_ZONE' ) ), 
    new DateTime( 'now', new DateTimeZone( getenv( 'MW_TIME_ZONE', true ) ?: getenv( 'MW_TIME_ZONE' ) ) )
    ) / 60
    . '|' . (getenv( 'MW_TIME_ZONE', true ) ?: getenv( 'MW_TIME_ZONE' ));*/

# Site language code, should be one of the list in ./languages/Names.php
# we have to use 'en' here for technical reasons (namespace and smw property names)
$wgLanguageCode = 'en';
# instead, we set the lang param as default user interface lang
# see also: https://www.mediawiki.org/wiki/Manual:$wgDefaultUserOptions
$wgDefaultUserOptions['language'] = getenv( 'MW_SITE_LANG', true ) ?: getenv( 'MW_SITE_LANG' );

# we have to override the options loading to apply our defaults
# https://www.mediawiki.org/wiki/Manual_talk:$wgDefaultUserOptions#Setting_$wgDefaultUserOptions['language']_=_'de';_fails
$wgHooks['LoadUserOptions'][] = function( $user, array &$options ) use ($wgDefaultUserOptions) {
    # lookup explicite user settings
    $dbr = wfGetDB( DB_PRIMARY );
    $res = $dbr->select(
        'user_properties',
        [ 'up_property', 'up_value' ],
        [ 'up_user' => $user->getId() ],
    );
    $data = [];
    foreach ( $res as $row ) {
        if ( $row->up_value === '0' ) {
            $row->up_value = 0;
        }
        $data[$row->up_property] = $row->up_value;
    }

    # apply default timezone if not set or if stored value is empty/invalid
    if (!array_key_exists('timecorrection', $data) || !is_string($data['timecorrection']) || $data['timecorrection'] === '') $options['timecorrection'] = $wgDefaultUserOptions['timecorrection'] ?? 'System|0';

    # apply default language if not set
    //if (!array_key_exists('language', $data)) $options['language'] = $wgDefaultUserOptions['language']; // does not work with Extension:ULS, prevents changing the language via settings
};

#$wgHooks['UserGetLanguageObject'][] = function( $user, &$code ) {
#    $code =  $user->getOption( 'language' );
#};

## The protocol and server name to use in fully-qualified URLs => set in Custom settings
$wgServer = getenv( 'MW_SITE_SERVER', true ) ?: getenv( 'MW_SITE_SERVER' );

# The name of the site. This is the name of the site as displayed throughout the site.
$wgSitename = getenv( 'MW_SITE_NAME', true ) ?: getenv( 'MW_SITE_NAME' );

# Default skin: you can change the default skin. Use the internal symbolic
# names, ie 'standard', 'nostalgia', 'cologneblue', 'monobook', 'vector', 'chameleon':
wfLoadExtension( 'Bootstrap' );
$wgDefaultSkin = getenv( 'MW_DEFAULT_SKIN', true ) ?: getenv( 'MW_DEFAULT_SKIN' );
$wgCitizenTableNowrapClasses[] = 'info_box'; # disable wrapping of info_box tables

# Citizen SMW Search Configuration
# ---------------------------------
# SearchGateway: which search backend the Citizen skin uses for suggestions.
#   "mwRestApi"  — (default) MediaWiki REST API (standard full-text search)
#   "mwActionApi" — MediaWiki Action API
#   "smwAskApi"  — Semantic MediaWiki Ask API (requires SMW)
# When set to "smwAskApi", typing in the search bar queries SMW directly.
# For Citizen > 3.0: Regardless of this setting, users can always type "/ask <query>" in the
# command palette to trigger an SMW search on-demand.
$wgCitizenSearchGateway = "smwAskApi";

# SearchSmwApiAction: the SMW API action to use.
#   "ask"           — (default) standard SMW ask query
#   "compoundquery"  — multiple sub-queries (requires Extension:SemanticCompoundQueries)
$wgCitizenSearchSmwApiAction = "compoundquery";

# SearchSmwAskApiQueryTemplate: the SMW query template with variable substitution.
# Available variables:
#   ${input}                       — raw user input
#   ${input_lowercase}             — lowercased input
#   ${input_normalized}            — lowercased, non-alphanumeric chars removed
#   ${input_normalized_tokenized}  — each word tokenized into separate conditions
# Printout aliases (mapped to result fields):
#   displaytitle — shown as the result label (multi-lang aware)
#   thumbnail    — result thumbnail image (via Special:Redirect/file/)
#   desc         — result description text (multi-lang aware)
#   type         — appended as suffix to the label, e.g. "Title (Category)"
# UUID detection: pasting a UUID auto-searches via [[HasUuid::<uuid>]]
# Namespace filtering: typing "Category:term" adds [[:Category:+]] condition
$wgCitizenSearchSmwAskApiQueryTemplate = "
[[HasNormalizedLabel::\${input_normalized}]][[HasOswId::!~*#*]];?HasLabel=displaytitle;?HasImage=thumbnail;?HasDescription=desc;limit=1
|[[HasNormalizedLabel::~*\${input_normalized_tokenized}*]][[HasOswId::!~*#*]];?HasLabel=displaytitle;?HasImage=thumbnail;?HasDescription=desc;limit=7
";

# InstantCommons allows wiki to use images from http://commons.wikimedia.org
$wgUseInstantCommons = getenv( 'MW_USE_INSTANT_COMMONS', true ) ?: getenv( 'MW_USE_INSTANT_COMMONS' );

# Name used for the project namespace. The name of the meta namespace (also known as the project namespace), used for pages regarding the wiki itself.
#$wgMetaNamespace = 'Site'; #just an alias. does not work at all of canonical namespace 'project' is created / used by an extension
#$wgMetaNamespaceTalk = 'Site_talk';

# The relative URL path to the logo. Make sure you change this from the default,
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
switch ( getenv( 'MW_MAIN_CACHE_TYPE', true ) ?: getenv( 'MW_MAIN_CACHE_TYPE' ) ) {
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
        $wgMemCachedServers = explode( ',', getenv( 'MW_MEMCACHED_SERVERS', true ) ?: getenv( 'MW_MEMCACHED_SERVERS' ) );
        $wgSessionsInObjectCache = true; # optional
        $wgSessionCacheType = CACHE_MEMCACHED; # optional
        break;
    default:
        $wgMainCacheType = CACHE_NONE;
}

#The path of the temporary directory. see https://www.mediawiki.org/wiki/Manual:$wgTmpDirectory
$wgTmpDirectory = $IP . '/images/temp';
$wgCacheDirectory = $IP . '/cache';

########################### Search ############################
wfLoadExtension( 'Elastica' );
wfLoadExtension( 'CirrusSearch' );
$wgCirrusSearchServers =  explode( ',', getenv( 'MW_CIRRUS_SEARCH_SERVERS', true ) ?: getenv( 'MW_CIRRUS_SEARCH_SERVERS' ) );
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

//allow fuzzy search and "do you mean" suggestions
//see also https://www.mediawiki.org/w/index.php?title=Topic:Wj3av65bti5a8v7o&topic_showPostId=wj6z0ty2ut72b3hw#flow-post-wj6z0ty2ut72b3hw
$wgCirrusSearchPhraseSuggestUseText = true;


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

wfLoadExtension( 'Math' ); # bundled in REL1_39
wfLoadExtension( 'CodeMirror' );

############ Multimedia & Editors ############
## File formats
wfLoadExtension( 'NativeSvgHandler' );
#wfLoadExtension( 'PagedTiffHandler' ); // not enabled by default
## Other Editors
wfLoadExtension( 'DrawioEditor' );
$wgDrawioEditorBackendUrl =  getenv( 'DRAWIO_SERVER', true ) ?: getenv( 'DRAWIO_SERVER' );
# Allow additional SVG namespaces (e.g. xhtml used by DrawIO exports)
# Can be extended in CustomSettings.php: $wgAllowedAdditionalSvgNamespaces[] = 'http://example.com/ns';
$wgAllowedAdditionalSvgNamespaces = [ 'http://www.w3.org/1999/xhtml' ];
wfLoadExtension( 'TimedMediaHandler' );
$wgFFmpegLocation = '/usr/bin/ffmpeg'; // Most common ffmpeg path on Linux
$wgMaxShellMemory = 2*1228800;
wfLoadExtension( 'EmbedVideo' );
$wgEmbedVideoFetchExternalThumbnails = false; #true will fetch external images before user consent
wfLoadExtension( '3DAlloy' ); #3D Files
#wfLoadExtension( 'WebDAV' ); // not enabled by default
$wgWebDAVAuthType = 'token';
$wgWebDAVInvalidateTokenOnUnlock = false; // MS Excel does a lock-unlock cycle right at the beginning

######################### Page Forms ###################
wfLoadExtension( 'PageForms' );
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
# $wgAllowImageTag = true;
## access images over img_auth.php
$wgUploadPath = "$wgScriptPath/img_auth.php";

####################### Semantic Access Control ####################
wfLoadExtension( 'SemanticACL' );
#wfLoadExtension( 'Lockdown' ); // not enabled by default

############## Uploads #####################
$wgEnableUploads  = getenv( 'MW_ENABLE_UPLOADS', true ) ?: getenv( 'MW_ENABLE_UPLOADS' );
$wgGroupPermissions['user']['reupload'] = true;
$wgGroupPermissions['user']['upload_by_url'] = true;
$wgAllowCopyUploads = true;
$wgCopyUploadsFromSpecialUpload = true;
$wgUploadSizeWarning = 2147483647; 
$wgMaxUploadSize = 2147483647; //allow max 2GB uploads

$wgFileExtensions = array( 'png', 'gif', 'jpg', 'jpeg', 'doc',
    'xls', 'csv', 'txt', 'json', 'mpp', 'pdf', 'ppt', 'tif', 'tiff', 'bmp', 'docx', 'xlsx',
    'pptx', 'ps', 'odt', 'ods', 'odp', 'odg', 'svg', 'mp4', 'mp3',
    'hdf', 'h4', 'hdf4', 'he2', 'h5', 'hdf5', 'he5', # HDF File format
);
# 3D Files
$wgFileExtensions = array_merge( $wgFileExtensions, array(
      'json', '3dj', '3djson', 'three',
      'buff', 'buffjson',
      'obj',
      'stl', 'stlb'
) );

$wgUseImageMagick = true;
$wgImageMagickConvertCommand = "/usr/bin/convert";
$wgMaxImageArea = 200e6; //Creates thumbnails of images up to 100 Megapixels
$wgMaxShellFileSize = 102400*10;

####################### Bundled extensions #########################
#wfLoadExtension( 'AbuseFilter' );
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
#wfLoadExtension( 'Math' ); # bundled in REL1_39 but customized
wfLoadExtension( 'MultimediaViewer' );
$wgMediaViewerEnableByDefault = false; //to enable direct download of files
wfLoadExtension( 'Nuke' );
#wfLoadExtension( 'OATHAuth' );
wfLoadExtension( 'PageImages' );
wfLoadExtension( 'ParserFunctions' );
$wgPFEnableStringFunctions = true;
wfLoadExtension( 'PdfHandler' );
wfLoadExtension( 'Poem' );
wfLoadExtension( 'ReplaceText' );
$wgGroupPermissions['bureaucrat']['replacetext'] = true;
#wfLoadExtension( 'Scribunto' ); # bundled in REL1_39 but customized
wfLoadExtension( 'SecureLinkFixer' );
#wfLoadExtension( 'SpamBlacklist' ); //not needed for private wiki
wfLoadExtension( 'SyntaxHighlight_GeSHi' );
#$wgPygmentizePath = '/usr/bin/pygmentize';
wfLoadExtension( 'TemplateData' );
wfLoadExtension( 'TextExtracts' );
wfLoadExtension( 'TitleBlacklist' );
#wfLoadExtension( 'VisualEditor' ); # bundled in REL1_39 but customized
wfLoadExtension( 'WikiEditor' );


##### Non-bundled Core Extensions ####
wfLoadExtension( 'MyVariables' ); #additional variables like USERLANGUAGECODE
$wgUseRCPatrol = false; // not enabled by default
#wfLoadExtension( 'ApprovedRevs' ); // not enabled by default
wfLoadExtension( 'UserMerge' ); //to merge and delete users
#wfLoadExtension( 'HitCounters' ); // not enabled by default
// By default nobody can use this function, enable for bureaucrat?
$wgGroupPermissions['bureaucrat']['usermerge'] = true;
wfLoadExtension( 'Thanks' );
wfLoadExtension( 'Echo' );
wfLoadExtension( 'BetaFeatures' );
wfLoadExtension( 'CookieWarning' );
$wgCookieWarningEnabled = true;
$wgCookieWarningMoreUrl = '/wiki/Project:Privacy_policy#Cookies';
wfLoadExtension( 'PDFEmbed' );
$wgPdfEmbed['width'] = 800; // Default width for the PDF object container.
$wgPdfEmbed['height'] = 1090; // Default height for the PDF object container.
$wgGroupPermissions['user']['embed_pdf'] = true; //Allow user the usage of the pdf tag

########## Linked Wiki ############
# wfLoadExtension( 'UrlGetParameters' ); // not enabled by default

########## External Data ############
wfLoadExtension( 'ExternalData' );

########### Semantic Mediawiki ###############
wfLoadExtension( 'SemanticMediaWiki' );
#strip protocol from MW_SITE_SERVER
enableSemantics( preg_replace( "#^[^:/.]*[:/]+#i", "", getenv( 'MW_SITE_SERVER', true ) ?: getenv( 'MW_SITE_SERVER' ) ) );

#$smwgChangePropagationProtection = false; #temp fix to restore locked pages
$smwgQMaxSize = 50; #increase max query conditions, default 12
$smwgQMaxDepth = 20; #increase property chain query limit, default 4
$maxRecursionDepth = 5; #increase limit of nested templates in query results, default 2

$smwgDefaultStore = 'SMW\SPARQLStore\SPARQLStore';
$smwgSparqlRepositoryConnector = 'blazegraph';
$smwgSparqlEndpoint["query"] = 'http://graphdb:9999/blazegraph/namespace/kb/sparql';
$smwgSparqlEndpoint["update"] = 'http://graphdb:9999/blazegraph/namespace/kb/sparql';
$smwgSparqlEndpoint["data"] = '';

# Optional name of default graph
$smwgSparqlDefaultGraph = (getenv( 'MW_SITE_SERVER', true ) ?: getenv( 'MW_SITE_SERVER' )) . '/id/';
# Namespace for export
$smwgNamespace =  (getenv( 'MW_SITE_SERVER', true ) ?: getenv( 'MW_SITE_SERVER' )) . '/id/';
#needs rebuild: php /var/www/html/w/extensions/SemanticMediaWiki/maintenance/rebuildData.php

#$smwgShowFactbox = SMW_FACTBOX_NONEMPTY; #Show factboxes only if they have some content
#$smwgShowFactbox = SMW_FACTBOX_SHOWN; #Enable the factbox to be always shown - has no effect?
$smwgShowFactbox = SMW_FACTBOX_HIDDEN; #Never show it

wfLoadExtension( 'SemanticResultFormats' );
wfLoadExtension( 'Mermaid' );
$mermaidgDefaultTheme = 'dark';
$srfgFormats[] = 'gantt';
# ModernTimeline removed: incompatible with SMW 6.x (SMWQueryResult class removed)
# wfLoadExtension( 'ModernTimeline' );
wfLoadExtension( 'Maps' );
$egMapsDefaultService = 'leaflet';
wfLoadExtension( 'SemanticCompoundQueries' );

#Enable Semantic NS
$smwgNamespacesWithSemanticLinks[NS_MAIN] = true;
$smwgNamespacesWithSemanticLinks[NS_USER] = true;
$smwgNamespacesWithSemanticLinks[NS_PROJECT] = true;
$smwgNamespacesWithSemanticLinks[NS_FILE] = true;
$smwgNamespacesWithSemanticLinks[NS_TEMPLATE] = true;
$smwgNamespacesWithSemanticLinks[NS_HELP] = true;
$smwgNamespacesWithSemanticLinks[NS_CATEGORY] = true;
#some NS need hardcoded IDs, see https://github.com/SemanticMediaWiki/SemanticMediaWiki/issues/5281
$smwgNamespacesWithSemanticLinks[102] = true; # SMW_NS_PROPERTY
$smwgNamespacesWithSemanticLinks[114] = true; # SMW_NS_SCHEMA
$smwgNamespacesWithSemanticLinks[108] = true; # SMW_NS_CONCEPT

# Register additional namespaces as content namespaces (needed for slot transformation, search, etc.)
# NS_CATEGORY, Item (7000) and others are set by Extension:OpenSemanticLab
$wgContentNamespaces[] = NS_FILE;
$wgContentNamespaces[] = 102; # SMW_NS_PROPERTY
$wgContentNamespaces[] = 114; # SMW_NS_SCHEMA
$wgContentNamespaces[] = 108; # SMW_NS_CONCEPT

# extra properties for e.g. for approval status
# see https://github.com/SemanticMediaWiki/SemanticExtraSpecialProperties/blob/master/docs/configuration.md
#wfLoadExtension( 'SemanticExtraSpecialProperties' );

############# Slots ############
wfLoadExtension( 'WSSlots' );
$wgWSSlotsDefaultSlotRoleLayout = [ 
	"display" => "none",
	"region" => "center",
	"placement" => "append"
];
$wgWSSlotsDefinedSlots = [
    "jsonschema"      => ["content_model" => "json", "slot_role_layout" => [ "region" => "footer", "display" => "details"]],
    "jsondata"        => ["content_model" => "json", "slot_role_layout" => [ "region" => "footer", "display" => "details"]],
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

# Set lua binaries to apt installed Lua5.1
$wgScribuntoEngineConf['luastandalone']['luaPath'] = '/usr/bin/lua';

########### CommentStreams ###############
# wfLoadExtension( 'CommentStreams' ); # not enabled by default
# $wgCommentStreamsEnableVoting = true;
# $wgCommentStreamsSuppressLogsFromRCs = false;
# $wgCommentStreamsAllowedNamespaces = []; # not enable by default in any namespace

######################## UI  #############################

wfLoadExtension( 'TreeAndMenu' );
wfLoadExtension( 'DisplayTitle' );
$wgAllowDisplayTitle = true;
$wgRestrictDisplayTitle = false;
wfLoadExtension( 'SimpleBatchUpload' );
#wfLoadExtension( 'Iframe' ); // not enabled by default
wfLoadExtension( 'Reveal' );
wfLoadExtension( 'WikiMarkdown' );
$wgAllowMarkdownExtra = true; // allows usage of Parsedown Extra
$wgAllowMarkdownExtended = true; // allows usage of Parsedown Extended

####################### Auth ####################
## Manual Account request and confirmation
#wfLoadExtension( 'ConfirmAccount' ); // not enabled by default

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
wfLoadExtension( 'FileApi' );
wfLoadExtension( 'MwJson' );
$wgMwJsonSlotRenderResultTransformation = [
    "enabled" => true,
];
wfLoadExtension( 'OpenSemanticLab' );
// $wgExtraSignatureNamespaces = [7100]; #allow signatures in NS LabNote
wfLoadExtension( 'WellplateEditor' );
wfLoadExtension( 'SvgEditor' );
wfLoadExtension( 'SpreadsheetEditor' );
wfLoadExtension( 'ChemEditor' );
wfLoadExtension( 'InteractiveSemanticGraph' );
// wfLoadExtension( 'InteractiveSemanticGraph2' );
wfLoadExtension( 'SciFileHandler' );
$wgFileExtensions = array_merge($wgFileExtensions, array(
    'hdf', 'h4', 'hdf4', 'he2', 'h5', 'hdf5', 'he5', # HDF File format
    'dx', 'jdx', 'jcm', # JCAMP-DX
    'mpr', 'mps', 'mpt', # Biologic
));
#wfLoadExtension( 'Chatbot' );
#wfLoadExtension( 'RdfExport' ); # not enabled by default
#wfLoadExtension( 'ApiGateway' );

####################### Custom Content #####################
wfLoadExtension( 'PageExchange' );
$wgPageExchangeFileDirectories[] = 'https://raw.githubusercontent.com/OpenSemanticLab/PagePackages/main/package_index.txt';
$wgPageExchangeMaxDisplayedPages = 0; # 0=hide page diffs, false=show all, N=show first N

$wgPageImagesNamespaces[] = 7000;
$wgPageImagesNamespaces[] = NS_CATEGORY;
$sespgEnabledPropertyList = [
    '_PAGEIMG',
];

$wgCitizenTableNowrapClasses[] = 'layout-table'; # disable wrapping of layout-table tables