const API_BASE = "api";

let currentUser = null;

async function checkAuth() {
  try {
    const response = await fetch(`${API_BASE}/login.php`);
    const data = await response.json();

    if (data.authenticated && data.user) {
      currentUser = data.user;
      updateProfileUI();
      return true;
    } else {
      window.location.href = "login.html";
      return false;
    }
  } catch (error) {
    console.warn("Auth check failed, redirecting to login");
    window.location.href = "login.html";
    return false;
  }
}

async function loadAllWeather() {
  const districts = Object.keys(DISTRICT_COORDS);
  await Promise.all(districts.map(async (district) => {
    const live = await fetchLiveWeather(district);
    if (live) weatherByDistrict[district] = live;
  }));
  // Update default weatherData to current district
  const sel = document.getElementById("district");
  const cur = sel ? sel.value : "Trincomalee";
  weatherData = weatherByDistrict[cur] || weatherData;
  refreshDashboard();
  document.getElementById("alert-count").textContent = weatherData.alerts;
}

function updateProfileUI() {
  if (!currentUser) return;

  const firstLetter = currentUser.fullName.charAt(0).toUpperCase();
  document.getElementById("profile-avatar").textContent = firstLetter;
  document.getElementById("profile-name").textContent = currentUser.fullName;
  document.getElementById("profile-role").textContent = currentUser.role;
  
  if (currentUser.role === 'Admin') {
    const navAdmin = document.getElementById("nav-admin");
    if (navAdmin) navAdmin.style.display = 'block';
  }
}

async function handleLogout() {
  try {
    await fetch(`${API_BASE}/logout.php`);
    window.location.href = "login.html";
  } catch (error) {
    console.error("Logout error:", error);
    window.location.href = "login.html";
  }
}

let weatherData = {
  temp: 29,
  text: "Partly cloudy. Heavy rain expected tomorrow in Trincomalee.",
  status: "System Online",
  alerts: 2,
  fields: 12,
  logs: 7,
  risk: "Rain Risk: High",
};

// District coordinates for Open-Meteo API
const DISTRICT_COORDS = {
  Trincomalee: { lat: 8.5922, lon: 81.2152 },
  Anuradhapura: { lat: 8.3114, lon: 80.4037 },
  Jaffna:       { lat: 9.6615, lon: 80.0255 },
  Kandy:        { lat: 7.2906, lon: 80.6337 },
};

function weatherCodeToText(code, isRain) {
  if (code === 0) return "Clear sky and sunny conditions.";
  if (code <= 2) return "Partly cloudy with some sun.";
  if (code === 3) return "Overcast and cloudy skies.";
  if (code <= 48) return "Foggy or mist conditions expected.";
  if (code <= 57) return "Light drizzle expected.";
  if (code <= 67) return "Rain showers expected. Plan field work accordingly.";
  if (code <= 77) return "Snow or sleet possible — unusual weather alert.";
  if (code <= 82) return "Heavy rain showers expected. Delay fertilizer application.";
  if (code <= 99) return "Thunderstorm risk. Secure equipment and avoid open fields.";
  return "Mixed conditions — check local forecast.";
}

function weatherCodeToRisk(code, precipProb) {
  if (precipProb >= 70 || code >= 80) return "Rain Risk: High";
  if (precipProb >= 40 || (code >= 61 && code < 80)) return "Rain Risk: Medium";
  if (code >= 45 && code < 61) return "Fog Risk: Low";
  if (code === 0 || code <= 2) return "Weather Risk: Low";
  return "Weather Risk: Moderate";
}

