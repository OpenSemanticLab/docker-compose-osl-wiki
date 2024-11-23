# Protect against web entry
if ( !defined( 'MEDIAWIKI' ) ) {
    exit;
}

#Debug Settings
if ( getenv( 'MW_SHOW_EXCEPTION_DETAILS' ) === 'true' ) {
#    error_reporting( -1 );
#    ini_set( 'display_errors', 1 );
#    $wgShowExceptionDetails = true;
}

########################### Core Settings ##########################
#local time zone
# we have to use "UTC" here, otherwise SMW stores time values with reference to the local time zone
# see also: https://www.semantic-mediawiki.org/wiki/Help:Type_Date
$wgLocaltimezone = "UTC";
#$wgLocaltimezone = getenv( 'MW_TIME_ZONE' );
# instead we store the timezone param as default user timezone setting
# the offset is dynamically calculated, e.g. 'ZoneInfo|120|Europe/Berlin';
date_default_timezone_set('UTC');
$wgDefaultUserOptions['timecorrection'] = 'ZoneInfo|' 
    . timezone_offset_get(
        new DateTimeZone( getenv( 'MW_TIME_ZONE' ) ), 
        new DateTime( 'now', new DateTimeZone( getenv( 'MW_TIME_ZONE' ) ) )
    ) / 60
    . '|' . getenv( 'MW_TIME_ZONE' );

# Site language code, should be one of the list in ./languages/Names.php
# we have to use 'en' here for technical reasons (namespace and smw property names)
$wgLanguageCode = 'en';
# instead, we set the lang param as default user interface lang
# see also: https://www.mediawiki.org/wiki/Manual:$wgDefaultUserOptions
$wgDefaultUserOptions['language'] = getenv( 'MW_SITE_LANG' );

# we have to override the options loading to apply our defaults
# https://www.mediawiki.org/wiki/Manual_talk:$wgDefaultUserOptions#Setting_$wgDefaultUserOptions['language']_=_'de';_fails
$wgHooks['LoadUserOptions'][] = function( $user, array &$options ) use ($wgDefaultUserOptions) {
    # lookup explicite user settings
    $dbr = wfGetDB( DB_MASTER );
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

    # apply default timezone if not set
    if (!array_key_exists('timecorrection', $data)) $options['timecorrection'] = $wgDefaultUserOptions['timecorrection'];

    # apply default language if not set
    //if (!array_key_exists('language', $data)) $options['language'] = $wgDefaultUserOptions['language']; // does not work with Extension:ULS, prevents changing the language via settings
};

#$wgHooks['UserGetLanguageObject'][] = function( $user, &$code ) {
#    $code =  $user->getOption( 'language' );
#};

## The protocol and server name to use in fully-qualified URLs => set in Custom settings
$wgServer = getenv( 'MW_SITE_SERVER' );

# The name of the site. This is the name of the site as displayed throughout the site.
$wgSitename = getenv( 'MW_SITE_NAME' );

# Default skin: you can change the default skin. Use the internal symbolic
# names, ie 'standard', 'nostalgia', 'cologneblue', 'monobook', 'vector', 'chameleon':
wfLoadExtension( 'Bootstrap' );
$wgDefaultSkin = getenv( 'MW_DEFAULT_SKIN' );
$wgCitizenTableNowrapClasses[] = 'info_box'; # disable wrapping of info_box tables
$wgCitizenSearchGateway = "smwAskApi";
$wgCitizenSearchSmwApiAction = "compoundquery";
$wgCitizenSearchSmwAskApiQueryTemplate = "
[[HasNormalizedLabel::\${input_normalized}]][[HasOswId::!~*#*]];?HasLabel=displaytitle;?HasImage=thumbnail;?HasDescription=desc;limit=1
|[[HasNormalizedLabel::~*\${input_normalized_tokenized}*]][[HasOswId::!~*#*]];?HasLabel=displaytitle;?HasImage=thumbnail;?HasDescription=desc;limit=7
";

# InstantCommons allows wiki to use images from http://commons.wikimedia.org
$wgUseInstantCommons = getenv( 'MW_USE_INSTANT_COMMONS' );

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

#The path of the temporary directory. see https://www.mediawiki.org/wiki/Manual:$wgTmpDirectory
$wgTmpDirectory = $IP . '/images/temp';
$wgCacheDirectory = $IP . '/cache';

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
## Other Editors
wfLoadExtension( 'DrawioEditor' );
$wgDrawioEditorBackendUrl =  getenv( 'DRAWIO_SERVER' );

######################### Page Forms ###################
wfLoadExtension( 'PageForms' );

############## Uploads #####################
$wgEnableUploads  = getenv( 'MW_ENABLE_UPLOADS' );

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
wfLoadExtension( 'Renameuser' );
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

########### Semantic Mediawiki ###############
wfLoadExtension( 'SemanticMediaWiki' );
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
#$smwgShowFactbox = SMW_FACTBOX_SHOWN; #Enable the factbox to be always shown - has no effect?
$smwgShowFactbox = SMW_FACTBOX_HIDDEN; #Never show it

wfLoadExtension( 'SemanticResultFormats' );
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

########### CommentStreams ###############
# wfLoadExtension( 'CommentStreams' ); # not enabled by default
# $wgCommentStreamsEnableVoting = true;
# $wgCommentStreamsSuppressLogsFromRCs = false;
# $wgCommentStreamsAllowedNamespaces = []; # not enable by default in any namespace

######################## UI  #############################
wfLoadExtension( 'DisplayTitle' );
$wgAllowDisplayTitle = true;
$wgRestrictDisplayTitle = false;
wfLoadExtension( 'SimpleBatchUpload' );

####################### Custom Extensions ####################
wfLoadExtension( 'FileApi' );
wfLoadExtension( 'MwJson' );
$wgMwJsonSlotRenderResultTransformation = [
    "enabled" => true,
];
wfLoadExtension( 'OpenSemanticLab' );
$wgExtraSignatureNamespaces = [7100]; #allow signatures in NS LabNote
wfLoadExtension( 'SemanticProperties' );
wfLoadExtension( 'WellplateEditor' );
wfLoadExtension( 'SvgEditor' );
wfLoadExtension( 'SpreadsheetEditor' );
wfLoadExtension( 'ChemEditor' );
wfLoadExtension( 'InteractiveSemanticGraph' );
wfLoadExtension( 'InteractiveSemanticGraph2' );
wfLoadExtension( 'SciFileHandler' );
$wgFileExtensions = array_merge($wgFileExtensions, array(
    'hdf', 'h4', 'hdf4', 'he2', 'h5', 'hdf5', 'he5', # HDF File format
    'dx', 'jdx', 'jcm', # JCAMP-DX
    'mpr', 'mps', 'mpt', # Biologic
));
#wfLoadExtension( 'Chatbot' );

####################### Custom Content #####################
wfLoadExtension( 'PageExchange' );
$wgPageExchangeFileDirectories[] = 'https://raw.githubusercontent.com/OpenSemanticLab/PagePackages/main/package_index.txt';

$wgPageImagesNamespaces[] = 7000;
$wgPageImagesNamespaces[] = NS_CATEGORY;
$sespgEnabledPropertyList = [
    '_PAGEIMG',
];

$wgCitizenTableNowrapClasses[] = 'layout-table'; # disable wrapping of layout-table tables