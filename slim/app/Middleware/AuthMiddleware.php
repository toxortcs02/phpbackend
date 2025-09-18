<?php 

namespace Docker\Slim\App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use PDO;

class AuthMiddleware {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function __invoke(Request $request, RequestHandler $handler): Response {
        $authHeader = $request->getHeaderLine('Authorization');

        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode(['error' => 'Token missing']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $token = $matches[1];

        // Buscar usuario con ese token
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE token = :token");
        $stmt->execute(['token' => $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode(['error' => 'Invalid token']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        // Verificar expiración
        if (strtotime($user['expired']) < time()) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode(['error' => 'Token expired']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        // Actualizar expiración a 5 minutos en el futuro
        $newExpired = date('Y-m-d H:i:s', time() + 300);
        $update = $this->pdo->prepare("UPDATE users SET expired = :expired WHERE id = :id");
        $update->execute(['expired' => $newExpired, 'id' => $user['id']]);

        // Guardar info del usuario en request para usar en controladores
        $request = $request->withAttribute('user', $user);

        return $handler->handle($request);
    }
}