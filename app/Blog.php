<?php
/**
 * This file contains the Charm kernel binding.
 */

namespace Neoground\Charm\Blog;

use Charm\Vivid\Kernel\EngineManager;
use Charm\Vivid\Kernel\Interfaces\ModuleInterface;

/**
 * Class Blog
 *
 * Charm kernel binding
 */
class Blog extends EngineManager implements ModuleInterface
{
    /**
     * Load the module
     */
    public function loadModule()
    {
        // Nothing to do yet.
    }

}