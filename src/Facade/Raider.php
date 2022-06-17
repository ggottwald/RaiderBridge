<?php

/**
 * This file is part of the TwigBridge package.
 *
 * @copyright Robert Crowe <hello@vivalacrowe.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace RaiderBridge\Facade;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Raider\Environment
 * @see \RaiderBridge\Bridge
 */
class Raider extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'raider';
    }
}
