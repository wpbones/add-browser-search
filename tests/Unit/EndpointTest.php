<?php

declare(strict_types=1);

namespace AddBrowserSearch\Tests\Unit;

use AddBrowserSearch\Endpoint;
use AddBrowserSearch\Tests\Support\MonkeyTestCase;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery;

final class EndpointTest extends MonkeyTestCase
{
  protected function setUp(): void
  {
    parent::setUp();
    $this->site();
  }

  public function testTheRuleMapsOpensearchXmlToTheQueryVariable(): void
  {
    Functions\expect('add_rewrite_rule')->once()->with('^opensearch\.xml$', 'index.php?add_browser_search=opensearch', 'top');

    Endpoint::addRewriteRule();
  }

  public function testTheQueryVariableIsPublic(): void
  {
    $this->assertSame(['p', 'add_browser_search'], Endpoint::queryVars(['p']));
  }

  public function testItFlushesTheRulesOnceNotOnEveryRequest(): void
  {
    Functions\expect('flush_rewrite_rules')->once()->with(false);

    Endpoint::flushOnce();
    $this->assertSame(Endpoint::RULES_VERSION, $this->written[Endpoint::RULES_OPTION]);

    // The next request finds the flag.
    Endpoint::flushOnce();
  }

  public function testActivationRegistersTheRuleBeforeFlushing(): void
  {
    $order = [];
    Functions\when('add_rewrite_rule')->alias(function () use (&$order) {
      $order[] = 'rule';
    });
    Functions\when('flush_rewrite_rules')->alias(function () use (&$order) {
      $order[] = 'flush';
    });

    Endpoint::activate();

    $this->assertSame(['rule', 'flush'], $order);
  }

  public function testDeactivationFlushesWithoutOurRule(): void
  {
    $filter = null;
    Filters\expectAdded('rewrite_rules_array')->once()->with(Mockery::on(function ($callback) use (&$filter) {
      $filter = $callback;

      return true;
    }));
    Functions\expect('flush_rewrite_rules')->once()->with(false);

    Endpoint::deactivate();

    $rules = ['^opensearch\.xml$' => 'index.php?add_browser_search=opensearch', '^other/?$' => 'index.php?other=1'];

    $this->assertSame(['^other/?$' => 'index.php?other=1'], $filter($rules));
    $this->assertContains(Endpoint::RULES_OPTION, $this->deleted);
  }

  public function testItAnswersOnlyTheDescriptionRequest(): void
  {
    Functions\when('get_query_var')->justReturn('');
    // Anything it tried to send or build would hit an undefined function: it must do nothing.
    Endpoint::serve();

    Functions\when('get_query_var')->justReturn('something-else');
    Endpoint::serve();

    $this->addToAssertionCount(1);
  }

  public function testTheHeadLinkIsWhatBrowsersLookFor(): void
  {
    $this->options['permalink_structure'] = '/%postname%/';
    $this->site(['name' => 'Tom "&" Jerry']);

    ob_start();
    Endpoint::headLink();
    $html = (string) ob_get_clean();

    $this->assertSame(
      "<link rel=\"search\" type=\"application/opensearchdescription+xml\" title=\"Tom &quot;&amp;&quot; Jerry\" href=\"https://example.test/opensearch.xml\" />\n",
      $html
    );
  }
}
