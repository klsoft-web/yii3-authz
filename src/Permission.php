<?php

namespace Klsoft\Yii3Authz;

use Attribute;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Permission
{
    /**
     * @param string $name Yiisoft\Rbac\Permission name
     * @param array $params Name-value pairs that would be passed to the rules associated with the roles and
     * permissions assigned to the user.
     */
    public function __construct(
        public readonly string $name,
        public readonly array  $params = []
    )
    {
    }
}
