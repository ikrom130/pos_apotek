<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();

    $stmt = $pdo->query(
        'SELECT DATABASE() AS database_name, VERSION() AS mysql_version'
    );

    $result = $stmt->fetch();

    echo "Koneksi berhasil.\n";
    echo "Database: " . $result['database_name'] . "\n";
    echo "MySQL: " . $result['mysql_version'] . "\n";
} catch (PDOException $exception) {
    echo "Koneksi gagal: " . $exception->getMessage() . "\n";
}