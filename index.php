<?php
require_once 'db.php';
requireLogin();

// --- Action Handlers ---
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_price') {
        requireAdmin();
        $id = (int)$_POST['id'];
        $price = (float)$_POST['price'];
        $trend = $_POST['trend'];
        $demand = $_POST['demand'];
        
        $stmt = $pdo->prepare('UPDATE market_prices SET price_per_kg = ?, trend = ?, demand = ? WHERE id = ?');
        $stmt->execute([$price, $trend, $demand, $id]);
        $message = "Price updated successfully.";
    } elseif ($_POST['action'] === 'add_crop') {
        requireAdmin();
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('INSERT INTO crops (crop_name, best_soils, best_seasons, best_water, budget_level, market_demand, duration, profit_score) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                trim($_POST['crop_name']),
                implode(',', $_POST['best_soils'] ?? []),
                implode(',', $_POST['best_seasons'] ?? []),
                implode(',', $_POST['best_water'] ?? []),
                $_POST['budget_level'],
                $_POST['market_demand'],
                trim($_POST['duration']),
                (int)$_POST['profit_score']
            ]);
            
            $cropId = (int) $pdo->lastInsertId();

            $marketStmt = $pdo->prepare('INSERT INTO market_prices (crop_name, price_per_kg, trend, demand) VALUES (?, ?, ?, ?)');
            $marketStmt->execute([
                trim($_POST['crop_name']),
                (float)$_POST['price'],
                $_POST['trend'],
                $_POST['market_demand']
            ]);

            $guideSteps = array_filter(array_map('trim', explode("\n", $_POST['guide'] ?? '')));
            $guideStmt = $pdo->prepare('INSERT INTO crop_guides (crop_id, guide_text) VALUES (?, ?)');
            foreach ($guideSteps as $step) {
                if (!empty($step)) {
                    $guideStmt->execute([$cropId, $step]);
                }
            }

            $fertStmt = $pdo->prepare('INSERT INTO fertilizer_rules (crop_id, fertilizer_type, kg_per_acre, price_per_kg, schedule_text) VALUES (?, ?, ?, ?, ?)');
            foreach (['Urea', 'MOP', 'TSP', 'Compost'] as $ftype) {
                $kg = (float)($_POST["fertilizer_" . strtolower($ftype) . "_kg"] ?? 0);
                if ($kg > 0) {
                    $fertStmt->execute([
                        $cropId,
                        $ftype,
                        $kg,
                        (float)($_POST["fertilizer_" . strtolower($ftype) . "_price"] ?? 0),
                        trim($_POST["fertilizer_" . strtolower($ftype) . "_schedule"] ?? '')
                    ]);
                }
            }

            $pdo->commit();
            $message = "Crop added successfully.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Error adding crop: " . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// --- Data Loading ---
function csv_to_array($value) { return array_map('trim', explode(',', $value)); }

$cropRows = $pdo->query('SELECT * FROM crops ORDER BY id')->fetchAll();
$guideRows = $pdo->query('SELECT crop_id, guide_text FROM crop_guides ORDER BY id')->fetchAll();
$fertilizerRows = $pdo->query('SELECT c.crop_name, f.fertilizer_type, f.kg_per_acre, f.price_per_kg, f.schedule_text FROM fertilizer_rules f JOIN crops c ON c.id = f.crop_id ORDER BY c.id, f.id')->fetchAll();
$marketRows = $pdo->query('SELECT * FROM market_prices ORDER BY id')->fetchAll();

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

