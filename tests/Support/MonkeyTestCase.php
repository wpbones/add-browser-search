<?php

declare(strict_types=1);

namespace AddBrowserSearch\Tests\Support;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Brain Monkey around every test, with the WordPress sanitizers it needs behaving like the real ones
 * where the behaviour is the point: esc_url_raw() strips what a URL may not contain, braces included,
 * and accepts only the schemes it is given.
 */
abstract class MonkeyTestCase extends TestCase
{
  /** @var array<string, mixed> What get_option() answers. */
  protected array $options = [];

  /** @var array<string, mixed> What update_option()/add_option() stored. */
  protected array $written = [];

  /** @var list<string> What delete_option() removed. */
  protected array $deleted = [];

  protected function setUp(): void
  {
    parent::setUp();
    Monkey\setUp();

    $this->options = [];
    $this->written = [];
    $this->deleted = [];

    Functions\stubs([
      '__' => static fn($text) => $text,
      'esc_attr' => static fn($text) => htmlspecialchars((string) $text, ENT_QUOTES),
      'esc_url' => static fn($text) => (string) $text,
      'wp_strip_all_tags' => static fn($text) => trim(strip_tags((string) $text)),
      'sanitize_text_field' => static fn($text) => trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $text))),
      'sanitize_email' => static fn($email) => (string) preg_replace('/[^a-z0-9+_.@-]/i', '', (string) $email),
      'is_email' => static fn($email) => (bool) preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', (string) $email),
      'wp_parse_url' => static fn($url, $component = -1) => parse_url((string) $url, $component),
      'esc_url_raw' => static function ($url, $protocols = null) {
        $url = (string) preg_replace('|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\[\]\\x80-\\xff]|i', '', (string) $url);
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, $protocols ?? ['http', 'https'], true) ? $url : '';
      },
      'get_option' => fn($name, $default = false) => array_key_exists($name, $this->options) ? $this->options[$name] : $default,
      'update_option' => function ($name, $value) {
        $this->written[$name] = $value;
        $this->options[$name] = $value;

        return true;
      },
      'add_option' => function ($name, $value) {
        $this->written[$name] = $value;
        $this->options[$name] = $value;

        return true;
      },
      'delete_option' => function ($name) {
        $this->deleted[] = $name;
        unset($this->options[$name]);

        return true;
      },
    ]);
  }

  protected function tearDown(): void
  {
    // A Brain Monkey expectation is a Mockery expectation, which PHPUnit does not count as an
    // assertion: without this a test made only of expect()->once() is reported as risky.
    $this->addToAssertionCount(Mockery::getContainer()->mockery_getExpectationCount());
    Monkey\tearDown();
    parent::tearDown();
  }

  /**
   * The site as most sites are: a title, a tagline, https.
   *
   * @param array<string, string> $info get_bloginfo() answers
   */
  protected function site(array $info = [], string $home = 'https://example.test'): void
  {
    $info += ['name' => 'Example Site', 'description' => 'Just another site'];

    Functions\when('get_bloginfo')->alias(static fn($key) => $info[$key] ?? '');
    Functions\when('home_url')->alias(static fn($path = '') => $home . ($path !== '' ? '/' . ltrim($path, '/') : ''));
  }
}
