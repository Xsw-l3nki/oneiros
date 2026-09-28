<?php
/**
 * Oneiros — Database Connection (PDO)
 */

class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $cfg = require __DIR__ . '/runtime-config.php';
            $dsn = "mysql:host={$cfg['db_host']};port={$cfg['db_port']};dbname={$cfg['db_name']};charset={$cfg['db_charset']}";
            try {
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];
                if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
                    $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci";
                }
                self::$pdo = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], $options);
                self::$pdo->exec("SET time_zone = '+00:00'");
            } catch (PDOException $e) {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode([
                    'error' => 'Database connection failed',
                    'detail' => ($cfg['debug'] ?? false) ? $e->getMessage() : 'Check includes/runtime-config.php'
                ]);
                error_log('Oneiros DB error: ' . $e->getMessage());
                exit;
            }
        }
        return self::$pdo;
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $parameter = is_int($key) ? $key + 1 : ':' . ltrim($key, ':');
            $type = is_int($value) ? PDO::PARAM_INT : (is_bool($value) ? PDO::PARAM_BOOL : ($value === null ? PDO::PARAM_NULL : PDO::PARAM_STR));
            $stmt->bindValue($parameter, $value, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row ?: null;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function insert(string $table, array $data): string
    {
        $cols = array_keys($data);
        $placeholders = array_map(fn($c) => ":$c", $cols);
        $sql = "INSERT INTO `$table` (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $placeholders) . ")";
        self::query($sql, $data);
        return $data['id'] ?? self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $whereCol, $whereVal): bool
    {
        $sets = array_map(fn($c) => "`$c` = :$c", array_keys($data));
        $sql = "UPDATE `$table` SET " . implode(', ', $sets) . " WHERE `$whereCol` = :__where";
        $params = array_merge($data, ['__where' => $whereVal]);
        return self::query($sql, $params)->rowCount() > 0;
    }

    public static function delete(string $table, string $whereCol, $whereVal): int
    {
        return self::query("DELETE FROM `$table` WHERE `$whereCol` = :v", ['v' => $whereVal])->rowCount();
    }
}
