<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class IsAdminMiddleware {
    
    public function __invoke(Request $request, RequestHandler $handler): Response {
 
        $isAdmin = $request->getAttribute('is_admin');
        
        if ($isAdmin === null) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode([
                'error' => 'Error de configuración: AuthMiddleware debe ejecutarse primero',
                'code' => 'MIDDLEWARE_ERROR'
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
        
        if (!$isAdmin || $isAdmin == 0) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode([
                'error' => 'Acceso denegado. Se requieren permisos de administrador',
                'code' => 'ADMIN_REQUIRED'
            ]));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }
        
        return $handler->handle($request);
    }
}