(() => {
  document.querySelectorAll("main table").forEach((table) => {
    const head = table.querySelector("tr");
    if (!head) return;
    const headers = Array.from(head.querySelectorAll("th"));
    const rows = Array.from(table.querySelectorAll("tr")).slice(1);
    if (!rows.length) return;
    const tools = document.createElement("div");
    tools.className = "table-tools";
    const search = document.createElement("input");
    search.type = "search";
    search.className = "table-search";
    search.placeholder = "جست‌وجو در تمام ردیف‌های این جدول...";
    const count = document.createElement("span");
    count.className = "table-count";
    tools.append(search, count);
    table.parentNode.insertBefore(tools, table);
    let sortIndex = -1, direction = 1;
    const filter = () => {
      const q = search.value.trim().toLocaleLowerCase();
      let shown = 0;
      rows.forEach(row => {
        const visible = !q || row.innerText.toLocaleLowerCase().includes(q);
        row.hidden = !visible;
        if (visible) shown++;
      });
      count.textContent = shown.toLocaleString("fa-IR") + " از " + rows.length.toLocaleString("fa-IR") + " ردیف";
    };
    headers.forEach((th, index) => {
      th.classList.add("sortable");
      th.addEventListener("click", () => {
        direction = sortIndex === index ? -direction : 1;
        sortIndex = index;
        headers.forEach(h => h.removeAttribute("data-sort"));
        th.dataset.sort = direction === 1 ? "asc" : "desc";
        const value = row => (row.cells[index]?.dataset.sortValue ?? row.cells[index]?.innerText ?? "").trim();
        rows.sort((a,b) => {
          const av=value(a).replace(/[,،]/g,""), bv=value(b).replace(/[,،]/g,"");
          const an=Number(av), bn=Number(bv);
          const cmp=av!=="" && bv!=="" && Number.isFinite(an) && Number.isFinite(bn)
            ? an-bn : av.localeCompare(bv,undefined,{numeric:true,sensitivity:"base"});
          return cmp*direction;
        });
        rows.forEach(row => table.appendChild(row));
        filter();
      });
    });
    search.addEventListener("input", filter);
    filter();
  });
  // Keep Kerio data fresh only while an application page is open and visible.
  (() => {
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
      if (status) status.textContent = "در حال به‌روزرسانی...";
      try {
        const response = await fetch("refresh.php", {
          method: "GET",
          cache: "no-store",
          credentials: "same-origin",
          headers: { "Accept": "application/json" }
        });
        const result = await response.json();
        if (!response.ok || result.success !== true) {
          throw new Error(result.error || "HTTP " + response.status);
        }
        if (status) {
          status.textContent = result.status === "already_running"
            ? "به‌روزرسانی در صفحه دیگری در حال اجراست"
            : "آخرین به‌روزرسانی: " + new Date(result.finished_at).toLocaleTimeString("fa-IR");
        }
      } catch (error) {
        if (status) status.textContent = "خطا در به‌روزرسانی: " + error.message;
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
      if (document.visibilityState === "visible") {
        start();
        refresh();
      } else {
        stop();
      }
    });
    window.addEventListener("pagehide", stop);
    if (document.visibilityState === "visible") start();
  })();

})();