<?php
namespace App\Models;

use PDO;
use PDOException;
use DateTime;

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
    public function getBookingsByCourtId($courtId) {
        $stmt = $this->conn->prepare("SELECT * FROM bookings WHERE court_id = :court_id");
        $stmt->bindParam(':court_id', $courtId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function deleteOldBookings($court_id) {
        // 1. Obtener reservas vencidas
        $query = "SELECT id FROM bookings 
                WHERE DATE_ADD(booking_datetime, INTERVAL (duration_blocks * 30) MINUTE) < :current_datetime 
                AND court_id = :court_id";
        
        $stmt = $this->conn->prepare($query);
        $currentDatetime = (new \DateTime())->format('Y-m-d H:i:s');
        $stmt->bindParam(':court_id', $court_id);
        $stmt->bindParam(':current_datetime', $currentDatetime);
        $stmt->execute();
        
        $oldBookings = $stmt->fetchAll(\PDO::FETCH_COLUMN); // Array de booking_id

        if (empty($oldBookings)) {
            return false; // no hay reservas a eliminar
        }

        // 2. Eliminar participantes
        $idsStr = implode(',', $oldBookings); // "1,2,3"
        $queryDeleteParticipants = "DELETE FROM booking_participants WHERE booking_id IN ($idsStr)";
        $this->conn->exec($queryDeleteParticipants);

        // 3. Eliminar reservas
        $queryDeleteBookings = "DELETE FROM bookings WHERE id IN ($idsStr)";
        $this->conn->exec($queryDeleteBookings);

        return true;
    }


}