<?php
namespace Docker\Slim\App\Config;

use PDO;
use PDOException;

class Database{
    private $host = 'localhost';
    private $db_name = 'seminariophp';
    private $username = 'seminariophp';
    private $password = 'seminariophp';             
    public $conn;

    public function getConnection(){
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->db_name};charset=utf8",
                $this->username,
                $this->password
            );

            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $exception) {
            echo "Error de conexión: " . $exception->getMessage();
        }
        return $this->conn;
    }
}