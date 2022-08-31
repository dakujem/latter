<?php


namespace Dakujem\Latter\Tests;

use Dakujem\Latter\Runtime;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Tester\Assert;

require_once('bootstrap.php');

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Test definitions ~~~~~~~~~~~~~~~~~~~~~~~~~~

class RoutinesTest extends BaseTest
{

    public function testExplicitRendering()
    {
        $v = $this->view(false);

        // should run without exception and return a response
        Assert::type(ResponseInterface::class, $v->render($this->response(), 'hello.latte', [], $this->latte()));
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
        }, ...$this->noticeOrWarning('name'));
        Assert::error(function () use ($v) {
            $v->render($this->response(), 'name.latte', []);
        }, ...$this->noticeOrWarning('name'));

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
        }, ...$this->noticeOrWarning('object'));

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
        }, ...$this->noticeOrWarning('name'));

        // now configure View with default param(s)
        $v->setParams(['name' => 'Hugo']);

        // and the template should render
        $this->assert('hello Hugo', 'name.latte', [], $v);

        // should fail with undefined var yet again
        Assert::error(function () use ($v) {
            $v->render($this->response(), 'has.latte');
        }, ...$this->noticeOrWarning('object'));

        // and the template should render with both the given parameter and the default one
        $this->assert('Hugo has got a car.', 'has.latte', ['object' => 'a car'], $v);

        // now set the second param as well
        $v->setParam('object', 'sausages');
        $this->assert('Hugo has got sausages.', 'has.latte', [], $v);
    }

    /**
     * In PHP 8+ the notices bacame warnings and the format of the message changed too.
     */
    private function noticeOrWarning(string $prop): array
    {
        return version_compare(PHP_VERSION, '8.0.0', '<') ? [E_NOTICE, 'Undefined variable: ' . $prop] : [E_WARNING, 'Undefined variable $' . $prop];
    }
}

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Tests ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

(new RoutinesTest())->run();

