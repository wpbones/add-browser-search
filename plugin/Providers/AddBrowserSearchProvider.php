<?php

namespace AddBrowserSearch\Providers;

use AddBrowserSearch\Endpoint;
use AddBrowserSearch\Migration;
use AddBrowserSearch\Settings;
use AddBrowserSearch\SettingsPage;
use AddBrowserSearch\WPBones\Support\ServiceProvider;

if (!defined('ABSPATH')) {
  exit();
}

/**
 * Wires the plugin on `init` (WP Bones loads the providers of config/plugin.php there).
 */
class AddBrowserSearchProvider extends ServiceProvider
{
  public function register()
  {
    // WP Bones loads the text domain it derives from the plugin name in `namespace` (wp-abs, the main
    // file's name); the plugin's text domain is its WordPress.org slug, so load that one too.
    load_plugin_textdomain('add-browser-search', false, 'add-browser-search/languages');

    // Settings first: everything below reads them. Once the option exists this costs nothing.
    if (get_option(Settings::OPTION, null) === null) {
      Migration::run();
    }

    Endpoint::register();

    if (is_admin()) {
      (new SettingsPage($this->plugin))->register();
    }
  }
}
