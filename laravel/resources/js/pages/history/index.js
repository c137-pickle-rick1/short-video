import { requestJson } from "../../shared/http.js";

function historyPageElements() {
  return {
    backButton: document.querySelector("[data-history-back='true']"),
    grid: document.querySelector("[data-history-record-grid='true']"),
    pagination: document.querySelector("[data-history-pagination='true']"),
    clearAllButton: document.querySelector("[data-history-clear-all='true']"),
    emptyState: document.querySelector("[data-history-empty-state='true']"),
    feedback: document.querySelector("[data-history-feedback='true']"),
    countSlots: [...document.querySelectorAll("[data-history-visible-count='true']")],
    totalCountSlots: [...document.querySelectorAll("[data-history-total-count='true']")]
  };
}

function updateHistoryPageState() {
  const { grid, emptyState, countSlots } = historyPageElements();
  if (!grid) {
    return;
  }

  const items = [...grid.querySelectorAll("[data-history-record-item='true']")];
  const visibleCount = items.length;

  for (const countSlot of countSlots) {
    countSlot.textContent = String(visibleCount);
  }

  grid.hidden = visibleCount === 0;

  if (emptyState) {
    emptyState.hidden = visibleCount !== 0;
  }

  updateHistoryPaginationState();
  updateHistoryToolbarState();
}

function decrementHistoryTotalCount() {
  setHistoryTotalCount(getHistoryTotalCount() - 1);
}

function setHistoryTotalCount(totalCount) {
  const { grid, totalCountSlots } = historyPageElements();
  const normalizedTotalCount = Math.max(0, totalCount);

  if (grid instanceof HTMLElement) {
    grid.dataset.historyTotalCount = String(normalizedTotalCount);
  }

  for (const totalCountSlot of totalCountSlots) {
    totalCountSlot.textContent = String(normalizedTotalCount);
  }
}

function getHistoryTotalCount() {
  const { grid } = historyPageElements();
  if (!(grid instanceof HTMLElement)) {
    return 0;
  }

  const totalCount = Number.parseInt(grid.dataset.historyTotalCount || "0", 10);
  return Number.isNaN(totalCount) ? 0 : totalCount;
}

function updateHistoryPaginationState() {
  const { grid, pagination } = historyPageElements();
  if (!(grid instanceof HTMLElement) || !(pagination instanceof HTMLElement)) {
    return;
  }

  const totalCount = getHistoryTotalCount();
  const perPage = Number.parseInt(grid.dataset.historyPerPage || "12", 10);
  pagination.hidden = totalCount <= perPage;
}

function updateHistoryToolbarState() {
  const { clearAllButton } = historyPageElements();
  if (!(clearAllButton instanceof HTMLButtonElement)) {
    return;
  }

  const totalCount = getHistoryTotalCount();
  clearAllButton.disabled = totalCount <= 0;
}

function showHistoryFeedback(message) {
  const { feedback } = historyPageElements();
  if (!feedback) {
    return;
  }

  feedback.textContent = message;
  feedback.hidden = false;
  feedback.classList.remove("hidden");
}

function clearHistoryFeedback() {
  const { feedback } = historyPageElements();
  if (!feedback) {
    return;
  }

  feedback.textContent = "";
  feedback.hidden = true;
  feedback.classList.add("hidden");
}

async function handleDeleteClick(button) {
  const item = button.closest("[data-history-record-item='true']");
  if (!(item instanceof HTMLElement)) {
    return;
  }

  clearHistoryFeedback();

  const deleteUrl = String(button.dataset.deleteUrl || "").trim();
  const originalMarkup = button.innerHTML;

  button.disabled = true;
  button.innerHTML = '<span class="text-xs font-semibold">删除中...</span>';

  try {
    await requestJson(deleteUrl, {
      method: "DELETE"
    });

    item.remove();
    decrementHistoryTotalCount();

    const { grid } = historyPageElements();
    const remainingItems = grid ? [...grid.querySelectorAll("[data-history-record-item='true']")] : [];
    const previousPageUrl = grid instanceof HTMLElement ? String(grid.dataset.historyPreviousPageUrl || "").trim() : "";

    if (remainingItems.length === 0 && previousPageUrl !== "") {
      window.location.assign(previousPageUrl);
      return;
    }

    updateHistoryPageState();
  } catch (error) {
    const message = error instanceof Error ? error.message : "删除失败，请稍后再试。";
    showHistoryFeedback(message);
    button.disabled = false;
    button.innerHTML = originalMarkup;
  }
}

async function handleClearAllClick(button) {
  clearHistoryFeedback();

  const clearUrl = String(button.dataset.clearUrl || "").trim();
  const originalMarkup = button.innerHTML;

  if (clearUrl === "") {
    return;
  }

  button.disabled = true;
  button.innerHTML = '<span class="text-xs font-semibold">清空中...</span>';

  try {
    const response = await requestJson(clearUrl, {
      method: "DELETE"
    });

    const removedCount = Number.parseInt(String(response?.removedCount ?? "0"), 10);
    const { grid } = historyPageElements();

    if (grid instanceof HTMLElement) {
      for (const item of grid.querySelectorAll("[data-history-record-item='true']")) {
        item.remove();
      }
    }

    setHistoryTotalCount(0);
    updateHistoryPageState();
    button.innerHTML = originalMarkup;
    showHistoryFeedback(`已清空 ${Number.isNaN(removedCount) ? 0 : removedCount} 条观看记录。`);
  } catch (error) {
    const message = error instanceof Error ? error.message : "清空失败，请稍后再试。";
    showHistoryFeedback(message);
    button.disabled = false;
    button.innerHTML = originalMarkup;
  }
}

function bindHistoryDeleteButtons() {
  for (const button of document.querySelectorAll("[data-history-record-delete='true']")) {
    button.addEventListener("click", () => {
      void handleDeleteClick(button);
    });
  }
}

function bindHistoryClearButton() {
  const { clearAllButton } = historyPageElements();
  if (!(clearAllButton instanceof HTMLButtonElement)) {
    return;
  }

  clearAllButton.addEventListener("click", () => {
    if (!window.confirm("确认清空当前账号的全部观看记录吗？")) {
      return;
    }

    void handleClearAllClick(clearAllButton);
  });
}

function bindHistoryBackButton() {
  const { backButton } = historyPageElements();
  if (!(backButton instanceof HTMLButtonElement)) {
    return;
  }

  backButton.addEventListener("click", () => {
    const fallbackUrl = String(backButton.dataset.fallbackUrl || "/me").trim() || "/me";

    if (window.history.length > 1) {
      window.history.back();
      return;
    }

    window.location.assign(fallbackUrl);
  });
}

updateHistoryPageState();
bindHistoryDeleteButtons();
bindHistoryClearButton();
bindHistoryBackButton();
