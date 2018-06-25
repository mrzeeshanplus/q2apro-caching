<?php

/*
	Plugin Name: q2apro-caching
	Plugin URI: https://github.com/q2apro/q2apro-caching
	Plugin Description: Cache plugin that caches each question in the file system.
	Plugin Version: 0.7
	Plugin Date: 2018-06-21
	Plugin Author: bndr + sama55 + stevenev + q2apro
	Plugin License: https://creativecommons.org/licenses/by-sa/3.0/legalcode
	Plugin Minimum Question2Answer Version: 1.7
*/

if (!defined('QA_VERSION'))
{ // don't allow this page to be requested directly from browser
	header('Location: ../../');
	exit;
}

//register a special module that resets the session flag if logging in
qa_register_plugin_module(
	'event', // type of module
	'qa-caching-event.php', // PHP file containing module class
	'qa_caching_session_reset_event', // module class name in that PHP file
	'q2a Caching Plugin Session Reset Event Handler' // human-readable name of module
);

if(isset($_SESSION['cache_use_off']))
{
	// no caching for anonymous users that posted something, see event that turns this status off		
	return;
}

/**
 * Register the plugin
 */
qa_register_plugin_module(
	'process', // type of module
	'qa-caching-main.php', // PHP file containing module class
	'qa_caching_main', // module class name in that PHP file
	'q2a Caching Plugin' // human-readable name of module
);
qa_register_plugin_module(
	'event', // type of module
	'qa-caching-event.php', // PHP file containing module class
	'qa_caching_event', // module class name in that PHP file
	'q2a Caching Plugin Event Handler' // human-readable name of module
);
qa_register_plugin_layer(
	'qa-caching-layer.php', // PHP file containing module class
	'q2a Caching Plugin Layer'
);
qa_register_plugin_overrides(
	'qa-caching-overrides.php' // PHP file containing overrided function
);
