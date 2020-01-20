<?php


namespace Dakujem\Latter\Tests;

use Dakujem\Latter\Runtime;
use Psr\Http\Message\ResponseInterface as Response;
use RuntimeException;
use Tester\Assert;

require_once('bootstrap.php');

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Test definitions ~~~~~~~~~~~~~~~~~~~~~~~~~~

class ChainingTest extends BaseTest
{

    /**
     * Test ways of explicit routine chaining.
     */
    public function testExplicitChaining()
    {
        $v = $this->view();

        // register a routine named 'next', that will render `hello.latte`
        $v->register('next', function (Runtime $context) {
            return $context->withTarget('hello.latte');
        });

        // register two routines that will render using a different routine ('next')
        $v->register('foo', function (Runtime $context) use ($v) {
            // this way might seem more intuitive, but the context is lost (!), NOT recommended
            return $v->complete('next');
        });
        $v->register('bar', function (Runtime $context) use ($v) {
            // this feels better and the context is preserved
            return $v->another($context, $v->getRoutine('next'));
        });

        // sanity test - render using the routine
        $this->assert('hello world', 'next', [], $v);

        // render using chaining
        $this->assert('hello world', 'foo', [], $v);
        $this->assert('hello world', 'bar', [], $v);
    }


    /**
     * This test is quite abstract,
     * but the point here is that `View::execute` method can be used for custom render handling.
     */
    public function testExplicitChainingUsingExecute()
    {
        $v = $this->view();
        $v->register('default', function (Runtime $context) use ($v) {
            if ($context->getTarget() !== 'default') {
                $response = $v->execute($context, [$v->getRoutine($context->getTarget())]);
                if (is_string($response)) {
                    return $response;
                } else {
                    $context = $response;
                }
            }
            return $context->withTarget('name.latte');
        });
        $v->register('hello', function (Runtime $context) use ($v) {
            return $v->another($context->withTarget('hello.latte'));
        });
        $v->register('serus', function (Runtime $context) use ($v) {
            return $context->withTarget('hello.latte')->withParam('name', 'Sevro');
        });

        // render a default template unless the target has been set to an existing routine
        $this->assert('hello Fero', 'default', ['name' => 'Fero'], $v);

        // the secondary routine 'hello' returns a response
        $this->assert('hello world', 'whatever----not-important', [], $v->pipeline(function (Runtime $context) {
            return $context->withTarget('hello'); // switch target to a secondary routine
        }, 'default'));

        // the secondary routine 'serus' returns a context
        $this->assert('hello Sevro', 'whatever----not-important', [], $v->pipeline(function (Runtime $context) {
            return $context->withTarget('serus'); // switch target to a secondary routine
        }, 'default'));
    }


    public function testExplicitChainingFromDefaultRoutine()
    {
        $v = $this->view();
        $v->registerDefault(function (Runtime $context) use ($v) {
            $nextContext = $context
                ->withTarget($context->getTarget() . '.latte')
                // use Guest as the default for 'name', do not overwrite if it exists
                ->withParams(array_merge(['name' => 'Guest'], $context->getParams()));
            return $v->another($nextContext, $v->getRoutine($nextContext->getTarget()));
        });

        // the default routine will render 'name.latte' template with the default name 'Guest'
        $this->assert('hello Guest', 'name', [], $v);

        // and then you decide that you want to show the user's real name...
        $v->register('name.latte', function (Runtime $context) {
            return $context->withParam('name', 'Bobek');
        });

        // Explanation: rendering 'name' will trigger the default (above)
        //      that will switch the target to 'name.latte' and
        //      that will in turn trigger rendering the routine 'name.latte',
        //      followed by Latte rendering template with the same file name ('name.latte').
        $this->assert('hello Bobek', 'name', [], $v);

        // Explanation: the default routine switched the target to 'has.latte', which got rendered.
        $this->assert('Guest has got bananas.', 'has', ['object' => 'bananas'], $v);

        // try a named routine that renders template 'has.latte' using the default routine
        $v->register('orange', function (Runtime $context) {
            return $context->withParam('name', 'Orange')->withTarget('has');
        });

        // missing template 'has'
        // Explanation: the default routine was not called, because a named routine was called. Let's fix this...
        Assert::exception(function () use ($v) {
            $v->render($this->response(), 'orange', ['object' => 'nothing']);
        }, RuntimeException::class);

        $v->register('orange', function (Runtime $context) use ($v) {
            return $v->another($context->withParam('name', 'Orange')->withTarget('has'), $v->getDefaultRoutine());
        });

        // Explanation: using View::next it is possible to force-execute the default rendering routine
        $this->assert('Orange has got nothing.', 'orange', ['object' => 'nothing'], $v);
    }


    /**
     * TODO implicit chaining could work ... but the algorithm would require tracking the target being rendered during pipelines
     */
    public function NOT_WORKING__testImplicitChainingFromDefaultRoutine()
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
    }

    /**
     * TODO implicit chaining could work ... but the algorithm would require tracking the target being rendered during pipelines
     */
    public function NOT_WORKING__testImplicitChainingFromDefaultRoutineThruAlias()
    {
        $v = $this->view();
        $v->registerDefault(function (Runtime $context) {
            return $context->withTarget($context->getTarget() . '.latte')->withParam('name', 'Guest');
        });

        // rendering 'has' will trigger the default that will switch the target to 'has.latte', which is the alias of 'foobar'
        $v->register('foobar', function (Runtime $context) {
            return $context->withParam('name', 'Fero');
        }, 'has.latte');

        // routine 'name.latte' should be imlicitly called
        $this->assert('Fero has got apples.', 'has', ['object' => 'apples'], $v);
    }


    /**
     * Implicit chaining does not work and would be challenging to implement.
     * - cyclic routine invoking (endless loop)
     * - routines invoked multiple times
     *
     * $view->register('foo.latte', function($context){ return $context->withParams(...); });
     * $view->render($response, 'foo.latte');
     * The above would cause endless loop, UNLESS it was guarded in a while that would run the rendering chain...
     */
    public function not__working___testImplicitChaining()
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

