<?php

declare(strict_types=1);

namespace AddBrowserSearch\Tests\Unit;

use AddBrowserSearch\Tests\Support\MonkeyTestCase;
use AddBrowserSearch\Urls;
use Brain\Monkey\Functions;

final class UrlsTest extends MonkeyTestCase
{
  protected function setUp(): void
  {
    parent::setUp();
    $this->site();
    Functions\when('add_query_arg')->alias(static function ($key, $value, $url) {
      return $url . (strpos($url, '?') === false ? '?' : '&') . rawurlencode($key) . '=' . rawurlencode($value);
    });
  }

  public function testTheStableAddressIsOpensearchXmlWithPrettyPermalinks(): void
  {
    $this->options['permalink_structure'] = '/%postname%/';

    $this->assertSame('https://example.test/opensearch.xml', Urls::description());
  }

  public function testWithoutPrettyPermalinksTheAddressIsTheQueryString(): void
  {
    $this->options['permalink_structure'] = '';

    $this->assertSame('https://example.test/?add_browser_search=opensearch', Urls::description());
    $this->assertSame(Urls::description(), Urls::plainDescription());
  }

  public function testSuggestionsAddressHasTheLiteralPlaceholder(): void
  {
    Functions\when('rest_url')->alias(static fn($path) => 'https://example.test/wp-json/' . $path);

    $this->assertSame('https://example.test/wp-json/add-browser-search/v1/suggest?q={searchTerms}', Urls::suggestions());
  }

  public function testSuggestionsAddressWorksWithPlainPermalinksToo(): void
  {
    Functions\when('rest_url')->alias(static fn($path) => 'https://example.test/?rest_route=/' . $path);

    $this->assertSame('https://example.test/?rest_route=/add-browser-search/v1/suggest&q={searchTerms}', Urls::suggestions());
  }

  public function testTheSiteIconIsAdvertisedAtSixteenAndSixtyFourPixels(): void
  {
    Functions\when('get_site_icon_url')->alias(static fn($size) => 'https://example.test/icon-' . $size . '.png');
    Functions\when('wp_check_filetype')->justReturn(['type' => 'image/png']);

    $icons = Urls::icons('');

    $this->assertSame(
      [
        ['url' => 'https://example.test/icon-16.png', 'width' => 16, 'height' => 16, 'type' => 'image/png'],
        ['url' => 'https://example.test/icon-64.png', 'width' => 64, 'height' => 64, 'type' => 'image/png'],
      ],
      $icons
    );
  }

  public function testNoSiteIconAndNoCustomIconMeansNoIcon(): void
  {
    Functions\when('get_site_icon_url')->justReturn('');

    $this->assertSame([], Urls::icons(''));
  }

  public function testACustomIconReplacesTheSiteIcon(): void
  {
    Functions\when('get_site_icon_url')->justReturn('https://example.test/site-icon.png');
    Functions\when('wp_check_filetype')->justReturn(['type' => 'image/x-icon']);
    Functions\when('wp_get_upload_dir')->justReturn(['baseurl' => 'https://example.test/wp-content/uploads', 'basedir' => '/nowhere']);

    $icons = Urls::icons('https://cdn.test/favicon.ico');

    $this->assertCount(1, $icons);
    $this->assertSame(['url' => 'https://cdn.test/favicon.ico', 'width' => 16, 'height' => 16, 'type' => 'image/x-icon'], $icons[0]);
  }

  public function testACustomPngOutsideTheUploadsIsAssumedSixtyFour(): void
  {
    Functions\when('wp_check_filetype')->justReturn(['type' => 'image/png']);
    Functions\when('wp_get_upload_dir')->justReturn(['baseurl' => 'https://example.test/wp-content/uploads', 'basedir' => '/nowhere']);

    $icon = Urls::icon('https://cdn.test/logo.png?v=3');

    $this->assertSame([64, 64], [$icon['width'], $icon['height']]);
  }
}
