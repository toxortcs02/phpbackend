<?php
namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class AdminMiddleware {
    public function __invoke(Request $request, RequestHandler $handler): Response {
        $user = $request->getAttribute('user');

        if (!$user || !$user['is_admin']) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode(['error' => 'Admin access required']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        return $handler->handle($request);
    }
}
