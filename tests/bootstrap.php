<?php

/**
 * Unit-suite bootstrap: no WordPress, no database.
 *
 * WordPress functions are stubbed per test with Brain Monkey (see Support/MonkeyTestCase), so a test
 * that reaches one that was not stubbed fails with a clear "undefined function". The few WordPress
 * classes the plugin builds are replaced by the small stubs of Support/wp-classes.php.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
  define('ABSPATH', __DIR__ . '/');
}

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/Support/wp-classes.php';