async function fetchLiveWeather(district) {
  const coords = DISTRICT_COORDS[district];
  if (!coords) return null;
  try {
    const url = `https://api.open-meteo.com/v1/forecast?latitude=${coords.lat}&longitude=${coords.lon}` +
      `&current=temperature_2m,weathercode,windspeed_10m` +
      `&daily=precipitation_probability_max,weathercode` +
      `&timezone=Asia%2FColombo&forecast_days=2`;
    const res = await fetch(url);
    if (!res.ok) return null;
    const d = await res.json();
    const temp    = Math.round(d.current.temperature_2m);
    const code    = d.current.weathercode;
    const precip  = d.daily.precipitation_probability_max[0] ?? 0;
    const tmrPrec = d.daily.precipitation_probability_max[1] ?? 0;
    const tmrCode = d.daily.weathercode[1] ?? code;
    const riskText = weatherCodeToRisk(tmrCode, tmrPrec);
    const alertCount = (tmrPrec >= 70 ? 1 : 0) + (code >= 80 ? 1 : 0) + (tmrCode >= 80 ? 1 : 0);
    return {
      temp,
      text: `${weatherCodeToText(code, precip >= 40)} Tomorrow: ${tmrPrec}% rain chance.`,
      status: "Live Weather",
      alerts: Math.max(1, alertCount),
      fields: weatherData.fields,
      logs: weatherData.logs,
      risk: riskText,
      precipToday: precip,
      precipTomorrow: tmrPrec,
      windspeed: d.current.windspeed_10m,
      weatherCode: code,
      district,
    };
  } catch (e) {
    console.warn("Weather API failed:", e.message);
    return null;
  }
}

let weatherByDistrict = {
  Trincomalee: weatherData,
};

let cropRules = [
  {
    crop: "Paddy",
    bestSoils: ["Alluvial", "Clay"],
    bestSeasons: ["Yala", "Maha"],
    bestWater: ["Irrigation", "Rainfed"],
    budget: "Medium",
    demand: "High",
    duration: "95 - 115 days",
    profit: 82,
    guide: [
      "Maintain a 3 cm to 5 cm water level during early growth.",
      "Apply nitrogen in split doses to reduce nutrient loss.",
      "Watch for brown planthopper after rainy days.",
    ],
  },
  {
    crop: "Maize",
    bestSoils: ["Alluvial", "Laterite"],
    bestSeasons: ["Yala", "Dry"],
    bestWater: ["Irrigation", "Groundwater"],
    budget: "Medium",
    demand: "Medium",
    duration: "90 - 110 days",
    profit: 76,
    guide: [
      "Use row spacing around 75 cm for better sunlight.",
      "Apply nitrogen at planting and again during rapid growth.",
      "Avoid waterlogging because maize roots need aeration.",
    ],
  },
  {
    crop: "Chili",
    bestSoils: ["Sandy", "Laterite"],
    bestSeasons: ["Dry", "Yala"],
    bestWater: ["Groundwater", "Irrigation"],
    budget: "High",
    demand: "High",
    duration: "120 - 150 days",
    profit: 90,
    guide: [
      "Use raised beds and drip irrigation where possible.",
      "Apply phosphorus before flowering to support pod formation.",
      "Protect plants from fungal disease during wet weather.",
    ],
  },
  {
    crop: "Red Onion",
    bestSoils: ["Sandy", "Alluvial"],
    bestSeasons: ["Dry", "Yala"],
    bestWater: ["Groundwater", "Irrigation"],
    budget: "Low",
    demand: "High",
    duration: "70 - 90 days",
    profit: 86,
    guide: [
      "Prepare loose soil so bulbs can expand properly.",
      "Avoid excess water near harvest to reduce bulb rot.",
      "Harvest when leaves bend and begin to dry.",
    ],
  },
];

