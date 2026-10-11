// ==========================================
// 1. Inisialisasi State Awal & Variabel DOM
// ==========================================
const html = document.documentElement;
const sidebar = document.getElementById("sidebar");
const overlay = document.getElementById("sidebar-overlay");
const themeToggle = document.getElementById("theme-toggle");
const themeIcon = document.getElementById("theme-icon");
let isSidebarOpen = false;

let revenueChartInstance = null;
let trafficChartInstance = null;

// ==========================================
// 2. Kontrol Interaksi Dark Mode & Sidebar
// ==========================================
function toggleSidebar() {
  isSidebarOpen = !isSidebarOpen;
  if (sidebar && overlay) {
    if (isSidebarOpen) {
      sidebar.classList.remove("-translate-x-full");
      overlay.classList.remove("hidden", "opacity-0");
    } else {
      sidebar.classList.add("-translate-x-full");
      overlay.classList.add("opacity-0");
      setTimeout(() => overlay.classList.add("hidden"), 300);
    }
  }
}

// Sinkronisasi status tema saat pertama kali halaman dibuka
if (
  localStorage.theme === "dark" ||
  (!("theme" in localStorage) &&
    window.matchMedia("(prefers-color-scheme: dark)").matches)
) {
  html.classList.add("dark");
  if (themeIcon) themeIcon.setAttribute("icon", "solar:moon-linear");
} else {
  html.classList.remove("dark");
  if (themeIcon) themeIcon.setAttribute("icon", "solar:sun-2-linear");
}

// Trigger tombol ganti tema
if (themeToggle) {
  themeToggle.addEventListener("click", () => {
    html.classList.toggle("dark");
    const isDark = html.classList.contains("dark");

    localStorage.theme = isDark ? "dark" : "light";
    if (themeIcon) {
      themeIcon.setAttribute(
        "icon",
        isDark ? "solar:moon-linear" : "solar:sun-2-linear",
      );
    }

    // Proteksi: Hanya ubah warna grafik jika objek chart telah terdefinisi
    if (revenueChartInstance && trafficChartInstance) {
      updateCharts(isDark);
    }
  });
}

// ==========================================
// 3. Setup Konfigurasi Chart.js
// ==========================================
if (typeof Chart !== "undefined") {
  Chart.defaults.font.family = "'Inter', sans-serif";
  Chart.defaults.color = "#94a3b8";
}

function initCharts() {
  const revenueCanvas = document.getElementById("revenueChart");
  const trafficCanvas = document.getElementById("trafficChart");

  if (!revenueCanvas || !trafficCanvas || typeof Chart === "undefined") return;

  const isDark = html.classList.contains("dark");
  const gridColor = isDark ? "#1E2536" : "#f1f5f9";
  const textColor = isDark ? "#94a3b8" : "#64748b";

  // 3.a. Line Chart
  const ctxRev = revenueCanvas.getContext("2d");
  let gradient = ctxRev.createLinearGradient(0, 0, 0, 400);
  gradient.addColorStop(0, "rgba(1, 6, 148, 0.2)");
  gradient.addColorStop(1, "rgba(1, 6, 148, 0)");

  revenueChartInstance = new Chart(ctxRev, {
    type: "line",
    data: {
      labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug"],
      datasets: [
        {
          label: "Revenue",
          data: [12, 19, 15, 25, 22, 30, 28, 35], // <-- PASTIKAN DATA INI SUDAH DIISI
          borderColor: "#010694",
          backgroundColor: gradient,
          tension: 0.4,
          fill: true,
          pointBackgroundColor: "#ffffff",
          pointBorderColor: "#010694",
          pointBorderWidth: 2,
          pointRadius: 4,
          pointHoverRadius: 6,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        y: {
          beginAtZero: true,
          grid: { color: gridColor, borderDash: [5, 5] }, // <-- PASTIKAN AKHIRAN ARRAY INI BERSIH [5, 5]
          ticks: { color: textColor, font: { size: 11 } },
          border: { display: false },
        },
        x: {
          grid: { display: false },
          ticks: { color: textColor, font: { size: 11 } },
          border: { display: false },
        },
      },
    },
  });

  // 3.b. Doughnut Chart
  const ctxTraffic = trafficCanvas.getContext("2d");
  trafficChartInstance = new Chart(ctxTraffic, {
    type: "doughnut",
    data: {
      labels: ["Direct", "Social", "Referral"],
      datasets: [
        {
          data: [45, 32, 23], // <-- PASTIKAN DATA INI SUDAH DIISI
          backgroundColor: [
            "#010694",
            "#6d70fc",
            isDark ? "#334155" : "#e2e8f0",
          ],
          borderWidth: 0,
          hoverOffset: 4,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: "75%",
      plugins: { legend: { display: false } },
    },
  });
}

function updateCharts(isDark) {
  if (!revenueChartInstance || !trafficChartInstance) return;

  const gridColor = isDark ? "#1E2536" : "#f1f5f9";
  const textColor = isDark ? "#94a3b8" : "#64748b";

  revenueChartInstance.options.scales.y.grid.color = gridColor;
  revenueChartInstance.options.scales.y.ticks.color = textColor;
  revenueChartInstance.options.scales.x.ticks.color = textColor;
  revenueChartInstance.update();

  trafficChartInstance.data.datasets[0].backgroundColor = [
    "#010694",
    "#6d70fc",
    isDark ? "#334155" : "#e2e8f0",
  ];
  trafficChartInstance.update();
}

document.addEventListener("DOMContentLoaded", initCharts);
