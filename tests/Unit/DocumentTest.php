<?php

declare(strict_types=1);

namespace AddBrowserSearch\Tests\Unit;

use AddBrowserSearch\Document;
use AddBrowserSearch\Tests\Support\MonkeyTestCase;

final class DocumentTest extends MonkeyTestCase
{
  /**
   * @return array<string, mixed>
   */
  private function settings(array $over = []): array
  {
    return $over + [
      'short_name' => 'Example',
      'description' => 'Search Example',
      'contact' => '',
      'tags' => '',
      'search_url' => 'https://example.test/?s={searchTerms}',
      'search_form' => 'https://example.test/',
    ];
  }

  /**
   * @return array<string, mixed>
   */
  private function urls(array $over = []): array
  {
    return $over + [
      'self' => 'https://example.test/opensearch.xml',
      'suggestions' => 'https://example.test/wp-json/add-browser-search/v1/suggest?q={searchTerms}',
      'icons' => [['url' => 'https://example.test/icon-16.png', 'width' => 16, 'height' => 16, 'type' => 'image/png']],
    ];
  }

  private function load(string $xml): \DOMXPath
  {
    $dom = new \DOMDocument();
    $this->assertTrue($dom->loadXML($xml, LIBXML_NOERROR), 'the document is well-formed XML');

    $xpath = new \DOMXPath($dom);
    $xpath->registerNamespace('os', Document::NS);
    $xpath->registerNamespace('moz', Document::NS_MOZ);

    return $xpath;
  }

  public function testItIsAnOpenSearchOneDotOneDescription(): void
  {
    $xml = Document::build($this->settings(), $this->urls());
    $x = $this->load($xml);

    $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $xml);
    $this->assertSame('OpenSearchDescription', $x->query('/*')->item(0)->localName);
    $this->assertSame('Example', $x->evaluate('string(/os:OpenSearchDescription/os:ShortName)'));
    $this->assertSame('Search Example', $x->evaluate('string(/os:OpenSearchDescription/os:Description)'));
    $this->assertSame('UTF-8', $x->evaluate('string(/os:OpenSearchDescription/os:InputEncoding)'));
    $this->assertSame('https://example.test/', $x->evaluate('string(/os:OpenSearchDescription/moz:SearchForm)'));
  }

  public function testTheHtmlUrlKeepsThePlaceholderAndEscapesAmpersands(): void
  {
    $xml = Document::build($this->settings(['search_url' => 'https://example.test/?s={searchTerms}&post_type=page']), $this->urls());
    $x = $this->load($xml);

    $this->assertStringContainsString('&amp;post_type=page', $xml);
    $this->assertSame(
      'https://example.test/?s={searchTerms}&post_type=page',
      $x->evaluate('string(/os:OpenSearchDescription/os:Url[@type="text/html"]/@template)')
    );
    $this->assertSame('get', $x->evaluate('string(/os:OpenSearchDescription/os:Url[@type="text/html"]/@method)'));
  }

  public function testItHasASelfUrlOfTheDescriptionType(): void
  {
    $x = $this->load(Document::build($this->settings(), $this->urls()));

    $this->assertSame(
      'https://example.test/opensearch.xml',
      $x->evaluate('string(/os:OpenSearchDescription/os:Url[@rel="self"]/@template)')
    );
    $this->assertSame(
      'application/opensearchdescription+xml',
      $x->evaluate('string(/os:OpenSearchDescription/os:Url[@rel="self"]/@type)')
    );
  }

  public function testSuggestionsUrlIsThereOnlyWhenGiven(): void
  {
    $on = $this->load(Document::build($this->settings(), $this->urls()));
    $off = $this->load(Document::build($this->settings(), $this->urls(['suggestions' => ''])));

    $this->assertSame(1, (int) $on->evaluate('count(/os:OpenSearchDescription/os:Url[@type="application/x-suggestions+json"])'));
    $this->assertSame(
      'https://example.test/wp-json/add-browser-search/v1/suggest?q={searchTerms}',
      $on->evaluate('string(/os:OpenSearchDescription/os:Url[@type="application/x-suggestions+json"]/@template)')
    );
    $this->assertSame(0, (int) $off->evaluate('count(/os:OpenSearchDescription/os:Url[@type="application/x-suggestions+json"])'));
  }

  public function testImagesCarryWidthHeightAndType(): void
  {
    $x = $this->load(Document::build($this->settings(), $this->urls()));

    $this->assertSame('16', $x->evaluate('string(/os:OpenSearchDescription/os:Image/@width)'));
    $this->assertSame('16', $x->evaluate('string(/os:OpenSearchDescription/os:Image/@height)'));
    $this->assertSame('image/png', $x->evaluate('string(/os:OpenSearchDescription/os:Image/@type)'));
    $this->assertSame('https://example.test/icon-16.png', $x->evaluate('string(/os:OpenSearchDescription/os:Image)'));
  }

  public function testNoIconMeansNoImageElement(): void
  {
    $x = $this->load(Document::build($this->settings(), $this->urls(['icons' => []])));

    $this->assertSame(0, (int) $x->evaluate('count(/os:OpenSearchDescription/os:Image)'));
  }

  public function testContactAndTagsAppearOnlyWhenSet(): void
  {
    $bare = $this->load(Document::build($this->settings(), $this->urls()));
    $full = $this->load(Document::build($this->settings(['contact' => 'me@example.test', 'tags' => 'shop blog']), $this->urls()));

    $this->assertSame(0, (int) $bare->evaluate('count(/os:OpenSearchDescription/os:Contact | /os:OpenSearchDescription/os:Tags)'));
    $this->assertSame('me@example.test', $full->evaluate('string(/os:OpenSearchDescription/os:Contact)'));
    $this->assertSame('shop blog', $full->evaluate('string(/os:OpenSearchDescription/os:Tags)'));
  }

  public function testTheLimitsHoldEvenForSettingsThatBypassedTheForm(): void
  {
    $x = $this->load(Document::build($this->settings(['short_name' => str_repeat('é', 40), 'description' => str_repeat('d', 2000)]), $this->urls()));

    $this->assertSame(16, mb_strlen((string) $x->evaluate('string(/os:OpenSearchDescription/os:ShortName)')));
    $this->assertSame(1024, strlen((string) $x->evaluate('string(/os:OpenSearchDescription/os:Description)')));
  }

  public function testMarkupAndControlCharactersCannotBreakTheDocument(): void
  {
    $xml = Document::build($this->settings(['short_name' => "A<b>&\x08\x0B", 'description' => "x </Description><evil/> \x1F"]), $this->urls());
    $x = $this->load($xml);

    $this->assertSame('A<b>&', $x->evaluate('string(/os:OpenSearchDescription/os:ShortName)'));
    $this->assertSame(0, (int) $x->evaluate('count(//evil)'));
  }
}
