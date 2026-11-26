<?php

namespace AddBrowserSearch;

if (!defined('ABSPATH')) {
  exit();
}

/**
 * Every address the plugin publishes.
 */
final class Urls
{
  /**
   * The query variable behind the description. `/opensearch.xml` is a rewrite rule to it; the
   * variable itself works whatever the permalink structure is, or when the web server answers a
   * request for a `.xml` file by itself.
   */
  public const QUERY_VAR = 'add_browser_search';
  public const QUERY_VALUE = 'opensearch';

  public const REST_NAMESPACE = 'add-browser-search/v1';
  public const REST_ROUTE = '/suggest';

  public const DESCRIPTION_TYPE = 'application/opensearchdescription+xml';
  public const SUGGESTIONS_TYPE = 'application/x-suggestions+json';

  /**
   * The stable address of the description: `/opensearch.xml` with pretty permalinks.
   */
  public static function description(): string
  {
    if (get_option('permalink_structure')) {
      return home_url('/opensearch.xml');
    }

    return self::plainDescription();
  }

  /**
   * The address that needs no rewrite rule.
   */
  public static function plainDescription(): string
  {
    return add_query_arg(self::QUERY_VAR, self::QUERY_VALUE, home_url('/'));
  }

  /**
   * The suggestions address, with {searchTerms} where the browser puts the typed words.
   */
  public static function suggestions(): string
  {
    $url = add_query_arg('q', Settings::TOKEN, rest_url(trim(self::REST_NAMESPACE . self::REST_ROUTE, '/')));

    return str_replace(Settings::TOKEN, Settings::SEARCH_TERMS, $url);
  }

  /**
   * One icon for the description, from its address.
   *
   * @return array{url: string, width: int, height: int, type: string}
   */
  public static function icon(string $url, int $width = 0, int $height = 0): array
  {
    $type = (string) (wp_check_filetype(strtok($url, '?') ?: $url)['type'] ?? '');

    if ($width <= 0 || $height <= 0) {
      [$width, $height] = self::dimensions($url, $type);
    }

    return ['url' => $url, 'width' => $width, 'height' => $height, 'type' => $type !== '' ? $type : 'image/png'];
  }

  /**
   * The icons to advertise: the one the administrator chose, or the site icon in the two sizes
   * browsers ask for (16 and 64 pixels). A site with neither advertises none, which is valid.
   *
   * @return list<array{url: string, width: int, height: int, type: string}>
   */
  public static function icons(string $custom): array
  {
    if ($custom !== '') {
      return [self::icon($custom)];
    }

    $icons = [];

    foreach ([16, 64] as $size) {
      $url = (string) get_site_icon_url($size);

      if ($url !== '') {
        $icons[] = self::icon($url, $size, $size);
      }
    }

    return $icons;
  }

  /**
   * The size of an icon that is not the site icon: an .ico is a favicon (16), anything else is
   * read from the file when it is in the uploads folder, else assumed 64.
   *
   * @return array{0: int, 1: int}
   */
  private static function dimensions(string $url, string $type): array
  {
    $uploads = wp_get_upload_dir();

    if (!empty($uploads['baseurl']) && !empty($uploads['basedir']) && strpos($url, $uploads['baseurl']) === 0) {
      $path = $uploads['basedir'] . substr(strtok($url, '?') ?: $url, strlen($uploads['baseurl']));
      $size = is_file($path) ? wp_getimagesize($path) : false;

      if (is_array($size) && $size[0] > 0 && $size[1] > 0) {
        return [(int) $size[0], (int) $size[1]];
      }
    }

    return $type === 'image/x-icon' || $type === 'image/vnd.microsoft.icon' ? [16, 16] : [64, 64];
  }
}
