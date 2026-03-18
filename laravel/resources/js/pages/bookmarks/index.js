import { requestJson } from "../../shared/http.js";

function bookmarkPageElements() {
  return {
    backButton: document.querySelector("[data-bookmark-back='true']"),
    grid: document.querySelector("[data-bookmark-record-grid='true']"),
    pagination: document.querySelector("[data-bookmark-pagination='true']"),
    emptyState: document.querySelector("[data-bookmark-empty-state='true']"),
    feedback: document.querySelector("[data-bookmark-feedback='true']"),
    totalCountSlots: [...document.querySelectorAll("[data-bookmark-total-count='true']")]
  };
}

function setBookmarkTotalCount(totalCount) {
  const { grid, totalCountSlots } = bookmarkPageElements();
  const normalizedTotalCount = Math.max(0, totalCount);

  if (grid instanceof HTMLElement) {
    grid.dataset.bookmarkTotalCount = String(normalizedTotalCount);
  }

  for (const totalCountSlot of totalCountSlots) {
    totalCountSlot.textContent = String(normalizedTotalCount);
  }
}

function getBookmarkTotalCount() {
  const { grid } = bookmarkPageElements();
  if (!(grid instanceof HTMLElement)) {
    return 0;
  }

  const totalCount = Number.parseInt(grid.dataset.bookmarkTotalCount || "0", 10);
  return Number.isNaN(totalCount) ? 0 : totalCount;
}

function updateBookmarkPageState() {
  const { grid, emptyState } = bookmarkPageElements();
  if (!(grid instanceof HTMLElement)) {
    return;
  }

  const items = [...grid.querySelectorAll("[data-bookmark-record-item='true']")];
  grid.hidden = items.length === 0;

  if (emptyState) {
    emptyState.hidden = items.length !== 0;
  }

  updateBookmarkPaginationState();
}

function updateBookmarkPaginationState() {
  const { grid, pagination } = bookmarkPageElements();
  if (!(grid instanceof HTMLElement) || !(pagination instanceof HTMLElement)) {
    return;
  }

  const totalCount = getBookmarkTotalCount();
  const perPage = Number.parseInt(grid.dataset.bookmarkPerPage || "12", 10);
  pagination.hidden = totalCount <= perPage;
}

function showBookmarkFeedback(message) {
  const { feedback } = bookmarkPageElements();
  if (!feedback) {
    return;
  }

  feedback.textContent = message;
  feedback.hidden = false;
  feedback.classList.remove("hidden");
}

function clearBookmarkFeedback() {
  const { feedback } = bookmarkPageElements();
  if (!feedback) {
    return;
  }

  feedback.textContent = "";
  feedback.hidden = true;
  feedback.classList.add("hidden");
}

async function handleBookmarkRemoveClick(button) {
  const item = button.closest("[data-bookmark-record-item='true']");
  if (!(item instanceof HTMLElement)) {
    return;
  }

  clearBookmarkFeedback();

  const removeUrl = String(button.dataset.removeUrl || "").trim();
  const originalMarkup = button.innerHTML;

  button.disabled = true;
  button.innerHTML = '<span class="text-xs font-semibold">取消中...</span>';

  try {
    await requestJson(removeUrl, {
      method: "DELETE"
    });

    item.remove();
    setBookmarkTotalCount(getBookmarkTotalCount() - 1);

    const { grid } = bookmarkPageElements();
    const remainingItems = grid ? [...grid.querySelectorAll("[data-bookmark-record-item='true']")] : [];
    const previousPageUrl = grid instanceof HTMLElement ? String(grid.dataset.bookmarkPreviousPageUrl || "").trim() : "";

    if (remainingItems.length === 0 && previousPageUrl !== "") {
      window.location.assign(previousPageUrl);
      return;
    }

    updateBookmarkPageState();
  } catch (error) {
    const message = error instanceof Error ? error.message : "取消收藏失败，请稍后再试。";
    showBookmarkFeedback(message);
    button.disabled = false;
    button.innerHTML = originalMarkup;
  }
}

function bindBookmarkRemoveButtons() {
  for (const button of document.querySelectorAll("[data-bookmark-record-remove='true']")) {
    button.addEventListener("click", () => {
      void handleBookmarkRemoveClick(button);
    });
  }
}

function bindBookmarkBackButton() {
  const { backButton } = bookmarkPageElements();
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

updateBookmarkPageState();
bindBookmarkRemoveButtons();
bindBookmarkBackButton();
