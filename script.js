function el(id) {
  return document.getElementById(id);
}

// Ensure PHP variables are present
var gFertilizerRules = window.fertilizerRules || {};
var gCropGuides = window.cropGuides || [];
var START_PAGE = window.START_PAGE || "dashboard";
var PHP_MESSAGE = window.PHP_MESSAGE || "";

// ==========================================
// 1. PAGE NAVIGATION
// ==========================================
function showPage(pageId) {
  var pages = document.querySelectorAll(".page");
  var links = document.querySelectorAll(".topnav a");

  for (var i = 0; i < pages.length; i++) {
    pages[i].classList.remove("active");
  }
  for (var j = 0; j < links.length; j++) {
    links[j].classList.remove("active");
  }

  var selectedPage = el(pageId);
  if (selectedPage) {
    selectedPage.classList.add("active");
  }

  for (var k = 0; k < links.length; k++) {
    if (links[k].getAttribute("href") === "#" + pageId) {
      links[k].classList.add("active");
    }
  }
}

// ==========================================
// 2. MODALS & TOASTS
// ==========================================
function showToast(message) {
  var t = el("toast");
  if (!t) return;
  t.textContent = message;
  t.classList.add("show");
  setTimeout(function () {
    t.classList.remove("show");
  }, 3000);
}

function openModal(cropName) {
  var cropData = null;
  for (var i = 0; i < gCropGuides.length; i++) {
    if (gCropGuides[i].crop === cropName) {
      cropData = gCropGuides[i];
      break;
    }
  }

  if (cropData) {
    el("modal-title").textContent = cropName + " Growth Guide";
    el("modal-description").textContent = "Expected duration: " + cropData.duration;
    el("modal-icon").textContent = "🌱";

    var ul = el("modal-list");
    ul.innerHTML = "";
    var guides = cropData.guide || [];
    for (var j = 0; j < guides.length; j++) {
      var li = document.createElement("li");
      li.textContent = guides[j];
      ul.appendChild(li);
    }
    el("modal-overlay").classList.add("active");
  }
}

function closeModals() {
  if (el("modal-overlay")) el("modal-overlay").classList.remove("active");
  if (el("alert-modal-overlay")) el("alert-modal-overlay").classList.remove("active");
}

// Event listeners for modals
if (el("modal-close")) el("modal-close").addEventListener("click", closeModals);
if (el("modal-close-btn")) el("modal-close-btn").addEventListener("click", closeModals);
if (el("alert-modal-close")) el("alert-modal-close").addEventListener("click", closeModals);
if (el("alert-modal-close-btn")) el("alert-modal-close-btn").addEventListener("click", closeModals);

if (el("warning-button")) {
  el("warning-button").addEventListener("click", function() {
    el("alert-modal-overlay").classList.add("active");
  });
}

// ==========================================
// 3. LIVE WEATHER (EXTERNAL API)
// ==========================================
var districts = [
  { id: "Trincomalee", lat: 8.5711, lon: 81.2335 },
  { id: "Anuradhapura", lat: 8.3114, lon: 80.4037 },
  { id: "Jaffna", lat: 9.6615, lon: 80.0255 },
  { id: "Kandy", lat: 7.2906, lon: 80.6337 }
];

function determineWeatherRisk(code) {
  if (code >= 95) return { risk: "Extreme Rain/Thunderstorm Risk", advice: "Halt all field activities. Risk of flooding." };
  if (code >= 80) return { risk: "High Rain Risk", advice: "Delay fertilizer application before heavy rain." };
  if (code >= 61) return { risk: "Moderate Rain Risk", advice: "Light activities possible. Avoid spraying." };
  return { risk: "Low Risk", advice: "Favorable conditions for farming activities." };
}

function getWeatherDescription(code) {
  if (code === 0) return "Clear sky";
  if (code === 1 || code === 2 || code === 3) return "Partly cloudy";
  if (code >= 61 && code <= 69) return "Rain";
  if (code >= 80 && code <= 82) return "Rain showers";
  if (code >= 95) return "Thunderstorm";
  return "Overcast";
}