let fertilizerRules = {
  Paddy: [
    {
      type: "Urea",
      kgPerAcre: 48,
      schedule: "Day 14 and Day 35 split application",
    },
    { type: "MOP", kgPerAcre: 24, schedule: "Apply at Day 21" },
    { type: "TSP", kgPerAcre: 12, schedule: "Base dressing before sowing" },
  ],
  Maize: [
    { type: "Urea", kgPerAcre: 38, schedule: "At planting and 30 days after" },
    { type: "MOP", kgPerAcre: 18, schedule: "Apply at Day 20" },
    { type: "Compost", kgPerAcre: 220, schedule: "Mix before planting" },
  ],
  Chili: [
    {
      type: "Urea",
      kgPerAcre: 30,
      schedule: "Small split doses every 3 weeks",
    },
    { type: "TSP", kgPerAcre: 22, schedule: "Before flowering" },
    { type: "Compost", kgPerAcre: 260, schedule: "Mix into raised beds" },
  ],
  "Red Onion": [
    {
      type: "Urea",
      kgPerAcre: 24,
      schedule: "Day 15 and Day 30 split application",
    },
    { type: "MOP", kgPerAcre: 20, schedule: "Apply before bulb formation" },
    { type: "Compost", kgPerAcre: 180, schedule: "Mix before planting" },
  ],
};

const fertilizerPrices = {
  Urea: 320,
  MOP: 360,
  TSP: 420,
  Compost: 35,
};

let marketData = [
  { crop: "Paddy", price: 150, trend: "up", demand: "High" },
  { crop: "Green Chili", price: 450, trend: "up", demand: "High" },
  { crop: "Maize", price: 180, trend: "down", demand: "Medium" },
  { crop: "Red Onion", price: 320, trend: "up", demand: "High" },
];

function getValue(id) {
  return document.getElementById(id).value;
}

async function fetchJson(url, options = {}) {
  const response = await fetch(url, options);
  if (!response.ok) {
    throw new Error(`Request failed: ${url}`);
  }

  const data = await response.json();
  if (!data.success) {
    throw new Error(data.message || "API request failed");
  }

  return data;
}

async function loadDataFromApi() {
  try {
    const data = await fetchJson(`${API_BASE}/data.php`);
    cropRules = data.crops;
    fertilizerRules = data.fertilizerRules;
    marketData = data.marketData;
    weatherByDistrict = data.weatherByDistrict;
    weatherData =
      weatherByDistrict.Trincomalee ||
      Object.values(weatherByDistrict)[0] ||
      weatherData;
    showToast("Database data loaded from MySQL.");
  } catch (error) {
    console.warn(error.message);
    showToast(
      "Using demo data. Start XAMPP and import database.sql for MySQL data.",
    );
  }
}

function updateDate() {
  const options = { year: "numeric", month: "long", day: "numeric" };
  document.getElementById("current-date").textContent =
    new Date().toLocaleDateString("en-US", options);
}

function refreshDashboard() {
  const districtSelect = document.getElementById("district");
  const selectedDistrict = districtSelect
    ? districtSelect.value
    : "Trincomalee";
  weatherData = weatherByDistrict[selectedDistrict] || weatherData;

  document.getElementById("temp-value").textContent =
    `${weatherData.temp}\u00B0C`;
  document.getElementById("weather-copy").textContent = weatherData.text;
  document.getElementById("weather-risk").textContent = weatherData.risk;
  document.getElementById("status-pill-text").textContent = weatherData.status;
  document.getElementById("alert-count").textContent = weatherData.alerts;
  document.getElementById("warning-alerts").textContent =
    `${weatherData.alerts} Critical Alerts`;
  document.getElementById("field-summary-value").textContent =
    `${weatherData.fields} Active Fields`;
  document.getElementById("system-logs-value").textContent =
    `${weatherData.logs} New Entries`;
}

function calculateCropScore(rule, inputs) {
  let score = 40;
  const reasons = [];

  if (rule.bestSoils.includes(inputs.soil)) {
    score += 15;
    reasons.push(`${rule.crop} suits ${inputs.soil} soil.`);
  }

  if (rule.bestSeasons.includes(inputs.season)) {
    score += 15;
    reasons.push(`${inputs.season} season is suitable for ${rule.crop}.`);
  }

  if (rule.bestWater.includes(inputs.water)) {
    score += 15;
    reasons.push(`${inputs.water} water source supports ${rule.crop}.`);
  }

  if (rule.budget === inputs.budget) {
    score += 8;
    reasons.push(
      `The ${inputs.budget.toLowerCase()} budget level matches this crop.`,
    );
  }

  if (rule.demand === inputs.demand) {
    score += 7;
    reasons.push(
      `Market demand is ${inputs.demand.toLowerCase()} for this crop.`,
    );
  }

  if (score > 100) {
    score = 100;
  }

  return { ...rule, score, reasons };
}

