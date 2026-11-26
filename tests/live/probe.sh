#!/usr/bin/env bash
# probe.sh — Add Browser Search on a real WordPress, in a local Playground: what a browser, a visitor
# and an administrator actually get. The unit suite cannot show these (headers, rewrite rules, the
# settings form's nonce, activation and deactivation, the migration from 1.x).
#
# Usage: tests/live/probe.sh <plugin folder or deploy output> <WordPress folder>
#   <WordPress folder>  an unpacked release (see .claude/scripts/plugin-playground-smoke.sh, which
#                       explains why a folder and not --wp=). PORT= picks the port (default 9431).
#
# The plugin is copied to a scratch folder first; nothing outside it is written. A 1.x settings
# option is planted before the plugin is activated, so activation migrates it. Exit 0 = every check
# passed and debug.log is empty.
set -uo pipefail

PLUGIN=${1:?usage: probe.sh <plugin folder> <WordPress folder>}
WPV=${2:?WordPress folder}
PORT=${PORT:-9431}
SLUG=add-browser-search
MAIN=wp-abs.php
BASE="http://127.0.0.1:$PORT"
PLAYGROUND_CLI=@wp-playground/cli@3.1.55

[ -f "$PLUGIN/$MAIN" ] || { echo "✗ $PLUGIN/$MAIN not found"; exit 1; }
[ -f "$WPV/wp-includes/version.php" ] || { echo "✗ $WPV is not a WordPress folder"; exit 1; }
lsof -ti tcp:$PORT >/dev/null && { echo "✗ port $PORT is already in use"; exit 1; }

T=$(mktemp -d "${TMPDIR:-/tmp}/abs-probe.XXXXXX")
cleanup() {
  local pids
  pids=$(lsof -ti tcp:$PORT); [ -n "$pids" ] && kill $pids 2>/dev/null
  rm -rf "$T"
}
trap cleanup EXIT

rsync -a --exclude node_modules --exclude .git "$PLUGIN/" "$T/$SLUG/"
cp -R "$WPV" "$T/wordpress"
mkdir -p "$T/logs" "$T/probe" "$T/mu"

# WordPress refuses a WPLANG whose language pack is not installed, so the locale is switched by a
# must-use plugin that reads a flag file the probe page creates and removes.
cat > "$T/mu/abs-locale.php" <<'PHP'
<?php
add_filter('locale', static fn($locale) => file_exists('/logs/de.flag') ? 'de_DE' : $locale);
PHP

# A page the probe calls to read and change the site from inside, since Playground has no WP-CLI.
cat > "$T/probe/probe.php" <<'PHP'
<?php
require '/wordpress/wp-load.php';
header('Content-Type: application/json');
$a = $_GET['a'] ?? '';
switch ($a) {
  case 'state':
    echo wp_json_encode([
      'new' => get_option('add_browser_search_settings', null),
      'legacy' => get_option('wp_abs_settings', null),
      'migrated' => get_option('add_browser_search_migrated', null),
      'rewrite' => get_option('add_browser_search_rewrite', null),
      'rule' => isset(get_option('rewrite_rules')['^opensearch\.xml$']),
      'active' => is_plugin_active('add-browser-search/wp-abs.php'),
    ]);
    break;
  case 'plain':
    $GLOBALS['wp_rewrite']->set_permalink_structure('');
    flush_rewrite_rules();
    echo '{}';
    break;
  case 'pretty':
    $GLOBALS['wp_rewrite']->set_permalink_structure('/%postname%/');
    flush_rewrite_rules();
    echo '{}';
    break;
  case 'deactivate':
    deactivate_plugins('add-browser-search/wp-abs.php');
    echo '{}';
    break;
  case 'activate':
    echo wp_json_encode(['error' => is_wp_error($r = activate_plugin('add-browser-search/wp-abs.php')) ? $r->get_error_message() : null]);
    break;
  case 'update_by_copy':
    // What WordPress finds after 1.x files are replaced by 2.0 ones while the plugin stays active: no
    // activation hook runs, there are only the old option, no new settings and no rewrite rule.
    delete_option('add_browser_search_settings');
    delete_option('add_browser_search_rewrite');
    update_option('wp_abs_settings', [
      'wp_abs_blogname' => 'Copied Over', 'wp_abs_description' => 'Moved without activation', 'wp_abs_contact' => '',
      'wp_abs_tags' => '', 'wp_abs_favicon' => '', 'wp_abs_image' => '',
      'wp_abs_searchurl' => home_url('/?s={searchTerms}'), 'wp_abs_searchform' => home_url(),
    ]);
    $rules = get_option('rewrite_rules');
    unset($rules['^opensearch\\.xml$']);
    update_option('rewrite_rules', $rules);
    echo '{}';
    break;
  case 'locale_de':
    touch('/logs/de.flag');
    echo '{}';
    break;
  case 'locale_en':
    @unlink('/logs/de.flag');
    echo '{}';
    break;
  case 'uninstall':
    define('WP_UNINSTALL_PLUGIN', 'add-browser-search/wp-abs.php');
    include WP_PLUGIN_DIR . '/add-browser-search/uninstall.php';
    wp_cache_flush();
    echo wp_json_encode([
      'new' => get_option('add_browser_search_settings', null),
      'migrated' => get_option('add_browser_search_migrated', null),
      'rewrite' => get_option('add_browser_search_rewrite', null),
      'legacy' => get_option('wp_abs_settings', null),
      'slug' => get_option('add_browser_search_slug', null),
    ]);
    break;
}
PHP