// --- Recommendation Logic ---
$recommendedCrops = [];
$showRecommendations = false;
$postDistrict = $_POST['district'] ?? 'Trincomalee';
$postSoil = $_POST['soil_type'] ?? '';
$postSeason = $_POST['season_type'] ?? '';
$postWater = $_POST['water_source'] ?? '';
$postBudget = $_POST['budget_level'] ?? '';
$postDemand = $_POST['market_demand'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'recommend') {
    $showRecommendations = true;

    foreach ($crops as $rule) {
        $score = 40;
        $reasons = [];
        
        if (in_array($postSoil, $rule['bestSoils'])) { $score += 15; $reasons[] = $rule['crop'] . " suits " . strtolower($postSoil) . " soil."; }
        if (in_array($postSeason, $rule['bestSeasons'])) { $score += 15; $reasons[] = $postSeason . " season is suitable."; }
        if (in_array($postWater, $rule['bestWater'])) { $score += 15; $reasons[] = strtolower($postWater) . " water source supports " . $rule['crop'] . "."; }
        if ($rule['budget'] === $postBudget) { $score += 8; $reasons[] = strtolower($postBudget) . " budget matches."; }
        if ($rule['demand'] === $postDemand) { $score += 7; $reasons[] = "Market demand is " . strtolower($postDemand) . "."; }
        
        if ($score > 100) $score = 100;
        
        $rec = $rule;
        $rec['score'] = $score;
        $rec['reasons'] = $reasons;
        $recommendedCrops[] = $rec;
    }
    
    usort($recommendedCrops, function($a, $b) { return $b['score'] - $a['score']; });
} else {
    // Default fallback
    foreach ($crops as $rule) {
        $rec = $rule;
        $rec['score'] = 92;
        $rec['reasons'] = ["Select your inputs and click generate to see custom reasons."];
        $recommendedCrops[] = $rec;
    }
}
$topCrop = $recommendedCrops[0] ?? null;

