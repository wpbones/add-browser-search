<?php

namespace AddBrowserSearch;

if (!defined('ABSPATH')) {
  exit();
}

/**
 * Moves the settings of version 1.x (the `wp_abs_settings` option) into the 2.0 ones.
 *
 * It runs on the first load that finds no 2.0 settings, so it does not matter how the new version
 * arrived: an update through WordPress, files copied by hand, or a fresh activation. It is safe to
 * run twice: it never overwrites settings that exist, and it deletes the old option when it is done.
 *
 * A 1.x site saved its defaults too (the title, the description, the administrator's email, the
 * favicon and the search address as they were on the day it was installed). A value that is still
 * the site's own default becomes an empty field here, so the engine follows the site from now on.
 */
final class Migration
{
  /**
   * Set once, so the settings page can tell the administrator that the old settings came along.
   */
  public const NOTICE_OPTION = 'add_browser_search_migrated';

  /**
   * Settle the settings option, and drop the old one.
   *
   * Without settings yet, the old ones are moved in, or the defaults saved when there are none: from
   * then on the option exists, so a request that finds it skips this method's work. With settings,
   * only the old key goes: they are the administrator's.
   *
   * @return bool True when 1.x settings were moved.
   */
  public static function run(): bool
  {
    $legacy = get_option(Settings::LEGACY_OPTION, null);
    $moved = false;

    if (get_option(Settings::OPTION, null) === null) {
      $moved = is_array($legacy);
      Settings::save($moved ? self::map($legacy) : Settings::defaults());

      if ($moved) {
        add_option(self::NOTICE_OPTION, '1', '', false);
      }
    }

    if ($legacy !== null) {
      delete_option(Settings::LEGACY_OPTION);
    }

    return $moved;
  }

  /**
   * The 1.x keys, as the 2.0 settings.
   *
   * @param array<string, mixed> $old
   * @return array<string, mixed>
   */
  public static function map(array $old): array
  {
    $errors = [];
    $title = Settings::siteText('name');
    $tagline = Settings::siteText('description');

    // 1.x seeded these with the escaped values core stores ("Tom &amp; Jerry"): compare and keep plain text.
    $name = trim(wp_specialchars_decode(Settings::text($old['wp_abs_blogname'] ?? ''), ENT_QUOTES));
    $description = trim(wp_specialchars_decode(Settings::text($old['wp_abs_description'] ?? ''), ENT_QUOTES));

    // 1.x defaulted the contact to the administrator's email and published it: that default is not kept.
    $contact = trim(self::string($old['wp_abs_contact'] ?? ''));
    $adminEmail = trim((string) get_option('admin_email'));

    if ($adminEmail !== '' && strcasecmp($contact, $adminEmail) === 0) {
      $contact = '';
    }

    // The old plugin's own default for the favicon was /favicon.ico of this site: now it is the site icon.
    $favicon = trim(self::string($old['wp_abs_favicon'] ?? ''));
    $image = trim(self::string($old['wp_abs_image'] ?? ''));
    $icon = $image !== '' ? $image : $favicon;

    if ($image === '' && self::sameAddress($favicon, home_url('/favicon.ico'))) {
      $icon = '';
    }

    $search = trim(self::string($old['wp_abs_searchurl'] ?? ''));
    $form = trim(self::string($old['wp_abs_searchform'] ?? ''));

    $input = [
      'short_name' => $name === $title ? '' : $name,
      'description' => $description === $tagline ? '' : $description,
      'contact' => $contact,
      'tags' => self::string($old['wp_abs_tags'] ?? ''),
      'image' => $icon,
      'search_url' => self::sameAddress($search, Settings::defaultSearchUrl()) ? '' : $search,
      'search_form' => self::sameAddress($form, Settings::defaultSearchForm()) ? '' : $form,
      'suggestions' => true,
    ];

    // Same cleaning as the form: a value 1.x let through that 2.0 refuses comes out empty.
    return Settings::sanitize($input, $errors);
  }

  /**
   * Two web addresses, compared without the scheme, the case or a trailing slash.
   */
  private static function sameAddress(string $a, string $b): bool
  {
    $normalise = static fn(string $url): string => strtolower(rtrim((string) preg_replace('#^https?://#i', '', trim($url)), '/'));

    return $a !== '' && $normalise($a) === $normalise($b);
  }

  private static function string(mixed $value): string
  {
    return is_scalar($value) ? (string) $value : '';
  }
}