function getAdvisorInputs() {
  return {
    district: getValue("district"),
    soil: getValue("soil-type"),
    season: getValue("season-type"),
    water: getValue("water-source"),
    budget: getValue("budget-level"),
    demand: getValue("market-demand"),
  };
}

function calculateLocalRecommendations(inputs) {
  return cropRules
    .map((rule) => calculateCropScore(rule, inputs))
    .sort((a, b) => b.score - a.score);
}

async function generateRecommendations(showNotification = true) {
  const inputs = getAdvisorInputs();
  let results = calculateLocalRecommendations(inputs);
  let sourceText = "demo logic";

  if (showNotification) {
    try {
      const data = await fetchJson(`${API_BASE}/recommend.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(inputs),
      });
      results = data.results;
      sourceText = "MySQL/PHP logic";
    } catch (error) {
      console.warn(error.message);
    }
  }

  renderCropResults(results);
  renderReasons(results[0], inputs);
  updateDailyAdvice(results[0], inputs);
  refreshDashboard();

  if (showNotification) {
    showToast(
      `Best match for ${inputs.district}: ${results[0].crop} (${results[0].score}%) using ${sourceText}.`,
    );
  }
}

function renderCropResults(results) {
  const container = document.getElementById("crop-results");
  container.innerHTML = "";

  results.slice(0, 3).forEach((item) => {
    const card = document.createElement("article");
    card.className = "crop-card";
    card.tabIndex = 0;
    card.dataset.crop = item.crop;

    card.innerHTML = `
      <div class="crop-card-top">
        <h3>${item.crop}</h3>
        <span class="score-badge">${item.score}%</span>
      </div>
      <p>Expected duration: ${item.duration}. Profit score: ${item.profit}/100.</p>
      <div class="crop-tags">
        <span>${item.bestSoils.join(" / ")}</span>
        <span>${item.bestSeasons.join(" / ")}</span>
        <span>${item.bestWater.join(" / ")}</span>
      </div>
      <button class="outline-button" type="button">View Growth Guide</button>
    `;

    card.addEventListener("click", () => openModal(item.crop));
    card.addEventListener("keydown", (event) => {
      if (event.key === "Enter") {
        openModal(item.crop);
      }
    });
    container.appendChild(card);
  });

  document.getElementById("recommendation-summary").textContent =
    `${results[0].crop} is the highest scoring option`;
}

function renderReasons(bestCrop, inputs) {
  const reasonList = document.getElementById("reason-list");
  const defaultReasons = [
    `District selected: ${inputs.district}.`,
    `The system compared soil, season, water source, budget, and demand.`,
  ];
  const reasons = [...defaultReasons, ...bestCrop.reasons];

  reasonList.innerHTML = reasons.map((reason) => `<li>${reason}</li>`).join("");
}

function updateDailyAdvice(bestCrop, inputs) {
  document.getElementById("daily-advice-title").textContent =
    `${bestCrop.crop} is currently the safest match`;
  document.getElementById("daily-advice-text").textContent =
    `Based on ${inputs.season} season, ${inputs.soil.toLowerCase()} soil, ${inputs.water.toLowerCase()} water, and ${inputs.demand.toLowerCase()} market demand.`;
  document.getElementById("daily-score").textContent = `${bestCrop.score}%`;
}

function renderFertilizerCalculator() {
  const crop = getValue("target-crop");
  const acres = Number(document.getElementById("land-size").value) || 0;
  const rows = fertilizerRules[crop];
  const body = document.getElementById("fertilizer-body");
  let totalCost = 0;

  body.innerHTML = "";

  rows.forEach((item) => {
    const totalKg = item.kgPerAcre * acres;
    const pricePerKg = item.pricePerKg || fertilizerPrices[item.type] || 0;
    totalCost += totalKg * pricePerKg;

    const row = document.createElement("tr");
    row.innerHTML = `
      <td>${item.type}</td>
      <td>${totalKg.toFixed(1)} kg</td>
      <td>${item.schedule}</td>
    `;
    body.appendChild(row);
  });

  document.getElementById("calculator-note").textContent =
    `Calculated for ${acres} acres of ${crop}`;
  document.getElementById("cost-estimate").textContent =
    `Estimated cost: LKR ${Math.round(totalCost).toLocaleString()}`;
  document.getElementById("fertilizer-tip").textContent =
    crop === "Paddy"
      ? "Avoid applying fertilizer just before heavy rain to reduce nutrient loss."
      : "Apply fertilizer in small doses and monitor plant response weekly.";
}

function renderMarket() {
  const marketBody = document.getElementById("market-body");
  marketBody.innerHTML = "";

  marketData.forEach((item) => {
    const row = document.createElement("tr");
    row.innerHTML = `
      <td>${item.crop}</td>
      <td>LKR ${item.price}</td>
      <td><span class="trend ${item.trend}">${item.trend === "up" ? "Up" : "Down"}</span></td>
      <td>${item.demand}</td>
    `;
    marketBody.appendChild(row);
  });

  renderProfitRanking();
}

let adminMarketData = [];

async function loadAdminMarket() {
  try {
    const data = await fetchJson(`${API_BASE}/admin_market.php`);
    adminMarketData = data.marketPrices;
    renderAdminMarket();
  } catch (error) {
    console.error("Failed to load admin market data:", error);
  }
}

function renderAdminMarket() {
  const body = document.getElementById("admin-market-body");
  if (!body) return;
  body.innerHTML = "";

  adminMarketData.forEach((item) => {
    const row = document.createElement("tr");
    row.innerHTML = `
      <td>${item.id}</td>
      <td><strong>${item.crop}</strong></td>
      <td>
        <input type="number" id="admin-price-${item.id}" value="${item.price}" class="admin-input-small" />
      </td>
      <td>
        <select id="admin-trend-${item.id}" class="admin-input-small">
          <option value="up" ${item.trend === 'up' ? 'selected' : ''}>Up</option>
          <option value="down" ${item.trend === 'down' ? 'selected' : ''}>Down</option>
          <option value="stable" ${item.trend === 'stable' ? 'selected' : ''}>Stable</option>
        </select>
      </td>
      <td>
        <select id="admin-demand-${item.id}" class="admin-input-small">
          <option value="High" ${item.demand === 'High' ? 'selected' : ''}>High</option>
          <option value="Medium" ${item.demand === 'Medium' ? 'selected' : ''}>Medium</option>
          <option value="Low" ${item.demand === 'Low' ? 'selected' : ''}>Low</option>
        </select>
      </td>
      <td>
        <button class="outline-button" onclick="saveAdminMarket(${item.id})">Save</button>
      </td>
    `;
    body.appendChild(row);
  });
}

window.saveAdminMarket = async function(id) {
  const price = document.getElementById(`admin-price-${id}`).value;
  const trend = document.getElementById(`admin-trend-${id}`).value;
  const demand = document.getElementById(`admin-demand-${id}`).value;

  try {
    await fetchJson(`${API_BASE}/admin_market.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id, price, trend, demand }),
    });
    showToast("Market price updated successfully!");
    
    // Refresh public data to reflect changes immediately
    await loadDataFromApi();
    renderMarket();
  } catch (error) {
    showToast("Error updating price: " + error.message);
  }
};

