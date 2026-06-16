<?php
declare(strict_types=1);

header('Content-Type: application/json');
require __DIR__ . '/db.php';

function read_json_body(): array
{
    $rawBody = file_get_contents('php://input');
    $data = json_decode($rawBody ?: '', true);
    return is_array($data) ? $data : [];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.',
    ]);
    exit;
}

$data = read_json_body();

if (empty($data['email']) || empty($data['password']) || empty($data['fullName'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Email, password, and full name are required.',
    ]);
    exit;
}

$email = trim($data['email']);
$password = $data['password'];
$fullName = trim($data['fullName']);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid email format.',
    ]);
    exit;
}

if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Password must be at least 6 characters.',
    ]);
    exit;
}

if (strlen($fullName) < 2) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Full name must be at least 2 characters.',
    ]);
    exit;
}

try {
    // Check if user already exists
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Email already registered.',
        ]);
        exit;
    }

    // Create new user
    $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    $stmt = $pdo->prepare(
        'INSERT INTO users (email, password_hash, full_name, role) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$email, $passwordHash, $fullName, 'Farmer']);

    session_start();
    $_SESSION['user_id'] = (int) $pdo->lastInsertId();
    $_SESSION['last_activity'] = time();

    echo json_encode([
        'success' => true,
        'message' => 'Registration successful. You are now logged in.',
        'user' => [
            'id' => $_SESSION['user_id'],
            'email' => $email,
            'fullName' => $fullName,
            'role' => 'Farmer',
        ],
    ]);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Email already registered.',
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error during registration.',
        ]);
    }
}
