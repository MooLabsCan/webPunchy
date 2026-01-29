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
    $whenIso = $data['when'] ?? null; // ISO string from client
    if (!$token || !$whenIso) {
        http_response_code(400);
        echo json_encode(['status' => 'fail', 'message' => 'Missing token or when']);
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

    // Find latest open record
    $openStmt = $pdo->prepare('SELECT id, clock_in FROM TimeRecords WHERE username = ? AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1');
    $openStmt->execute([$username]);
    $open = $openStmt->fetch();
    if (!$open) {
        http_response_code(409);
        echo json_encode(['status' => 'no_open_record', 'message' => 'No open record to punch out.']);
        exit;
    }

    $whenTs = strtotime($whenIso);
    if ($whenTs === false) {
        http_response_code(400);
        echo json_encode(['status' => 'fail', 'message' => 'Invalid timestamp']);
        exit;
    }
    $when = date('Y-m-d H:i:s', $whenTs);

    // Validate end >= start
    $startTs = strtotime($open['clock_in']);
    if ($whenTs < $startTs) {
        http_response_code(400);
        echo json_encode(['status' => 'invalid_range', 'message' => 'End time cannot be before start time.']);
        exit;
    }

    $durationMs = ($whenTs - $startTs) * 1000;

    // Update row
    $upd = $pdo->prepare('UPDATE TimeRecords SET clock_out = ?, duration_ms = ? WHERE id = ?');
    $upd->execute([$when, $durationMs, $open['id']]);

    echo json_encode(['status' => 'ok', 'record' => [
        'id' => (int)$open['id'],
        'username' => $username,
        'clock_in' => $open['clock_in'],
        'clock_out' => $when,
        'duration_ms' => (int)$durationMs
    ]]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'fail', 'message' => $e->getMessage()]);
}
