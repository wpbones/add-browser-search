<?php

namespace AddBrowserSearch;

if (!defined('ABSPATH')) {
  exit();
}

/**
 * The OpenSearch 1.1 description document: https://github.com/dewitt/opensearch
 */
final class Document
{
  public const NS = 'http://a9.com/-/spec/opensearch/1.1/';
  public const NS_MOZ = 'http://www.mozilla.org/2006/browser/search/';

  /**
   * The document for this site, as its settings and addresses stand now.
   */
  public static function forSite(): string
  {
    $s = Settings::resolved();

    return self::build($s, [
      'self' => Urls::description(),
      'suggestions' => $s['suggestions'] ? Urls::suggestions() : '',
      'icons' => Urls::icons($s['image']),
    ]);
  }

  /**
   * @param array<string, mixed> $s Resolved settings (Settings::resolved()).
   * @param array{self: string, suggestions: string, icons: list<array{url: string, width: int, height: int, type: string}>} $urls
   */
  public static function build(array $s, array $urls): string
  {
    $xml = new \XMLWriter();
    $xml->openMemory();
    $xml->setIndent(true);
    $xml->setIndentString('  ');
    $xml->startDocument('1.0', 'UTF-8');

    $xml->startElement('OpenSearchDescription');
    $xml->writeAttribute('xmlns', self::NS);
    $xml->writeAttribute('xmlns:moz', self::NS_MOZ);

    self::element($xml, 'ShortName', Settings::truncate(self::clean($s['short_name']), Settings::SHORT_NAME_MAX));
    self::element($xml, 'Description', Settings::truncate(self::clean($s['description']), Settings::DESCRIPTION_MAX));

    if ($s['contact'] !== '') {
      self::element($xml, 'Contact', self::clean($s['contact']));
    }

    if ($s['tags'] !== '') {
      self::element($xml, 'Tags', Settings::truncate(self::clean($s['tags']), Settings::TAGS_MAX));
    }

    self::element($xml, 'InputEncoding', 'UTF-8');

    foreach ($urls['icons'] as $icon) {
      $xml->startElement('Image');
      $xml->writeAttribute('width', (string) $icon['width']);
      $xml->writeAttribute('height', (string) $icon['height']);
      $xml->writeAttribute('type', $icon['type']);
      $xml->text(self::clean($icon['url']));
      $xml->endElement();
    }

    self::url($xml, 'text/html', $s['search_url']);

    if ($urls['suggestions'] !== '') {
      self::url($xml, Urls::SUGGESTIONS_TYPE, $urls['suggestions']);
    }

    $xml->startElement('Url');
    $xml->writeAttribute('rel', 'self');
    $xml->writeAttribute('type', Urls::DESCRIPTION_TYPE);
    $xml->writeAttribute('template', $urls['self']);
    $xml->endElement();

    self::element($xml, 'moz:SearchForm', self::clean($s['search_form']));

    $xml->endElement();
    $xml->endDocument();

    return $xml->outputMemory();
  }

  private static function url(\XMLWriter $xml, string $type, string $template): void
  {
    $xml->startElement('Url');
    $xml->writeAttribute('type', $type);
    $xml->writeAttribute('method', 'get');
    $xml->writeAttribute('template', $template);
    $xml->endElement();
  }

  private static function element(\XMLWriter $xml, string $name, string $text): void
  {
    $xml->startElement($name);
    $xml->text($text);
    $xml->endElement();
  }

  /**
   * XML 1.0 refuses most control characters: a stray one would make the whole document invalid.
   */
  private static function clean(mixed $value): string
  {
    return trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $value));
  }
}
