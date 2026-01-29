<?php
// CORS similar to check_session.php
$allowed_origins = [
    'http://localhost:5173',
    'https://learni.liap.ca',
    'https://mooai.liap.ca',
    'https://liap.ca',
    'https://www.liap.ca'
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: " . $origin);
    header("Access-Control-Allow-Headers: Content-Type");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/AccountConfig.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'fail', 'message' => 'Invalid method']);
        exit;
    }

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        http_response_code(500);
        echo json_encode(['status' => 'fail', 'message' => 'DB not available']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $token = $data['token'] ?? null;
    if (!$token) {
        http_response_code(400);
        echo json_encode(['status' => 'fail', 'message' => 'Missing token']);
        exit;
    }

    // Resolve user by token
    $stmt = $pdo->prepare('SELECT username FROM users WHERE auth_token = ?');
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['status' => 'invalid_token', 'message' => 'Authentication failed.']);
        exit;
    }
    $username = $user['username'];

    // Ensure table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS TimeRecords (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(255) NOT NULL,
        clock_in DATETIME NOT NULL,
        clock_out DATETIME NULL,
        duration_ms BIGINT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_open (username, clock_out)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Open record
    $openStmt = $pdo->prepare('SELECT id, username, clock_in FROM TimeRecords WHERE username = ? AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1');
    $openStmt->execute([$username]);
    $open = $openStmt->fetch() ?: null;

    // Completed records
    $recStmt = $pdo->prepare('SELECT id, username, clock_in, clock_out, duration_ms FROM TimeRecords WHERE username = ? AND clock_out IS NOT NULL ORDER BY clock_in DESC LIMIT 500');
    $recStmt->execute([$username]);
    $records = $recStmt->fetchAll();

    echo json_encode([
        'status' => 'ok',
        'open_record' => $open,
        'records' => $records
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'fail', 'message' => $e->getMessage()]);
}
