(() => {
  // Refresh Kerio data only while an application page is open and visible.
  let running = false;
  let timer = null;
  const status = document.createElement("span");
  status.className = "refresh-status";
  status.textContent = "به‌روزرسانی خودکار فعال";
  const header = document.querySelector("main header");
  if (header) header.appendChild(status);

  const refresh = async () => {
    if (running || document.visibilityState !== "visible") return;
    running = true;
    status.textContent = "در حال به‌روزرسانی...";
    try {
      const response = await fetch("refresh.php", {
        method: "GET",
        cache: "no-store",
        credentials: "same-origin",
        headers: { "Accept": "application/json" }
      });
      const result = await response.json();
      if (!response.ok || result.success !== true) throw new Error(result.error || "HTTP " + response.status);
      status.textContent = result.status === "already_running"
        ? "به‌روزرسانی در صفحه دیگری در حال اجراست"
        : "آخرین به‌روزرسانی: " + new Date(result.finished_at).toLocaleTimeString("fa-IR");
    } catch (error) {
      status.textContent = "خطا در به‌روزرسانی: " + error.message;
    } finally {
      running = false;
    }
  };

  const start = () => {
    if (timer !== null) return;
    refresh();
    timer = window.setInterval(refresh, 10000);
  };
  const stop = () => {
    if (timer !== null) window.clearInterval(timer);
    timer = null;
  };
  document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === "visible") { start(); refresh(); }
    else stop();
  });
  window.addEventListener("pagehide", stop);
  if (document.visibilityState === "visible") start();
})();
