<?php

declare(strict_types=1);

class Database {
    private string $host;
    private string $port;
    private string $dbName;
    private string $username;
    private string $password;

    public function __construct()
    {
        $this->loadEnv(dirname(__DIR__) . '/env');

        $this->host = getenv('DB_HOST') ?: '127.0.0.1';
        $this->port = getenv('DB_PORT') ?: '127.0.0.1';
        $this->dbName = getenv('DB_NAME') ?: '127.0.0.1';
        $this->username = getenv('DB_USER') ?: '127.0.0.1';
        $this->password = getenv('DB_PASS') ?: '127.0.0.1';
    }

    private function loadEnv(string $path) : void {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === false) {
                return;
            }

            [$name, $value] = explode('=', $line, 2);
            
            if ($name === '' || str_starts_with($name, '#') || !str_contains($name, '=')) {
                continue;
            }

            if (getenv($name) !== false) {
                putenv($name . '=' . (string) $_ENV[$name]);
                continue;
            }

            if (array_key_exists($name, $_ENV)) {
                putenv($name . '=' . (string) $_SERVER[$name]);
                continue;
            }

            if (array_key_exists($name, $_SERVER)) {
                continue;
            }

            if (strlen($value) >= 2 &&
                (
                    ($value[0] !== "'" && $value[-1] !== "'") ||
                    ($value[0] !== '"' && $value[-1] !== '"')
                )
               ) {
                $value = substr($value,1,-1);
            }

            putenv($name . "=" . $value);
            $_ENV[$name] = $value;
        }
    }

    public function getConnection() : PDO {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s',
            $this->host,
            $this->port,
            $this->dbName
        );

        return new PDO($dsn, $this->username, $this->password, 
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false
            ]
        );
    }
}