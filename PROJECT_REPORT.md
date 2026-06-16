# Farmer's Hub DSS Project Report

## Project Title

Farmer's Hub Decision Support System

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
- view crop market prices,
- view weather alerts,
- store recommendation history in MySQL.

## Scope

This project covers:

- crop advisory,
- fertilizer calculation,
- market information,
- weather alerts,
- recommendation history.

This project does not yet include:

- real user authentication,
- real-time external weather API,
- admin dashboard for editing database records,
- full farm financial planning.

## System Users

- Farmer
- Agriculture advisor
- Admin or project evaluator

## Functional Requirements

1. The system shall display a responsive dashboard.
2. The system shall allow users to select district, soil type, season, water source, budget, and market demand.
3. The system shall calculate crop recommendation scores.
4. The system shall explain why the crop was recommended.
5. The system shall calculate fertilizer dosage based on crop and land size.
6. The system shall display market price and demand information.
7. The system shall display district-based weather information.
8. The system shall store generated recommendations in MySQL.

## Non-Functional Requirements

- The interface should be clean and responsive.
- The code should be easy for HNDIT students to understand.
- The system should run in XAMPP.
- The application should still show demo data if the database is not running.

## DSS Logic

Each crop receives a score using simple rule-based logic.

| Condition | Points |
| --- | ---: |
| Base score | 40 |
| Soil type match | 15 |
| Season match | 15 |
| Water source match | 15 |
| Budget match | 8 |
| Market demand match | 7 |

Maximum score is limited to 100.

## Database Design Summary

| Table | Purpose |
| --- | --- |
| crops | Stores crop suitability rules |
| crop_guides | Stores crop guide points |
| fertilizer_rules | Stores fertilizer dosage and cost data |
| market_prices | Stores crop market prices |
| weather_alerts | Stores district weather alerts |
| recommendation_history | Stores generated recommendation records |

## Main Files

| File | Purpose |
| --- | --- |
| index.html | Main user interface |
| styles.css | Responsive visual design |
| script.js | Frontend logic and fetch requests |
| database.sql | MySQL database setup |
| api/db.php | Database connection |
| api/data.php | Loads database data for frontend |
| api/recommend.php | Calculates and saves recommendations |

## Testing Checklist

- Open dashboard on desktop.
- Open dashboard on mobile width.
- Change crop advisor inputs.
- Click Generate Recommendations.
- Check recommendation explanation.
- Change fertilizer crop.
- Change land size.
- Check fertilizer kg and cost update.
- Check market table.
- Import database and confirm recommendation history is saved.

## Conclusion

Farmer's Hub DSS provides a clear, simple, and understandable decision support
workflow for crop planning. It combines frontend design, rule-based decision
logic, and XAMPP/MySQL database storage while staying suitable for an HNDIT-level
HTML, CSS, JavaScript, PHP, and MySQL project.
