<?php

if (!defined('ABSPATH')) {
  exit();
}

/*
|--------------------------------------------------------------------------
| Plugin deactivation
|--------------------------------------------------------------------------
|
| Included when the plugin is deactivated: /opensearch.xml stops existing.
| The settings stay; uninstall.php removes them when the plugin is deleted.
|
*/

AddBrowserSearch\Endpoint::deactivate();