function renderProfitRanking() {
  const profitList = document.getElementById("profit-list");
  const ranked = cropRules
    .map((crop) => {
      const market = marketData.find(
        (item) =>
          item.crop.includes(crop.crop) || crop.crop.includes(item.crop),
      );
      const marketBoost = market && market.trend === "up" ? 8 : 0;
      return { ...crop, finalScore: crop.profit + marketBoost };
    })
    .sort((a, b) => b.finalScore - a.finalScore);

  profitList.innerHTML = ranked
    .slice(0, 3)
    .map(
      (item, index) => `
        <article class="profit-card">
          <div class="profit-card-top">
            <h3>${index + 1}. ${item.crop}</h3>
            <span class="score-badge">${item.finalScore}/100</span>
          </div>
          <p>Good selling potential based on crop profit score and current price trend.</p>
        </article>
      `,
    )
    .join("");
}

function openModal(cropName) {
  const crop = cropRules.find((item) => item.crop === cropName);
  if (!crop) {
    return;
  }

  document.getElementById("modal-title").textContent =
    `${crop.crop} Growth Guide`;
  document.getElementById("modal-description").textContent =
    `${crop.crop} usually takes ${crop.duration}. Follow these actions for better results.`;
  document.getElementById("modal-list").innerHTML = crop.guide
    .map((item) => `<li>${item}</li>`)
    .join("");
  document.getElementById("modal-overlay").classList.add("show");
  document.getElementById("modal-overlay").setAttribute("aria-hidden", "false");
}

