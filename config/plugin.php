<?php

if (!defined('ABSPATH')) {
  exit();
}

return [

  /*
  |--------------------------------------------------------------------------
  | Logging
  |--------------------------------------------------------------------------
  |
  | The plugin logs nothing of its own; the framework's default is kept.
  |
  */

  'logging' => [
    'type' => 'errorlog',
  ],

  /*
  |--------------------------------------------------------------------------
  | Autoloaded Service Providers
  |--------------------------------------------------------------------------
  |
  | The provider below wires the OpenSearch description, the <link> tag in
  | the page head and the Settings page.
  |
  */

  'providers' => [
    'AddBrowserSearch\Providers\AddBrowserSearchProvider',
  ],

];
