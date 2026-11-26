<?php

namespace AddBrowserSearch;

if (!defined('ABSPATH')) {
  exit();
}

/**
 * Serves the OpenSearch description and points the pages at it.
 *
 * - `/opensearch.xml` is a rewrite rule to a query variable; the same variable works without
 *   pretty permalinks.
 * - Every page of the site carries `<link rel="search" type="application/opensearchdescription+xml">`,
 *   which is what makes a browser offer the engine.
 */
final class Endpoint
{
  public const RULE = '^opensearch\.xml$';

  /**
   * Bump it when the rule changes: a site whose stored rules predate it flushes once.
   */
  public const RULES_VERSION = '2';
  public const RULES_OPTION = 'add_browser_search_rewrite';

  public static function register(): void
  {
    self::addRewriteRule();

    add_filter('query_vars', [self::class, 'queryVars']);
    add_action('wp_loaded', [self::class, 'flushOnce']);
    add_action('template_redirect', [self::class, 'serve'], 0);
    add_action('wp_head', [self::class, 'headLink'], 2);
  }

  public static function addRewriteRule(): void
  {
    add_rewrite_rule(self::RULE, 'index.php?' . Urls::QUERY_VAR . '=' . Urls::QUERY_VALUE, 'top');
  }

  /**
   * @param array<int, string> $vars
   * @return array<int, string>
   */
  public static function queryVars(array $vars): array
  {
    $vars[] = Urls::QUERY_VAR;

    return $vars;
  }

  /**
   * Flush the rewrite rules once per RULES_VERSION: a plugin updated by copying files, or one
   * that came from 1.x, never ran an activation. After init, so the rules of other plugins that
   * register on init are in.
   */
  public static function flushOnce(): void
  {
    if (get_option(self::RULES_OPTION) === self::RULES_VERSION) {
      return;
    }

    flush_rewrite_rules(false);
    update_option(self::RULES_OPTION, self::RULES_VERSION);
  }

  /**
   * Activation: the rule is not registered yet (init has run), so register it and flush.
   */
  public static function activate(): void
  {
    self::addRewriteRule();
    flush_rewrite_rules(false);
    update_option(self::RULES_OPTION, self::RULES_VERSION);
  }

  /**
   * Deactivation: init has already added the rule in this request, so filter it out of what the
   * flush generates; a plain flush would keep it until the next one.
   */
  public static function deactivate(): void
  {
    add_filter('rewrite_rules_array', static function ($rules) {
      unset($rules[self::RULE]);

      return $rules;
    });

    flush_rewrite_rules(false);
    delete_option(self::RULES_OPTION);
  }

  /**
   * Answer a request for the description. Runs before WordPress picks a template, and before
   * canonical redirects.
   */
  public static function serve(): void
  {
    if (get_query_var(Urls::QUERY_VAR) !== Urls::QUERY_VALUE) {
      return;
    }

    status_header(200);
    header('Content-Type: ' . Urls::DESCRIPTION_TYPE . '; charset=UTF-8');
    header('X-Robots-Tag: noindex');
    header('Cache-Control: public, max-age=3600');

    echo Document::forSite(); // phpcs:ignore WordPress.Security.EscapeOutput -- XML built by XMLWriter, which escapes.

    exit();
  }

  /**
   * The tag browsers look for. The title is what a browser shows in its "add search engine" prompt.
   */
  public static function headLink(): void
  {
    printf(
      "<link rel=\"search\" type=\"%s\" title=\"%s\" href=\"%s\" />\n",
      esc_attr(Urls::DESCRIPTION_TYPE),
      esc_attr(Settings::resolved()['short_name']),
      esc_url(Urls::description())
    );
  }
}
