<?php

if (!defined('ABSPATH')) {
  exit();
}

/*
|--------------------------------------------------------------------------
| Plugin updated
|--------------------------------------------------------------------------
|
| Included when the plugin is updated. Nothing to do here: the 1.x settings
| are moved in by AddBrowserSearch\Migration, which runs on the first load that
| finds no 2.0 settings, however the new files arrived. The rewrite rules are
| flushed once by AddBrowserSearch\Endpoint::flushOnce().
|
*/
