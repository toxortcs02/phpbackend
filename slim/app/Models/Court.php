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

    // NUEVO MÉTODO: Para obtener todas las canchas, requerido por getAllCourts()
    public function getAll() {
        $stmt = $this->conn->query("SELECT * FROM {$this->table}");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // MEJORA: Unificado y con parámetros directos, como lo llama el controlador
    public function create($name, $description) {
        try {
            $query = "INSERT INTO {$this->table} (name, description) VALUES (:name, :description)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':description', $description);

            if ($stmt->execute()) {
                return $this->conn->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            // MEJORA: Lanzamos la excepción para que el controlador la capture
            throw $e;
        }
    }

    // MEJORA: Renombrado de 'editCourt' a 'update' y con firma compatible
    public function update($id, $name, $description): bool {
        try {
            $fields = [];
            $params = ['id' => $id];

            if ($name !== null) {
                $fields[] = 'name = :name';
                $params['name'] = $name;
            }
            if ($description !== null) {
                $fields[] = 'description = :description';
                $params['description'] = $description;
            }

            if (empty($fields)) {
                return false;
            }

            $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";
            $stmt = $this->conn->prepare($sql);
            
            $stmt->execute($params);
            // Devuelve true solo si se afectó al menos una fila
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw $e;
        }
    }

    // NUEVO MÉTODO: Para eliminar una cancha, requerido por deleteCourt()
    public function delete($id): bool {
        try {
            $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            throw $e;
        }
    }

    public function hasBookings($courtId): bool {
        $stmt = $this->conn->prepare("SELECT 1 FROM bookings WHERE court_id = :court_id LIMIT 1");
        $stmt->bindParam(':court_id', $courtId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() !== false;
    }
}

