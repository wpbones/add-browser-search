# Add Browser Search

Publish your site's search as an OpenSearch description, so browsers offer to add it as a search engine and can search your site from the address bar.

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/add-browser-search)](https://wordpress.org/plugins/add-browser-search/)
[![WordPress Plugin Required PHP Version](https://img.shields.io/wordpress/plugin/required-php/add-browser-search)](https://wordpress.org/plugins/add-browser-search/)
[![WordPress Plugin Tested WP Version](https://img.shields.io/wordpress/plugin/tested/add-browser-search)](https://wordpress.org/plugins/add-browser-search/)
[![License](https://img.shields.io/badge/license-GPLv2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

## Overview

The plugin serves an [OpenSearch 1.1](https://github.com/dewitt/opensearch) description of your WordPress search at `/opensearch.xml` and puts a `<link rel="search">` tag in the head of every page. A browser that reads it can offer "add this search engine", and a visitor who adds it searches your site from the address bar.

It works as soon as it is activated: every field defaults to something read from your site (title, tagline, site icon, search URL).

Built on the [WPBones](https://wpbones.com) framework. Version 2.0.0 is a rewrite of the 2009 plugin. It needs WordPress 6.6 and PHP 8.1, and migrates the settings of 1.x on its own.

## Links

- [WordPress.org Plugin Page](https://wordpress.org/plugins/add-browser-search/)
- [Support Forum](https://wordpress.org/support/plugin/add-browser-search/)
- [Changelog](readme.txt#L1)

## Features

| | |
|---|---|
| **Description** | `/opensearch.xml`, `Content-Type: application/opensearchdescription+xml; charset=UTF-8`. `ShortName` (at most 16 characters, enforced), `Description` (at most 1024), `Contact`, `Tags`, `Image` (the site icon at 16 and 64 px by default, with width, height and type), a `text/html` `Url` template (`/?s={searchTerms}` by default), `InputEncoding`, a `Url rel="self"` and `moz:SearchForm`. Also at `/?add_browser_search=opensearch`, whatever the permalinks. |
| **Discovery** | `<link rel="search" type="application/opensearchdescription+xml" title="…" href="…">` in `wp_head`. |
| **Suggestions** | On by default. `application/x-suggestions+json` at `/wp-json/add-browser-search/v1/suggest?q=…`, answering `["query", ["title", …]]` from the titles of published, non-password-protected posts and pages. Public, at most 8 titles, a query of at most 100 characters, cached for a minute. |
| **Settings** | Settings › Add Browser Search: the fields above, the address of the description with Copy and Open, a preview of the XML, and how to check it in Firefox and Chromium browsers. |
| **Migration** | The 1.x option `wp_abs_settings` is moved into `add_browser_search_settings` and deleted. Values that were only the site's defaults become empty fields, so they follow the site. |

## For developers

```php
// The address of the description
add_browser_search_url();

// How many suggestions (1 to 10, default 8)
add_filter('add_browser_search_suggestions_limit', fn() => 5);

// Which post types are searched (default: post, page)
add_filter('add_browser_search_suggestions_post_types', fn($types) => [...$types, 'product']);
```

Options: `add_browser_search_settings` (the settings), `add_browser_search_migrated` and `add_browser_search_rewrite` (flags), plus WP Bones' own `add_browser_search_slug*`. All are deleted when the plugin is deleted from the Plugins screen.

## Requirements

- WordPress >= 6.6
- PHP >= 8.1

## Installation

Install it from the Plugins screen, or upload the folder to `/wp-content/plugins/` and activate it. Then open Settings › Add Browser Search.

## Development

Needs Composer. There is no JavaScript build, so no `yarn install`.

```sh
composer install            # WP Bones, PHPUnit, Brain Monkey
composer test               # the unit suite, no WordPress needed
tests/live/probe.sh . <an unpacked WordPress folder>   # a real WordPress in a local Playground
php bones                   # the WP Bones CLI
php bones deploy ../build/add-browser-search --wp --no-build   # the package for WordPress.org
```

The main file is `wp-abs.php` and must stay so: WordPress tracks an active plugin by `folder/main-file`. WP Bones names the main file after the plugin, so `composer.json` patches the vendored `Plugin.php` after `rename --update` to keep it pointing at `wp-abs.php`.

## License

GPLv2 or later.
