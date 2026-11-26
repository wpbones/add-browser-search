# Add Browser Search
Contributors: gfazioli
Donate link: https://www.paypal.com/donate/?hosted_button_id=TBPD4R78VDMCJ
Tags: opensearch, search, browser, search engine, suggestions
Requires at least: 6.6
Tested up to: 7.2
Stable tag: 2.0.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publish your site's search as an OpenSearch description, so browsers offer to add it as a search engine and can search your site from the address bar.

## Description

Add Browser Search makes your WordPress search available to the browsers your visitors use. It publishes an [OpenSearch](https://github.com/dewitt/opensearch) description of your search at a stable address, `/opensearch.xml`, and points every page of your site at it. A browser that reads it can offer "add this search engine", and once it is added your visitors search your site straight from the address bar, without opening it first.

It works the moment you activate it. Every field has a sensible default read from your site, so the engine follows your site title, tagline and site icon when you change them. The Settings page is there for when you want to change something.

### What it publishes

* **A valid OpenSearch 1.1 description**, served as `application/opensearchdescription+xml`, with the name (at most 16 characters, as the standard says), the description, an optional contact and tags, the icon, the search address, the search form page and a self link browsers use to keep the engine up to date.
* **Search suggestions**, on by default: while someone types, the browser asks your site for matching titles. The answer comes from a small public REST route that searches the titles of published posts and pages, lists at most 8, never lists password-protected posts and is cached for a minute.
* **A `<link rel="search">` tag** in the head of every page: that is what browsers look for.

### Settings

Under Settings › Add Browser Search:

* **Name, Description, Contact, Tags.** Empty means the site title, the tagline, none and none.
* **Icon address.** Empty means your site icon (Appearance › Customize › Site Identity), advertised at 16 and 64 pixels.
* **Search address.** Where the browser sends the typed words, with `{searchTerms}` where they go. Empty means your own search (`/?s={searchTerms}`). You can point it elsewhere, for instance at a Google Programmable Search page.
* **Search page.** The page with your search form. Empty means your home page.
* **Suggestions.** On or off.
* The address of the description, with Copy and Open buttons, a preview of the XML browsers receive, and short notes on how Firefox and the Chromium browsers pick the engine up.

### For developers

* `add_browser_search_url()` returns the address of the description.
* The `add_browser_search_suggestions_limit` filter changes how many suggestions are returned (1 to 10, 8 by default).
* The `add_browser_search_suggestions_post_types` filter changes which post types are searched (`post` and `page` by default).
* The suggestions are at `/wp-json/add-browser-search/v1/suggest?q=…` and answer `["query", ["title", …]]`.

Documentation and source: [github.com/wpbones/add-browser-search](https://github.com/wpbones/add-browser-search). Built on the [WP Bones](https://wpbones.com) framework.

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`, or install it from the Plugins screen.
2. Activate the plugin through the Plugins screen.
3. Open Settings › Add Browser Search to see the address of the description and, if you like, change the defaults.
4. Open `https://your-site.example/opensearch.xml` in a browser to see the description.

If you are upgrading from 1.x, your settings were moved for you: see the changelog.

## Frequently Asked Questions

= Where is the description? =

At `/opensearch.xml` on your site. The same description is always available at `/?add_browser_search=opensearch`, which does not depend on permalinks. A site without pretty permalinks uses that address in its pages.

= `/opensearch.xml` gives a 404 =

Some web servers answer every request for a `.xml` file by themselves and never hand it to WordPress. Use the address with `?add_browser_search=opensearch` instead, or ask your host to pass `/opensearch.xml` to `index.php`. The Settings page shows both addresses.

= Which browsers use it? =

Firefox and the browsers built on Chromium (Chrome, Edge, Brave and others) read OpenSearch descriptions. Safari does not. What each browser offers, and where, changes from one version to the next: the Settings page has a short note for Firefox and for Chromium browsers.

= The icon does not show up =

Set a site icon in Appearance › Customize › Site Identity, or give an icon address on the Settings page. A browser can keep the icon it fetched when the engine was added for a while.

= Does it add anything to my pages? =

One short `<link rel="search">` tag in the head. Nothing is loaded in the front end.

= What does it send, and to whom? =

Nothing: the plugin makes no request to any other site and collects no data. It only answers requests that browsers make to your own site. The contact address you may enter is published in the description, so use one you are happy to show.

= Are the suggestions safe? =

The route is public, because a browser sends no login with it. It can only read the titles of published posts and pages that are not password protected, it limits the query to 100 characters and the answer to 8 titles, and it caches the answer for a minute. Turn Suggestions off on the Settings page if you do not want it: the route then answers 404 and the description no longer mentions it.

= I used version 1.x. What happened to my settings? =

They were moved into the new settings the first time the plugin loaded, and the old option was deleted. Values that were only your site's defaults (the title, the tagline, `favicon.ico`, the search address) became empty fields, so they follow your site from now on. See the changelog for the details.

= Does it work on multisite? =

Each site publishes its own description, from its own settings. Activate the plugin on the sites that want it.

= Can I request a new feature? =

Yes! Please submit feature requests at [the support forum](https://wordpress.org/support/plugin/add-browser-search/).

= Can I report a bug? =

Yes! Please report bugs at [the support forum](https://wordpress.org/support/plugin/add-browser-search/).

## Screenshots

1. The Settings page: the address of the description with its Copy and Open buttons, and the fields (name, description, contact, tags, icon, search address, search page, suggestions).
2. The preview of the XML browsers receive, and the notes that tell how to check it in Firefox and in the Chromium browsers.

## Changelog

### 2.0.0

A rewrite. The plugin keeps its purpose and its folder and file names, so an update does not deactivate it, and everything else is new. It now needs WordPress 6.6 and PHP 8.1 (1.x was written for WordPress 2.6).

What changes for a site that used 1.x:

* **Your settings are migrated.** On the first load the 1.x settings (`wp_abs_settings`) are moved into the new ones and the old option is deleted. Name, description, contact, tags, the icon (the 64 pixel image if you set one, else a favicon of your own), the search address and the search page all come across. Values that were the site's own defaults (title, tagline, `favicon.ico` in the site root, `/?s={searchTerms}`, the home page) become empty fields, so they follow your site from now on. The settings page tells you once that this happened.
* **The address stays `/opensearch.xml`.** It is now a proper rewrite rule (flushed on activation and deactivation) and is served as `application/opensearchdescription+xml`, not as `text/html`. The old plugin had no template tag; the constants `OPENSEARCH_XML` and `WPABS_VERSION` still exist.
* **The settings page moved and was rewritten.** It is Settings › Add Browser Search. The 1.x page had no nonce and no sanitising; this one checks the capability and a nonce, sanitises every field, and tells you which field it refused and why.
* **The administrator's email is no longer the default contact.** 1.x published it by default. The contact is empty unless you fill it in: a 1.x contact equal to the site's administration email is dropped by the migration, and any other address you entered comes across as it was.
* **A name over 16 characters is cut**, as OpenSearch requires (a longer name made the description invalid).
* **The icon**: your site icon is advertised at 16 and 64 pixels, with width, height and type, instead of a guessed `/favicon.ico`.
* New: search suggestions in the browser's address bar, from a small public REST route (on by default, can be turned off).
* New: the description declares `InputEncoding`, a self link and `moz:SearchForm`, and the output is escaped (1.x printed the settings as they were typed).
* New: Italian, Spanish, French, German and Brazilian Portuguese translations.
* New: unit tests and a live check on WordPress 7.2.
* The plugin's options are removed when you delete it from the Plugins screen, not when you deactivate it.

### 1.x

* 1.24 Minor rev
* 1.23 Test on WordPress 2.7
* 1.22 Fix options page
* 1.21 Fix WordPress version/check bug
* 1.2 Improve admin panel and add some extra setting for OpenSearch standard
* 1.12 Fix link configuration panel in plugin description (thanks emanuele)
* 1.11 Fix doc and links
* 1.1 Added WP ABS configuration panel
* 1.0 First release

## Upgrade Notice

### 2.0.0

A rewrite: it needs WordPress 6.6 and PHP 8.1. Your 1.x settings are moved automatically; check Settings › Add Browser Search afterwards.
