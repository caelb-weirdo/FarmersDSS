<?php
declare(strict_types=1);

header('Content-Type: application/json');
require __DIR__ . '/db.php';

function csv_to_array(string $value): array
{
    return array_map('trim', explode(',', $value));
}

function read_json_body(): array
{
    $rawBody = file_get_contents('php://input');
    $data = json_decode($rawBody ?: '', true);
    return is_array($data) ? $data : [];
}

function calculate_score(array $crop, array $inputs): array
{
    $score = 40;
    $reasons = [];
    $bestSoils = csv_to_array($crop['best_soils']);
    $bestSeasons = csv_to_array($crop['best_seasons']);
    $bestWater = csv_to_array($crop['best_water']);

    if (in_array($inputs['soil'], $bestSoils, true)) {
        $score += 15;
        $reasons[] = "{$crop['crop_name']} suits {$inputs['soil']} soil.";
    }

    if (in_array($inputs['season'], $bestSeasons, true)) {
        $score += 15;
        $reasons[] = "{$inputs['season']} season is suitable for {$crop['crop_name']}.";
    }

    if (in_array($inputs['water'], $bestWater, true)) {
        $score += 15;
        $reasons[] = "{$inputs['water']} water source supports {$crop['crop_name']}.";
    }

    if ($crop['budget_level'] === $inputs['budget']) {
        $score += 8;
        $reasons[] = 'The ' . strtolower($inputs['budget']) . ' budget level matches this crop.';
    }

    if ($crop['market_demand'] === $inputs['demand']) {
        $score += 7;
        $reasons[] = 'Market demand is ' . strtolower($inputs['demand']) . ' for this crop.';
    }

    return [
        'crop' => $crop['crop_name'],
        'bestSoils' => $bestSoils,
        'bestSeasons' => $bestSeasons,
        'bestWater' => $bestWater,
        'budget' => $crop['budget_level'],
        'demand' => $crop['market_demand'],
        'duration' => $crop['duration'],
        'profit' => (int) $crop['profit_score'],
        'score' => min($score, 100),
        'reasons' => $reasons,
    ];
}

$inputs = read_json_body();
$requiredFields = ['district', 'soil', 'season', 'water', 'budget', 'demand'];

foreach ($requiredFields as $field) {
    if (empty($inputs[$field])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => "Missing field: {$field}",
        ]);
        exit;
    }
}

$cropRows = $pdo->query('SELECT * FROM crops ORDER BY id')->fetchAll();
$results = [];

foreach ($cropRows as $crop) {
    $results[] = calculate_score($crop, $inputs);
}

usort($results, fn ($a, $b) => $b['score'] <=> $a['score']);
$best = $results[0];

$statement = $pdo->prepare(
    'INSERT INTO recommendation_history
      (district, soil_type, season_type, water_source, budget_level, market_demand, recommended_crop, score)
     VALUES
      (:district, :soil, :season, :water, :budget, :demand, :crop, :score)'
);

$statement->execute([
    ':district' => $inputs['district'],
    ':soil' => $inputs['soil'],
    ':season' => $inputs['season'],
    ':water' => $inputs['water'],
    ':budget' => $inputs['budget'],
    ':demand' => $inputs['demand'],
    ':crop' => $best['crop'],
    ':score' => $best['score'],
]);

echo json_encode([
    'success' => true,
    'results' => $results,
    'historyId' => (int) $pdo->lastInsertId(),
]);
