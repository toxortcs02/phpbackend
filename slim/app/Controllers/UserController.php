<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use App\Models\User;
use App\Config\Database;    
class UserController {

    private $db;
    
    public function __construct(PDO $db) {
        $this->db = $db;
    }
    
    public function login(Request $request, Response $response) {
        $data = $request->getParsedBody(); // obtiene JSON o form-data

        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        $user = new User($this->db); // le pasás la conexión
        $result = $user->loginUser($email, $password);

        if ($result) {
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } else {
            $response->getBody()->write(json_encode(["error" => "Credenciales inválidas"]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
    }
}
