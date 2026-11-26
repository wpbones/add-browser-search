<?php

declare(strict_types=1);

namespace AddBrowserSearch\Tests\Unit;

use AddBrowserSearch\Migration;
use AddBrowserSearch\Settings;
use AddBrowserSearch\Tests\Support\MonkeyTestCase;

final class MigrationTest extends MonkeyTestCase
{
  protected function setUp(): void
  {
    parent::setUp();
    $this->site();
  }

  /**
   * What 1.x saved on its first run, on this site: its defaults.
   *
   * @return array<string, string>
   */
  private function legacyDefaults(): array
  {
    return [
      'wp_abs_blogname' => 'Example Site',
      'wp_abs_description' => 'Just another site',
      'wp_abs_contact' => 'admin@example.test',
      'wp_abs_tags' => '',
      'wp_abs_favicon' => 'https://example.test/favicon.ico',
      'wp_abs_image' => '',
      'wp_abs_searchurl' => 'https://example.test/?s={searchTerms}',
      'wp_abs_searchform' => 'https://example.test',
    ];
  }

  public function testValuesThatWereJustTheSitesDefaultsBecomeEmptyFieldsSoTheEngineFollowsTheSite(): void
  {
    $new = Migration::map($this->legacyDefaults());

    $this->assertSame('', $new['short_name']);
    $this->assertSame('', $new['description']);
    $this->assertSame('', $new['image'], 'the favicon.ico default gives way to the site icon');
    $this->assertSame('', $new['search_url']);
    $this->assertSame('', $new['search_form']);
    $this->assertTrue($new['suggestions']);
  }

  public function testTheAdministratorEmailThatWasOnlyTheOldDefaultIsNotKeptPublic(): void
  {
    $this->assertSame('', Migration::map($this->legacyDefaults())['contact']);

    $old = $this->legacyDefaults();
    $old['wp_abs_contact'] = 'ADMIN@example.test';
    $this->assertSame('', Migration::map($old)['contact'], 'case does not matter');
  }

  public function testAContactTheAdministratorChoseIsKept(): void
  {
    $old = $this->legacyDefaults();
    $old['wp_abs_contact'] = 'help@example.test';

    $this->assertSame('help@example.test', Migration::map($old)['contact']);
  }

  public function testATitleWithAnEntityStillCountsAsTheDefault(): void
  {
    // Core stores "Tom & Jerry" as "Tom &amp; Jerry"; 1.x seeded its settings with that, and its form
    // showed the value decoded, so a re-saved form holds "Tom & Jerry".
    $this->site(['name' => 'Tom &amp; Jerry', 'description' => 'Cat &amp; mouse']);
    $old = $this->legacyDefaults();
    $old['wp_abs_blogname'] = 'Tom & Jerry';
    $old['wp_abs_description'] = 'Cat &amp; mouse';

    $new = Migration::map($old);

    $this->assertSame('', $new['short_name']);
    $this->assertSame('', $new['description']);
  }

  public function testACustomNameWithAnEntityIsMovedAsPlainText(): void
  {
    $old = $this->legacyDefaults();
    $old['wp_abs_blogname'] = 'R&amp;D Search';

    $this->assertSame('R&D Search', Migration::map($old)['short_name']);
  }

  public function testCustomValuesAreMovedAcross(): void
  {
    $new = Migration::map([
      'wp_abs_blogname' => 'Acme Search',
      'wp_abs_description' => 'Find anything at Acme',
      'wp_abs_contact' => 'hi@acme.test',
      'wp_abs_tags' => 'acme shop',
      'wp_abs_favicon' => 'https://example.test/favicon.ico',
      'wp_abs_image' => 'https://example.test/logo-64.png',
      'wp_abs_searchurl' => 'https://www.google.com/cse?q={searchTerms}&cx=123',
      'wp_abs_searchform' => 'https://example.test/search-page/',
    ]);

    $this->assertSame('Acme Search', $new['short_name']);
    $this->assertSame('Find anything at Acme', $new['description']);
    $this->assertSame('hi@acme.test', $new['contact']);
    $this->assertSame('acme shop', $new['tags']);
    $this->assertSame('https://example.test/logo-64.png', $new['image'], 'the 64px image wins over the favicon');
    $this->assertSame('https://www.google.com/cse?q={searchTerms}&cx=123', $new['search_url']);
    $this->assertSame('https://example.test/search-page/', $new['search_form']);
  }

  public function testACustomFaviconBecomesTheIconWhenThereIsNoImage(): void
  {
    $old = $this->legacyDefaults();
    $old['wp_abs_favicon'] = 'https://cdn.test/favicon.ico';

    $this->assertSame('https://cdn.test/favicon.ico', Migration::map($old)['image']);
  }

  public function testAnOldNameLongerThanSixteenCharactersIsCutAndAnUnusableSearchAddressDropped(): void
  {
    $old = $this->legacyDefaults();
    $old['wp_abs_blogname'] = 'A name that was never valid';
    $old['wp_abs_searchurl'] = 'https://other.test/?q=fixed';

    $new = Migration::map($old);

    $this->assertSame('A name that was', $new['short_name']);
    $this->assertSame('', $new['search_url'], 'without {searchTerms} it cannot work, so it is the default again');
  }

  public function testRunMovesTheOldOptionIntoTheNewOneAndDeletesTheOldKey(): void
  {
    $this->options[Settings::LEGACY_OPTION] = $this->legacyDefaults() + [];
    $this->options[Settings::LEGACY_OPTION]['wp_abs_tags'] = 'one two';

    $this->assertTrue(Migration::run());

    $this->assertSame('one two', $this->written[Settings::OPTION]['tags']);
    $this->assertContains(Settings::LEGACY_OPTION, $this->deleted);
    $this->assertArrayNotHasKey(Settings::LEGACY_OPTION, $this->options);
    $this->assertSame('1', $this->written[Migration::NOTICE_OPTION], 'the settings page will say so once');
  }

  public function testRunNeverOverwritesSettingsThatExist(): void
  {
    $this->options[Settings::OPTION] = ['short_name' => 'Keep me'];
    $this->options[Settings::LEGACY_OPTION] = $this->legacyDefaults();

    $this->assertFalse(Migration::run());

    $this->assertSame(['short_name' => 'Keep me'], $this->options[Settings::OPTION]);
    $this->assertArrayNotHasKey(Settings::OPTION, $this->written);
    $this->assertContains(Settings::LEGACY_OPTION, $this->deleted);
  }

  public function testRunOnAFreshSiteSavesTheDefaultsSoLaterRequestsSkipIt(): void
  {
    $this->assertFalse(Migration::run());

    $this->assertSame(Settings::defaults(), $this->written[Settings::OPTION]);
    $this->assertSame([], $this->deleted);
  }

  public function testRunTwiceMovesOnce(): void
  {
    $this->options[Settings::LEGACY_OPTION] = $this->legacyDefaults();

    $this->assertTrue(Migration::run());
    $this->assertFalse(Migration::run());
  }

  public function testACorruptOldOptionIsDroppedAndTheDefaultsAreSaved(): void
  {
    $this->options[Settings::LEGACY_OPTION] = 'not an array';

    $this->assertFalse(Migration::run());

    $this->assertSame(Settings::defaults(), $this->written[Settings::OPTION]);
    $this->assertContains(Settings::LEGACY_OPTION, $this->deleted);
  }
}
