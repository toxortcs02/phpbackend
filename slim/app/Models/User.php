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

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
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

        try{
            $query ="SELECT * FROM users  WHERE email  = :email";
            $statement = $conn->prepare($query);
            $statement->bindParam(':email', $email, PDO::PARAM_STR);
            $statement->execute();
            $user = $statement->fetch();
        }
        catch (PDOException $e) {
            throw new \Exception("Error in login: " . $e->getMessage());
        }
            
        if ($user && password_verify($password, $user['password'])) {
            $fecha = new DateTime();
            $fecha->modify('+5 minutes');
            $fechaFormateada = $fecha->format('Y-m-d H:i:s');
            $query = "UPDATE * FROM users 
                SET expired = ':fecha'
            WHERE email = :email ";
            $statement = $conn->prepare($query);
            $statement->bindParam(':email', $email, PDO::PARAM_STR);
            $statement->bindParam(':fecha', $fecha, PDO::PARAM_STR);
            $statement->execute();
            return $user;
        }
    }
}