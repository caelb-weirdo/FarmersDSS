<?php
declare(strict_types=1);

header('Content-Type: application/json');
require __DIR__ . '/db.php';
require __DIR__ . '/auth.php';

// Ensure only admins can access this API
require_admin();

function read_json_body(): array
{
    $rawBody = file_get_contents('php://input');
    $data = json_decode($rawBody ?: '', true);
    return is_array($data) ? $data : [];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->query('SELECT id, crop_name as crop, price_per_kg as price, trend, demand, updated_at FROM market_prices ORDER BY crop_name ASC');
        $prices = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'marketPrices' => $prices
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error fetching market prices.']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = read_json_body();
    
    if (empty($data['id']) || empty($data['price']) || empty($data['trend']) || empty($data['demand'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare('UPDATE market_prices SET price_per_kg = ?, trend = ?, demand = ?, updated_at = CURDATE() WHERE id = ?');
        $stmt->execute([
            $data['price'],
            $data['trend'],
            $data['demand'],
            $data['id']
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Market price updated successfully.'
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error updating market price.']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
}