SETUP=$(cat <<'PHP'
<?php
require '/wordpress/wp-load.php';
$GLOBALS['wp_rewrite']->set_permalink_structure('/%postname%/');
wp_insert_post(['post_title' => 'Hello Probe Post', 'post_status' => 'publish', 'post_content' => 'x']);
wp_insert_post(['post_title' => 'Hello Probe Page', 'post_status' => 'publish', 'post_type' => 'page', 'post_content' => 'x']);
wp_insert_post(['post_title' => 'Hello Draft Secret', 'post_status' => 'draft', 'post_content' => 'x']);
wp_insert_post(['post_title' => 'Hello Private Secret', 'post_status' => 'private', 'post_content' => 'x']);
wp_insert_post(['post_title' => 'Hello Password Secret', 'post_status' => 'publish', 'post_password' => 'pw', 'post_content' => 'x']);
wp_insert_post(['post_title' => 'Only in the body', 'post_status' => 'publish', 'post_content' => 'zebra-needle']);
update_option('blogname', 'Acme Probe Shop Of Everything');
wp_insert_user(['user_login' => 'absadmin', 'user_pass' => 'pass-123-ABS', 'user_email' => 'absadmin@example.test', 'role' => 'administrator']);
wp_insert_user(['user_login' => 'abssub', 'user_pass' => 'pass-123-ABS', 'user_email' => 'abssub@example.test', 'role' => 'subscriber']);
update_option('wp_abs_settings', [
  'wp_abs_blogname' => 'Acme Search',
  'wp_abs_description' => 'Find anything at Acme',
  'wp_abs_contact' => 'hi@acme.test',
  'wp_abs_tags' => 'acme shop',
  'wp_abs_favicon' => home_url('/favicon.ico'),
  'wp_abs_image' => '',
  'wp_abs_searchurl' => 'https://www.google.com/search?q={searchTerms}&hl=it',
  'wp_abs_searchform' => home_url(),
]);
PHP
)
SETUP_JSON=$(printf '%s' "$SETUP" | python3 -c 'import json,sys; print(json.dumps(sys.stdin.read()))')

cat > "$T/blueprint.json" <<JSON
{
  "steps": [
    { "step": "defineWpConfigConsts", "consts": { "WP_DEBUG": true, "WP_DEBUG_LOG": "/logs/debug.log", "WP_DEBUG_DISPLAY": false } },
    { "step": "runPHP", "code": $SETUP_JSON },
    { "step": "activatePlugin", "pluginPath": "$SLUG/$MAIN" }
  ]
}
JSON

( cd "$T" && exec npx -y "$PLAYGROUND_CLI" server --blueprint="$T/blueprint.json" --port=$PORT \
  --mount-before-install="$T/wordpress:/wordpress" --wordpress-install-mode=install-from-existing-files \
  --mount="$T/$SLUG:/wordpress/wp-content/plugins/$SLUG" --mount="$T/logs:/logs" --mount="$T/probe:/wordpress/abs-probe" --mount="$T/mu:/wordpress/wp-content/mu-plugins" \
  > "$T/playground.log" 2>&1 ) &
server=$!
for _ in $(seq 1 120); do grep -q 'Ready!' "$T/playground.log" 2>/dev/null && break; kill -0 $server 2>/dev/null || break; perl -e 'select(undef, undef, undef, 2)'; done
grep -q 'Ready!' "$T/playground.log" || { echo "✗ Playground did not start:"; tail -5 "$T/playground.log"; exit 1; }

