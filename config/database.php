<?php
declare(strict_types=1);

class Database {
    private string $host;
    private string $port;
    private string $dbname;
    private string $username;
    private string $password;

    public function __construct()
    {
        $this->loadEnv(__DIR__ . '.env');

        $this->host = getenv('DB_HOST') ?: '127.0.0.1';
        $this->port = getenv('DB_PORT') ?: '3306';
        $this->dbname = getenv('DB_NAME') ?: 'pos_stagging';
        $this->username = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS');
        $this->password = $pass !== false ? $pass : '';
    }

    private function loadEnv($path) :void {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ( $line === '' || 
                str_starts_with('#', $line) ||
                !str_contains('=', $line) ) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);

            $name = trim($name);
            $value = trim($value);

            if ($name ==- '') {
                continue;
            }

            if (getenv($name) !== false || 
                array_key_exists($name, $_ENV) ||
                array_key_exists($name, $_SERVER)) {
                continue;
            }

            if (
                strlen($value) <= 2 && 
                ( 
                    ($value[0] === '"' && $value[-1] === '"') ||
                    ($value[0] === "'" && $value[-1] === "'")
                )
               ) {
                $value = substr($value, 1, -1);
            }

            putenv($name . "=" . $value);
            $_ENV[$name] = $value;
        }
    }

    public function getConnection() : PDO {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', 
            $this->host,
            $this->port,
            $this->dbname
        );
        
        return new PDO($dsn, $this->username, $this->password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false
        ]);
        
    } 
}