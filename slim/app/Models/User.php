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

    public function create() {
        try {
            $query = "INSERT INTO {$this->table} 
                     (email, first_name, last_name, password, is_admin) 
                     VALUES (:email, :first_name, :last_name, :password, :is_admin)";
            
            $stmt = $this->conn->prepare($query);
            
            // Hash password
            $hashed_password = password_hash($this->password, PASSWORD_DEFAULT);
            
            $stmt->bindParam(':email', $this->email);
            $stmt->bindParam(':first_name', $this->first_name);
            $stmt->bindParam(':last_name', $this->last_name);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':is_admin', $this->is_admin);
            
            if ($stmt->execute()) {
                $this->id = $this->conn->lastInsertId();
                return true;
            }
            return false;
            
        } catch (PDOException $e) {
            throw new \Exception("Error creating user: " . $e->getMessage());
        }
    }


    public function loginUser($email, $password){
        $db = new Database();
        $conn = $db->getConnection();
        // Buscar el usuario por email
        try{
            $query ="SELECT * FROM users  WHERE email  = :email";
            $statement = $conn->prepare($query);
            $statement->bindParam(':email', $email, PDO::PARAM_STR);
            $statement->execute();
            $user = $statement->fetch(PDO::FETCH_ASSOC);
        }
        catch (PDOException $e) {
            throw new \Exception("Error in login: " . $e->getMessage());
        }
        
        // Verificamos la contraseña
        if ($user && password_verify($password, $user['password'])) {
            $token = bin2hex(random_bytes(32));
            $fecha = new DateTime();
            $fecha->modify('+5 minutes');
            $fechaFormateada = $fecha->format('Y-m-d H:i:s');
            $query = "UPDATE users 
                SET token = :token expired = ':fecha'
            WHERE email = :email ";
            $statement = $conn->prepare($query);
            $statement->bindParam(':token', $token, PDO::PARAM_STR);
            $statement->bindParam(':fecha', $fechaFormateada, PDO::PARAM_STR); 
            $statement->bindParam(':email', $email, PDO::PARAM_STR);
            $statement->execute();

            $user['token'] = $token;
            $user['expired'] = $fechaFormateada;
            return $user;
        }
    }
}