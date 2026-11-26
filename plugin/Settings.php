<?php

namespace AddBrowserSearch;

if (!defined('ABSPATH')) {
  exit();
}

/**
 * The plugin's settings: one array in one option.
 *
 * `stored()` is what the administrator saved. `resolved()` is what the OpenSearch document uses:
 * an empty field falls back to a default read from the site (its title, its description, its site
 * icon, its search URL), so the engine follows the site when the site changes.
 */
final class Settings
{
  public const OPTION = 'add_browser_search_settings';

  /**
   * The option the 1.x versions kept their settings in. 2.0.0 moves it into OPTION and deletes it.
   */
  public const LEGACY_OPTION = 'wp_abs_settings';

  /**
   * OpenSearch 1.1: ShortName is at most 16 characters, Description at most 1024, Tags at most 256.
   */
  public const SHORT_NAME_MAX = 16;
  public const DESCRIPTION_MAX = 1024;
  public const TAGS_MAX = 256;

  /**
   * What a search URL template has to contain for the browser to put the typed words in it.
   */
  public const SEARCH_TERMS = '{searchTerms}';

  /**
   * A stand-in for {searchTerms}: esc_url_raw() strips the braces.
   */
  private const TOKEN = 'ABSSEARCHTERMSTOKEN';

  /**
   * The fields, with what an empty one means.
   *
   * @return array<string, mixed>
   */
  public static function defaults(): array
  {
    return [
      'short_name' => '',
      'description' => '',
      'contact' => '',
      'tags' => '',
      'image' => '',
      'search_url' => '',
      'search_form' => '',
      'suggestions' => true,
    ];
  }

  /**
   * What was saved, over the defaults, every field of its own type.
   *
   * @return array<string, mixed>
   */
  public static function stored(): array
  {
    $saved = get_option(self::OPTION, []);

    if (!is_array($saved)) {
      $saved = [];
    }

    $result = self::defaults();

    foreach ($result as $key => $default) {
      if (!array_key_exists($key, $saved)) {
        continue;
      }

      $result[$key] = is_bool($default) ? (bool) $saved[$key] : (is_scalar($saved[$key]) ? (string) $saved[$key] : '');
    }

    return $result;
  }

  /**
   * What the document uses: the saved value, or the site's own where the field is empty.
   *
   * @return array<string, mixed>
   */
  public static function resolved(): array
  {
    $s = self::stored();

    if ($s['short_name'] === '') {
      $s['short_name'] = self::defaultShortName();
    }

    if ($s['description'] === '') {
      $s['description'] = self::defaultDescription();
    }

    if ($s['search_url'] === '') {
      $s['search_url'] = self::defaultSearchUrl();
    }

    if ($s['search_form'] === '') {
      $s['search_form'] = self::defaultSearchForm();
    }

    return $s;
  }

  /**
   * The site title, cut to the 16 characters OpenSearch allows.
   */
  public static function defaultShortName(): string
  {
    $name = trim((string) get_bloginfo('name'));

    if ($name === '') {
      $name = (string) wp_parse_url(home_url(), PHP_URL_HOST);
    }

    return self::truncate($name, self::SHORT_NAME_MAX);
  }

  public static function defaultDescription(): string
  {
    $description = trim((string) get_bloginfo('description'));

    if ($description === '') {
      /* translators: %s: the site title. */
      $description = sprintf(__('Search %s', 'add-browser-search'), (string) get_bloginfo('name'));
    }

    return self::truncate($description, self::DESCRIPTION_MAX);
  }

  public static function defaultSearchUrl(): string
  {
    return home_url('/?s=' . self::SEARCH_TERMS);
  }

  public static function defaultSearchForm(): string
  {
    return home_url('/');
  }

  /**
   * Clean what a form posted. Never trusts a key it does not know.
   *
   * A field that is invalid comes back empty (so it means its default) and is named in $errors.
   *
   * @param array<string, mixed> $input
   * @param array<string, string> $errors field => message
   * @return array<string, mixed>
   */
  public static function sanitize(array $input, array &$errors = []): array
  {
    $clean = self::defaults();

    $clean['short_name'] = self::truncate(self::text($input['short_name'] ?? ''), self::SHORT_NAME_MAX);
    $clean['description'] = self::truncate(self::text($input['description'] ?? ''), self::DESCRIPTION_MAX);
    $clean['tags'] = self::truncate(self::text($input['tags'] ?? ''), self::TAGS_MAX);

    $contact = trim(self::scalar($input['contact'] ?? ''));
    if ($contact !== '') {
      $email = sanitize_email($contact);

      if ($email === '' || !is_email($email)) {
        $errors['contact'] = __('The contact is not a valid email address.', 'add-browser-search');
      } else {
        $clean['contact'] = $email;
      }
    }

    foreach (['image' => __('The icon is not a valid web address.', 'add-browser-search'), 'search_form' => __('The search page is not a valid web address.', 'add-browser-search')] as $field => $message) {
      $value = trim(self::scalar($input[$field] ?? ''));

      if ($value === '') {
        continue;
      }

      $url = esc_url_raw($value, ['http', 'https']);

      if ($url === '') {
        $errors[$field] = $message;
      } else {
        $clean[$field] = $url;
      }
    }

    $template = trim(self::scalar($input['search_url'] ?? ''));
    if ($template !== '') {
      $url = self::template($template);

      if ($url === '') {
        $errors['search_url'] = sprintf(
          /* translators: %s: the {searchTerms} placeholder. */
          __('The search address must be a web address that contains %s.', 'add-browser-search'),
          self::SEARCH_TERMS
        );
      } else {
        $clean['search_url'] = $url;
      }
    }

    $clean['suggestions'] = !empty($input['suggestions']);

    return $clean;
  }

  /**
   * A search URL template, or an empty string when it is not one: http or https, with {searchTerms}.
   */
  public static function template(string $url): string
  {
    $clean = esc_url_raw(str_replace(self::SEARCH_TERMS, self::TOKEN, trim($url)), ['http', 'https']);

    // No placeholder in the address, or an address WordPress refuses, comes out as ''.
    if ($clean === '' || strpos($clean, self::TOKEN) === false) {
      return '';
    }

    return str_replace(self::TOKEN, self::SEARCH_TERMS, $clean);
  }

  /**
   * @param array<string, mixed> $settings Already sanitized.
   */
  public static function save(array $settings): void
  {
    update_option(self::OPTION, $settings);
  }

  public static function text(mixed $value): string
  {
    return sanitize_text_field(self::scalar($value));
  }

  /**
   * Cut by characters, never in the middle of a multibyte one, and without a space left at the end.
   */
  public static function truncate(string $value, int $max): string
  {
    $cut = function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);

    // The cut may leave a space at the end.
    return rtrim($cut);
  }

  private static function scalar(mixed $value): string
  {
    return is_scalar($value) ? (string) $value : '';
  }
}