// --- Market Profit Ranking ---
$rankedMarkets = [];
foreach ($crops as $c) {
    $match = null;
    foreach ($marketRows as $m) {
        if (strpos($m['crop_name'], $c['crop']) !== false || strpos($c['crop'], $m['crop_name']) !== false) {
            $match = $m; break;
        }
    }
    $boost = ($match && $match['trend'] === 'up') ? 8 : 0;
    $c['finalScore'] = $c['profit'] + $boost;
    $rankedMarkets[] = $c;
}
usort($rankedMarkets, function($a, $b) { return $b['finalScore'] - $a['finalScore']; });
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Farmar's DSS</title>
    <link rel="icon" type="image/png" href="logo.png" />
    <link rel="apple-touch-icon" href="logo.png" />
    <link rel="stylesheet" href="styles.css" />
    <script>
        // Pass essential data to minimal JS
        var fertilizerRules = <?php echo json_encode($fertilizerRules); ?>;
        var cropGuides = <?php echo json_encode($crops); ?>;
        var START_PAGE = "<?php echo (isset($_POST['action']) && $_POST['action'] === 'recommend') ? 'crop-advisory' : ((isset($_POST['action']) && ($_POST['action'] === 'update_price' || $_POST['action'] === 'add_crop')) ? 'admin-panel' : 'dashboard'); ?>";
        var PHP_MESSAGE = "<?php echo htmlspecialchars($message); ?>";
    </script>
  </head>
  <body>
    <div class="app-shell">
      <header class="topbar">
        <a class="brand" href="#dashboard" aria-label="Farmar's DSS home" onclick="showPage('dashboard'); return false;">
          <img src="logo.png" alt="Farmar's DSS Logo" class="brand-mark brand-logo" />
          <span>
            <span class="brand-label">Farmar's DSS</span>
            <span class="brand-subtitle">Smart decisions for every harvest</span>
          </span>
        </a>

        <nav class="topnav" aria-label="Primary navigation">
          <a href="#dashboard" onclick="showPage('dashboard'); return false;" class="active">Dashboard</a>
          <a href="#crop-advisory" onclick="showPage('crop-advisory'); return false;">Crop Advisor</a>
          <a href="#calculator" onclick="showPage('calculator'); return false;">Calculator</a>
          <a href="#market-watch" onclick="showPage('market-watch'); return false;">Market</a>
          <?php if (isAdmin()): ?>
          <a href="#admin-panel" onclick="showPage('admin-panel'); return false;">Admin Panel</a>
          <?php endif; ?>
        </nav>

        <div class="topbar-actions">
          <button class="icon-btn" id="warning-button" aria-label="View alerts">
            !
            <span class="badge" id="alert-count">0</span>
          </button>
          <div class="profile" id="profile-section">
            <div class="avatar" id="profile-avatar"><?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?></div>
            <div class="profile-meta">
              <p id="profile-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
              <span id="profile-role"><?php echo htmlspecialchars($_SESSION['role']); ?></span>
            </div>
          </div>
          <a href="logout.php" class="outline-button" style="text-decoration:none;">Logout</a>
        </div>
      </header>

      <main class="pages">
        <section id="dashboard" class="page active">
          <div class="page-header">
            <div>
              <p class="eyebrow">Dashboard</p>
              <h1>Welcome back, <?php echo explode(' ', $_SESSION['full_name'])[0]; ?>!</h1>
              <p class="date-text" id="current-date"><?php echo date('F j, Y'); ?></p>
            </div>
            <div class="status-pill">
              <span class="status-dot"></span>
              <span id="status-pill-text">System Online</span>
            </div>
          </div>

          <div class="dashboard-grid">
            <article class="panel weather-card">
              <div class="weather-top">
                <div>
                  <p class="label">Live Weather (<span id="wd-district-label">Trincomalee</span>)</p>
                  <h2 id="temp-value">--&deg;C</h2>
                  <p class="weather-copy" id="weather-copy">Loading live weather data...</p>
                </div>
                <div class="weather-icon" aria-hidden="true">
                  <div class="sun"></div>
                  <div class="cloud"></div>
                </div>
              </div>
              <div class="advice-strip">
                <strong id="weather-risk">Loading...</strong>
                <span>Delay fertilizer application before heavy rain.</span>
              </div>
            </article>

            <article class="panel decision-card">
              <div class="card-header">
                <p class="label">Today's DSS Advice</p>
                <span>Auto summary</span>
              </div>
              <h2 id="daily-advice-title">
                <?php echo $topCrop ? htmlspecialchars($topCrop['crop']) : 'Select inputs'; ?> is currently the safest match
              </h2>
              <p id="daily-advice-text">
                <?php if($showRecommendations): ?>
                  Based on <?php echo htmlspecialchars($postSeason); ?> season, <?php echo htmlspecialchars(strtolower($postSoil)); ?> soil, <?php echo htmlspecialchars(strtolower($postWater)); ?> water, and <?php echo htmlspecialchars(strtolower($postDemand)); ?> market demand.
                <?php else: ?>
                  Go to the Crop Advisor tab to generate your custom recommendation based on your land and season.
                <?php endif; ?>
              </p>
              <div class="score-row">
                <span>Recommendation confidence</span>
                <strong id="daily-score"><?php echo $topCrop ? $topCrop['score'] : '--'; ?>%</strong>
              </div>
            </article>
          </div>

          <div class="quick-row">
            <article class="metric-card">
              <p class="label">Decision Alerts</p>
              <strong id="warning-alerts">0 Critical Alerts</strong>
              <span>Rain and pest monitoring</span>
            </article>
            <article class="metric-card">
              <p class="label">Field Summary</p>
              <strong id="field-summary-value">12 Active Fields</strong>
              <span>6.8 acres under planning</span>
            </article>
            <article class="metric-card">
              <p class="label">System Logs</p>
              <strong id="system-logs-value">7 New Entries</strong>
              <span>Updated market and weather data</span>
            </article>
          </div>

          <div class="section-title" style="margin-top: 2rem;">
            <div>
              <p class="eyebrow">Live Weather</p>
              <h2 style="margin:0; font-size:1.4rem; font-weight:700;">All Districts Overview</h2>
            </div>
            <span style="font-size:0.82rem; color:var(--muted);">Powered by Open-Meteo · Updates on load</span>
          </div>
          <div id="district-weather-grid" class="district-weather-grid">
            <div class="dwc-loading">Fetching live weather for all districts...</div>
          </div>
        </section>

        <!-- CROP ADVISOR -->
        <section id="crop-advisory" class="page">
          <div class="section-title">
            <p class="eyebrow">Advisory</p>
            <h1>Crop Advisory Engine</h1>
          </div>

          <div class="grid-two advisory-layout">
            <article class="panel advisory-inputs">
              <div class="card-header">
                <p class="label">Input Panel</p>
                <span>Change values and generate</span>
              </div>

              <form action="index.php" method="POST">
                <input type="hidden" name="action" value="recommend">
                <div class="form-grid">
                    <div class="form-group">
                    <label for="district">District</label>
                    <select id="district" name="district">
                        <option value="Trincomalee" <?php if($postDistrict=='Trincomalee') echo 'selected'; ?>>Trincomalee</option>
                        <option value="Anuradhapura" <?php if($postDistrict=='Anuradhapura') echo 'selected'; ?>>Anuradhapura</option>
                        <option value="Jaffna" <?php if($postDistrict=='Jaffna') echo 'selected'; ?>>Jaffna</option>
                        <option value="Kandy" <?php if($postDistrict=='Kandy') echo 'selected'; ?>>Kandy</option>
                    </select>
                    </div>
                    <div class="form-group">
                    <label for="soil-type">Soil Type</label>
                    <select id="soil-type" name="soil_type">
                        <option value="Alluvial" <?php if($postSoil=='Alluvial') echo 'selected'; ?>>Alluvial</option>
                        <option value="Laterite" <?php if($postSoil=='Laterite') echo 'selected'; ?>>Laterite</option>
                        <option value="Sandy" <?php if($postSoil=='Sandy') echo 'selected'; ?>>Sandy</option>
                        <option value="Clay" <?php if($postSoil=='Clay') echo 'selected'; ?>>Clay</option>
                    </select>
                    </div>
                    <div class="form-group">
                    <label for="season-type">Current Season</label>
                    <select id="season-type" name="season_type">
                        <option value="Yala" <?php if($postSeason=='Yala') echo 'selected'; ?>>Yala</option>
                        <option value="Maha" <?php if($postSeason=='Maha') echo 'selected'; ?>>Maha</option>
                        <option value="Dry" <?php if($postSeason=='Dry') echo 'selected'; ?>>Dry</option>
                    </select>
                    </div>
                    <div class="form-group">
                    <label for="water-source">Water Source</label>
                    <select id="water-source" name="water_source">
                        <option value="Irrigation" <?php if($postWater=='Irrigation') echo 'selected'; ?>>Irrigation</option>
                        <option value="Rainfed" <?php if($postWater=='Rainfed') echo 'selected'; ?>>Rainfed</option>
                        <option value="Groundwater" <?php if($postWater=='Groundwater') echo 'selected'; ?>>Groundwater</option>
                    </select>
                    </div>
                    <div class="form-group">
                    <label for="budget-level">Budget Level</label>
                    <select id="budget-level" name="budget_level">
                        <option value="Medium" <?php if($postBudget=='Medium') echo 'selected'; ?>>Medium</option>
                        <option value="Low" <?php if($postBudget=='Low') echo 'selected'; ?>>Low</option>
                        <option value="High" <?php if($postBudget=='High') echo 'selected'; ?>>High</option>
                    </select>
                    </div>
                    <div class="form-group">
                    <label for="market-demand">Market Demand</label>
                    <select id="market-demand" name="market_demand">
                        <option value="High" <?php if($postDemand=='High') echo 'selected'; ?>>High</option>
                        <option value="Medium" <?php if($postDemand=='Medium') echo 'selected'; ?>>Medium</option>
                        <option value="Low" <?php if($postDemand=='Low') echo 'selected'; ?>>Low</option>
                    </select>
                    </div>
                </div>

                <button class="primary-button" type="submit" id="generate-button">
                    Generate Recommendations
                </button>
              </form>
            </article>

            <article class="panel advisory-results">
              <div class="card-header">
                <p class="label">Best Match Crops</p>
                <span id="recommendation-summary"><?php echo $topCrop ? htmlspecialchars($topCrop['crop']) . " is the highest scoring option" : "Top choices for current inputs"; ?></span>
              </div>
              <div class="match-cards" id="crop-results">
                <?php $i = 0; foreach ($recommendedCrops as $item): if ($i++ >= 3) break; ?>
                    <article class="crop-card" tabindex="0" onclick="openModal('<?php echo htmlspecialchars($item['crop']); ?>')">
                    <div class="crop-card-top">
                        <h3><?php echo htmlspecialchars($item['crop']); ?></h3>
                        <span class="score-badge"><?php echo $item['score']; ?>%</span>
                    </div>
                    <p>Expected duration: <?php echo htmlspecialchars($item['duration']); ?>. Profit score: <?php echo $item['profit']; ?>/100.</p>
                    <div class="crop-tags">
                        <span><?php echo htmlspecialchars(implode(" / ", $item['bestSoils'])); ?></span>
                        <span><?php echo htmlspecialchars(implode(" / ", $item['bestSeasons'])); ?></span>
                        <span><?php echo htmlspecialchars(implode(" / ", $item['bestWater'])); ?></span>
                    </div>
                    <button class="outline-button" type="button">View Growth Guide</button>
                    </article>
                <?php endforeach; ?>
              </div>
            </article>
          </div>

          <article class="panel explanation-panel">
            <div class="card-header">
              <p class="label">Why This Recommendation?</p>
              <span>Explainable DSS output</span>
            </div>
            <ul class="reason-list" id="reason-list">
                <?php if ($showRecommendations && $topCrop): ?>
                    <li>District selected: <?php echo htmlspecialchars($postDistrict); ?>.</li>
                    <li>The system compared soil, season, water source, budget, and demand.</li>
                    <?php foreach ($topCrop['reasons'] as $r): ?>
                        <li><?php echo htmlspecialchars($r); ?></li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>Generate a recommendation to see detailed reasons here.</li>
                <?php endif; ?>
            </ul>
          </article>
        </section>

        <!-- CALCULATOR -->
        <section id="calculator" class="page">
          <div class="section-title">
            <p class="eyebrow">Calculator</p>
            <h1>Input & Fertilizer Calculator</h1>
          </div>

          <div class="calculator-top">
            <div class="panel input-field">
              <label for="target-crop">Target Crop</label>
              <select id="target-crop">
                <?php foreach ($crops as $c): ?>
                    <option value="<?php echo htmlspecialchars($c['crop']); ?>"><?php echo htmlspecialchars($c['crop']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="panel input-field">
              <label for="land-size">Land Size in Acres</label>
              <input id="land-size" type="number" min="0.25" step="0.25" value="2.5" />
            </div>
          </div>

          <article class="panel results-card">
            <div class="card-header">
              <p class="label">Required Fertilizer Dosage</p>
              <span id="calculator-note">Calculated for 2.5 acres</span>
            </div>
            <div class="results-grid">
              <div class="table-wrap">
                <table>
                  <thead>
                    <tr>
                      <th>Type</th>
                      <th>Kilograms</th>
                      <th>Application Schedule</th>
                    </tr>
                  </thead>
                  <tbody id="fertilizer-body">
                    <!-- Javascript populates this based on user typing without reloading -->
                  </tbody>
                </table>
              </div>
              <aside class="side-callout">
                <strong id="cost-estimate">Estimated cost: LKR 0</strong>
                <p id="fertilizer-tip">Apply during early morning for maximum absorption.</p>
              </aside>
            </div>
          </article>
        </section>

        <!-- MARKET -->
        <section id="market-watch" class="page">
          <div class="section-title">
            <p class="eyebrow">Market</p>
            <h1>Live Crop Market Watch</h1>
          </div>

          <div class="grid-two market-layout">
            <article class="panel pricing-panel">
              <div class="card-header">
                <p class="label">Local Market Prices</p>
                <span>Current LKR/kg and weekly trends</span>
              </div>
              <div class="table-wrap">
                <table class="price-table">
                  <thead>
                    <tr>
                      <th>Crop</th>
                      <th>Price</th>
                      <th>Trend</th>
                      <th>Demand</th>
                    </tr>
                  </thead>
                  <tbody id="market-body">
                    <?php foreach ($marketRows as $m): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($m['crop_name']); ?></td>
                            <td>LKR <?php echo htmlspecialchars((string)$m['price_per_kg']); ?></td>
                            <td><span class="trend <?php echo htmlspecialchars($m['trend']); ?>"><?php echo ucfirst($m['trend']); ?></span></td>
                            <td><?php echo htmlspecialchars($m['demand']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </article>

            <article class="panel trend-panel">
              <div class="card-header">
                <p class="label">Profit Opportunity</p>
                <span>Simple DSS ranking</span>
              </div>
              <div class="profit-list" id="profit-list">
                <?php $i = 0; foreach ($rankedMarkets as $rm): if ($i++ >= 3) break; ?>
                    <article class="profit-card">
                        <div class="profit-card-top">
                            <h3><?php echo $i; ?>. <?php echo htmlspecialchars($rm['crop']); ?></h3>
                            <span class="score-badge"><?php echo $rm['finalScore']; ?>/100</span>
                        </div>
                        <p>Good selling potential based on crop profit score and current price trend.</p>
                    </article>
                <?php endforeach; ?>
              </div>
            </article>
          </div>
        </section>

        <!-- ADMIN PANEL -->
        <?php if (isAdmin()): ?>
        <section id="admin-panel" class="page">
          <div class="section-title">
            <p class="eyebrow">Admin</p>
            <h1>Manage Market Prices</h1>
          </div>

          <article class="panel">
            <div class="card-header">
              <p class="label">Live Market Prices</p>
              <span>Update prices, demand, and trends</span>
            </div>
            <div class="table-wrap">
              <table class="price-table">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Crop</th>
                    <th>Price per kg (LKR)</th>
                    <th>Trend</th>
                    <th>Demand</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody id="admin-market-body">
                    <?php foreach ($marketRows as $m): ?>
                    <tr>
                        <form method="POST" action="index.php">
                            <input type="hidden" name="action" value="update_price">
                            <input type="hidden" name="id" value="<?php echo $m['id']; ?>">
                            <td><?php echo $m['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($m['crop_name']); ?></strong></td>
                            <td><input type="number" name="price" value="<?php echo htmlspecialchars((string)$m['price_per_kg']); ?>" class="admin-input-small" required /></td>
                            <td>
                                <select name="trend" class="admin-input-small">
                                    <option value="up" <?php if($m['trend']=='up') echo 'selected'; ?>>Up</option>
                                    <option value="down" <?php if($m['trend']=='down') echo 'selected'; ?>>Down</option>
                                    <option value="stable" <?php if($m['trend']=='stable') echo 'selected'; ?>>Stable</option>
                                </select>
                            </td>
                            <td>
                                <select name="demand" class="admin-input-small">
                                    <option value="High" <?php if($m['demand']=='High') echo 'selected'; ?>>High</option>
                                    <option value="Medium" <?php if($m['demand']=='Medium') echo 'selected'; ?>>Medium</option>
                                    <option value="Low" <?php if($m['demand']=='Low') echo 'selected'; ?>>Low</option>
                                </select>
                            </td>
                            <td><button type="submit" class="outline-button">Save</button></td>
                        </form>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </article>

          <article class="panel" style="margin-top: 2rem;">
            <div class="card-header">
              <p class="label">Add New Crop Details</p>
              <span>Add a new crop with recommendations, guide, fertilizer, and market details</span>
            </div>
            
            <form action="index.php" method="POST" class="admin-add-form">
              <input type="hidden" name="action" value="add_crop">
              <div class="form-grid">
                <div class="form-group">
                  <label for="add-crop-name">Crop Name</label>
                  <input type="text" id="add-crop-name" name="crop_name" placeholder="e.g. Carrot" required />
                </div>
                <div class="form-group">
                  <label for="add-crop-duration">Expected Duration</label>
                  <input type="text" id="add-crop-duration" name="duration" placeholder="e.g. 80 - 100 days" required />
                </div>
                <div class="form-group">
                  <label for="add-crop-profit">Profit Score (0-100)</label>
                  <input type="number" id="add-crop-profit" name="profit_score" min="0" max="100" placeholder="e.g. 85" required />
                </div>
                <div class="form-group">
                  <label for="add-crop-price">Initial Market Price per kg (LKR)</label>
                  <input type="number" id="add-crop-price" name="price" min="1" placeholder="e.g. 350" required />
                </div>
                <div class="form-group">
                  <label for="add-crop-trend">Market Price Trend</label>
                  <select id="add-crop-trend" name="trend">
                    <option value="stable">Stable</option>
                    <option value="up">Up</option>
                    <option value="down">Down</option>
                  </select>
                </div>
                <div class="form-group">
                  <label for="add-crop-budget">Budget Level</label>
                  <select id="add-crop-budget" name="budget_level">
                    <option value="Low">Low</option>
                    <option value="Medium" selected>Medium</option>
                    <option value="High">High</option>
                  </select>
                </div>
                <div class="form-group">
                  <label for="add-crop-demand">Market Demand</label>
                  <select id="add-crop-demand" name="market_demand">
                    <option value="High" selected>High</option>
                    <option value="Medium">Medium</option>
                    <option value="Low">Low</option>
                  </select>
                </div>
              </div>

              <div class="form-section-title" style="margin-top: 1.5rem; font-weight: 700; color: var(--accent); border-bottom: 1px solid var(--border); padding-bottom: 5px;">Suitability Options</div>
              <div class="checkbox-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-top: 1rem;">
                <div class="checkbox-group">
                  <span class="checkbox-group-label" style="display: block; font-weight: 600; margin-bottom: 8px;">Best Soil Types</span>
                  <div class="checkbox-options" style="display: flex; flex-direction: column; gap: 8px;">
                    <label class="checkbox-label" style="display: flex; align-items: center; gap: 8px; font-weight: normal;"><input type="checkbox" name="best_soils[]" value="Alluvial" style="width: auto; min-height: auto;" /> Alluvial</label>
                    <label class="checkbox-label" style="display: flex; align-items: center; gap: 8px; font-weight: normal;"><input type="checkbox" name="best_soils[]" value="Laterite" style="width: auto; min-height: auto;" /> Laterite</label>
                    <label class="checkbox-label" style="display: flex; align-items: center; gap: 8px; font-weight: normal;"><input type="checkbox" name="best_soils[]" value="Sandy" style="width: auto; min-height: auto;" /> Sandy</label>
                    <label class="checkbox-label" style="display: flex; align-items: center; gap: 8px; font-weight: normal;"><input type="checkbox" name="best_soils[]" value="Clay" style="width: auto; min-height: auto;" /> Clay</label>
                  </div>
                </div>
                
                <div class="checkbox-group">
                  <span class="checkbox-group-label" style="display: block; font-weight: 600; margin-bottom: 8px;">Best Seasons</span>
                  <div class="checkbox-options" style="display: flex; flex-direction: column; gap: 8px;">
                    <label class="checkbox-label" style="display: flex; align-items: center; gap: 8px; font-weight: normal;"><input type="checkbox" name="best_seasons[]" value="Yala" style="width: auto; min-height: auto;" /> Yala</label>
                    <label class="checkbox-label" style="display: flex; align-items: center; gap: 8px; font-weight: normal;"><input type="checkbox" name="best_seasons[]" value="Maha" style="width: auto; min-height: auto;" /> Maha</label>
                    <label class="checkbox-label" style="display: flex; align-items: center; gap: 8px; font-weight: normal;"><input type="checkbox" name="best_seasons[]" value="Dry" style="width: auto; min-height: auto;" /> Dry</label>
                  </div>
                </div>
                
                <div class="checkbox-group">
                  <span class="checkbox-group-label" style="display: block; font-weight: 600; margin-bottom: 8px;">Best Water Sources</span>
                  <div class="checkbox-options" style="display: flex; flex-direction: column; gap: 8px;">
                    <label class="checkbox-label" style="display: flex; align-items: center; gap: 8px; font-weight: normal;"><input type="checkbox" name="best_water[]" value="Irrigation" style="width: auto; min-height: auto;" /> Irrigation</label>
                    <label class="checkbox-label" style="display: flex; align-items: center; gap: 8px; font-weight: normal;"><input type="checkbox" name="best_water[]" value="Rainfed" style="width: auto; min-height: auto;" /> Rainfed</label>
                    <label class="checkbox-label" style="display: flex; align-items: center; gap: 8px; font-weight: normal;"><input type="checkbox" name="best_water[]" value="Groundwater" style="width: auto; min-height: auto;" /> Groundwater</label>
                  </div>
                </div>
              </div>

              <div class="form-section-title" style="margin-top: 1.5rem; font-weight: 700; color: var(--accent); border-bottom: 1px solid var(--border); padding-bottom: 5px;">Growth Guide Details</div>
              <div class="form-group full-width" style="margin-top: 1rem;">
                <label for="add-crop-guide">Growth Guide Steps (one step per line)</label>
                <textarea id="add-crop-guide" name="guide" placeholder="Step 1: Keep soil moist during germination.&#10;Step 2: Apply nitrogen split doses.&#10;Step 3: Harvest when bulbs mature." rows="4" style="width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 5px; outline: none; font-family: inherit;" required></textarea>
              </div>

              <div class="form-section-title" style="margin-top: 1.5rem; font-weight: 700; color: var(--accent); border-bottom: 1px solid var(--border); padding-bottom: 5px;">Fertilizer Dosage & Costs</div>
              <div class="fertilizer-rules-inputs" style="display: grid; gap: 12px; margin-top: 1rem;">
                <div class="fertilizer-row-input" style="display: grid; grid-template-columns: 100px 1fr 1fr 2fr; gap: 10px; align-items: center;">
                  <span class="fertilizer-name-badge" style="font-weight: bold; background: var(--surface-soft); padding: 8px; border-radius: 4px; border: 1px solid var(--border); text-align: center;">Urea</span>
                  <input type="number" name="fertilizer_urea_kg" placeholder="Kg per acre" min="0" step="0.1" />
                  <input type="number" name="fertilizer_urea_price" placeholder="Price per kg (LKR)" min="0" step="1" value="320" />
                  <input type="text" name="fertilizer_urea_schedule" placeholder="Application schedule details" />
                </div>
                <div class="fertilizer-row-input" style="display: grid; grid-template-columns: 100px 1fr 1fr 2fr; gap: 10px; align-items: center;">
                  <span class="fertilizer-name-badge" style="font-weight: bold; background: var(--surface-soft); padding: 8px; border-radius: 4px; border: 1px solid var(--border); text-align: center;">MOP</span>
                  <input type="number" name="fertilizer_mop_kg" placeholder="Kg per acre" min="0" step="0.1" />
                  <input type="number" name="fertilizer_mop_price" placeholder="Price per kg (LKR)" min="0" step="1" value="360" />
                  <input type="text" name="fertilizer_mop_schedule" placeholder="Application schedule details" />
                </div>
                <div class="fertilizer-row-input" style="display: grid; grid-template-columns: 100px 1fr 1fr 2fr; gap: 10px; align-items: center;">
                  <span class="fertilizer-name-badge" style="font-weight: bold; background: var(--surface-soft); padding: 8px; border-radius: 4px; border: 1px solid var(--border); text-align: center;">TSP</span>
                  <input type="number" name="fertilizer_tsp_kg" placeholder="Kg per acre" min="0" step="0.1" />
                  <input type="number" name="fertilizer_tsp_price" placeholder="Price per kg (LKR)" min="0" step="1" value="420" />
                  <input type="text" name="fertilizer_tsp_schedule" placeholder="Application schedule details" />
                </div>
                <div class="fertilizer-row-input" style="display: grid; grid-template-columns: 100px 1fr 1fr 2fr; gap: 10px; align-items: center;">
                  <span class="fertilizer-name-badge" style="font-weight: bold; background: var(--surface-soft); padding: 8px; border-radius: 4px; border: 1px solid var(--border); text-align: center;">Compost</span>
                  <input type="number" name="fertilizer_compost_kg" placeholder="Kg per acre" min="0" step="0.1" />
                  <input type="number" name="fertilizer_compost_price" placeholder="Price per kg (LKR)" min="0" step="1" value="35" />
                  <input type="text" name="fertilizer_compost_schedule" placeholder="Application schedule details" />
                </div>
              </div>

              <button type="submit" class="primary-button" style="margin-top: 1.5rem;">Add Crop & Details</button>
            </form>
          </article>
        </section>
        <?php endif; ?>
      </main>

      <div class="toast" id="toast" aria-live="polite"></div>

      <!-- Alert Popup Modal -->
      <div class="modal-overlay" id="alert-modal-overlay" aria-hidden="true">
        <div class="modal-card alert-modal-card" role="dialog" aria-modal="true" aria-labelledby="alert-modal-title">
          <button class="modal-close" id="alert-modal-close" aria-label="Close alerts">&#x2715;</button>
          <div class="modal-header">
            <div class="modal-crop-icon alert-icon">&#x26A0;</div>
            <div>
              <p class="eyebrow">Active Alerts</p>
              <h2 id="alert-modal-title">Decision Alerts</h2>
            </div>
          </div>
          <div class="modal-body" id="alert-modal-body"></div>
          <div class="modal-footer">
            <button class="outline-button" id="alert-modal-close-btn">Dismiss</button>
          </div>
        </div>
      </div>

      <div class="modal-overlay" id="modal-overlay" aria-hidden="true">
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="modal-title">
          <button class="modal-close" id="modal-close" aria-label="Close modal">x</button>
          <div class="modal-header">
            <div class="modal-crop-icon" id="modal-icon"></div>
            <div>
              <p class="eyebrow">Growth Guide</p>
              <h2 id="modal-title">Crop Growth Guide</h2>
            </div>
          </div>
          <div class="modal-body">
            <p id="modal-description"></p>
            <ul id="modal-list"></ul>
          </div>
          <div class="modal-footer">
            <button class="outline-button" id="modal-close-btn">Close</button>
          </div>
        </div>
      </div>
    </div>

    <script src="script.js"></script>
  </body>
</html>
