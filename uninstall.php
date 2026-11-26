<?php

/**
 * Runs when the plugin is deleted from the Plugins screen, not when it is deactivated.
 *
 * It removes every option the plugin (or its 1.x version) created. The suggestions cache lives in
 * the object cache for a minute and never in the options table, so there is nothing else to clean.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
  exit();
}

$add_browser_search_options = [
  // Settings, and the one-time notice about the 1.x settings that were moved in.
  'add_browser_search_settings',
  'add_browser_search_migrated',
  // The flag that says the rewrite rules were flushed.
  'add_browser_search_rewrite',
  // 1.x settings, in case a site was never loaded since the update.
  'wp_abs_settings',
  // What WP Bones keeps for a plugin: its options row, and the migrations bookkeeping.
  'add_browser_search_slug',
  'add_browser_search_slug_db_version',
  'add_browser_search_slug_migrations',
  'add_browser_search_slug_migrations_failure',
  'add_browser_search_slug_migrations.lock',
];

if (is_multisite()) {
  foreach (get_sites(['fields' => 'ids', 'number' => 0]) as $add_browser_search_site) {
    switch_to_blog($add_browser_search_site);

    foreach ($add_browser_search_options as $add_browser_search_option) {
      delete_option($add_browser_search_option);
    }

    restore_current_blog();
  }
} else {
  foreach ($add_browser_search_options as $add_browser_search_option) {
    delete_option($add_browser_search_option);
  }
}
