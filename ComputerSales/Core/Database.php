<?php
// ComputerSales/Core/Database.php
// FIX: Collation-aware PDO connection to prevent FK #3780 errors
// FIX: Uses utf8mb4_unicode_ci to match existing users table

namespace ComputerSales\Core;

use PDO;
use PDOException;
use PDOStatement;

class Database {
    private static ?PDO $instance = null;
    private PDO $connection;

    public function __construct() {
        if (self::$instance === null) {
            try {
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    // CRITICAL FIX: Set connection collation to match existing tables
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, SESSION sql_mode='STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'"
                ]);
            } catch (PDOException $e) {
                error_log("CS Database Error: " . $e->getMessage());
                throw new \Exception("Database connection failed");
            }
        }
        $this->connection = self::$instance;
    }

    public function getConnection(): PDO {
        return $this->connection;
    }

    public function query(string $sql, array $params = []): PDOStatement {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetch(string $sql, array $params = []): ?array {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function fetchAll(string $sql, array $params = []): array {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function insert(string $table, array $data): int {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, array_values($data));
        return (int)$this->connection->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $whereParams): int {
        $set = implode(' = ?, ', array_keys($data)) . ' = ?';
        $sql = "UPDATE {$table} SET {$set} WHERE {$where}";
        $params = array_merge(array_values($data), $whereParams);
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function delete(string $table, string $where, array $params): int {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function beginTransaction(): bool {
        return $this->connection->beginTransaction();
    }

    public function commit(): bool {
        return $this->connection->commit();
    }

    public function rollback(): bool {
        return $this->connection->rollBack();
    }

    // Helper: Check if a table exists
    public function tableExists(string $table): bool {
        $sql = "SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = ? LIMIT 1";
        $result = $this->fetch($sql, [DB_NAME, $table]);
        return $result !== null;
    }

    // Helper: Get column info to debug FK issues
    public function getColumnInfo(string $table, string $column): ?array {
        $sql = "SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, COLLATION_NAME 
                FROM information_schema.columns 
                WHERE table_schema = ? AND table_name = ? AND column_name = ?";
        return $this->fetch($sql, [DB_NAME, $table, $column]);
    }
}