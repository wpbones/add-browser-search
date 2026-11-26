<?php
/**
 * Settings > Add Browser Search.
 *
 * Variables: $plugin (the WP Bones plugin), $settings (saved), $resolved (what the document uses),
 * $errors, $submitted, $saved, $migrated, $field (the name of the form array), $xml, $urls,
 * $limits, $defaults.
 */

if (!defined('ABSPATH')) {
  exit();
}

/**
 * The value to show in a field: what was typed when it was refused, else what is saved.
 */
$abs_value = static function (string $key) use ($settings, $errors, $submitted) {
  if (isset($errors[$key])) {
    return is_scalar($submitted[$key] ?? '') ? (string) ($submitted[$key] ?? '') : '';
  }

  return (string) $settings[$key];
};
?>
<div class="wrap add-browser-search">
  <h1><?php esc_html_e('Add Browser Search', 'add-browser-search'); ?></h1>

  <p class="description">
    <?php esc_html_e('Your site publishes its search as an OpenSearch description. Browsers read it to offer "add this search engine" and to search your site straight from the address bar.', 'add-browser-search'); ?>
  </p>

  <?php if ($migrated) : ?>
    <div class="notice notice-info is-dismissible"><p>
      <?php esc_html_e('The settings of the previous version were moved here. Look them over and save.', 'add-browser-search'); ?>
    </p></div>
  <?php endif; ?>

  <?php if ($saved) : ?>
    <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved.', 'add-browser-search'); ?></p></div>
  <?php endif; ?>

  <?php if ($errors) : ?>
    <div class="notice notice-error"><p><strong><?php esc_html_e('Some fields were not saved:', 'add-browser-search'); ?></strong></p>
      <ul>
        <?php foreach ($errors as $message) : ?>
          <li><?php echo esc_html($message); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <h2><?php esc_html_e('Your search engine', 'add-browser-search'); ?></h2>

  <table class="form-table" role="presentation">
    <tr>
      <th scope="row"><label for="abs-description-url"><?php esc_html_e('Description address', 'add-browser-search'); ?></label></th>
      <td>
        <div class="abs-copy">
          <input type="text" id="abs-description-url" class="large-text code" readonly value="<?php echo esc_attr($urls['description']); ?>" />
          <button type="button" class="button" data-abs-copy="#abs-description-url" data-copied="<?php esc_attr_e('Copied', 'add-browser-search'); ?>"><?php esc_html_e('Copy', 'add-browser-search'); ?></button>
          <a class="button" href="<?php echo esc_url($urls['description']); ?>" target="_blank" rel="noopener"><?php esc_html_e('Open', 'add-browser-search'); ?></a>
        </div>
        <p class="description">
          <?php
          printf(
            /* translators: %s: an address. */
            esc_html__('Always works, whatever the permalinks: %s', 'add-browser-search'),
            '<code>' . esc_html($urls['plain']) . '</code>'
          );
          ?>
        </p>
      </td>
    </tr>
  </table>

  <form method="post" action="<?php echo esc_url(\AddBrowserSearch\SettingsPage::url()); ?>">
    <?php echo $plugin->csrfField(); // phpcs:ignore WordPress.Security.EscapeOutput -- hidden nonce fields built by WordPress. ?>

    <table class="form-table" role="presentation">
      <tr>
        <th scope="row"><label for="abs-short-name"><?php esc_html_e('Name', 'add-browser-search'); ?></label></th>
        <td>
          <input type="text" id="abs-short-name" name="<?php echo esc_attr($field); ?>[short_name]" class="regular-text" maxlength="<?php echo (int) $limits['short_name']; ?>"
            value="<?php echo esc_attr($abs_value('short_name')); ?>" placeholder="<?php echo esc_attr($defaults['short_name']); ?>" data-abs-count="<?php echo (int) $limits['short_name']; ?>" />
          <span class="abs-count" aria-live="polite"></span>
          <p class="description"><?php esc_html_e('What the browser calls the engine. At most 16 characters. Empty: your site title.', 'add-browser-search'); ?></p>
          <?php if (isset($errors['short_name'])) : ?><p class="abs-error"><?php echo esc_html($errors['short_name']); ?></p><?php endif; ?>
        </td>
      </tr>
      <tr>
        <th scope="row"><label for="abs-description"><?php esc_html_e('Description', 'add-browser-search'); ?></label></th>
        <td>
          <textarea id="abs-description" name="<?php echo esc_attr($field); ?>[description]" class="large-text" rows="3" maxlength="<?php echo (int) $limits['description']; ?>"
            placeholder="<?php echo esc_attr($defaults['description']); ?>" data-abs-count="<?php echo (int) $limits['description']; ?>"><?php echo esc_textarea($abs_value('description')); ?></textarea>
          <span class="abs-count" aria-live="polite"></span>
          <p class="description"><?php esc_html_e('A sentence about what the search finds. At most 1024 characters. Empty: your tagline.', 'add-browser-search'); ?></p>
        </td>
      </tr>
      <tr>
        <th scope="row"><label for="abs-contact"><?php esc_html_e('Contact', 'add-browser-search'); ?></label></th>
        <td>
          <input type="email" id="abs-contact" name="<?php echo esc_attr($field); ?>[contact]" class="regular-text" value="<?php echo esc_attr($abs_value('contact')); ?>" />
          <p class="description"><?php esc_html_e('An email address people can write to about this search engine. It is public: the description lists it. Empty: none.', 'add-browser-search'); ?></p>
          <?php if (isset($errors['contact'])) : ?><p class="abs-error"><?php echo esc_html($errors['contact']); ?></p><?php endif; ?>
        </td>
      </tr>
      <tr>
        <th scope="row"><label for="abs-tags"><?php esc_html_e('Tags', 'add-browser-search'); ?></label></th>
        <td>
          <input type="text" id="abs-tags" name="<?php echo esc_attr($field); ?>[tags]" class="regular-text" maxlength="<?php echo (int) $limits['tags']; ?>" value="<?php echo esc_attr($abs_value('tags')); ?>" />
          <p class="description"><?php esc_html_e('Keywords, separated by spaces.', 'add-browser-search'); ?></p>
        </td>
      </tr>
      <tr>
        <th scope="row"><label for="abs-image"><?php esc_html_e('Icon address', 'add-browser-search'); ?></label></th>
        <td>
          <input type="url" id="abs-image" name="<?php echo esc_attr($field); ?>[image]" class="large-text code" value="<?php echo esc_attr($abs_value('image')); ?>" />
          <p class="description"><?php esc_html_e('The icon next to the engine in the browser: a PNG, or an .ico. Empty: your site icon (Appearance > Customize > Site Identity), if you set one.', 'add-browser-search'); ?></p>
          <?php if (isset($errors['image'])) : ?><p class="abs-error"><?php echo esc_html($errors['image']); ?></p><?php endif; ?>
        </td>
      </tr>
      <tr>
        <th scope="row"><label for="abs-search-url"><?php esc_html_e('Search address', 'add-browser-search'); ?></label></th>
        <td>
          <input type="text" id="abs-search-url" name="<?php echo esc_attr($field); ?>[search_url]" class="large-text code" value="<?php echo esc_attr($abs_value('search_url')); ?>" placeholder="<?php echo esc_attr($defaults['search_url']); ?>" />
          <p class="description">
            <?php
            printf(
              /* translators: %s: the {searchTerms} placeholder. */
              esc_html__('Where the browser sends the typed words: put %s where they go. Empty: your own search. You can point it at another search, such as a Google Programmable Search page.', 'add-browser-search'),
              '<code>{searchTerms}</code>'
            );
            ?>
          </p>
          <?php if (isset($errors['search_url'])) : ?><p class="abs-error"><?php echo esc_html($errors['search_url']); ?></p><?php endif; ?>
        </td>
      </tr>
      <tr>
        <th scope="row"><label for="abs-search-form"><?php esc_html_e('Search page', 'add-browser-search'); ?></label></th>
        <td>
          <input type="url" id="abs-search-form" name="<?php echo esc_attr($field); ?>[search_form]" class="large-text code" value="<?php echo esc_attr($abs_value('search_form')); ?>" placeholder="<?php echo esc_attr($defaults['search_form']); ?>" />
          <p class="description"><?php esc_html_e('The page with your search form, which Firefox opens when someone chooses to search from the engine itself. Empty: your home page.', 'add-browser-search'); ?></p>
          <?php if (isset($errors['search_form'])) : ?><p class="abs-error"><?php echo esc_html($errors['search_form']); ?></p><?php endif; ?>
        </td>
      </tr>
      <tr>
        <th scope="row"><?php esc_html_e('Suggestions', 'add-browser-search'); ?></th>
        <td>
          <label for="abs-suggestions">
            <input type="checkbox" id="abs-suggestions" name="<?php echo esc_attr($field); ?>[suggestions]" value="1" <?php checked($settings['suggestions']); ?> />
            <?php esc_html_e('Suggest the titles of posts and pages while someone types in the address bar', 'add-browser-search'); ?>
          </label>
          <p class="description"><?php esc_html_e('A public address answers with at most 8 published titles, cached for a minute. Password-protected posts are never listed.', 'add-browser-search'); ?></p>
        </td>
      </tr>
    </table>

    <?php submit_button(); ?>
  </form>

  <h2><?php esc_html_e('Preview', 'add-browser-search'); ?></h2>
  <p class="description"><?php esc_html_e('What browsers receive from the address above, as saved. Save first to see a change.', 'add-browser-search'); ?></p>
  <pre class="abs-xml" id="abs-xml" tabindex="0" data-abs-url="<?php echo esc_attr($urls['description']); ?>"><?php echo esc_html($xml); ?></pre>

  <h2><?php esc_html_e('Check it', 'add-browser-search'); ?></h2>
  <ol class="abs-check">
    <li>
      <?php
      printf(
        /* translators: %s: an address. */
        esc_html__('Open your site in a new tab: its page source holds a %s tag, and the address above shows the XML.', 'add-browser-search'),
        '<code>&lt;link rel="search" …&gt;</code>'
      );
      ?>
    </li>
    <li><?php esc_html_e('Firefox: open the search menu, or the address bar menu, on any page of your site and choose "Add" with your engine\'s name. Then type its keyword, or pick it as the default.', 'add-browser-search'); ?></li>
    <li><?php esc_html_e('Chrome, Edge, Brave and other Chromium browsers: visit your site and search it once; the engine appears under Settings > Search engine > Manage search engines and site search, where you can give it a shortcut. Typing your site address and pressing Tab or Space then searches it.', 'add-browser-search'); ?></li>
    <li><?php esc_html_e('Safari does not read OpenSearch descriptions.', 'add-browser-search'); ?></li>
    <li>
      <?php esc_html_e('Try the two addresses a browser calls:', 'add-browser-search'); ?>
      <a href="<?php echo esc_url($urls['search']); ?>" target="_blank" rel="noopener"><?php esc_html_e('a search', 'add-browser-search'); ?></a><?php if ($settings['suggestions']) : ?>,
        <a href="<?php echo esc_url($urls['suggestions']); ?>" target="_blank" rel="noopener"><?php esc_html_e('the suggestions', 'add-browser-search'); ?></a><?php endif; ?>.
    </li>
  </ol>
</div>
