<?php

/**
 * Stand-ins for the WordPress classes the plugin uses. They keep what they were given, so a test
 * can read it back.
 */

declare(strict_types=1);

if (!class_exists('WP_Error')) {
  class WP_Error
  {
    public function __construct(public $code = '', public $message = '', public $data = '')
    {
    }
  }
}

if (!class_exists('WP_REST_Response')) {
  class WP_REST_Response
  {
    /** @var array<string, string> */
    public array $headers = [];

    public function __construct(public $data = null, public int $status = 200)
    {
    }

    public function header($key, $value): void
    {
      $this->headers[$key] = $value;
    }
  }
}

if (!class_exists('WP_REST_Request')) {
  class WP_REST_Request
  {
    /** @param array<string, mixed> $params */
    public function __construct(private array $params = [])
    {
    }

    public function get_param($key)
    {
      return $this->params[$key] ?? null;
    }
  }
}

if (!class_exists('WP_Query')) {
  class WP_Query
  {
    /** @var array<int, array<string, mixed>> The arguments of every query made. */
    public static array $made = [];

    /** @var list<int> The ids the next query finds. */
    public static array $found = [];

    public array $posts;

    public function __construct(array $args = [])
    {
      self::$made[] = $args;
      $this->posts = self::$found;
    }
  }
}