fail=0
ok() { echo "  ✓ $1"; }
bad() { echo "  ✗ $1"; fail=1; }
check() { if [ "$2" = "$3" ]; then ok "$1"; else bad "$1 — expected [$3] got [$2]"; fi; }
has() { if grep -qF -- "$3" <<<"$2"; then ok "$1"; else bad "$1 — [$3] not found"; fi; }
hasi() { if grep -qiF -- "$3" <<<"$2"; then ok "$1"; else bad "$1 — [$3] not found"; fi; }
hasnt() { if grep -qF -- "$3" <<<"$2"; then bad "$1 — [$3] found"; else ok "$1"; fi; }
probe() { curl -s "$BASE/abs-probe/probe.php?a=$1"; }
jqv() { python3 -c "import json,sys; d=json.load(sys.stdin)$1; print(json.dumps(d) if isinstance(d,(dict,list,bool)) or d is None else d)"; }

# No `login` step in the blueprint: it would log every cookie-less request in, and the anonymous
# checks below (what a browser or a visitor gets) would be an administrator's.
login() { # <jar> <user> <password>: the first requests after "Ready!" can be bounced by Playground itself, so it tries a few times
  local try
  for try in 1 2 3 4; do
    curl -s -o /dev/null "$BASE/wp-login.php"
    _login "$@"
    grep -q wordpress_logged_in "$1" 2>/dev/null && return 0
    perl -e 'select(undef, undef, undef, 2)'
  done
  return 1
}
_login() {
  curl -s -c "$1" -b "$1" -o "$T/login.html" -D "$T/login.hdr" -H 'Cookie: wordpress_test_cookie=WP%20Cookie%20check' \
    --data-urlencode "log=$2" --data-urlencode "pwd=$3" --data 'testcookie=1' "$BASE/wp-login.php"
}
J="$T/admin.jar"
login "$J" absadmin pass-123-ABS
[ -n "$(grep wordpress_logged_in "$J")" ] && ok "logged in as admin" || { bad "could not log in as admin"; head -20 "$T/login.hdr" >&2; grep -io 'id="login_error".*' "$T/login.html" | head -3 >&2 ; }

echo "• the description"
headers=$(curl -s -D - -o "$T/doc.xml" "$BASE/opensearch.xml")
check "anonymous GET /opensearch.xml → 200" "$(printf '%s' "$headers" | head -1 | tr -d '\r' | awk '{print $2}')" 200
hasi "Content-Type is the OpenSearch type" "$(printf '%s' "$headers" | tr -d '\r')" "Content-Type: application/opensearchdescription+xml; charset=UTF-8"
if command -v xmllint >/dev/null; then
  xmllint --noout "$T/doc.xml" 2>"$T/xmllint.err" && ok "xmllint: well-formed" || bad "xmllint: $(cat "$T/xmllint.err")"
fi
doc=$(cat "$T/doc.xml")
has "migrated name" "$doc" "<ShortName>Acme Search</ShortName>"
has "migrated description" "$doc" "<Description>Find anything at Acme</Description>"
has "migrated contact" "$doc" "<Contact>hi@acme.test</Contact>"
has "migrated tags" "$doc" "<Tags>acme shop</Tags>"
has "migrated search address, & escaped" "$doc" 'template="https://www.google.com/search?q={searchTerms}&amp;hl=it"'
has "suggestions url" "$doc" 'type="application/x-suggestions+json"'
has "self url" "$doc" "rel=\"self\" type=\"application/opensearchdescription+xml\" template=\"$BASE/opensearch.xml\""
has "search form defaults to the home page" "$doc" "<moz:SearchForm>$BASE/</moz:SearchForm>"
has "InputEncoding" "$doc" "<InputEncoding>UTF-8</InputEncoding>"
code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/?add_browser_search=opensearch")
check "the query-string address answers too" "$code" 200

echo "• the head link"
home=$(curl -s "$BASE/")
has "home page has the search link" "$home" '<link rel="search" type="application/opensearchdescription+xml" title="Acme Search" href="'"$BASE"'/opensearch.xml" />'

echo "• the migration"
state=$(probe state)
check "old option is gone" "$(printf '%s' "$state" | jqv "['legacy']")" null
check "new option holds the name" "$(printf '%s' "$state" | jqv "['new']['short_name']")" "Acme Search"
check "favicon.ico default dropped, icon empty" "$(printf '%s' "$state" | jqv "['new']['image']")" ""
check "the rewrite rule is stored" "$(printf '%s' "$state" | jqv "['rule']")" true

