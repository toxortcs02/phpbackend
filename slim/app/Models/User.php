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
}