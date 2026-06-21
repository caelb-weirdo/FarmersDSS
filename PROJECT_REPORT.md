# Farmar's DSS Project Report

## Project Title

Farmar's Decision Support System (Farmar's DSS)

## Problem Statement

Farmers need simple support when choosing suitable crops, estimating fertilizer
requirements, and checking market/weather conditions. Many small farmers make
decisions using experience only, without comparing soil, season, water source,
budget, and market demand together.

## Project Objective

The objective of this project is to create a simple web-based DSS that helps a
farmer or agriculture advisor:

- choose suitable crops,
- understand why a crop is recommended,
- calculate fertilizer dosage,
- view live market prices and trends,
- view live weather alerts for multiple districts,
- manage crop and market data through an Admin Panel.

## Scope

This project covers:

- secure user authentication (login, register, logout),
- crop advisory with an explainable scoring engine,
- fertilizer calculation with cost estimates,
- live market price information,
- live weather alerts from the Open-Meteo API for four districts,
- role-based admin panel to add crops and update market prices.

This project does not yet include:

- email verification for new registrations,
- real-time pest or disease alerts,
- full farm financial planning modules.

## System Users

- Farmer — can view the dashboard, use the Crop Advisor, Calculator, and Market Watch.
- Admin — has all Farmer access plus the Admin Panel to add crops, update fertilizer rules, and update market prices.

## Functional Requirements

1. The system shall redirect unauthenticated users to the login page.
2. The system shall allow users to register new accounts with a default Farmer role.
3. The system shall display a responsive dashboard with live weather and DSS advice.
4. The system shall allow users to select district, soil type, season, water source, budget, and market demand to generate crop recommendations.
5. The system shall score crops using a rule-based algorithm and display the top 3 results.
6. The system shall explain why the top crop was recommended.
7. The system shall calculate fertilizer dosage and total cost based on crop and land size.
8. The system shall display market price and demand information for all crops.
9. The system shall display live weather for all four monitored districts using the Open-Meteo API.
10. The system shall allow Admins to update market prices, trends, and demand from a browser form.
11. The system shall allow Admins to add new crops with full suitability, guide, and fertilizer details.

## Non-Functional Requirements

- The interface must be clean, responsive, and mobile-friendly.
- The code must use plain PHP, HTML, CSS, and JavaScript — no frameworks.
- The system must run locally on XAMPP with Apache and MySQL.
- The code must be readable and understandable for HNDIT-level students.

## DSS Logic

Each crop receives a score using simple rule-based logic. All scoring happens
server-side in `index.php` at the time of the form submission.

| Condition          | Points |
| ------------------ | -----: |
| Base score         |     40 |
| Soil type match    |     15 |
| Season match       |     15 |
| Water source match |     15 |
| Budget match       |      8 |
| Market demand match|      7 |

Maximum score is capped at 100.

## Database Design Summary

| Table                  | Purpose                                              |
| ---------------------- | ---------------------------------------------------- |
| users                  | Stores user accounts, hashed passwords, and roles    |
| crops                  | Stores crop suitability rules for the advisor engine |
| crop_guides            | Stores step-by-step crop growth guide points         |
| fertilizer_rules       | Stores fertilizer dosage and cost data per crop      |
| market_prices          | Stores current crop market prices and trends         |
| weather_alerts         | Stores district weather information (legacy/backup)  |
| recommendation_history | Stores a log of generated recommendation records     |

## Main Files

| File          | Purpose                                                        |
| ------------- | -------------------------------------------------------------- |
| index.php     | Main application — dashboard, advisor, calculator, market, admin panel |
| login.php     | Login form and login POST handler                              |
| register.php  | Registration form and registration POST handler                |
| logout.php    | Destroys session and redirects to login                        |
| db.php        | Database connection, `isLoggedIn()`, `requireLogin()`, `isAdmin()`, `requireAdmin()` |
| script.js     | Minimal JavaScript — page navigation, weather API, calculator  |
| styles.css    | Responsive visual design                                       |
| database.sql  | MySQL database setup with sample data                          |

## Architecture Overview

This project uses a **Traditional Server-Side PHP Architecture**:

- PHP connects to MySQL and generates HTML output directly on the server.
- All pages use standard HTML `<form method="POST">` for form submissions.
- There is no separate API folder. The backend and frontend live together in `index.php`.
- JavaScript is minimal (~180 lines) and only handles:
  1. Tab/page switching (without a browser reload).
  2. Live weather fetching from the Open-Meteo external API.
  3. Fertilizer calculator real-time math.
  4. Opening and closing popup modals.

## Testing Checklist

- Open `http://localhost/FarmerDSS/login.php` in a browser.
- Log in as Admin (admin@gmail.com / admin@123).
- Verify Dashboard loads with live weather and user name.
- Click Crop Advisor, select inputs, click Generate Recommendations.
- Check that the top 3 crops and the explanation list are correct.
- Click Calculator, change crop and land size, check fertilizer kg and cost.
- Click Market Watch, verify the price table loads from the database.
- Click Admin Panel, update a market price, click Save.
- Click Admin Panel, add a new crop with full details, click Add Crop & Details.
- Log out, verify redirect to login page.
- Register a new account, verify redirect to dashboard.
- Log in again as Farmer, confirm Admin Panel is not visible.

## Conclusion

Farmar's DSS provides a clear, simple, and understandable decision support
workflow for crop planning. The system is built using a traditional PHP
server-side rendering approach, making the code straightforward for HNDIT-level
students to read, maintain, and extend. It combines responsive frontend design,
rule-based decision logic, live weather data, and XAMPP/MySQL database storage.
