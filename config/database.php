<?php
// config/database.php

class Database {

    private $host = "localhost";
    private $port = "3307";
    private $db_name = "pos_apotek";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;

        try {

            $this->conn = new PDO(
                "mysql:host=" . $this->host . 
                ";port=" . $this->port . 
                ";dbname=" . $this->db_name, 
                $this->username, 
                $this->password
            );

            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // set default array fetch
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // 3. set off emulasi prepared statements
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

            $this->conn->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);

        } catch(PDOException $exception) {
            die("Connection failed: " . $exception->getMessage());
        }

        return $this->conn;
    }
}
