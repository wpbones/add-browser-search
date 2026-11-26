<?php

namespace AddBrowserSearch;

use AddBrowserSearch\WPBones\Routing\Csrf;

if (!defined('ABSPATH')) {
  exit();
}

/**
 * Settings > Add Browser Search.
 *
 * The form posts to the page itself. WP Bones refuses a POST without its nonce before this class
 * runs (Csrf::guard); load() checks the same nonce and the capability again, so the page is safe
 * on its own too.
 */
final class SettingsPage
{
  public const SLUG = 'add-browser-search';
  public const CAPABILITY = 'manage_options';
  public const FIELD = 'add_browser_search';

  /**
   * The plugin instance (a WP Bones Plugin).
   */
  private $plugin;

  private string $hook = '';

  /** @var array<string, string> */
  private array $errors = [];

  /**
   * What the form posted, to show a field that was refused as it was typed.
   *
   * @var array<string, mixed>
   */
  private array $submitted = [];

  public function __construct($plugin)
  {
    $this->plugin = $plugin;
  }

  public function register(): void
  {
    add_action('admin_menu', [$this, 'addPage']);
    add_filter('plugin_action_links_' . plugin_basename($this->plugin->file), [$this, 'actionLinks']);
  }

  public function addPage(): void
  {
    $hook = add_options_page(
      __('Add Browser Search', 'add-browser-search'),
      __('Add Browser Search', 'add-browser-search'),
      self::CAPABILITY,
      self::SLUG,
      [$this, 'render']
    );

    if (!$hook) {
      return;
    }

    $this->hook = $hook;

    Csrf::guard($hook, $this->plugin->csrfAction());
    add_action("load-{$hook}", [$this, 'load']);
    add_action('admin_enqueue_scripts', [$this, 'enqueue']);
  }

  /**
   * @param array<int, string> $links
   * @return array<int, string>
   */
  public function actionLinks(array $links): array
  {
    array_unshift($links, sprintf('<a href="%s">%s</a>', esc_url(self::url()), esc_html__('Settings', 'add-browser-search')));

    return $links;
  }

  public static function url(): string
  {
    return admin_url('options-general.php?page=' . self::SLUG);
  }

  public function enqueue(string $hook): void
  {
    if ($hook !== $this->hook) {
      return;
    }

    $version = (string) $this->plugin->Version;

    wp_enqueue_style('add-browser-search-settings', $this->plugin->css . '/settings.css', [], $version);
    wp_enqueue_script('add-browser-search-settings', $this->plugin->js . '/settings.js', [], $version, true);
  }

  /**
   * Runs before the page renders: a POST saves, then redirects so a reload does not post again.
   */
  public function load(): void
  {
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
      return;
    }

    if (!current_user_can(self::CAPABILITY)) {
      wp_die(esc_html__('Sorry, you are not allowed to manage these settings.', 'add-browser-search'), '', ['response' => 403]);
    }

    check_admin_referer($this->plugin->csrfAction(), Csrf::FIELD);

    $input = isset($_POST[self::FIELD]) && is_array($_POST[self::FIELD]) ? wp_unslash($_POST[self::FIELD]) : [];

    $this->errors = [];
    $this->submitted = $input;
    // A refused field keeps what was saved before; the valid ones are saved.
    Settings::save(Settings::merge(Settings::stored(), Settings::sanitize($input, $this->errors), $this->errors));

    if (!$this->errors) {
      wp_safe_redirect(add_query_arg('settings-updated', 'true', self::url()));
      exit();
    }
  }

  public function render(): void
  {
    // A 1.x site that never got the new option (it was only copied in) is settled here at the latest.
    Migration::run();

    $migrated = (bool) get_option(Migration::NOTICE_OPTION);

    if ($migrated) {
      delete_option(Migration::NOTICE_OPTION);
    }

    $resolved = Settings::resolved();
    $sample = rawurlencode(__('hello', 'add-browser-search'));

    $this->plugin
      ->view('settings.index')
      ->with([
        'settings' => Settings::stored(),
        'resolved' => $resolved,
        'errors' => $this->errors,
        'submitted' => $this->submitted,
        'saved' => empty($this->errors) && isset($_GET['settings-updated']), // phpcs:ignore WordPress.Security.NonceVerification
        'migrated' => $migrated,
        'field' => self::FIELD,
        'xml' => Document::forSite(),
        'urls' => [
          'description' => Urls::description(),
          'plain' => Urls::plainDescription(),
          'suggestions' => str_replace(Settings::SEARCH_TERMS, $sample, Urls::suggestions()),
          'search' => str_replace(Settings::SEARCH_TERMS, $sample, $resolved['search_url']),
        ],
        'limits' => ['short_name' => Settings::SHORT_NAME_MAX, 'description' => Settings::DESCRIPTION_MAX, 'tags' => Settings::TAGS_MAX],
        'defaults' => [
          'short_name' => Settings::defaultShortName(),
          'description' => Settings::defaultDescription(),
          'search_url' => Settings::defaultSearchUrl(),
          'search_form' => Settings::defaultSearchForm(),
        ],
      ])
      ->render();
  }
}
