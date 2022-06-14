<?php
/**
 * This file is part of the TwigBridge package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace RaiderBridge\Extension\Laravel;

use Raider\Extension\AbstractExtension;
use RaiderBridge\NodeVisitor\GetAttrAdjuster;

/**
 * Access to Laravel model properties using ArrayAccess.
 */
class Model extends AbstractExtension
{
    /**
     * @inheritDoc
     */
    public function getNodeVisitors()
    {
        return [
            new GetAttrAdjuster,
        ];
    }
}
