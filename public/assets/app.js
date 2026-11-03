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

  const refreshPageContent = async () => {
    const response = await fetch(window.location.href, {
      method: "GET",
      cache: "no-store",
      credentials: "same-origin",
      headers: { "Accept": "text/html", "X-Requested-With": "XMLHttpRequest" }
    });
    if (!response.ok) throw new Error("بارگذاری گزارش‌ها ناموفق بود (HTTP " + response.status + ")");
    const parsed = new DOMParser().parseFromString(await response.text(), "text/html");
    const currentMain = document.querySelector("main.main");
    const updatedMain = parsed.querySelector("main.main");
    if (!currentMain || !updatedMain) throw new Error("ساختار صفحه گزارش قابل به‌روزرسانی نیست");
    const currentHeader = currentMain.querySelector(":scope > header");
    const updatedHeader = updatedMain.querySelector(":scope > header");
    if (!currentHeader || !updatedHeader) throw new Error("سربرگ صفحه پیدا نشد");

    const preservedControls = currentHeader.querySelector(".refresh-controls");
    currentHeader.replaceWith(updatedHeader);
    if (preservedControls) updatedHeader.appendChild(preservedControls);
    Array.from(currentMain.children)
      .filter(node => node.tagName !== "HEADER")
      .forEach(node => node.remove());
    Array.from(updatedMain.children)
      .filter(node => node.tagName !== "HEADER")
      .forEach(node => currentMain.appendChild(document.importNode(node, true)));
  };

  const refresh = async () => {
    if (!running || inFlight || document.visibilityState !== "visible") return;
    inFlight = true;
    status.textContent = "در حال دریافت اطلاعات تازه از Kerio...";
    try {
      const response = await fetch("refresh.php", {
        method: "GET",
        cache: "no-store",
        credentials: "same-origin",
        headers: { "Accept": "application/json" }
      });
      const result = await response.json();
      if (!response.ok || result.success !== true) throw new Error(result.error || "HTTP " + response.status);
      if (result.busy) {
        status.textContent = "به‌روزرسانی دیگری در حال اجراست؛ نوبت بعدی تلاش می‌شود";
        return;
      }
      await refreshPageContent();
      status.textContent = "دریافت از Kerio و گزارش‌ها به‌روز شد: " +
        new Date(result.finished_at).toLocaleTimeString("fa-IR");
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
