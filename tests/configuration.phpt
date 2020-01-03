<?php


namespace Dakujem\Latter\Tests;

use Dakujem\Latter\Runtime;
use Dakujem\Latter\View;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Tester\Assert;

require_once('bootstrap.php');

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Test definitions ~~~~~~~~~~~~~~~~~~~~~~~~~~

class ConfigurationTest extends BaseTest
{

    public function testEngineConfiguration()
    {
        $v = $this->view(false);

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


    public function testConfigurationWrapper()
    {
        $v = $this->view(false);
        $v->configure(function () use ($v) {
            // $this must be bound to the View instance
            Assert::type(View::class, $this);
            Assert::same($v, $this);
        });
    }


    public function testConfigurationScope()
    {
        $v = $this->view(false);
        $v->configure(function (callable $engineProvider) {
            /** @var View $this $this is bound to the View instance */
            $this->setEngine($engineProvider);
            $this->register('foo', function (Runtime $context) {
                return $this->another($context->withParam('name', 'Gent'), $this->getRoutine('bar'));
            });
            $this->register('bar', function (Runtime $context) {
                return $context->withTarget('name.latte');
            });
        }, function () {
            return $this->latte();
        });

        // if this renders correctly and without exceptions/errors,
        // then the 'bar' and 'foo' routines must have been invoked in the correct scope
        $this->assert('hello Gent', 'foo', [], $v);
    }
}

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Tests ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

(new ConfigurationTest())->run();

