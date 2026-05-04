<?php
declare(strict_types=1);

function db(): mysqli
{
    static $conn = null;
    if ($conn instanceof mysqli) {
        return $conn;
    }

    $host = getenv('PMMS_DB_HOST') ?: '127.0.0.1';
    $user = getenv('PMMS_DB_USER') ?: 'root';
    $pass = getenv('PMMS_DB_PASS') ?: '';
    $name = getenv('PMMS_DB_NAME') ?: 'pmms';
    $port = (int) (getenv('PMMS_DB_PORT') ?: 3306);

    $conn = new mysqli($host, $user, $pass, $name, $port);
    if ($conn->connect_error) {
        http_response_code(500);
        exit('Database connection failed: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');
    $conn->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    return $conn;
}

function run_query(string $sql, string $types = '', array $params = []): mysqli_stmt
{
    $stmt = db()->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        exit('Query prepare failed');
    }
    if ($types !== '' && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        http_response_code(500);
        exit('Query execute failed');
    }
    return $stmt;
}

function fetch_all_assoc(mysqli_stmt $stmt): array
{
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function fetch_one_assoc(mysqli_stmt $stmt): ?array
{
    $result = $stmt->get_result();
    if (!$result) {
        return null;
    }
    $row = $result->fetch_assoc();
    return $row ?: null;
}
