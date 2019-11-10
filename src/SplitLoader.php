<?php


namespace Dakujem\Latter;


use Latte\Loaders\FileLoader;

class SplitLoader extends FileLoader
{

    function locateFile(string $name): ?string
    {
        $fname = strtr($name, '.', DIRECTORY_SEPARATOR);
        $file = $this->basePath . $fname; // extension ??
    }

}