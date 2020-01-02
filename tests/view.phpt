<?php


namespace Dakujem\Latter\Tests;

use Dakujem\Latter\PipelineRelay;
use Dakujem\Latter\Renderer;
use Dakujem\Latter\Runtime;
use Dakujem\Latter\View;
use Latte\Engine;
use Latte\Loaders\FileLoader;
use LogicException;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Tester\Assert;
use Tester\TestCase;

require_once('bootstrap.php');

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Test definitions ~~~~~~~~~~~~~~~~~~~~~~~~~~

class ViewTest extends TestCase
{

    public function testConfiguration()
    {
        $v = $this->view(false);

        // should run without exception and return a response
        Assert::type(ResponseInterface::class, $v->render($this->response(), 'hello.latte', [], $this->latte()));

        // missing template file
        Assert::exception(function () use ($v) {
            $v->render($this->response(), 'foo.latte', [], $this->latte());
        }, RuntimeException::class);

        // missing engine
        Assert::exception(function () use ($v) {
            $v->render($this->response(), 'hello.latte');
        }, LogicException::class, 'Engine is needed.');

        // now set the engine provider
        $v->setEngine(function () {
            return $this->latte();
        });
        Assert::type(ResponseInterface::class, $v->render($this->response(), 'hello.latte'));

        // assert correct rendering
        $this->assert('hello world', 'hello.latte', [], $v);
    }

    public function testRoutineWithTarget()
    {
        $v = $this->view();

        // missing template file
        Assert::exception(function () use ($v) {
            $this->assert('hello world', 'hello', [], $v);
        }, RuntimeException::class);

        $v->register('hello', function (Runtime $context) {
            // set rendering target to the context
            return $context->withTarget('hello.latte');
        });

        // should render correctly this time
        $this->assert('hello world', 'hello', [], $v);
    }

    public function testRoutineWithParameters()
    {
        $v = $this->view();

        // should fail with undefined var
        Assert::error(function () use ($v) {
            $v->render($this->response(), 'name.latte');
        }, E_NOTICE, 'Undefined variable: name');
        Assert::error(function () use ($v) {
            $v->render($this->response(), 'name.latte', []);
        }, E_NOTICE, 'Undefined variable: name');

        // with the variable given, the template should render fine
        $this->assert('hello John', 'name.latte', ['name' => 'John'], $v);

        // now register a routine that will "inject" the parameter
        $v->register('name.latte', function (Runtime $context) {
            // set rendering parameters to the context
            return $context->withParams(['name' => 'John']);
        });
        // and the template should render
        $this->assert('hello John', 'name.latte', [], $v);

        // now let's test multiple params...
        $v->register('has.latte', function (Runtime $context) {
            // note the array_merge and the order of arguments (defaults first)
            return $context->withParams(array_merge(['name' => 'The teacher'], $context->getParams()));
        });
        // the name has been set in the routine, but the object is still missing:
        Assert::error(function () use ($v) {
            $v->render($this->response(), 'has.latte', []);
        }, E_NOTICE, 'Undefined variable: object');

        // we provide the object...
        $this->assert('The teacher has got oranges.', 'has.latte', ['object' => 'oranges'], $v);

        // and we can still redefine the name as well, if we wish to:
        // Note: this is possible due to how array_merge is used in the routine, note the order of arrays in the call
        $this->assert('Hugo has got oranges.', 'has.latte', ['name' => 'Hugo', 'object' => 'oranges'], $v);
    }


    public function testDefaultParameters()
    {
        $v = $this->view();

        // should fail with undefined var
        Assert::error(function () use ($v) {
            $v->render($this->response(), 'name.latte');
        }, E_NOTICE, 'Undefined variable: name');

        // now configure View with default param(s)
        $v->setParams(['name' => 'Hugo']);

        // and the template should render
        $this->assert('hello Hugo', 'name.latte', [], $v);

        // should fail with undefined var yet again
        Assert::error(function () use ($v) {
            $v->render($this->response(), 'has.latte');
        }, E_NOTICE, 'Undefined variable: object');

        // and the template should render with both the given parameter and the default one
        $this->assert('Hugo has got a car.', 'has.latte', ['object' => 'a car'], $v);

        // now set the second param as well
        $v->setParam('object', 'sausages');
        $this->assert('Hugo has got sausages.', 'has.latte', [], $v);
    }

