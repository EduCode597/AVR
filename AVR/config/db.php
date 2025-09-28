<?php
// Robust PDO connection (UTF-8, exceptions) with sensible fallbacks for XAMPP/local and production.
// You can override settings via environment variables: DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS

// 1) Local defaults (XAMPP)
$DB = [
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => (int)(getenv('DB_PORT') ?: 3307), // XAMPP often uses 3307; we will still try 3306 below
    'name' => getenv('DB_NAME') ?: 'avr_db',
    'user' => getenv('DB_USER') ?: 'root',
    'pass' => getenv('DB_PASS') !== false ? getenv('DB_PASS') : ''
];

// 2) Auto-switch to production DB on your domain if env vars not provided
$hostHeader = strtolower((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
$isProdDomain = ($hostHeader === 'fnpsshop.zya.me' || str_ends_with($hostHeader, '.zya.me'));
$envProvided = (getenv('DB_HOST') !== false) || (getenv('DB_NAME') !== false) || (getenv('DB_USER') !== false);
if ($isProdDomain && !$envProvided) {
    $DB = [
        'host' => 'sql205.hstn.me',
        'port' => 3306,
        'name' => 'mseet_40041759_avr_db',
        'user' => 'mseet_40041759',
        'pass' => 'NzouAndKPE8L',
    ];
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    global $DB;
    $hosts = array_unique([$DB['host'], '127.0.0.1', 'localhost']);
    $ports = array_unique([(int)$DB['port'], 3306, 3307]);
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $lastErr = null;
    foreach ($hosts as $h) {
        foreach ($ports as $p) {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $h, $p, $DB['name']);
            try {
                $pdo = new PDO($dsn, $DB['user'], $DB['pass'], $opts);
                return $pdo;
            } catch (Throwable $e) {
                $lastErr = $e;
                // try next combination
            }
        }
    }
    // If we reach here, all attempts failed.
    if ($lastErr) {
        throw ($lastErr instanceof PDOException) ? $lastErr : new PDOException($lastErr->getMessage());
    }
    throw new PDOException('Database connection failed: no hosts/ports available');
}

// Tiny helper to prepare queries (execution left to caller)
function q(string $sql, array $params = []): PDOStatement { return db()->prepare($sql); }

?>
