<?php

namespace Legume;

use Pimple\Container;
use Psr\Container\ContainerInterface;

/**
 * PSR-11 compliant wrapper for the Pimple dependency injector.
 */
class Pimple extends Container implements ContainerInterface
{
    public function get($id)
    {
        return parent::offsetGet($id);
    }

    public function has($id)
    {
        return parent::offsetExists($id);
    }
}
