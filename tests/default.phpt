<?php


namespace Dakujem\Latter\Tests;

use Dakujem\Latter\Runtime;
use LogicException;
use RuntimeException;
use Tester\Assert;

require_once('bootstrap.php');

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Test definitions ~~~~~~~~~~~~~~~~~~~~~~~~~~

class DefaultRoutineTest extends BaseTest
{

    public function testDefaultRoutine()
    {
        $v = $this->view();

        // sanity test (rendering should fail)
        Assert::exception(function () use ($v) {
            $v->render($this->response(), 'hello', [], $this->latte());
        }, RuntimeException::class);

        // register a default routine that will add '.latte' suffix to rendering targets and set the `name` parameter
        $v->registerDefault(function (Runtime $context) {
            return $context->withTarget($context->getTarget() . '.latte')->withParam('name', 'Foobar');
        });

        // render using an alias
        $this->assert('hello world', 'hello', [], $v);
        $this->assert('hello Foobar', 'name', [], $v);
    }


    public function testDefaultRoutineNotInvoked()
    {
        $v = $this->view();

        // register a default routine
        $v->registerDefault(function () {
            throw new LogicException('This should never be reached.');
        });

        // the default will be called, because 'hello' routine does not exist
        Assert::exception(function () use ($v) {
            $v->render($this->response(), 'hello', [], $this->latte());
        }, LogicException::class);

        // this time neither using the routine nor the alias of the routine will invoke the default routine
        $v->register('hello', function (Runtime $context) {
            return $context->withTarget('hello.latte');
        }, 'alias');
        $this->assert('hello world', 'hello', [], $v);
        $this->assert('hello world', 'alias', [], $v);
    }


    public function testDefaultRoutineInvokedOnceUsingAlias()
    {
        $counter = 0;
        $v = $this->view();
        $v->registerDefault(function () use (&$counter) {
            $counter += 1;
        });
        $v->alias('foo', 'hello.latte');
        $this->assert('hello world', 'foo', [], $v);
        Assert::same(1, $counter);
    }


    /**
     * Test that the default routine is not invoked multiple times when using alias chaining.
     */
    public function testDefaultRoutineInvokedOnceUsingAliasChaining()
    {
        $v = $this->view();

        $counter = 0;
        $v->registerDefault(function () use (&$counter) {
            $counter += 1;
        });

        // foo -> next -> hello -> hello.latte
        $v->alias('foo', 'next');
        $v->alias('next', 'hello');
        $v->alias('hello', 'hello.latte');

        // the default routine will only be called once
        $this->assert('hello world', 'foo', [], $v);
        Assert::same(1, $counter);
    }


    /**
     * Test that the default routine is only invoked once, even if the target is constantly being changed.
     */
    public function testDefaultRoutineInvokedOnce()
    {
        $counter = 0;
        $v = $this->view();
        $v->registerDefault(function (Runtime $context) use (&$counter) {
            $counter += 1;
            if ($counter >= 10) {
                throw new LogicException('Looping!');
            }
            return $context->withTarget($context->getTarget() . rand(1, 9));
        });

        Assert::exception(function () use ($v) {
            return $v->render($this->response(), 'foo');
        }, RuntimeException::class); // missing template
        Assert::same(1, $counter);
    }
}

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Tests ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

(new DefaultRoutineTest())->run();

