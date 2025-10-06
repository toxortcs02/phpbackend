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
    // obtiene una cancha por su id y devuelve un array asociativo
    public function findById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM courts WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function findByName($name) {
        $stmt = $this->conn->prepare("SELECT * FROM courts WHERE name = :name");
        $stmt->bindParam(':name', $name);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    

    public function createCourt() {
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

    public function editCourt($id) {
        try {
            $query = "UPDATE {$this->table} 
                    SET name = :name, description = :description
                    WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);

            $stmt->bindParam(':name', $this->name, PDO::PARAM_STR);
            $stmt->bindParam(':description', $this->description, PDO::PARAM_STR);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);

            return $stmt->execute();

        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
            return false;
        }
    }
}