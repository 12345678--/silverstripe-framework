<?php

/**
 * Main file that handles every page request.
 *
 * The main.php does a number of set-up activities for the request.
 * 
 *  - Includes the first one of the following files that it finds: (root)/_ss_environment.php, 
 *    (root)/../_ss_environment.php, or (root)/../../_ss_environment.php
 *  - Gets an up-to-date manifest from {@link ManifestBuilder}
 *  - Sets up error handlers with {@link Debug::loadErrorHandlers()}
 *  - Calls {@link DB::connect()}, passing it the global variable $databaseConfig that should 
 &    be defined in an _config.php
 *  - Sets up the default director rules using {@link Director::addRules()}
 * 
 * After that, it calls {@link Director::direct()}, which is responsible for doing most of the 
 * real work.
 *
 * Finally, main.php will use {@link Profiler} to show a profile if the querystring variable 
 * "debug_profile" is set.
 *
 * CONFIGURING THE WEBSERVER
 *
 * To use Sapphire, every request that doesn't point directly to a file should be rewritten to
 * sapphire/main.php?url=(url).  For example, http://www.example.com/about-us/rss would be rewritten 
 * to http://www.example.com/sapphire/main.php?url=about-us/rss
 *
 * It's important that requests that point directly to a file aren't rewritten; otherwise, visitors 
 * won't be able to download any CSS, JS, image files, or other downloads.
 *
 * On Apache, RewriteEngine can be used to do this.
 *
 * @package sapphire
 * @subpackage core
 * @see Director::direct()
 */


/**
 * Include Sapphire's core code
 */
require_once("core/Core.php");

header("Content-type: text/html; charset=\"utf-8\"");
if (function_exists('mb_http_output')) {
	mb_http_output('UTF-8');
	mb_internal_encoding('UTF-8');
}

Session::start();

// Apache rewrite rules use this
if (isset($_GET['url'])) {
	$url = $_GET['url'];
	// IIS includes get variables in url
	$i = strpos($url, '?');
	if($i !== false) {
		$url = substr($url, 0, $i);
	}
	
// Lighttpd uses this
} else {
	list($url, $query) = explode('?', $_SERVER['REQUEST_URI'], 2);
	parse_str($query, $_GET);
	if ($_GET) $_REQUEST = array_merge((array)$_REQUEST, (array)$_GET);
}

// Fix glitches in URL generation
if (substr($url, 0, strlen(BASE_URL)) == BASE_URL) $url = substr($url, strlen(BASE_URL));


if (isset($_GET['debug_profile'])) {
	Profiler::init();
	Profiler::mark('all_execution');
	Profiler::mark('main.php init');
}

// Connect to database
require_once("core/model/DB.php");

if (isset($_GET['debug_profile'])) Profiler::mark('DB::connect');
DB::connect($databaseConfig);
if (isset($_GET['debug_profile'])) Profiler::unmark('DB::connect');

if (isset($_GET['debug_profile'])) Profiler::unmark('main.php init');

// Direct away - this is the "main" function, that hands control to the appropriate controller
Director::direct($url);

if (isset($_GET['debug_profile'])) {
	Profiler::unmark('all_execution');
	Profiler::show(isset($_GET['profile_trace']));
}
