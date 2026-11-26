<?php

if (!defined('ABSPATH')) {
  exit();
}

/*
|--------------------------------------------------------------------------
| Plugin activation
|--------------------------------------------------------------------------
|
| Included when the plugin is activated: the settings are settled (the 1.x
| ones are moved in) and /opensearch.xml starts to exist.
|
*/

AddBrowserSearch\Migration::run();
AddBrowserSearch\Endpoint::activate();
