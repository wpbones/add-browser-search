<?php

declare(strict_types=1);

namespace AddBrowserSearch\Tests\Unit;

use AddBrowserSearch\Settings;
use AddBrowserSearch\Suggestions;
use AddBrowserSearch\Tests\Support\MonkeyTestCase;
use Brain\Monkey\Functions;

final class SuggestionsTest extends MonkeyTestCase
{
  /** @var array<string, mixed> The object cache. */
  private array $cache = [];

  protected function setUp(): void
  {
    parent::setUp();
    $this->site();

    $this->cache = [];
    \WP_Query::$made = [];
    \WP_Query::$found = [];

    Functions\when('apply_filters')->alias(static fn($hook, $value) => $value);
    Functions\when('wp_cache_get_last_changed')->justReturn('1.0');
    Functions\when('wp_cache_get')->alias(fn($key, $group) => $this->cache[$group . '|' . $key] ?? false);
    Functions\when('wp_cache_set')->alias(function ($key, $value, $group) {
      $this->cache[$group . '|' . $key] = $value;

      return true;
    });
    Functions\when('get_the_title')->alias(static fn($id) => [1 => 'Hello &amp; welcome', 2 => '<em>Hello</em> world', 3 => 'Hello &amp; welcome', 4 => '   '][$id] ?? '');
  }

  public function testTheAnswerIsTheOpenSearchSuggestionsFormat(): void
  {
    \WP_Query::$found = [1, 2];

    $response = Suggestions::respond(new \WP_REST_Request(['q' => 'hel']));

    $this->assertInstanceOf(\WP_REST_Response::class, $response);
    $this->assertSame(['hel', ['Hello & welcome', 'Hello world']], $response->data);
  }

  public function testItSaysItsOwnContentTypeAndMayBeCachedForAMinute(): void
  {
    $response = Suggestions::respond(new \WP_REST_Request(['q' => 'hel']));

    $this->assertSame('application/x-suggestions+json; charset=UTF-8', $response->headers['Content-Type']);
    $this->assertSame('public, max-age=60', $response->headers['Cache-Control']);
  }

  public function testItSearchesTitlesOfPublishedPublicPostsAndPagesOnly(): void
  {
    Suggestions::titles('hello');

    $args = \WP_Query::$made[0];

    $this->assertSame(['post', 'page'], $args['post_type']);
    $this->assertSame('publish', $args['post_status']);
    $this->assertSame('hello', $args['s']);
    $this->assertSame(['post_title'], $args['search_columns']);
    $this->assertFalse($args['has_password']);
    $this->assertSame('ids', $args['fields']);
    $this->assertTrue($args['no_found_rows']);
  }

  public function testTheCountIsLimited(): void
  {
    Suggestions::titles('hello');
    $this->assertSame(8, \WP_Query::$made[0]['posts_per_page']);

    // A filter may ask for more, never for more than ten, and never for less than one.
    Functions\when('apply_filters')->alias(static fn($hook, $value) => $hook === 'add_browser_search_suggestions_limit' ? 500 : $value);
    $this->assertSame(10, Suggestions::limit());

    Functions\when('apply_filters')->alias(static fn($hook, $value) => $hook === 'add_browser_search_suggestions_limit' ? -3 : $value);
    $this->assertSame(1, Suggestions::limit());
  }

  public function testTitlesAreCleanedOfMarkupEntitiesBlanksAndRepeats(): void
  {
    $this->assertSame(
      ['Hello & welcome', 'Hello world'],
      Suggestions::clean(['Hello &amp; welcome', '<em>Hello</em> world', 'Hello &amp; welcome', '   ', ''], 8)
    );
    $this->assertSame(['a', 'b'], Suggestions::clean(['a', 'b', 'c', 'd'], 2));
  }

  public function testTheQueryIsOneLineOfPlainTextOfBoundedLength(): void
  {
    $this->assertSame('hello world', Suggestions::normalize("  <b>hello</b>\n  world "));
    $this->assertSame(100, mb_strlen(Suggestions::normalize(str_repeat('è', 500))));
    $this->assertSame('', Suggestions::normalize(['array']));
    $this->assertSame('', Suggestions::normalize(null));
  }

  public function testAnEmptyQueryAsksNothing(): void
  {
    $response = Suggestions::respond(new \WP_REST_Request([]));

    $this->assertSame(['', []], $response->data);
    $this->assertSame([], \WP_Query::$made);
  }

  public function testTheSecondAskForTheSameWordsIsAnsweredFromTheCache(): void
  {
    \WP_Query::$found = [1];

    $first = Suggestions::titles('Hello');
    $second = Suggestions::titles('hello');

    $this->assertSame(['Hello & welcome'], $first);
    $this->assertSame($first, $second);
    $this->assertCount(1, \WP_Query::$made, 'one query for two asks');
  }

  public function testItAnswersNotFoundWhenSuggestionsAreOff(): void
  {
    $this->options[Settings::OPTION] = ['suggestions' => false];

    $response = Suggestions::respond(new \WP_REST_Request(['q' => 'hello']));

    $this->assertInstanceOf(\WP_Error::class, $response);
    $this->assertSame(404, $response->data['status']);
    $this->assertSame([], \WP_Query::$made, 'nothing is searched when the feature is off');
  }
}