echo "• the suggestions"
headers=$(curl -s -D - -o "$T/sug.json" "$BASE/wp-json/add-browser-search/v1/suggest?q=hello")
hasi "Content-Type" "$(printf '%s' "$headers" | tr -d '\r')" "Content-Type: application/x-suggestions+json; charset=UTF-8"
hasi "Cache-Control" "$(printf '%s' "$headers" | tr -d '\r')" "Cache-Control: public, max-age=60"
sug=$(cat "$T/sug.json")
has "published post and page are suggested" "$sug" "Hello Probe Post"
has "…and the page" "$sug" "Hello Probe Page"
hasnt "draft is not suggested" "$sug" "Draft"
hasnt "private post is not suggested" "$sug" "Private"
hasnt "password-protected post is not suggested" "$sug" "Password"
check "format is [query, [titles]]" "$(printf '%s' "$sug" | jqv "[0]")" hello
check "only titles are searched, not content" "$(curl -s "$BASE/wp-json/add-browser-search/v1/suggest?q=zebra-needle")" '["zebra-needle",[]]'
check "empty query" "$(curl -s "$BASE/wp-json/add-browser-search/v1/suggest?q=")" '["",[]]'
check "markup in the query is stripped" "$(curl -s "$BASE/wp-json/add-browser-search/v1/suggest?q=%3Cb%3Ehello%3C/b%3E" | jqv "[0]")" hello
long=$(python3 -c "print('a'*500)")
check "a very long query is cut to 100" "$(curl -s "$BASE/wp-json/add-browser-search/v1/suggest?q=$long" | jqv "[0]" | awk '{print length($0)}')" 100

echo "• the settings page"
page=$(curl -s -L -b "$J" -c "$J" "$BASE/wp-admin/options-general.php?page=add-browser-search")
has "page renders" "$page" "<h1>Add Browser Search</h1>"
has "shows the XML address" "$page" "$BASE/opensearch.xml"
has "shows the migration notice once" "$page" "The settings of the previous version were moved here"
page2=$(curl -s -L -b "$J" -c "$J" "$BASE/wp-admin/options-general.php?page=add-browser-search")
hasnt "…and not twice" "$page2" "The settings of the previous version were moved here"
has "the XML preview is there" "$page2" "OpenSearchDescription"
nonce=$(printf '%s' "$page2" | grep -o 'name="_wpbones_nonce" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
[ -n "$nonce" ] && ok "form carries the WP Bones nonce" || bad "no nonce field in the form"

code=$(curl -s -o "$T/nonce.html" -w '%{http_code}' -b "$J" -c "$J" -d 'add_browser_search[short_name]=Hacked' "$BASE/wp-admin/options-general.php?page=add-browser-search")
check "POST without a nonce is refused" "$code" 403
check "…and changed nothing" "$(probe state | jqv "['new']['short_name']")" "Acme Search"
code=$(curl -s -o /dev/null -w '%{http_code}' -b "$J" -c "$J" -d 'add_browser_search[short_name]=Hacked&_wpbones_nonce=0000000000' "$BASE/wp-admin/options-general.php?page=add-browser-search")
check "POST with a wrong nonce is refused" "$code" 403

code=$(curl -s -o "$T/bad.html" -w '%{http_code}' -b "$J" -c "$J" --data-urlencode "_wpbones_nonce=$nonce" \
  --data-urlencode 'add_browser_search[short_name]=This name is far too long' --data-urlencode 'add_browser_search[search_url]=https://example.com/?s=fixed' \
  --data-urlencode 'add_browser_search[contact]=nobody' --data-urlencode 'add_browser_search[image]=javascript:alert(1)' --data-urlencode 'add_browser_search[suggestions]=1' \
  "$BASE/wp-admin/options-general.php?page=add-browser-search")
check "invalid fields re-render the page" "$code" 200
has "…naming the search address" "$(cat "$T/bad.html")" "must be a web address that contains {searchTerms}"
has "…and the contact" "$(cat "$T/bad.html")" "not a valid email address"
has "…and the icon" "$(cat "$T/bad.html")" "not a valid web address"
check "the valid fields were saved: the name was cut to 16 characters" "$(probe state | jqv "['new']['short_name']")" "This name is far"
check "the bad search address was not saved: the previous one stays" "$(probe state | jqv "['new']['search_url']")" "https://www.google.com/search?q={searchTerms}&hl=it"
check "javascript: icon was not saved" "$(probe state | jqv "['new']['image']")" ""

page=$(curl -s -L -b "$J" -c "$J" "$BASE/wp-admin/options-general.php?page=add-browser-search")
nonce=$(printf '%s' "$page" | grep -o 'name="_wpbones_nonce" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
loc=$(curl -s -o /dev/null -D - -b "$J" -c "$J" --data-urlencode "_wpbones_nonce=$nonce" --data-urlencode 'add_browser_search[short_name]=Probe Shop' \
  --data-urlencode 'add_browser_search[description]=Probe & <b>description</b>' --data-urlencode 'add_browser_search[tags]=a b' \
  --data-urlencode 'add_browser_search[search_url]=https://example.com/find?q={searchTerms}&x=1' \
  "$BASE/wp-admin/options-general.php?page=add-browser-search" | tr -d '\r' | grep -i '^location:')
has "a valid save redirects with settings-updated" "$loc" "settings-updated=true"
check "suggestions unchecked is saved as off" "$(probe state | jqv "['new']['suggestions']")" false
doc=$(curl -s "$BASE/opensearch.xml")
has "the document follows the new name" "$doc" "<ShortName>Probe Shop</ShortName>"
has "…and the description, escaped" "$doc" "<Description>Probe &amp; description</Description>"
has "…and the search address" "$doc" 'template="https://example.com/find?q={searchTerms}&amp;x=1"'
hasnt "suggestions url is gone when off" "$doc" "x-suggestions+json"
check "suggestions route answers 404 when off" "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/wp-json/add-browser-search/v1/suggest?q=hello")" 404

echo "• who may change it"
J2="$T/sub.jar"
login "$J2" abssub pass-123-ABS
whoami=$(curl -s -L -b "$J2" -c "$J2" "$BASE/wp-admin/profile.php" | grep -o 'abssub' | head -1)
if [ "$whoami" = abssub ]; then
  code=$(curl -s -o "$T/sub.html" -w '%{http_code}' -b "$J2" -c "$J2" "$BASE/wp-admin/options-general.php?page=add-browser-search")
  check "a subscriber cannot open the settings page" "$code" 403
else
  echo "  - subscriber login did not stick in this Playground; capability not exercised"
fi

echo "• plain permalinks"
probe plain >/dev/null
check "the query-string address still answers" "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/?add_browser_search=opensearch")" 200
has "the head link points at it" "$(curl -s "$BASE/")" 'href="'"$BASE"'/?add_browser_search=opensearch"'
has "…and so does the self url" "$(curl -s "$BASE/?add_browser_search=opensearch")" "template=\"$BASE/?add_browser_search=opensearch\""
probe pretty >/dev/null

