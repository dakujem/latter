<?php

namespace Dakujem\Latter\Tests;

use Dakujem\Latter\Renderer;
use Dakujem\Latter\View;
use Latte\Engine;
use Latte\Loaders\FileLoader;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/../vendor/autoload.php';

abstract class BaseTest extends TestCase
{

    protected function view(bool $setEngine = true): View
    {
        $v = (new View());
        if ($setEngine) {
            $v->setEngine(function () {
                return $this->latte();
            });
        }
        return $v;
    }


    protected function latte()
    {
        $engine = new Engine();

        // Configure the file loader to search for templates in a dedicated directory.
        $loader = new FileLoader(__DIR__ . '/templates');
        $engine->setLoader($loader);

        // Set a temporary directory, where compiled Latte templates will be stored.
        $engine->setTempDirectory(__DIR__ . '/temp');

        return $engine;
    }


    protected function response(): ResponseInterface
    {
        return new Response();
    }


    protected function assert(string $expected, string $template, array $params, Renderer $renderer, bool $trim = true): void
    {
        $r = $renderer->render($this->response(), $template, $params);
        $body = $r->getBody();
        $body->rewind();
        $content = $body->getContents();
        Assert::same($expected, $trim ? trim($content) : $content);
    }
}
