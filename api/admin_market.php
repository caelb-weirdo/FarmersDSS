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
    $action = $data['action'] ?? 'update';
    
    if ($action === 'add') {
        if (
            empty($data['crop_name']) ||
            empty($data['best_soils']) ||
            empty($data['best_seasons']) ||
            empty($data['best_water']) ||
            empty($data['budget_level']) ||
            empty($data['market_demand']) ||
            empty($data['duration']) ||
            !isset($data['profit_score']) ||
            empty($data['price']) ||
            empty($data['trend'])
        ) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields for adding a crop.']);
            exit;
        }
        
        try {
            $pdo->beginTransaction();
            
            // Check if crop already exists
            $stmt = $pdo->prepare('SELECT id FROM crops WHERE LOWER(crop_name) = LOWER(?)');
            $stmt->execute([$data['crop_name']]);
            if ($stmt->fetch()) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'A crop with this name already exists.']);
                exit;
            }
            
            // 1. Insert into crops table
            $stmt = $pdo->prepare('INSERT INTO crops (crop_name, best_soils, best_seasons, best_water, budget_level, market_demand, duration, profit_score) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $data['crop_name'],
                $data['best_soils'],
                $data['best_seasons'],
                $data['best_water'],
                $data['budget_level'],
                $data['market_demand'],
                $data['duration'],
                (int)$data['profit_score']
            ]);
            $crop_id = (int)$pdo->lastInsertId();
            
            // 2. Insert into crop_guides table
            if (!empty($data['guide'])) {
                $guideLines = array_filter(array_map('trim', explode("\n", $data['guide'])));
                $stmt = $pdo->prepare('INSERT INTO crop_guides (crop_id, guide_text) VALUES (?, ?)');
                foreach ($guideLines as $line) {
                    $stmt->execute([$crop_id, $line]);
                }
            }
            
            // 3. Insert into market_prices table
            $stmt = $pdo->prepare('INSERT INTO market_prices (crop_name, price_per_kg, trend, demand, updated_at) VALUES (?, ?, ?, ?, CURDATE())');
            $stmt->execute([
                $data['crop_name'],
                (float)$data['price'],
                $data['trend'],
                $data['market_demand'],
            ]);
            
            // 4. Insert into fertilizer_rules table
            if (!empty($data['fertilizers']) && is_array($data['fertilizers'])) {
                $stmt = $pdo->prepare('INSERT INTO fertilizer_rules (crop_id, fertilizer_type, kg_per_acre, price_per_kg, schedule_text) VALUES (?, ?, ?, ?, ?)');
                foreach ($data['fertilizers'] as $rule) {
                    if (empty($rule['type']) || (float)$rule['kgPerAcre'] <= 0) {
                        continue;
                    }
                    $stmt->execute([
                        $crop_id,
                        $rule['type'],
                        (float)$rule['kgPerAcre'],
                        (float)($rule['pricePerKg'] ?: 0),
                        $rule['schedule'] ?: ''
                    ]);
                }
            }
            
            $pdo->commit();
            echo json_encode([
                'success' => true,
                'message' => 'New crop details added successfully.'
            ]);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error adding crop details: ' . $e->getMessage()]);
        }
    } else {
        // Update existing market price
        if (empty($data['id']) || empty($data['price']) || empty($data['trend']) || empty($data['demand'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields for update.']);
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
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
}
