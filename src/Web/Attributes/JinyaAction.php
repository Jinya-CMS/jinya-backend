<?php

namespace App\Web\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class JinyaAction
{

    public const GET = 'GET';
    public const PUT = 'PUT';
    public const POST = 'POST';
    public const DELETE = 'DELETE';
    public const HEAD = 'HEAD';

    /**
     * Route constructor.
     * @param string $url
     * @param string $method
     */
    public function __construct(
        public string $url,
        public string $method,
    ) {
    }
}