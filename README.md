# Farmers DSS

Farmers DSS is a simple Decision Support System for crop planning, built with
plain PHP, HTML, CSS, JavaScript, and MySQL — running on XAMPP. It uses a
traditional server-side PHP architecture so the code is easy for beginners to
read and understand.

## Main Features

- **Secure Login & Registration** — Email and password authentication with bcrypt hashing. Sessions are managed entirely by PHP.
- **Role-Based Access** — Two roles: `Farmer` and `Admin`. Admin users see an extra Admin Panel tab.
- **Dashboard** — Personalized welcome, live weather card, daily DSS advice, and metric summary cards.
- **Live Weather (All Districts)** — Fetches real-time weather for Trincomalee, Anuradhapura, Jaffna, and Kandy from the Open-Meteo API.
- **Crop Advisory Engine** — Select your district, soil type, season, water source, budget, and market demand. The PHP backend scores all crops and shows the top 3 matches with an explanation.
- **Fertilizer Calculator** — Select a crop and land size. JavaScript instantly shows required fertilizer kg and estimated total cost in LKR.
- **Market Watch** — Live table of crop prices, demand levels, and price trends loaded directly from MySQL.
- **Admin Panel** — Admins can update market prices and add entirely new crops (with suitability rules, growth guides, and fertilizer dosages) without touching the database directly.

## Technology Used

- PHP 7+
- MySQL
- HTML5
- CSS3
- Vanilla JavaScript (minimal, ~180 lines)
- XAMPP

## Folder Structure

```text
Farmers DSS/
  db.php           — Database connection and auth helper functions
  index.php        — Main application (all pages in one file)
  login.php        — Login form and POST handler
  register.php     — Registration form and POST handler
  logout.php       — Destroys session and redirects to login
  script.js        — Minimal JS: tab navigation, weather API, calculator
  styles.css       — Responsive visual design
  database.sql     — MySQL schema and sample data
  README.md
  PROJECT_REPORT.md
```

## XAMPP Setup

1. Copy the `Farmers DSS` folder into your XAMPP `htdocs` folder.

```text
C:\xampp\htdocs\Farmers DSS
```

2. Open the XAMPP Control Panel.

3. Start **Apache** and **MySQL**.

4. Open phpMyAdmin:

```text
http://localhost/phpmyadmin
```

5. Import the database:
   - Click **New** on the left sidebar, name it `farmer_dss`, then click **Create**.
   - Select the new `farmer_dss` database.
   - Click the **Import** tab at the top.
   - Choose the `database.sql` file from the project folder.
   - Click **Go**.

6. Open the project:

```text
http://localhost/Farmers DSS/login.php
```

## Demo Credentials

| Role   | Email                     | Password    |
| ------ | ------------------------- | ----------- |
| Admin  | admin@gmail.com           | admin@123   |
| Farmer | farmer@farmer-dss.com     | password123 |

All new registrations through the Register page default to the **Farmer** role.

## How the Architecture Works

This project uses **Traditional Server-Side PHP Rendering**. There is no separate
`api/` folder and no complex JavaScript data loading.

**Flow:**

1. User opens `login.php`. PHP processes the form submission and creates a session.
2. PHP redirects to `index.php`.
3. At the top of `index.php`, PHP connects to MySQL, runs all queries, and stores results in PHP arrays.
4. The HTML below uses simple `<?php foreach() ?>` loops to build the tables and cards directly.
5. When the user clicks **Generate Recommendations**, the form submits via `POST` back to `index.php`. PHP scores the crops server-side and re-renders the page with results.
6. JavaScript only handles: live tab switching, calling the Open-Meteo weather API, and performing fertilizer calculation math in real-time (without a page reload).

## Database Tables

| Table                  | Purpose                                         |
| ---------------------- | ----------------------------------------------- |
| users                  | User accounts, hashed passwords, roles          |
| crops                  | Crop suitability rules for the advisory engine  |
| crop_guides            | Step-by-step growth guide points per crop       |
| fertilizer_rules       | Fertilizer type, kg/acre, price/kg, schedule    |
| market_prices          | Current prices, demand, and trend per crop      |
| weather_alerts         | District weather data (legacy/backup)           |
| recommendation_history | Log of generated recommendation records         |

## How the Recommendation Logic Works

Each crop starts with a base score of `40`. Points are added based on how well
the user's inputs match each crop's stored suitability rules.

| Condition           | Points |
| ------------------- | -----: |
| Soil type match     |    +15 |
| Season match        |    +15 |
| Water source match  |    +15 |
| Budget match        |     +8 |
| Market demand match |     +7 |

Maximum score is capped at 100. The top 3 crops are displayed.

**Example:**

```
Input: Alluvial soil, Yala season, Irrigation water, Medium budget, High demand

Result: Paddy scores 95 because it matches all five conditions.
```

## Database Connection Settings

The database connection is in `db.php`:

```php
$host = 'localhost';
$db   = 'farmer_dss';
$user = 'root';
$pass = '';
```

If your MySQL password is different, update `db.php`.

## Future Improvements

- Add email verification for new registrations.
- Add password reset by email.
- Add a "Remember Me" persistent login option.
- Add printable or downloadable recommendation reports.
- Add charts for market price history.
- Add more districts and crop varieties.


## Team 4

- N. Arsh – TRI/IT/2025/F/06 
- M.A.M. Atheef – TRI/IT/2025/F/29
- E. Caleb – TRI/IT/2025/F/39
- A.N.R. Ayyas – TRI/IT/2025/F/54
- M.S.S. Simar – TRI/IT/2025/F/82

- S.Sanusiyajini – TRI/IT/2025/F/16 ( C )
- N. Sajera – TRI/IT/2025/F/65 ( V.C )
- M.N.F. Nafla – TRI/IT/2025/F/77 
- A.F. Farhana – TRI/IT/2025/F/94
- A.F. Sujitha – TRI/IT/2025/F/97
- S. Sivaranjini – TRI/IT/2025/F/106 

- Project Supervisor: Don Bosco Prashanth
