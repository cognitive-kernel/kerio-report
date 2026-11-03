(() => {
  let running = false;
  let timer = null;
  let inFlight = false;
  const intervalMs = 10000;
  const header = document.querySelector("main header");
  if (!header) return;

  const status = document.createElement("span");
  status.className = "refresh-status";
  status.setAttribute("role", "status");
  status.textContent = "به‌روزرسانی خودکار فعال";

  const toggle = document.createElement("button");
  toggle.type = "button";
  toggle.className = "refresh-toggle";
  toggle.textContent = "توقف به‌روزرسانی";
  toggle.setAttribute("aria-pressed", "true");

  const controls = document.createElement("div");
  controls.className = "refresh-controls";
  controls.append(status, toggle);
  header.appendChild(controls);

  const formatMacAddress = (value) => {
    const raw = String(value || "").trim();
    const compact = raw.replace(/[^0-9a-f]/gi, "");
    return compact.length === 12 ? compact.toLowerCase() : raw.toLowerCase();
  };

  const renderLiveTop = (rows) => {
    const body = document.getElementById("live-consumers-body");
    if (!body || !Array.isArray(rows)) return;
    body.replaceChildren();
    if (rows.length === 0) {
      const tr = document.createElement("tr");
      const td = document.createElement("td");
      td.colSpan = 5;
      td.textContent = "هنوز نمونه ترافیکی ثبت نشده است.";
      tr.appendChild(td);
      body.appendChild(tr);
      return;
    }
    for (const item of rows) {
      const tr = document.createElement("tr");
      const values = [
        item.hostname || "نامشخص",
        item.ip_address || "",
        formatMacAddress(item.mac_address) || "نامشخص",
        Number(item.consumption_kb || 0).toLocaleString("fa-IR", { maximumFractionDigits: 2 }) + " KB",
        item.sampled_at || ""
      ];
      for (const value of values) {
        const td = document.createElement("td");
        td.textContent = String(value);
        tr.appendChild(td);
      }
      body.appendChild(tr);
    }
  };

  const refresh = async () => {
    if (!running || inFlight || document.visibilityState !== "visible") return;
    inFlight = true;
    status.textContent = "در حال دریافت اطلاعات از Kerio...";
    try {
      const response = await fetch("refresh.php", {
        method: "GET",
        cache: "no-store",
        credentials: "same-origin",
        headers: { "Accept": "application/json" }
      });
      const result = await response.json();
      if (!response.ok || result.success !== true) throw new Error(result.error || "HTTP " + response.status);
      renderLiveTop(result.live_top);
      status.textContent = "به‌روزرسانی شد: " + new Date(result.finished_at).toLocaleTimeString("fa-IR");
    } catch (error) {
      status.textContent = "خطا در به‌روزرسانی: " + error.message;
    } finally {
      inFlight = false;
    }
  };

  const start = () => {
    if (running || document.visibilityState !== "visible") return;
    running = true;
    toggle.textContent = "توقف به‌روزرسانی";
    toggle.setAttribute("aria-pressed", "true");
    refresh();
    timer = window.setInterval(refresh, intervalMs);
  };

  const stop = () => {
    running = false;
    if (timer !== null) window.clearInterval(timer);
    timer = null;
    toggle.textContent = "ادامه به‌روزرسانی";
    toggle.setAttribute("aria-pressed", "false");
    status.textContent = "به‌روزرسانی متوقف شد";
  };

  toggle.addEventListener("click", () => running ? stop() : start());
  document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === "visible") start();
    else {
      if (timer !== null) window.clearInterval(timer);
      timer = null;
    }
  });
  window.addEventListener("pagehide", () => {
    if (timer !== null) window.clearInterval(timer);
    timer = null;
  });
  start();
})();
