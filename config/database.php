<?php
/**
 * Database Configuration
 * Library Management System
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'library_management_system');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Database connection options
define('DB_OPTIONS', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_STRINGIFY_FETCHES => false,
]);

/**
 * Get database connection
 * @return PDO
 * @throws PDOException
 */
function getDBConnection(): PDO {
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, DB_OPTIONS);
        return $pdo;
    } catch (PDOException $e) {
        // Log error but don't expose details to user
        error_log('Database Connection Error: ' . $e->getMessage());
        throw new PDOException('Database connection failed. Please check your configuration.');
    }
}

/**
 * Execute a query with prepared statements
 * @param string $sql
 * @param array $params
 * @return PDOStatement
 */
function executeQuery(string $sql, array $params = []): PDOStatement {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Get single row
 * @param string $sql
 * @param array $params
 * @return array|null
 */
function getRow(string $sql, array $params = []): ?array {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetch() ?: null;
}

/**
 * Get all rows
 * @param string $sql
 * @param array $params
 * @return array
 */
function getRows(string $sql, array $params = []): array {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetchAll();
}

/**
 * Insert a record and return last insert ID
 * @param string $table
 * @param array $data
 * @return int
 */
function insertRecord(string $table, array $data): int {
    $columns = array_keys($data);
    $placeholders = array_fill(0, count($columns), '?');
    $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $stmt = executeQuery($sql, array_values($data));
    return (int) getDBConnection()->lastInsertId();
}

/**
 * Update a record
 * @param string $table
 * @param array $data
 * @param string $where
 * @param array $whereParams
 * @return int Number of affected rows
 */
function updateRecord(string $table, array $data, string $where, array $whereParams = []): int {
    $set = [];
    $params = [];
    foreach ($data as $column => $value) {
        $set[] = "{$column} = ?";
        $params[] = $value;
    }
    $sql = "UPDATE {$table} SET " . implode(', ', $set) . " WHERE {$where}";
    $stmt = executeQuery($sql, array_merge($params, $whereParams));
    return $stmt->rowCount();
}

/**
 * Begin transaction
 */
function beginTransaction(): void {
    getDBConnection()->beginTransaction();
}

/**
 * Commit transaction
 */
function commitTransaction(): void {
    getDBConnection()->commit();
}

/**
 * Rollback transaction
 */
function rollbackTransaction(): void {
    getDBConnection()->rollBack();
}

/**
 * Check if in transaction
 * @return bool
 */
function inTransaction(): bool {
    return getDBConnection()->inTransaction();
}