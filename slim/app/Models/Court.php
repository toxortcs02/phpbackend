<?php
namespace App\Models;

use App\Config\Database;
use PDO;
use PDOException;
use DateTime;
class Court {
    private $conn;
    private $table = 'courts';
    public $id;
    public $name;
    public $description;
    public function __construct(PDO $db) {
        $this->conn = $db;
    }



    public function create() {
        try {
            $query = "INSERT INTO {$this->table} 
                    (name, description) 
                    VALUES (:name, :description)";
            
            $stmt = $this->conn->prepare($query);

            $stmt->bindParam(':name', $this->name, PDO::PARAM_STR);
            $stmt->bindParam(':description', $this->description, PDO::PARAM_STR);

            if ($stmt->execute()) {
                $this->id = $this->conn->lastInsertId();
                return $this->id;
            }
            return false;

        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
            return false;
        }
    }

}