<?php
/**
 * Database — PDO Singleton Wrapper
 *
 * Provides a single shared PDO connection with convenience helpers.
 * Zero framework dependencies — mirrors the project's vanilla PHP approach.
 *
 * Usage:
 *   $db  = Database::getInstance();
 *   $row = $db->fetchOne('SELECT * FROM recipe_extractions WHERE id = ?', [$id]);
 */
class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    // ── Constructor ────────────────────────────────────────────────────────────

    private function __construct()
    {
        if (!class_exists('Config')) {
            require_once __DIR__ . '/Config.php';
        }
        Config::load();

        // Read from Config which already resolved via $_SERVER/$_ENV/getenv chain
        $host    = Config::get('connections.mysql.host',      'db');
        $port    = Config::get('connections.mysql.port',      3306);
        $dbname  = Config::get('connections.mysql.database',  'cleanplate');
        $charset = Config::get('connections.mysql.charset',   'utf8mb4');
        $user    = Config::get('connections.mysql.username',  'root');
        $pass    = Config::get('connections.mysql.password',  '');

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        $this->pdo = new PDO($dsn, $user, $pass, $options);
    }

    // ── Singleton ──────────────────────────────────────────────────────────────

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Prevent cloning / unserialization
    private function __clone() {}
    public function __wakeup(): void
    {
        throw new \RuntimeException('Database instance cannot be unserialized.');
    }

    // ── Query helpers ──────────────────────────────────────────────────────────

    /**
     * Prepare and execute a query, returning the PDOStatement.
     *
     * @param  string  $sql
     * @param  array   $params  Positional (?) or named (:key) parameters
     * @return \PDOStatement
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Return a single row as an associative array, or null if not found.
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * Return all rows as an array of associative arrays.
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Execute a statement (INSERT / UPDATE / DELETE) and return the row count.
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    /**
     * Return the last inserted auto-increment ID.
     */
    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Return the raw PDO instance for edge cases.
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    // ── Pagination helper ──────────────────────────────────────────────────────

    /**
     * Run a SELECT with LIMIT/OFFSET and also return the total matching rows.
     *
     * @param  string $countSql   e.g. "SELECT COUNT(*) FROM ... WHERE ..."
     * @param  string $dataSql    e.g. "SELECT * FROM ... WHERE ... ORDER BY ... LIMIT ? OFFSET ?"
     * @param  array  $countParams  Params for the count query
     * @param  array  $dataParams   Params for the data query (must end with limit, offset)
     * @return array{data: array, total: int}
     */
    public function paginate(
        string $countSql,
        string $dataSql,
        array $countParams = [],
        array $dataParams = []
    ): array {
        $total = (int) $this->query($countSql, $countParams)->fetchColumn();
        $data  = $this->fetchAll($dataSql, $dataParams);
        return ['data' => $data, 'total' => $total];
    }
}
