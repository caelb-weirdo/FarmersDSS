<?php
declare(strict_types=1);

header('Content-Type: application/json');
require __DIR__ . '/db.php';
require __DIR__ . '/auth.php';

function read_json_body(): array
{
    $rawBody = file_get_contents('php://input');
    $data = json_decode($rawBody ?: '', true);
    return is_array($data) ? $data : [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = read_json_body();

    if (empty($data['email']) || empty($data['password'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Email and password are required.',
        ]);
        exit;
    }

    try {
        $stmt = $pdo->prepare('SELECT id, email, full_name, role, password_hash FROM users WHERE email = ?');
        $stmt->execute([$data['email']]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($data['password'], $user['password_hash'])) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid email or password.',
            ]);
            exit;
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['last_activity'] = time();

        echo json_encode([
            'success' => true,
            'message' => 'Login successful.',
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'fullName' => $user['full_name'],
                'role' => $user['role'],
            ],
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error during login.',
        ]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user = get_logged_in_user();

    if ($user) {
        echo json_encode([
            'success' => true,
            'authenticated' => true,
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'fullName' => $user['full_name'],
                'role' => $user['role'],
            ],
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'authenticated' => false,
            'user' => null,
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.',
    ]);
}
