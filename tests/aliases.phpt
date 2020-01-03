<?php


namespace Dakujem\Latter\Tests;

use Dakujem\Latter\Runtime;
use RuntimeException;
use Tester\Assert;

require_once('bootstrap.php');

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Test definitions ~~~~~~~~~~~~~~~~~~~~~~~~~~

class AliasesTest extends BaseTest
{


    public function testAliases()
    {
        $v = $this->view();

        // sanity test (rendering should fail)
        Assert::exception(function () use ($v) {
            $v->render($this->response(), 'foo', [], $this->latte());
        }, RuntimeException::class);

        // rendering 'foo' will render 'hello.latte'
        $v->alias('foo', 'hello.latte');

        // render using an alias
        $this->assert('hello world', 'foo', [], $v);

        // rendering 'bar' will render 'next',
        // that will try to render 'foo' (registered above),
        // that will in turn render 'hello.latte'
        $v->alias('bar', 'next');
        $v->alias('next', 'foo');

        // render using an aliases (all 3 should work and result in the same output)
        $this->assert('hello world', 'bar', [], $v);
        $this->assert('hello world', 'next', [], $v);
    }


    public function testRoutineAliases()
    {
        $v = $this->view();

        // sanity test (rendering should fail)
        Assert::exception(function () use ($v) {
            $v->render($this->response(), 'foo', [], $this->latte());
        }, RuntimeException::class);

        // register a rendering routine with an alias 'foo'
        $v->register('template', function (Runtime $context) {
            return $context->withParams(['name' => 'Uncle Bob'])->withTarget('name.latte');
        }, 'foo');

        // register another alias 'bar'
        $v->alias('bar', 'template');

        // render using both the routine and the alias
        $this->assert('hello Uncle Bob', 'template', [], $v);
        $this->assert('hello Uncle Bob', 'foo', [], $v);
        $this->assert('hello Uncle Bob', 'bar', [], $v);
    }
}

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Tests ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

(new AliasesTest())->run();