    public function testPipelineConfigAndInvoking()
    {
        $v = $this->view();
        $used = [];
        $v->register('a', function () use (&$used) {
            $used[] = 'a';
        });
        $v->register('b', function () use (&$used) {
            $used[] = 'b';
        });

        Assert::type(PipelineRelay::class, $v->pipeline('a'));
        Assert::type(PipelineRelay::class, $v->pipeline('b'));
        Assert::type(PipelineRelay::class, $v->pipeline('a', 'b'));

        Assert::exception(function () use ($v) {
            $v->pipeline('foo');
        }, LogicException::class);

        $this->assert('hello world', 'hello.latte', [], $v->pipeline('a'));
        $this->assert('hello world', 'hello.latte', [], $v->pipeline('b'));
        $this->assert('hello world', 'hello.latte', [], $v->pipeline('a', 'b'));
        $this->assert('hello world', 'hello.latte', [], $v->pipeline('b', 'a'));
        Assert::same(['a', 'b', 'a', 'b', 'b', 'a'], $used);
    }

    public function testPipelineRendering()
    {
        $v = $this->view();
        $v->register('withName', function (Runtime $context) {
            return $context->withParam('name', 'Pete');
        });
        $v->register('withObject', function (Runtime $context) {
            return $context->withParam('object', 'a flying saucer');
        });

        // as usually, the rendering will fail:
        Assert::error(function () use ($v) {
            $v->render($this->response(), 'has.latte');
        }, [
            [E_NOTICE, 'Undefined variable: name'],
            [E_NOTICE, 'Undefined variable: object'],
        ]);

        // use the pipeline to set the missing vars
        Assert::error(function () use ($v) {
            $v->pipeline('withName')->render($this->response(), 'has.latte');
        }, [
            [E_NOTICE, 'Undefined variable: object'],
        ]);
        Assert::error(function () use ($v) {
            $v->pipeline('withObject')->render($this->response(), 'has.latte');
        }, [
            [E_NOTICE, 'Undefined variable: name'],
        ]);
        $this->assert('Pete has got a flying saucer.', 'has.latte', [], $v->pipeline('withName', 'withObject'));
    }


    public function testAliases()
    {
        $v = $this->view();

        // sanity test (rendering should fail)
        Assert::exception(function () use ($v) {
            $v->render($this->response(), 'hello', [], $this->latte());
        }, RuntimeException::class);

        // render using an alias
        $this->assert('hello world', 'hello.latte', [], $v);
    }


    public function testDefaultRoutine()
    {
        $v = $this->view();

        // sanity test (rendering should fail)
        Assert::exception(function () use ($v) {
            $v->render($this->response(), 'hello', [], $this->latte());
        }, RuntimeException::class);

        // register a default routine that will add '.latte' suffix to rendering targets and set the `name` parameter
        $v->registerDefault(function(Runtime $context){
            return $context->withTarget($context->getTarget().'.latte')->withParam('name', 'Foobar');
        });

        // render using an alias
        $this->assert('hello world', 'hello', [], $v);
        $this->assert('hello Foobar', 'name', [], $v);
    }


    public function not__working___testChaining()
    {
        $v = $this->view();

        // sanity test (rendering should fail)
        Assert::exception(function () use ($v) {
            $v->render($this->response(), 'foo', [], $this->latte());
        }, RuntimeException::class);

        // register a routine that will set target to a second routine
        $v->register('foo', function(Runtime $context){
            return $context->withTarget('next');
        });
        // register a routine named 'next', that will render `hello.latte`
        $v->register('next', function(Runtime $context){
            return $context->withTarget('hello.latte');
        });

        // render using an alias
        $this->assert('hello world', 'foo', [], $v);
    }


    private function view(bool $setEngine = true): View
    {
        $v = (new View());
        if ($setEngine) {
            $v->setEngine(function () {
                return $this->latte();
            });
        }
        return $v;
    }


    private function latte()
    {
        $engine = new Engine();

        // Configure the file loader to search for templates in a dedicated directory.
        $loader = new FileLoader(__DIR__ . '/templates');
        $engine->setLoader($loader);

        // Set a temporary directory, where compiled Latte templates will be stored.
        $engine->setTempDirectory(__DIR__ . '/temp');

        return $engine;
    }


    private function response(): ResponseInterface
    {
        return new Response();
    }

    private function assert(string $expected, string $template, array $params, Renderer $renderer, bool $trim = true): void
    {
        $r = $renderer->render($this->response(), $template, $params);
        $body = $r->getBody();
        $body->rewind();
        $content = $body->getContents();
        Assert::same($expected, $trim ? trim($content) : $content);
    }
}

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Tests ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

(new ViewTest())->run();

