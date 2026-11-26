<?php

namespace AddBrowserSearch;

if (!defined('ABSPATH')) {
  exit();
}

/**
 * Search suggestions in the OpenSearch suggestions format: `["query", ["title", "title"]]`.
 *
 * The browser asks for them while the visitor types in the address bar, so the route is public by
 * design. It only ever reads the titles of published posts and pages that are not password
 * protected, with a hard limit on the count and on the length of the query, and the answer is
 * cached for a minute (in the object cache when the site has one, and by the browser).
 */
final class Suggestions
{
  public const LIMIT = 8;
  public const MAX_LIMIT = 10;
  public const QUERY_MAX = 100;
  public const CACHE_GROUP = 'add_browser_search';
  public const CACHE_SECONDS = 60;

  /**
   * The route's callback.
   *
   * @return \WP_REST_Response|\WP_Error
   */
  public static function respond(\WP_REST_Request $request)
  {
    if (!Settings::stored()['suggestions']) {
      return new \WP_Error(
        'add_browser_search_suggestions_off',
        __('Search suggestions are turned off.', 'add-browser-search'),
        ['status' => 404]
      );
    }

    $query = self::normalize($request->get_param('q'));
    $response = new \WP_REST_Response([$query, self::titles($query)]);

    $response->header('Content-Type', Urls::SUGGESTIONS_TYPE . '; charset=UTF-8');
    $response->header('Cache-Control', 'public, max-age=' . self::CACHE_SECONDS);

    return $response;
  }

  /**
   * What the visitor typed, as one line of plain text, at most QUERY_MAX characters.
   */
  public static function normalize(mixed $value): string
  {
    $text = is_scalar($value) ? sanitize_text_field((string) $value) : '';

    return trim(Settings::truncate($text, self::QUERY_MAX));
  }

  /**
   * The titles that contain the query: published posts and pages, newest relevance first.
   *
   * @return list<string>
   */
  public static function titles(string $query): array
  {
    if ($query === '') {
      return [];
    }

    $limit = self::limit();
    $key = 'titles:' . $limit . ':' . md5(function_exists('mb_strtolower') ? mb_strtolower($query, 'UTF-8') : strtolower($query));
    $key .= ':' . wp_cache_get_last_changed('posts');

    $cached = wp_cache_get($key, self::CACHE_GROUP);

    if (is_array($cached)) {
      return $cached;
    }

    $posts = new \WP_Query([
      'post_type' => self::postTypes(),
      'post_status' => 'publish',
      's' => $query,
      'search_columns' => ['post_title'],
      'has_password' => false,
      'posts_per_page' => $limit,
      'orderby' => 'relevance',
      'fields' => 'ids',
      'no_found_rows' => true,
      'ignore_sticky_posts' => true,
      'update_post_meta_cache' => false,
      'update_post_term_cache' => false,
    ]);

    $titles = self::clean(array_map(static fn($id) => get_the_title($id), (array) $posts->posts), $limit);

    wp_cache_set($key, $titles, self::CACHE_GROUP, self::CACHE_SECONDS);

    return $titles;
  }

  /**
   * Titles as plain text: no markup, no entities, no empty ones, no repeats, at most $limit.
   *
   * @param array<int, mixed> $titles
   * @return list<string>
   */
  public static function clean(array $titles, int $limit): array
  {
    $clean = [];

    foreach ($titles as $title) {
      $title = trim(wp_strip_all_tags(html_entity_decode((string) $title, ENT_QUOTES, 'UTF-8')));

      if ($title !== '' && !in_array($title, $clean, true)) {
        $clean[] = $title;
      }
    }

    return array_slice($clean, 0, $limit);
  }

  public static function limit(): int
  {
    $limit = (int) apply_filters('add_browser_search_suggestions_limit', self::LIMIT);

    return max(1, min(self::MAX_LIMIT, $limit));
  }

  /**
   * @return list<string>
   */
  public static function postTypes(): array
  {
    $types = apply_filters('add_browser_search_suggestions_post_types', ['post', 'page']);

    return array_values(array_filter((array) $types, 'is_string'));
  }
}
