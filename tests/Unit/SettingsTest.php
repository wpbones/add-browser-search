<?php

declare(strict_types=1);

namespace AddBrowserSearch\Tests\Unit;

use AddBrowserSearch\Settings;
use AddBrowserSearch\Tests\Support\MonkeyTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class SettingsTest extends MonkeyTestCase
{
  protected function setUp(): void
  {
    parent::setUp();
    $this->site();
  }

  public function testShortNameIsCutToSixteenCharactersNotBytes(): void
  {
    $clean = Settings::sanitize(['short_name' => 'Caffè società è bella ancora']);

    $this->assertSame('Caffè società è', $clean['short_name']);
    $this->assertSame(
      16,
      mb_strlen(Settings::sanitize(['short_name' => 'Caffèsocietàèbellaancora'])['short_name']),
      'sixteen characters, which are more than sixteen bytes'
    );
  }

  public function testDescriptionIsCutToTheOpenSearchLimit(): void
  {
    $clean = Settings::sanitize(['description' => str_repeat('a', 3000)]);

    $this->assertSame(1024, strlen($clean['description']));
  }

  public function testTagsAreCutToTwoHundredFiftySixCharacters(): void
  {
    $clean = Settings::sanitize(['tags' => str_repeat('b', 400)]);

    $this->assertSame(256, strlen($clean['tags']));
  }

  public function testTextFieldsLoseMarkup(): void
  {
    $clean = Settings::sanitize(['short_name' => '<script>alert(1)</script>Shop', 'description' => '<b>Find</b> things']);

    $this->assertSame('alert(1)Shop', $clean['short_name']);
    $this->assertSame('Find things', $clean['description']);
  }

  public function testSearchAddressNeedsThePlaceholderAndKeepsItsBraces(): void
  {
    $errors = [];
    $clean = Settings::sanitize(['search_url' => 'https://www.google.com/search?q={searchTerms}&hl=it'], $errors);

    $this->assertSame('https://www.google.com/search?q={searchTerms}&hl=it', $clean['search_url']);
    $this->assertSame([], $errors);
  }

  public function testSearchAddressWithoutThePlaceholderIsRefusedAndNamed(): void
  {
    $errors = [];
    $clean = Settings::sanitize(['search_url' => 'https://example.test/?s=fixed'], $errors);

    $this->assertSame('', $clean['search_url']);
    $this->assertArrayHasKey('search_url', $errors);
  }

  #[DataProvider('unsafeAddresses')]
  public function testAddressesThatAreNotWebAddressesAreRefused(string $field, string $value): void
  {
    $errors = [];
    $clean = Settings::sanitize([$field => $value], $errors);

    $this->assertSame('', $clean[$field]);
    $this->assertArrayHasKey($field, $errors);
  }

  /**
   * @return array<string, array{0: string, 1: string}>
   */
  public static function unsafeAddresses(): array
  {
    return [
      'javascript search address' => ['search_url', 'javascript:alert({searchTerms})'],
      'ftp search address' => ['search_url', 'ftp://example.test/?q={searchTerms}'],
      'javascript icon' => ['image', 'javascript:alert(1)'],
      'data icon' => ['image', 'data:image/png;base64,AAAA'],
      'javascript search page' => ['search_form', 'javascript:alert(1)'],
    ];
  }

  public function testContactMustBeAnEmailAddress(): void
  {
    $errors = [];
    $this->assertSame('', Settings::sanitize(['contact' => 'not an email'], $errors)['contact']);
    $this->assertArrayHasKey('contact', $errors);

    $errors = [];
    $this->assertSame('me@example.test', Settings::sanitize(['contact' => ' me@example.test '], $errors)['contact']);
    $this->assertSame([], $errors);
  }

  public function testUnknownKeysAndNonScalarValuesAreIgnored(): void
  {
    $clean = Settings::sanitize(['evil' => 'x', 'short_name' => ['array'], 'tags' => new \stdClass()]);

    $this->assertArrayNotHasKey('evil', $clean);
    $this->assertSame('', $clean['short_name']);
    $this->assertSame('', $clean['tags']);
    $this->assertSame(array_keys(Settings::defaults()), array_keys($clean));
  }

  public function testSuggestionsAreOnlyOnWhenTheBoxIsChecked(): void
  {
    $this->assertTrue(Settings::sanitize(['suggestions' => '1'])['suggestions']);
    // An unchecked box posts nothing.
    $this->assertFalse(Settings::sanitize([])['suggestions']);
  }

  public function testDefaultsComeFromTheSiteAndFollowIt(): void
  {
    $resolved = Settings::resolved();

    $this->assertSame('Example Site', $resolved['short_name']);
    $this->assertSame('Just another site', $resolved['description']);
    $this->assertSame('https://example.test/?s={searchTerms}', $resolved['search_url']);
    $this->assertSame('https://example.test/', $resolved['search_form']);
    $this->assertTrue($resolved['suggestions']);

    $this->site(['name' => 'Renamed']);
    $this->assertSame('Renamed', Settings::resolved()['short_name']);
  }

  public function testTheDefaultNameIsCutToSixteenCharacters(): void
  {
    $this->site(['name' => 'A very long site title indeed']);

    $this->assertSame('A very long site', Settings::resolved()['short_name']);
  }

  public function testSavedValuesBeatTheDefaults(): void
  {
    $this->options[Settings::OPTION] = ['short_name' => 'Mine', 'search_url' => 'https://other.test/?q={searchTerms}', 'suggestions' => false];

    $resolved = Settings::resolved();

    $this->assertSame('Mine', $resolved['short_name']);
    $this->assertSame('https://other.test/?q={searchTerms}', $resolved['search_url']);
    $this->assertFalse($resolved['suggestions']);
  }

  public function testACorruptOptionFallsBackToTheDefaults(): void
  {
    $this->options[Settings::OPTION] = 'garbage';

    $this->assertSame(Settings::defaults(), Settings::stored());
  }
}
