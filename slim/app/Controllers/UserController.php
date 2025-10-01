<?php
namespace App\Controllers;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use App\Models\User;
use App\Config\Database;    
use PDO;
use PDOException;

class UserController {

    private $db;
    
    public function __construct(PDO $db) {
        $this->db = $db;
    }
    public function register(Request $request, Response $response) {
            try {
                $data = $request->getParsedBody();
                $email = $data['email'] ?? '';
                $password = $data['password'] ?? '';
                $first = $data['first_name'] ?? '';
                $last = $data['last_name'] ?? '';
                if (empty($email) || empty($password) || empty($first) || empty($last)) {
                    $response->getBody()->write(json_encode([
                        "error" => "Todos los campos son requeridos"
                    ]));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
                }
                $user = new User($this->db);

                if ($user->findByEmail($email)) {
                    $response->getBody()->write(json_encode([
                        "error" => "El email ya está registrado"
                    ]));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
                }
                
                $result = $user->registerUser($email, $password, $first, $last);

            }
            catch (\Exception $e) {
                $response->getBody()->write(json_encode([
                    "error" => $e->getMessage()
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }
            if ($result) {
                $response->getBody()->write(json_encode([
                    "message" => "Usuario registrado exitosamente",
                    "user_id" => $result
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
            } else {
                $response->getBody()->write(json_encode([
                    "error" => "No se pudo registrar el usuario"
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

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
        
    public function logout(Request $request, Response $response): Response {
        try {
            // Extraer el token del header Authorization: Bearer <token>
            $authHeader = $request->getHeaderLine('Authorization');
            $token = null;
            if (preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
                $token = $matches[1];
            }

            if (!$token) {
                $response->getBody()->write(json_encode([
                    "error" => "Token no proporcionado"
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
            }

            // Verificar si el token existe y no está vencido
            $stmt = $this->db->prepare("SELECT expired FROM users WHERE token = :token");
            $stmt->bindParam(':token', $token);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $response->getBody()->write(json_encode([
                    "error" => "Token no encontrado"
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
            }

            if (strtotime($user['expired']) < time()) {
                $response->getBody()->write(json_encode([
                    "error" => "Token vencido"
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
            }

            // Si todo bien, invalidar el token
            $stmt = $this->db->prepare("UPDATE users SET token = NULL, expired = NULL WHERE token = :token");
            $stmt->bindParam(':token', $token);
            $stmt->execute();

            $response->getBody()->write(json_encode([
                "message" => "Sesión cerrada exitosamente"
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch (PDOException $e) {
            $response->getBody()->write(json_encode([
                "error" => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }


    public function getAll(Request $request, Response $response): Response {
        try {
            $statement = $this->db->query(
                "SELECT id, email, first_name, last_name, is_admin FROM users"
            );
            $users = $statement->fetchAll(PDO::FETCH_ASSOC);

            $payload = json_encode($users, JSON_UNESCAPED_UNICODE);
            $response->getBody()->write($payload);
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);

        } catch (PDOException $e) {
            $error = ["error" => "Error al obtener los datos: " . $e->getMessage()];
            $response->getBody()->write(json_encode($error));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }
    
}
