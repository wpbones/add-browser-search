<?php

declare(strict_types=1);

namespace AddBrowserSearch\Tests\Unit;

use AddBrowserSearch\Providers\AddBrowserSearchProvider;
use AddBrowserSearch\Tests\Support\MonkeyTestCase;
use Brain\Monkey\Functions;

final class ProviderTest extends MonkeyTestCase
{
  public function testTheTranslationsAreLoadedFromTheFolderThePluginIsActuallyIn(): void
  {
    $plugin = new \stdClass();
    $plugin->file = '/srv/wp-content/plugins/add-browser-search-copy/wp-abs.php';

    Functions\when('plugin_basename')->alias(static fn($file) => 'add-browser-search-copy/wp-abs.php');
    Functions\expect('load_plugin_textdomain')->once()->with('add-browser-search', false, 'add-browser-search-copy/languages');

    (new AddBrowserSearchProvider($plugin))->loadTextDomain();
  }
}
