<?php
declare(strict_types=1);

header('Content-Type: application/json');
require __DIR__ . '/db.php';

function csv_to_array(string $value): array
{
    return array_map('trim', explode(',', $value));
}

$cropRows = $pdo->query('SELECT * FROM crops ORDER BY id')->fetchAll();
$guideRows = $pdo->query('SELECT crop_id, guide_text FROM crop_guides ORDER BY id')->fetchAll();
$fertilizerRows = $pdo->query(
    'SELECT c.crop_name, f.fertilizer_type, f.kg_per_acre, f.price_per_kg, f.schedule_text
     FROM fertilizer_rules f
     JOIN crops c ON c.id = f.crop_id
     ORDER BY c.id, f.id'
)->fetchAll();
$marketRows = $pdo->query('SELECT crop_name, price_per_kg, trend, demand FROM market_prices ORDER BY id')->fetchAll();
$weatherRows = $pdo->query('SELECT * FROM weather_alerts ORDER BY id')->fetchAll();

$guidesByCropId = [];
foreach ($guideRows as $guide) {
    $guidesByCropId[(int) $guide['crop_id']][] = $guide['guide_text'];
}

$fertilizerRules = [];
foreach ($fertilizerRows as $row) {
    $cropName = $row['crop_name'];
    $fertilizerRules[$cropName][] = [
        'type' => $row['fertilizer_type'],
        'kgPerAcre' => (float) $row['kg_per_acre'],
        'pricePerKg' => (float) $row['price_per_kg'],
        'schedule' => $row['schedule_text'],
    ];
}

$crops = [];
foreach ($cropRows as $crop) {
    $cropId = (int) $crop['id'];
    $crops[] = [
        'crop' => $crop['crop_name'],
        'bestSoils' => csv_to_array($crop['best_soils']),
        'bestSeasons' => csv_to_array($crop['best_seasons']),
        'bestWater' => csv_to_array($crop['best_water']),
        'budget' => $crop['budget_level'],
        'demand' => $crop['market_demand'],
        'duration' => $crop['duration'],
        'profit' => (int) $crop['profit_score'],
        'guide' => $guidesByCropId[$cropId] ?? [],
    ];
}

$market = [];
foreach ($marketRows as $row) {
    $market[] = [
        'crop' => $row['crop_name'],
        'price' => (float) $row['price_per_kg'],
        'trend' => $row['trend'],
        'demand' => $row['demand'],
    ];
}

$weather = [];
foreach ($weatherRows as $row) {
    $weather[$row['district']] = [
        'temp' => (int) $row['temperature'],
        'text' => $row['weather_text'],
        'risk' => $row['risk_text'],
        'alerts' => (int) $row['alert_count'],
        'status' => 'System Online',
        'fields' => 12,
        'logs' => 7,
    ];
}

echo json_encode([
    'success' => true,
    'crops' => $crops,
    'fertilizerRules' => $fertilizerRules,
    'marketData' => $market,
    'weatherByDistrict' => $weather,
]);
