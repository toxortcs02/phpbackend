<?php
namespace App\Models;

use PDO;
use PDOException;

class Court {
    private $conn;
    private $table = 'courts';

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    public function findById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByName($name) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE name = :name");
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function create($name, $description) {
        $query = "INSERT INTO {$this->table} (name, description) VALUES (:name, :description)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':description', $description);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    public function update($id, $name, $description): bool {
        $fields = [];
        $params = ['id' => $id];

        if ($name !== null && $name !== '') {
            $fields[] = 'name = :name';
            $params['name'] = $name;
        }
        if ($description !== null && $description !== '') {
            $fields[] = 'description = :description';
            $params['description'] = $description;
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function delete($id): bool {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function hasBookings($courtId): bool {
        $stmt = $this->conn->prepare("SELECT 1 FROM bookings WHERE court_id = :court_id LIMIT 1");
        $stmt->bindParam(':court_id', $courtId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() !== false;
    }
    public function getAllCourts() {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table}");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}