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
  }, 3500);
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
  if (el("modal-overlay"))       el("modal-overlay").classList.remove("active");
  if (el("alert-modal-overlay")) el("alert-modal-overlay").classList.remove("active");
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

// Module-level so all async callbacks share the same counter
var activeAlerts = 0;

function determineWeatherRisk(code) {
  if (code >= 95) return { risk: "Extreme Rain/Thunderstorm Risk", advice: "Halt all field activities. Risk of flooding.", level: "high" };
  if (code >= 80) return { risk: "High Rain Risk",      advice: "Delay fertilizer application before heavy rain.", level: "high" };
  if (code >= 61) return { risk: "Moderate Rain Risk",  advice: "Light activities possible. Avoid spraying.",     level: "medium" };
  return             { risk: "Low Risk",               advice: "Favorable conditions for farming activities.",   level: "low" };
}

function getWeatherDescription(code) {
  if (code === 0)                         return "Clear sky";
  if (code >= 1  && code <= 3)            return "Partly cloudy";
  if (code >= 61 && code <= 69)           return "Rain";
  if (code >= 80 && code <= 82)           return "Rain showers";
  if (code >= 95)                         return "Thunderstorm";
  return "Overcast";
}

function renderDistrictWeatherCards() {
  var grid = el("district-weather-grid");
  if (!grid) return;

  // Pre-render skeleton cards in fixed positions so order is stable
  var skeletonHtml = "";
  for (var s = 0; s < districts.length; s++) {
    skeletonHtml +=
      "<div class='skeleton-card' id='dwc-" + districts[s].id + "'>" +
        "<div class='skeleton-line s-short'></div>" +
        "<div class='skeleton-line s-big'></div>" +
        "<div class='skeleton-line s-medium'></div>" +
        "<div class='skeleton-line s-full'></div>" +
      "</div>";
  }
  grid.innerHTML = skeletonHtml;

  // Reset alert state
  activeAlerts = 0;
  var alertBody = el("alert-modal-body");
  if (alertBody) alertBody.innerHTML = "";
  if (el("alert-count"))    el("alert-count").textContent = "0";
  if (el("warning-alerts")) el("warning-alerts").textContent = "0 Critical Alerts";

  for (var i = 0; i < districts.length; i++) {
    (function(d) {
      var url =
        "https://api.open-meteo.com/v1/forecast?latitude=" + d.lat +
        "&longitude=" + d.lon +
        "&current=temperature_2m,weather_code,wind_speed_10m,relative_humidity_2m";

      fetch(url)
        .then(function(response) { return response.json(); })
        .then(function(data) {
          if (!data.current) return;

          var temp     = Math.round(data.current.temperature_2m);
          var code     = data.current.weather_code;
          var wind     = data.current.wind_speed_10m;
          var humidity = data.current.relative_humidity_2m;
          var text     = getWeatherDescription(code);
          var riskObj  = determineWeatherRisk(code);

          // Update Trincomalee main dashboard card
          if (d.id === "Trincomalee") {
            if (el("temp-value"))    el("temp-value").innerHTML = temp + "&deg;C";
            if (el("weather-copy"))  el("weather-copy").textContent = text + ". Wind: " + wind + " km/h";
            if (el("weather-risk"))  el("weather-risk").textContent = riskObj.risk;
            // Fix: also update the static advice span
            var adviceSpan = document.querySelector(".advice-strip span");
            if (adviceSpan) adviceSpan.textContent = riskObj.advice;
          }

          // Replace skeleton card in-place (stable order)
          var slot = el("dwc-" + d.id);
          if (slot) {
            slot.className = "district-weather-card risk-" + riskObj.level;
            slot.innerHTML =
              "<div class='dwc-header'>" +
                "<span class='dwc-district'>" + d.id + "</span>" +
              "</div>" +
              "<div class='dwc-temp'>" + temp + "&deg;C</div>" +
              "<p class='dwc-text'>" + text + "</p>" +
              "<div class='dwc-footer'>" +
                "<span class='dwc-risk'>" + riskObj.risk + "</span>" +
                "<span class='dwc-wind'>&#128168; " + wind + " km/h &nbsp;&#128167; " + humidity + "%</span>" +
              "</div>";
          }

          // Accumulate alerts correctly
          if (code >= 80) {
            activeAlerts++;
            if (el("alert-count"))    el("alert-count").textContent = activeAlerts;
            if (el("warning-alerts")) el("warning-alerts").textContent = activeAlerts + " Critical Alert" + (activeAlerts > 1 ? "s" : "");
            if (alertBody) {
              alertBody.innerHTML +=
                "<div class='alert-item' style='margin-bottom:1rem; padding-bottom:1rem; border-bottom:1px solid var(--border);'>" +
                  "<strong>" + d.id + ": " + riskObj.risk + "</strong>" +
                  "<p style='margin:4px 0 0;'>" + riskObj.advice + "</p>" +
                "</div>";
            }
          }
        })
        .catch(function() {
          var slot = el("dwc-" + d.id);
          if (slot) {
            slot.className = "district-weather-card risk-low";
            slot.innerHTML =
              "<div class='dwc-header'><span class='dwc-district'>" + d.id + "</span></div>" +
              "<p class='dwc-text' style='color:var(--danger);'>Weather data unavailable.</p>";
          }
        });
    })(districts[i]);
  }
}

