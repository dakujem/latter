<?php

namespace Dakujem\Latter;

use Latte\Engine;

/**
 * Completing agent interface.
 */
interface Agent
{
    /**
     * Complete a target template.
     *
     * @param string      $target
     * @param array       $params
     * @param Engine|null $latte
     * @return string
     */
    function complete(string $target, array $params = [], Engine $latte = null): string;
}
