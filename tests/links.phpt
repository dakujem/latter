<?php


namespace Dakujem\Latter\Tests;

use Dakujem\Latter\View;
use Dakujem\Sleeve;
use Latte\Engine;
use Latte\Loaders\StringLoader;
use Latte\MacroNode;
use Latte\Macros\MacroSet;
use Latte\PhpWriter;
use RuntimeException;
use Slim\App;
use Slim\Factory\AppFactory;
use Tester\Assert;

require_once('bootstrap.php');

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Test definitions ~~~~~~~~~~~~~~~~~~~~~~~~~~

class LinksTest extends BaseTest
{
    private function slim(): App
    {
        $app = AppFactory::create(null, new Sleeve()); // Slim v4

        /** @var Sleeve $container */
        $container = $app->getContainer();

        $container->set('latte', $container->factory(function () use ($container, $app) {
            $engine = new Engine();

            // The section below configures the `{link}` and `n:href` macros and the `urlFor` filter.
            $engine->addFilter('urlFor', function ($route, ...$args) use ($app) {
                // the filter will call the `urlFor` method of the route parser
                return $app->getRouteCollector()->getRouteParser()->urlFor($route, ...$args);
                // Note: if you are using slim v3, use `$container->get('router')->pathFor( ... )` instead.
            });
            $macroSet = new MacroSet($engine->getCompiler());
            $linkMacro = function (MacroNode $node, PhpWriter $writer) {
                return $writer->using($node)->write('echo ($this->filters->urlFor)(%node.word, %node.args?);');
            };
            $macroSet->addMacro('link', $linkMacro);
            $macroSet->addMacro('href', null, null, function (MacroNode $node, PhpWriter $writer) use ($linkMacro) {
                return ' ?> href="<?php ' . $linkMacro($node, $writer) . ' ?>"<?php ';
            });


            // the rest of the Latte engine configuration goes here
            // ...

            $engine->setLoader(new StringLoader());

            return $engine;
        }));

        $container->set('view', function () use ($container) {
            $view = new View();

            // optionally set an engine factory (recommended)
            $view->setEngine(function () use ($container): Engine {
                return $container->get('latte');
            });

            return $view;
        });

        $handler = 'MyController';
        $app->get('/', $handler)->setName('root');
        $app->get('/hello/{name}', $handler)->setName('hello');
        $app->get('/resource/{resource}/action/{action}', $handler)->setName('rc');
        $app->get('/foobar', $handler)->setName('foo');

        return $app;
    }


    public function testLinkMacros()
    {
        /** @var Sleeve $container */
        $container = $this->slim()->getContainer();
        $view = $container->get('view');

        $this->assert('Hello Arrakis.', 'Hello {$world}.', ['world' => 'Arrakis',], $view);
        $this->assert('/foobar', "{='foo'|urlFor}", [], $view);
        $this->assert('/foobar', "{link foo}", [], $view);
        $this->assert('/foobar', "{\$t|urlFor}", ['t' => 'foo'], $view);
        $this->assert('/foobar', "{link \$t}", ['t' => 'foo'], $view);
        $this->assert('/hello/hugo', "{link hello [name => \$name]}", ['name' => 'hugo'], $view);
        $this->assert('/hello/hugo', "{link hello, [name => hugo]}", [], $view);
        $this->assert('/resource/apple/action/eat?param1=val1&param2=val2', "{link rc [resource => apple, action => eat], [param1 => val1, param2 => val2]}", [], $view);
        $this->assert('<a href="/foobar">foo link</a>', "<a n:href='foo'>foo link</a>", [], $view);
        $this->assert('<a href="/hello/hugo?a=b">hello link</a>', "<a n:href='hello [name => hugo], [a => b]'>hello link</a>", [], $view);

        Assert::exception(function () use ($view) {
            $view->render($this->response(), "{link ninja}");
        }, RuntimeException::class, 'Named route does not exist for name: ninja');
    }
}

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Tests ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

(new LinksTest())->run();

