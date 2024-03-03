<?php

namespace Dbout\WpRestApi\Tests\fixtures;

use Dbout\WpRestApi\Exceptions\NotFoundException;

class RouteWithNotFoundException
{
    /**
     * @return never
     * @throws NotFoundException
     */
    public function execute(): never
    {
        throw new NotFoundException();
    }
}
