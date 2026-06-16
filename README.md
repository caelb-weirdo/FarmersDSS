# Farmer's DSS

Farmer's DSS is a simple decision support system for crop planning. It uses
HTML, CSS, vanilla JavaScript, PHP, and MySQL through XAMPP.

## Main Features

- **User Authentication**: Login and registration system with email/password authentication.
- **Role-Based Access**: Support for Farmer and Admin user roles.
- **Responsive dashboard** with weather, alerts, field summary, and DSS advice.
- **Crop recommendation engine** using soil, season, water source, budget, and market demand.
- **Clear explanation** for each recommendation.
- **Fertilizer calculator** based on selected crop and land size.
- **Market price section** with demand and trend data.
- **MySQL database support** using PHP API files.
- **Fallback demo data** in JavaScript, so the interface still works without MySQL.

## Technology Used

- HTML5
- CSS3
- Vanilla JavaScript
- PHP
- MySQL
- XAMPP

## Folder Structure

```text
FarmerDSS/
  api/
    auth.php
    data.php
    db.php
    login.php
    logout.php
    recommend.php
    register.php
  database.sql
  auth.js
  index.html
  login.html
  register.html
  script.js
  styles.css
  README.md
```

## XAMPP Setup

1. Copy the `FarmerDSS` folder into your XAMPP `htdocs` folder.

Example:

```text
C:\xampp\htdocs\FarmerDSS
```

2. Start XAMPP Control Panel.

3. Start:

- Apache
- MySQL

4. Open phpMyAdmin:

```text
http://localhost/phpmyadmin
```

5. Import the database:

- Click **Import**
- Choose `database.sql`
- Click **Go**

This creates the `farmer_dss` database and sample tables/data.

6. Open the project in your browser:

```text
http://localhost/FarmerDSS/index.html
```

You will be redirected to the login page. Use the demo credentials below to log in.

## Authentication & Demo Credentials

The system uses email/password authentication with session-based login.

**Demo Users:**

- **Admin Account**: admin@farmer-dss.com | Password: password123
- **Farmer Account**: farmer@farmer-dss.com | Password: password123

**Create a New Account:**

Visit the registration page to create a new account. All new registrations default to the "Farmer" role.

## Database Tables

### users

Stores user account information.

Fields:

- `email` - Unique email address (login credential)
- `password_hash` - Bcrypt-hashed password
- `full_name` - User's full name
- `role` - Enum: 'Farmer' or 'Admin'
- `created_at` - Account creation timestamp

### crops

Stores crop rules used by the recommendation engine.

Important fields:

- `crop_name`
- `best_soils`
- `best_seasons`
- `best_water`
- `budget_level`
- `market_demand`
- `duration`
- `profit_score`

### crop_guides

Stores growth guide points for each crop.

### fertilizer_rules

Stores fertilizer type, kg per acre, price per kg, and application schedule.

### market_prices

Stores current crop prices, demand level, and price trend.

### weather_alerts

Stores district-based weather information and alert count.

### recommendation_history

Stores generated recommendations when the user clicks **Generate Recommendations**.

## How the Recommendation Logic Works

The system starts each crop with a base score of `40`.

Then it adds points:

- Soil type match: `+15`
- Season match: `+15`
- Water source match: `+15`
- Budget level match: `+8`
- Market demand match: `+7`

The crop with the highest score becomes the best recommendation.

Example:

```text
Input:
Soil: Alluvial
Season: Yala
Water: Irrigation
Budget: Medium
Demand: High

Output:
Paddy may score highest because it matches soil, season, water, budget, and demand.
```

## PHP API Files

### api/db.php

Connects PHP to MySQL.

Default XAMPP settings:

```php
$host = 'localhost';
$database = 'farmer_dss';
$username = 'root';
$password = '';
```

If your MySQL password is different, update this file.

### api/auth.php

Session management and authentication utilities. Provides:

- `get_current_user()` - Returns logged-in user or null
- `is_authenticated()` - Boolean check for active session
- `require_auth()` - Exit with 401 if not authenticated
- `require_admin()` - Exit with 403 if not an admin

### api/login.php

Handles user login and session initialization.

**POST Request:**

```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**GET Request:** Returns current user information if authenticated.

### api/register.php

Handles user registration with email validation and password hashing.

**POST Request:**

```json
{
  "email": "user@example.com",
  "password": "password123",
  "fullName": "John Doe"
}
```

Validates:

- Email format
- Password minimum 6 characters
- Full name minimum 2 characters
- No duplicate email registration

### api/logout.php

Destroys the session and logs out the user.

### api/data.php

Returns crops, fertilizer rules, market data, and weather data as JSON.

### api/recommend.php

Receives user inputs from JavaScript, calculates crop scores in PHP, saves the
best recommendation to `recommendation_history`, and returns the results as JSON.

## JavaScript Fetch Connection

The frontend uses `fetch()` to load data:

```js
fetch("api/data.php");
```

When the user clicks **Generate Recommendations**, it sends inputs to:

```js
fetch("api/recommend.php");
```

If XAMPP or MySQL is not running, the system uses the local demo data in
`script.js`.

## Future Improvements

- Add email verification for new registrations.
- Add "Remember Me" login option with persistent tokens.
- Add field profile management for users.
- Add admin panel to manage crops, fertilizer rules, market prices, and weather data.
- Add role-based dashboards for Admin users.
- Add printable recommendation report.
- Add real weather API integration.
- Add charts for market price history.
- Add password reset functionality.

## Project Notes

This project is intentionally built with simple code so it is easy to understand
and explain. The logic is not hidden inside a framework. HTML controls the
structure, CSS controls the design, JavaScript controls the interface behavior,
and PHP/MySQL stores and processes DSS data.