function closeModal() {
  const modal = document.getElementById("modal-overlay");
  modal.classList.remove("show");
  modal.setAttribute("aria-hidden", "true");
}

function showToast(message) {
  const toast = document.getElementById("toast");
  toast.innerHTML = `<strong>Live update:</strong> ${message}`;
  toast.classList.add("show");

  clearTimeout(showToast.timeoutId);
  showToast.timeoutId = setTimeout(() => {
    toast.classList.remove("show");
  }, 2600);
}

function showPage(pageId) {
  const pageExists = document.getElementById(pageId);
  const safePageId = pageExists ? pageId : "dashboard";

  document.querySelectorAll(".page").forEach((page) => {
    page.classList.toggle("active", page.id === safePageId);
  });

  document.querySelectorAll(".topnav a").forEach((link) => {
    link.classList.toggle(
      "active",
      link.getAttribute("href") === `#${safePageId}`,
    );
  });

  history.replaceState(null, "", `#${safePageId}`);
}

async function handleAddCrop(event) {
  event.preventDefault();
  
  const cropName = document.getElementById("add-crop-name").value.trim();
  const duration = document.getElementById("add-crop-duration").value.trim();
  const profitScore = parseInt(document.getElementById("add-crop-profit").value);
  const price = parseFloat(document.getElementById("add-crop-price").value);
  const trend = document.getElementById("add-crop-trend").value;
  const budgetLevel = document.getElementById("add-crop-budget").value;
  const marketDemand = document.getElementById("add-crop-demand").value;
  const guide = document.getElementById("add-crop-guide").value;

  // Checkboxes
  const checkedSoils = Array.from(document.querySelectorAll('input[name="best_soils"]:checked')).map(el => el.value);
  const checkedSeasons = Array.from(document.querySelectorAll('input[name="best_seasons"]:checked')).map(el => el.value);
  const checkedWater = Array.from(document.querySelectorAll('input[name="best_water"]:checked')).map(el => el.value);

  if (checkedSoils.length === 0) {
    showToast("Please select at least one soil type.");
    return;
  }
  if (checkedSeasons.length === 0) {
    showToast("Please select at least one season.");
    return;
  }
  if (checkedWater.length === 0) {
    showToast("Please select at least one water source.");
    return;
  }

  // Fertilizers
  const fertilizers = [
    {
      type: "Urea",
      kgPerAcre: parseFloat(document.getElementById("fertilizer-urea-kg").value) || 0,
      pricePerKg: parseFloat(document.getElementById("fertilizer-urea-price").value) || 320,
      schedule: document.getElementById("fertilizer-urea-schedule").value.trim()
    },
    {
      type: "MOP",
      kgPerAcre: parseFloat(document.getElementById("fertilizer-mop-kg").value) || 0,
      pricePerKg: parseFloat(document.getElementById("fertilizer-mop-price").value) || 360,
      schedule: document.getElementById("fertilizer-mop-schedule").value.trim()
    },
    {
      type: "TSP",
      kgPerAcre: parseFloat(document.getElementById("fertilizer-tsp-kg").value) || 0,
      pricePerKg: parseFloat(document.getElementById("fertilizer-tsp-price").value) || 420,
      schedule: document.getElementById("fertilizer-tsp-schedule").value.trim()
    },
    {
      type: "Compost",
      kgPerAcre: parseFloat(document.getElementById("fertilizer-compost-kg").value) || 0,
      pricePerKg: parseFloat(document.getElementById("fertilizer-compost-price").value) || 35,
      schedule: document.getElementById("fertilizer-compost-schedule").value.trim()
    }
  ].filter(f => f.kgPerAcre > 0);

  const payload = {
    action: 'add',
    crop_name: cropName,
    duration: duration,
    profit_score: profitScore,
    price: price,
    trend: trend,
    budget_level: budgetLevel,
    market_demand: marketDemand,
    guide: guide,
    best_soils: checkedSoils.join(","),
    best_seasons: checkedSeasons.join(","),
    best_water: checkedWater.join(","),
    fertilizers: fertilizers
  };

  const submitBtn = document.getElementById("add-crop-submit-btn");
  const originalText = submitBtn.textContent;
  submitBtn.disabled = true;
  submitBtn.textContent = "Adding Crop...";

  try {
    const data = await fetchJson(`${API_BASE}/admin_market.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });

    showToast("Crop and all details added successfully!");
    document.getElementById("admin-add-crop-form").reset();
    
    // Refresh tables and recommendation lists immediately
    await loadDataFromApi();
    renderMarket();
    
    // Dynamically update Calculator target crop dropdown options
    const targetCropSelect = document.getElementById("target-crop");
    if (targetCropSelect) {
      // Clear and re-populate crop option list
      targetCropSelect.innerHTML = cropRules.map(c => `<option value="${c.crop}">${c.crop}</option>`).join("");
      renderFertilizerCalculator();
    }
    
    await generateRecommendations(false);
    
    if (currentUser && currentUser.role === 'Admin') {
      await loadAdminMarket();
    }
  } catch (error) {
    showToast("Error: " + error.message);
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = originalText;
  }
}

function bindEvents() {
  document.querySelectorAll(".topnav a, .brand").forEach((link) => {
    link.addEventListener("click", (event) => {
      event.preventDefault();
      const pageId = link.getAttribute("href").replace("#", "");
      showPage(pageId);
    });
  });

  document
    .getElementById("logout-button")
    .addEventListener("click", handleLogout);

  document.getElementById("warning-button").addEventListener("click", () => {
    openAlertModal();
  });

  document
    .getElementById("generate-button")
    .addEventListener("click", async () => {
      const button = document.getElementById("generate-button");
      button.textContent = "Generating...";
      button.disabled = true;

      setTimeout(() => {
        generateRecommendations().finally(() => {
          button.textContent = "Generate Recommendations";
          button.disabled = false;
        });
      }, 500);
    });

  document.getElementById("district").addEventListener("change", async () => {
    refreshDashboard();
    await generateRecommendations(false);
  });

  [
    "soil-type",
    "season-type",
    "water-source",
    "budget-level",
    "market-demand",
  ].forEach((id) => {
    document.getElementById(id).addEventListener("change", () => {
      document.getElementById("recommendation-summary").textContent =
        "Inputs changed. Click Generate Recommendations.";
    });
  });

  ["target-crop", "land-size"].forEach((id) => {
    document
      .getElementById(id)
      .addEventListener("input", renderFertilizerCalculator);
  });

  document.getElementById("modal-close").addEventListener("click", closeModal);
  document
    .getElementById("modal-close-btn")
    .addEventListener("click", closeModal);
  document
    .getElementById("modal-overlay")
    .addEventListener("click", (event) => {
      if (event.target.id === "modal-overlay") {
        closeModal();
      }
    });

  // Alert modal close buttons
  document.getElementById("alert-modal-close").addEventListener("click", closeAlertModal);
  document.getElementById("alert-modal-close-btn").addEventListener("click", closeAlertModal);
  document.getElementById("alert-modal-overlay").addEventListener("click", (e) => {
    if (e.target.id === "alert-modal-overlay") closeAlertModal();
  });

  const addCropForm = document.getElementById("admin-add-crop-form");
  if (addCropForm) {
    addCropForm.addEventListener("submit", handleAddCrop);
  }
}

function openAlertModal() {
  const wd = weatherData;
  const isCriticalRain = (wd.precipTomorrow ?? 70) >= 70;
  const isHighWind = (wd.windspeed ?? 0) > 35;
  const isThunder = (wd.weatherCode ?? 0) >= 95;

  const alerts = [
    {
      critical: isCriticalRain,
      badge: isCriticalRain ? "Critical" : "Warning",
      title: `Rain Alert — ${wd.district || "District"}: ${wd.precipTomorrow ?? "—"}% chance tomorrow`,
      detail: "Delay fertilizer and pesticide application before rain to prevent nutrient runoff.",
    },
    {
      critical: isThunder,
      badge: isThunder ? "Critical" : "Info",
      title: isThunder ? "Thunderstorm Risk Detected" : "Pest & Disease Monitoring Active",
      detail: isThunder
        ? "Secure farming equipment. Avoid open fields during lightning risk period."
        : "Monitor brown planthopper and fungal disease after wet conditions.",
    },
  ];

  if (isHighWind) {
    alerts.push({
      critical: false,
      badge: "Warning",
      title: `High Wind Speed: ${wd.windspeed} km/h`,
      detail: "Strong winds may damage tall crops. Consider windbreaks for vulnerable plants.",
    });
  }

  const body = document.getElementById("alert-modal-body");
  body.innerHTML = `
    <ul class="alert-list">
      ${alerts.map((a) => `
        <li class="${a.critical ? "alert-critical" : ""}">
          <span class="alert-badge">${a.badge}</span>
          <div class="alert-text">
            <strong>${a.title}</strong>
            <span>${a.detail}</span>
          </div>
        </li>
      `).join("")}
    </ul>
    <p style="margin-top:14px;font-size:0.85rem;color:var(--muted);">Weather data from Open-Meteo · Updated live</p>
  `;

  const overlay = document.getElementById("alert-modal-overlay");
  overlay.classList.add("show");
  overlay.setAttribute("aria-hidden", "false");
}

function closeAlertModal() {
  const overlay = document.getElementById("alert-modal-overlay");
  overlay.classList.remove("show");
  overlay.setAttribute("aria-hidden", "true");
}

async function startApp() {
  const isAuthenticated = await checkAuth();
  if (!isAuthenticated) {
    return;
  }

  updateDate();
  await loadDataFromApi();

  // Fetch live weather for all districts in parallel
  showToast("Fetching live weather data...");
  await loadAllWeather();

  refreshDashboard();
  renderMarket();
  renderFertilizerCalculator();
  await generateRecommendations(false);
  
  if (currentUser && currentUser.role === 'Admin') {
    await loadAdminMarket();
  }
  
  bindEvents();

  const initialPage = window.location.hash.replace("#", "") || "dashboard";
  showPage(initialPage);
}

window.addEventListener("DOMContentLoaded", startApp);