// ==========================================
// 4. FERTILIZER CALCULATOR
// ==========================================
function updateCalculator() {
  var landInput  = el("land-size");
  var cropSelect = el("target-crop");
  var body       = el("fertilizer-body");
  var costEl     = el("cost-estimate");

  if (!landInput || !cropSelect || !body) return;

  var acres     = parseFloat(landInput.value) || 0;
  var crop      = cropSelect.value;
  var rules     = gFertilizerRules[crop] || [];

  body.innerHTML = "";
  var totalCost  = 0;

  for (var i = 0; i < rules.length; i++) {
    var r          = rules[i];
    var requiredKg = r.kgPerAcre * acres;
    var rowCost    = requiredKg * r.pricePerKg;
    totalCost     += rowCost;

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

  if (costEl)           costEl.textContent = "Estimated cost: LKR " + totalCost.toLocaleString();
  if (el("calculator-note")) el("calculator-note").textContent = "Calculated for " + acres + " acres";
}

// ==========================================
// 5. METRIC CARD ANIMATION
// ==========================================
function animateMetricCards() {
  var cards = document.querySelectorAll(".metric-card");
  cards.forEach(function(card, idx) {
    setTimeout(function() {
      card.classList.add("highlight");
      setTimeout(function() { card.classList.remove("highlight"); }, 600);
    }, idx * 120);
  });
}

// ==========================================
// 6. PASSWORD TOGGLE
// ==========================================
function initPasswordToggles() {
  var toggles = document.querySelectorAll(".password-toggle");
  toggles.forEach(function(btn) {
    btn.addEventListener("click", function() {
      var wrap  = btn.closest(".password-input-wrap");
      var input = wrap ? wrap.querySelector("input") : null;
      if (!input) return;
      var isHidden = input.type === "password";
      input.type   = isHidden ? "text" : "password";
      btn.textContent = isHidden ? "🙈" : "👁️";
      btn.setAttribute("aria-label", isHidden ? "Hide password" : "Show password");
    });
  });
}

// ==========================================
// 7. INITIALIZE  (all wiring inside DOMContentLoaded)
// ==========================================
document.addEventListener("DOMContentLoaded", function () {
  // Page navigation
  showPage(START_PAGE);

  // Modal close buttons
  if (el("modal-close"))          el("modal-close").addEventListener("click", closeModals);
  if (el("modal-close-btn"))      el("modal-close-btn").addEventListener("click", closeModals);
  if (el("alert-modal-close"))    el("alert-modal-close").addEventListener("click", closeModals);
  if (el("alert-modal-close-btn"))el("alert-modal-close-btn").addEventListener("click", closeModals);

  // Close modals by clicking overlay backdrop
  if (el("modal-overlay")) {
    el("modal-overlay").addEventListener("click", function(e) {
      if (e.target === el("modal-overlay")) closeModals();
    });
  }
  if (el("alert-modal-overlay")) {
    el("alert-modal-overlay").addEventListener("click", function(e) {
      if (e.target === el("alert-modal-overlay")) closeModals();
    });
  }

  // Alert (warning) button
  if (el("warning-button")) {
    el("warning-button").addEventListener("click", function() {
      el("alert-modal-overlay").classList.add("active");
    });
  }

  // Calculator inputs
  if (el("land-size"))    el("land-size").addEventListener("input", updateCalculator);
  if (el("target-crop"))  el("target-crop").addEventListener("change", updateCalculator);

  // Escape key closes any open modal
  document.addEventListener("keydown", function(e) {
    if (e.key === "Escape") closeModals();
  });

  // Load weather & calculator
  renderDistrictWeatherCards();
  updateCalculator();

  // Animate metric cards on dashboard load
  animateMetricCards();

  // Password toggles
  initPasswordToggles();

  // Show PHP message as toast (safely encoded by json_encode in PHP)
  if (PHP_MESSAGE && PHP_MESSAGE.length > 0) {
    showToast(PHP_MESSAGE);
  }
});
