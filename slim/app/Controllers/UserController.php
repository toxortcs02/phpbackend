<?php
namespace App\Controllers;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use App\Models\User;
use App\Config\Database;    
use PDO;

class UserController {

    private $db;
    
    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function login(Request $request, Response $response) {
        try {
            $data = $request->getParsedBody();
            
            $email = $data['email'] ?? '';
            $password = $data['password'] ?? '';
            
            if (empty($email) || empty($password)) {
                $response->getBody()->write(json_encode([
                    "error" => "Email y contraseña son requeridos"
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            
            $user = new User($this->db);
            $result = $user->loginUser($email, $password);
            
            if ($result) {
                unset($result['password']);
                
                $response->getBody()->write(json_encode([
                    "message" => "Login exitoso",
                    "user" => $result,
                    "token" => $result['token']
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            } else {
                $response->getBody()->write(json_encode([
                    "error" => "Credenciales inválidas"
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
            }
            
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                "error" => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
        
    
}
