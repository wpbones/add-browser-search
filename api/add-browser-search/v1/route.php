<?php

use AddBrowserSearch\WPBones\Routing\API\Route;

if (!defined('ABSPATH')) {
  exit();
}

/*
|--------------------------------------------------------------------------
| /wp-json/add-browser-search/v1/suggest?q=…
|--------------------------------------------------------------------------
|
| The search suggestions a browser asks for while the visitor types. Public on
| purpose (a browser sends no cookie and no nonce); see AddBrowserSearch\Suggestions
| for what it can read and how it is limited.
|
*/

Route::get(
  '/suggest',
  static function (WP_REST_Request $request) {
    return AddBrowserSearch\Suggestions::respond($request);
  },
  [
    'permission_callback' => '__return_true',
    'args' => [
      'q' => [
        'type' => 'string',
        'default' => '',
        'description' => 'The words the visitor typed.',
        'sanitize_callback' => 'sanitize_text_field',
      ],
    ],
  ]
);
