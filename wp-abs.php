<?php

/**
 * Plugin Name: Add Browser Search
 * Plugin URI: https://github.com/wpbones/add-browser-search
 * Description: Publish your site's search as an OpenSearch description, so browsers offer to add it as a search engine and can search your site from the address bar.
 * Version: 2.0.0
 * Requires at least: 6.6
 * Requires PHP: 8.1
 * Author: Giovambattista Fazioli
 * Author URI: https://undolog.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: add-browser-search
 * Domain Path: languages
 *
 */

if (!defined('ABSPATH')) {
  exit();
}

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader for
| our application. We just need to utilize it! We'll simply require it
| into the script here so that we don't have to worry about manual
| loading any of our classes later on. It feels nice to relax.
|
*/

require_once __DIR__ . '/bootstrap/autoload.php';
