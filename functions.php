<?php

// return, not exit(): this file is in composer's autoload.files, which PHPUnit loads before WordPress
// exists, and an exit() there ends the run with status 0 and no output.
if (!defined('ABSPATH')) {
  return;
}

/*
|--------------------------------------------------------------------------
| Global functions
|--------------------------------------------------------------------------
|
| Loaded by Composer (autoload.files). Version 1.x defined the constants below;
| they are kept so that code written against it keeps working.
|
*/

if (!defined('OPENSEARCH_XML')) {
  define('OPENSEARCH_XML', 'opensearch.xml');
}

if (!defined('WPABS_VERSION')) {
  define('WPABS_VERSION', '2.0.1');
}

if (!function_exists('add_browser_search_url')) {
  /**
   * The address of the OpenSearch description of this site (`/opensearch.xml`, or the plain
   * query-string address when the site has no pretty permalinks).
   */
  function add_browser_search_url(): string
  {
    return AddBrowserSearch\Urls::description();
  }
}
