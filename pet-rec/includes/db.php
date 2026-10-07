<?php
require_once __DIR__ . '/config.php';

/**
 * PDO Database Singleton Connection
 * (for shared hosting — ultra-stable, no Composer)
 */
class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => true,
            ];
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Don't expose credentials on error
            die('<div style="padding:40px;font-family:sans-serif;max-width:700px;margin:40px auto;background:#FFF5F0;border:1px solid #FFD1C0;border-radius:16px;">
            <h2 style="color:#E55536;margin:0 0 16px 0;">🔧 Database Connection Required</h2>
            <p style="color:#4A4A4A;line-height:1.6;margin-bottom:16px;">
                Welcome to <strong>BeethovenPets</strong>! Before we can launch, please complete these 2 quick steps:
            </p>
            <ol style="color:#4A4A4A;line-height:1.8;padding-left:20px;margin-bottom:20px;">
                <li><strong>Import the SQL file:</strong> Upload <code style="background:#FFE4D9;padding:2px 6px;border-radius:6px;">database/beethovenpets_schema.sql</code> to your phpMyAdmin / MySQL manager (cPanel or aaPanel).</li>
                <li><strong>Update credentials:</strong> Open <code style="background:#FFE4D9;padding:2px 6px;border-radius:6px;">includes/config.php</code> and set your MySQL username, password, and database name (typically <code>cpusername_dbname</code> on cPanel).</li>
            </ol>
            <p style="color:#888;font-size:14px;">If you need help, check the included <code>DEPLOYMENT-README.md</code> file.</p>
            <details style="margin-top:20px;color:#888;font-size:12px;"><summary>Developer Debug Info</summary><pre style="white-space:pre-wrap;word-wrap:break-word;background:#1A1A2E;color:#E8E6E3;padding:16px;border-radius:10px;font-size:12px;">' . htmlspecialchars($e->getMessage()) . '</pre></details>
            </div>');
        }
    }

    public static function getInstance(): Database {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    public function getConnection(): PDO {
        return $this->pdo;
    }

    // Shortcut: SELECT single row
    public function fetch(string $sql, array $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() ?: null;
    }

    // Shortcut: SELECT all rows
    public function fetchAll(string $sql, array $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // Shortcut: INSERT / UPDATE / DELETE — returns affected rows or lastInsertId
    public function execute(string $sql, array $params = [], bool $returnId = false) {
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute($params);
        return $returnId && str_starts_with(trim(strtoupper($sql)), 'INSERT')
            ? (int) $this->pdo->lastInsertId()
            : $result;
    }
}

// Global shortcut: $db = pawDB()->getConnection();
function pawDB(): Database {
    return Database::getInstance();
}