echo "• translations"
probe locale_de >/dev/null
check "the settings page is in German with the plugin's own text domain" "$(curl -s -L -b "$J" -c "$J" "$BASE/wp-admin/options-general.php?page=add-browser-search" | grep -c 'Deine Suchmaschine')" 1
probe locale_en >/dev/null

echo "• deactivate and activate"
probe deactivate >/dev/null
code=$(curl -s -o "$T/gone.html" -w '%{http_code}' "$BASE/opensearch.xml")
check "after deactivation /opensearch.xml is not served (404)" "$code" 404
hasnt "…and the home page has no search link" "$(curl -s "$BASE/")" 'rel="search" type="application/opensearchdescription+xml"'
check "…the rewrite rule is gone" "$(probe state | jqv "['rule']")" false
check "settings survive deactivation" "$(probe state | jqv "['new']['short_name']")" "Probe Shop"
probe activate >/dev/null
check "after activation /opensearch.xml is back (200)" "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/opensearch.xml")" 200

echo "• an update that replaces the files of an active 1.x plugin (no activation hook)"
probe update_by_copy >/dev/null
headers=$(curl -s -D - -o "$T/copied.xml" "$BASE/opensearch.xml")
check "the first request already serves /opensearch.xml (200)" "$(printf '%s' "$headers" | head -1 | tr -d '\r' | awk '{print $2}')" 200
has "…with the migrated 1.x name" "$(cat "$T/copied.xml")" "<ShortName>Copied Over</ShortName>"
check "…the default search address was dropped, so it follows the site" "$(probe state | jqv "['new']['search_url']")" ""
check "…and the old option is gone" "$(probe state | jqv "['legacy']")" null

echo "• uninstall"
un=$(probe uninstall)
check "uninstall removes the settings" "$(printf '%s' "$un" | jqv "['new']")" null
check "…the rewrite flag" "$(printf '%s' "$un" | jqv "['rewrite']")" null

echo "• debug.log"
if [ -s "$T/logs/debug.log" ]; then bad "debug.log is not empty"; sed 's/^/    /' "$T/logs/debug.log" | head -30; else ok "debug.log is empty"; fi

[ "$fail" = 0 ] && echo "✓ probe passed on $(grep -o "wp_version = '[^']*'" "$WPV/wp-includes/version.php")" || echo "✗ probe failed"
exit $fail
