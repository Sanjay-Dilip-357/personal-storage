<?php
/**
 * PERSONAL STORAGE — Database Connection
 */

declare(strict_types=1);

class Database
{
    private static ?Database $instance = null;
    private ?PDO $connection = null;

    private function __construct()
    {
        $host = Config::get('DB_HOST', '127.0.0.1');
        $port = Config::get('DB_PORT', '3306');
        $db   = Config::get('DB_DATABASE', 'personal_storage');
        $user = Config::get('DB_USERNAME', 'root');
        $pass = Config::get('DB_PASSWORD', '');

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+05:30'"
        ];

        try {
            $this->connection = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            error_log('[DB Error] Connection failed: ' . $e->getMessage());
            throw new RuntimeException('Database connection failed.');
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }

    public static function getPDO(): PDO
    {
        return self::getInstance()->getConnection();
    }
}