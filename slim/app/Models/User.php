<?php
namespace App\Models;

use App\Config\Database;
use PDO;
use PDOException;
use DateTime;


class User {
    private $conn;
    private $table = 'users';


    public $id;
    public $email;
    public $first_name;
    public $last_name;
    public $password;
    public $token;
    public $expired;
    public $is_admin;

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    public function findByEmail($email) {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function create() {
        try {
            $query = "INSERT INTO {$this->table} 
                    (email, first_name, last_name, password, is_admin) 
                    VALUES (:email, :first_name, :last_name, :password, :is_admin)";
            
            $stmt = $this->conn->prepare($query);

            $stmt->bindParam(':email', $this->email, PDO::PARAM_STR);
            $stmt->bindParam(':first_name', $this->first_name, PDO::PARAM_STR);
            $stmt->bindParam(':last_name', $this->last_name, PDO::PARAM_STR);
            $stmt->bindParam(':password', $this->password, PDO::PARAM_STR);
            $stmt->bindParam(':is_admin', $this->is_admin, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $this->id = $this->conn->lastInsertId();
                return $this->id;
            }
            return false;

        } catch (PDOException $e) {
            throw new \Exception("Error creando usuario: " . $e->getMessage());
        }
    }

    public function registerUser($email, $password, $first_name, $last_name, $is_admin = 0) {
        $this->email = $email;
        $this->password = $password;
        $this->first_name = $first_name;
        $this->last_name = $last_name;
        $this->is_admin = $is_admin;

        return $this->create();
    }



    public function loginUser($email, $password) {
        try {
            $query = "SELECT * FROM users WHERE email = :email";
            $statement = $this->conn->prepare($query);
            $statement->bindParam(':email', $email, PDO::PARAM_STR);
            $statement->execute();
            $user = $statement->fetch(PDO::FETCH_ASSOC);
            if ($user && $password === $user['password']) {
                
                $token = bin2hex(random_bytes(32));
                
                $fecha = new DateTime();
                $fecha->modify('+5 minutes');
                $fechaFormateada = $fecha->format('Y-m-d H:i:s');
                
                $updateQuery = "UPDATE users 
                            SET token = :token, expired = :expired 
                            WHERE email = :email";
                
                $updateStmt = $this->conn->prepare($updateQuery);
                
                
                $updateStmt->bindParam(':token', $token, PDO::PARAM_STR);
                $updateStmt->bindParam(':expired', $fechaFormateada, PDO::PARAM_STR);  
                $updateStmt->bindParam(':email', $email, PDO::PARAM_STR);
                
                if ($updateStmt->execute()) {
                    $user['token'] = $token;
                    $user['expired'] = $fechaFormateada;
                    
                    return $user;
                } else {
                    throw new \Exception("Error al actualizar token de usuario");
                }
            }
            return false;
            
        } catch (PDOException $e) {
            error_log("SQL Error: " . $e->getMessage());
            error_log("Query: " . ($updateQuery ?? 'Query no definida'));
            
            throw new \Exception("Error en login: " . $e->getMessage());
        }
    }
    
}