function renderDistrictWeatherCards() {
  var grid = el("district-weather-grid");
  if (!grid) return;
  grid.innerHTML = "";

  var activeAlerts = 0;
  var alertBody = el("alert-modal-body");
  if (alertBody) alertBody.innerHTML = "";

  for (var i = 0; i < districts.length; i++) {
    (function(d) {
      var url = "https://api.open-meteo.com/v1/forecast?latitude=" + d.lat + "&longitude=" + d.lon + "&current=temperature_2m,weather_code,wind_speed_10m,relative_humidity_2m";
      fetch(url)
        .then(function(response) { return response.json(); })
        .then(function(data) {
          if (!data.current) return;
          var temp = Math.round(data.current.temperature_2m);
          var code = data.current.weather_code;
          var wind = data.current.wind_speed_10m;
          var humidity = data.current.relative_humidity_2m;
          var text = getWeatherDescription(code);
          var riskObj = determineWeatherRisk(code);

          // Update Trincomalee as main dashboard weather if it's Trincomalee
          if (d.id === "Trincomalee") {
            if (el("temp-value")) el("temp-value").innerHTML = temp + "&deg;C";
            if (el("weather-copy")) el("weather-copy").textContent = text + ". Wind: " + wind + "km/h";
            if (el("weather-risk")) el("weather-risk").textContent = riskObj.risk;
          }

          // Build grid card
          var cardHtml = "<article class='panel weather-card' style='margin:0;'>";
          cardHtml += "<div class='weather-top' style='border-bottom: 1px solid var(--border); padding-bottom: 1rem; margin-bottom: 1rem;'>";
          cardHtml += "<div><p class='label'>" + d.id + "</p><h2 style='font-size: 2rem; margin: 0.5rem 0;'>" + temp + "&deg;C</h2>";
          cardHtml += "<p class='weather-copy' style='margin:0;'>" + text + "</p></div>";
          cardHtml += "<div class='weather-icon' aria-hidden='true'><div class='sun'></div><div class='cloud'></div></div></div>";
          cardHtml += "<div style='display:flex; justify-content:space-between; margin-bottom: 1rem;'><span style='font-size:0.9rem'>Humidity: " + humidity + "%</span><span style='font-size:0.9rem'>Wind: " + wind + "km/h</span></div>";
          cardHtml += "<div class='advice-strip'><strong>" + riskObj.risk + "</strong><span>" + riskObj.advice + "</span></div>";
          cardHtml += "</article>";
          grid.innerHTML += cardHtml;

          // Add to alerts if risk is high
          if (code >= 80 && alertBody) {
            activeAlerts++;
            alertBody.innerHTML += "<div class='alert-item' style='margin-bottom:1rem; padding-bottom:1rem; border-bottom:1px solid var(--border);'><strong>" + d.id + ": " + riskObj.risk + "</strong><p style='margin:0;'>" + riskObj.advice + "</p></div>";
            if (el("alert-count")) el("alert-count").textContent = activeAlerts;
            if (el("warning-alerts")) el("warning-alerts").textContent = activeAlerts + " Critical Alerts";
          }
        })
        .catch(function() {
          console.log("Failed to load weather for " + d.id);
        });
    })(districts[i]);
  }
}

// ==========================================
// 4. FERTILIZER CALCULATOR
// ==========================================
function updateCalculator() {
  var landInput = el("land-size");
  var cropSelect = el("target-crop");
  var body = el("fertilizer-body");
  var costEl = el("cost-estimate");

  if (!landInput || !cropSelect || !body) return;

  var acres = parseFloat(landInput.value) || 0;
  var crop = cropSelect.value;
  var rules = gFertilizerRules[crop] || [];

  body.innerHTML = "";
  var totalCost = 0;

  for (var i = 0; i < rules.length; i++) {
    var r = rules[i];
    var requiredKg = r.kgPerAcre * acres;
    var rowCost = requiredKg * r.pricePerKg;
    totalCost += rowCost;

    var tr = "<tr>";
    tr += "<td><span class='fertilizer-badge'>" + r.type + "</span></td>";
    tr += "<td>" + requiredKg.toFixed(2) + " kg</td>";
    tr += "<td>" + r.schedule + "</td>";
    tr += "</tr>";
    body.innerHTML += tr;
  }

  if (rules.length === 0) {
    body.innerHTML = "<tr><td colspan='3' style='text-align:center;'>No fertilizer rules found for this crop.</td></tr>";
  }

  if (costEl) costEl.textContent = "Estimated cost: LKR " + totalCost.toLocaleString();
  if (el("calculator-note")) el("calculator-note").textContent = "Calculated for " + acres + " acres";
}

if (el("land-size")) el("land-size").addEventListener("input", updateCalculator);
if (el("target-crop")) el("target-crop").addEventListener("change", updateCalculator);

// ==========================================
// 5. INITIALIZE
// ==========================================
document.addEventListener("DOMContentLoaded", function () {
  showPage(START_PAGE);
  renderDistrictWeatherCards();
  updateCalculator();

  if (PHP_MESSAGE) {
    showToast(PHP_MESSAGE);
  }
});
