<?php


namespace Dakujem\Latter\Tests;

use Dakujem\Latter\Runtime;
use Psr\Http\Message\ResponseInterface as Response;

require_once('bootstrap.php');

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Test definitions ~~~~~~~~~~~~~~~~~~~~~~~~~~

class ChainingTest extends BaseTest
{

    public function testExplicitChaining()
    {
        $v = $this->view();

        // register a routine named 'next', that will render `hello.latte`
        $v->register('next', function (Runtime $context) {
            return $context->withTarget('hello.latte');
        });

        // register two routines that will render using a different routine ('next')
        $v->register('foo', function (Runtime $context) use ($v) {
            // this way is a bit cumbersome and the context is lost (!)
            return $v->render($context->getResponse(), 'next');
        });
        $v->register('bar', function (Runtime $context) use ($v) {
            // this feels better and the context is preserved
            return $v->next($context, $v->getRoutine('next'));
        });

        // sanity test - render using the routine
        $this->assert('hello world', 'next', [], $v);

        // render using chaining
        $this->assert('hello world', 'foo', [], $v);
        $this->assert('hello world', 'bar', [], $v);
    }


    /**
     * TODO this should work ... (implicit chaining, at least from the default routine)
     */
    public function NOT_WORKING__testUsingDefaultRoutineFollowedImplicitlyByNamedRoutine()
    {
        $v = $this->view();
        $v->registerDefault(function (Runtime $context) {
            return $context->withTarget($context->getTarget() . '.latte')->withParam('name', 'Guest');
        });

        // rendering 'name' will trigger the default that will switch the target to 'name.latte'
        $v->register('name.latte', function (Runtime $context) {
            return $context->withParam('name', 'Fero');
        });

        // routine 'name.latte' should be imlicitly called
        $this->assert('hello Fero', 'name', [], $v);

        // rendering 'has' will trigger the default that will switch the target to 'has.latte', which is the alias of 'foobar'
        $v->register('foobar', function (Runtime $context) {
            return $context->withParam('name', 'Fero');
        }, 'has.latte');

        // routine 'name.latte' should be imlicitly called
        $this->assert('Fero has got apples.', 'has', ['object' => 'apples'], $v);
    }

    /**
     * TODO this should work ... (implicit chaining, at least from the default routine)
     */
    public function NOT_WORKING__testExplicitChainingWithCustomHandling()
    {
        $v = $this->view();
        $v->register('whatever', function (Runtime $context) use ($v) {
            $response = $v->execute($v->getRoutine('foo'));
            if ($response instanceof Response) {
//                 ...
            }
            // ...
            return $response->withParam('name', 'Fero');
        });

    }

    /**
     * This INTENTIONALLY does not work, because it could cause trouble, for example cyclic routine invoking (endless loop).
     *
     * $view->register('foo.latte', function($context){ return $context->withParams(...); });
     * $view->render($response, 'foo.latte');
     *
     * TODO The above would cause endless loop, UNLESS it was guarded in a while that would run the rendering chain...
     */
    public function intentionally__not__working___testRoutineChaining()
    {
        $v = $this->view();

        // register a routine that will set target to a second routine
        $v->register('foo', function (Runtime $context) {
            return $context->withTarget('next');
        });
        // register a routine named 'next', that will render `hello.latte`
        $v->register('next', function (Runtime $context) {
            return $context->withTarget('hello.latte');
        });

        // render using an alias
        $this->assert('hello world', 'foo', [], $v);
    }

}

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Tests ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

(new ChainingTest())->run();

