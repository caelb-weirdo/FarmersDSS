CREATE DATABASE IF NOT EXISTS farmer_dss;
USE farmer_dss;

DROP TABLE IF EXISTS recommendation_history;
DROP TABLE IF EXISTS fertilizer_rules;
DROP TABLE IF EXISTS crop_guides;
DROP TABLE IF EXISTS market_prices;
DROP TABLE IF EXISTS weather_alerts;
DROP TABLE IF EXISTS crops;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(120) NOT NULL,
  role ENUM('Farmer', 'Admin') DEFAULT 'Farmer',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE crops (
  id INT AUTO_INCREMENT PRIMARY KEY,
  crop_name VARCHAR(80) NOT NULL,
  best_soils VARCHAR(160) NOT NULL,
  best_seasons VARCHAR(120) NOT NULL,
  best_water VARCHAR(160) NOT NULL,
  budget_level VARCHAR(40) NOT NULL,
  market_demand VARCHAR(40) NOT NULL,
  duration VARCHAR(80) NOT NULL,
  profit_score INT NOT NULL
);

CREATE TABLE crop_guides (
  id INT AUTO_INCREMENT PRIMARY KEY,
  crop_id INT NOT NULL,
  guide_text VARCHAR(255) NOT NULL,
  FOREIGN KEY (crop_id) REFERENCES crops(id)
);

CREATE TABLE fertilizer_rules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  crop_id INT NOT NULL,
  fertilizer_type VARCHAR(80) NOT NULL,
  kg_per_acre DECIMAL(8,2) NOT NULL,
  price_per_kg DECIMAL(8,2) NOT NULL,
  schedule_text VARCHAR(255) NOT NULL,
  FOREIGN KEY (crop_id) REFERENCES crops(id)
);

CREATE TABLE market_prices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  crop_name VARCHAR(80) NOT NULL,
  price_per_kg DECIMAL(8,2) NOT NULL,
  trend VARCHAR(20) NOT NULL,
  demand VARCHAR(40) NOT NULL,
  updated_at DATE NOT NULL
);

CREATE TABLE weather_alerts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  district VARCHAR(80) NOT NULL,
  temperature INT NOT NULL,
  weather_text VARCHAR(255) NOT NULL,
  risk_text VARCHAR(120) NOT NULL,
  alert_count INT NOT NULL,
  updated_at DATE NOT NULL
);

CREATE TABLE recommendation_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  district VARCHAR(80) NOT NULL,
  soil_type VARCHAR(80) NOT NULL,
  season_type VARCHAR(80) NOT NULL,
  water_source VARCHAR(80) NOT NULL,
  budget_level VARCHAR(40) NOT NULL,
  market_demand VARCHAR(40) NOT NULL,
  recommended_crop VARCHAR(80) NOT NULL,
  score INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO crops
  (crop_name, best_soils, best_seasons, best_water, budget_level, market_demand, duration, profit_score)
VALUES
  ('Paddy', 'Alluvial,Clay', 'Yala,Maha', 'Irrigation,Rainfed', 'Medium', 'High', '95 - 115 days', 82),
  ('Maize', 'Alluvial,Laterite', 'Yala,Dry', 'Irrigation,Groundwater', 'Medium', 'Medium', '90 - 110 days', 76),
  ('Chili', 'Sandy,Laterite', 'Dry,Yala', 'Groundwater,Irrigation', 'High', 'High', '120 - 150 days', 90),
  ('Red Onion', 'Sandy,Alluvial', 'Dry,Yala', 'Groundwater,Irrigation', 'Low', 'High', '70 - 90 days', 86);

INSERT INTO crop_guides (crop_id, guide_text)
VALUES
  (1, 'Maintain a 3 cm to 5 cm water level during early growth.'),
  (1, 'Apply nitrogen in split doses to reduce nutrient loss.'),
  (1, 'Watch for brown planthopper after rainy days.'),
  (2, 'Use row spacing around 75 cm for better sunlight.'),
  (2, 'Apply nitrogen at planting and again during rapid growth.'),
  (2, 'Avoid waterlogging because maize roots need aeration.'),
  (3, 'Use raised beds and drip irrigation where possible.'),
  (3, 'Apply phosphorus before flowering to support pod formation.'),
  (3, 'Protect plants from fungal disease during wet weather.'),
  (4, 'Prepare loose soil so bulbs can expand properly.'),
  (4, 'Avoid excess water near harvest to reduce bulb rot.'),
  (4, 'Harvest when leaves bend and begin to dry.');

INSERT INTO fertilizer_rules
  (crop_id, fertilizer_type, kg_per_acre, price_per_kg, schedule_text)
VALUES
  (1, 'Urea', 48, 320, 'Day 14 and Day 35 split application'),
  (1, 'MOP', 24, 360, 'Apply at Day 21'),
  (1, 'TSP', 12, 420, 'Base dressing before sowing'),
  (2, 'Urea', 38, 320, 'At planting and 30 days after'),
  (2, 'MOP', 18, 360, 'Apply at Day 20'),
  (2, 'Compost', 220, 35, 'Mix before planting'),
  (3, 'Urea', 30, 320, 'Small split doses every 3 weeks'),
  (3, 'TSP', 22, 420, 'Before flowering'),
  (3, 'Compost', 260, 35, 'Mix into raised beds'),
  (4, 'Urea', 24, 320, 'Day 15 and Day 30 split application'),
  (4, 'MOP', 20, 360, 'Apply before bulb formation'),
  (4, 'Compost', 180, 35, 'Mix before planting');

INSERT INTO market_prices (crop_name, price_per_kg, trend, demand, updated_at)
VALUES
  ('Paddy', 150, 'up', 'High', CURDATE()),
  ('Green Chili', 450, 'up', 'High', CURDATE()),
  ('Maize', 180, 'down', 'Medium', CURDATE()),
  ('Red Onion', 320, 'up', 'High', CURDATE());

INSERT INTO weather_alerts
  (district, temperature, weather_text, risk_text, alert_count, updated_at)
VALUES
  ('Trincomalee', 29, 'Partly cloudy. Heavy rain expected tomorrow in Trincomalee.', 'Rain Risk: High', 2, CURDATE()),
  ('Anuradhapura', 31, 'Hot afternoon with possible evening showers.', 'Heat Risk: Medium', 1, CURDATE()),
  ('Jaffna', 30, 'Dry and windy conditions expected.', 'Water Risk: Medium', 1, CURDATE()),
  ('Kandy', 25, 'Cool weather with scattered rainfall.', 'Rain Risk: Medium', 1, CURDATE());

-- Sample users (password: password123)
INSERT INTO users (email, password_hash, full_name, role)
VALUES
  ('admin@farmer-dss.com', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86E36ZxQrCa', 'Admin User', 'Admin'),
  ('farmer@farmer-dss.com', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86E36ZxQrCa', 'Sample Farmer', 'Farmer');
