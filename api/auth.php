<?php
declare(strict_types=1);

session_start();

header('Content-Type: application/json');
require __DIR__ . '/db.php';

const SESSION_TIMEOUT = 3600; // 1 hour

function get_current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    if (!empty($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
        session_destroy();
        return null;
    }

    $_SESSION['last_activity'] = time();

    try {
        $stmt = $GLOBALS['pdo']->prepare('SELECT id, email, full_name, role FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    } catch (Exception $e) {
        return null;
    }
}

function is_authenticated(): bool
{
    return get_current_user() !== null;
}

function require_auth(): void
{
    if (!is_authenticated()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Not authenticated. Please login first.',
        ]);
        exit;
    }
}

function require_admin(): void
{
    $user = get_current_user();
    if ($user === null || $user['role'] !== 'Admin') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Admin access required.',
        ]);
        exit;
    }
}
