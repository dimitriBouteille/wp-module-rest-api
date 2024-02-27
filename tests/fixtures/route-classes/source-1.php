<?php

namespace App\Routes;

use Dbout\WpRestApi\Attributes\Action;
use Dbout\WpRestApi\Attributes\Route;
use Dbout\WpRestApi\Enums\Method;

#[Route('app/v2', 'document/(?P<documentId>\d+)')]
class MyRoute
{
    #[Action(Method::GET)]
    public function get(): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success' => true,
        ]);
    }
}
