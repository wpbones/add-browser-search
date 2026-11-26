<?php

if (!defined('ABSPATH')) {
  exit();
}

/*
|--------------------------------------------------------------------------
| WordPress REST API configuration
|--------------------------------------------------------------------------
|
| The plugin's route lives in api/add-browser-search/v1/route.php: the search
| suggestions, which browsers read without being logged in.
|
*/

return [
  'wp' => [
    'require_authentication' => false,
  ],

  'custom' => [
    'path' => '/api',
    'enabled' => true,
  ],

  'auth' => [
    'basic' => false,
  ],
];
