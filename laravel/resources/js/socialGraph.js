import { requestJson } from "./app/http.js";

function syncAuthorFollowButton(button) {
  if (!(button instanceof HTMLButtonElement)) {
    return;
  }

  const isEnabled = button.dataset.enabled === "true";
  const isFollowing = button.dataset.following === "true";
  const isLoading = button.dataset.loading === "true";
  const label = isEnabled
    ? isLoading
      ? "处理中..."
      : isFollowing
        ? button.dataset.labelFollowing || "已关注"
        : button.dataset.labelFollow || "关注"
    : button.dataset.labelDisabled || "暂不可关注";

  button.disabled = !isEnabled || isLoading;
  button.textContent = label;
  button.setAttribute("aria-pressed", String(isFollowing));
  button.className = [
    button.dataset.baseClass ||
      "inline-flex h-11 shrink-0 items-center justify-center rounded-full px-5 text-sm font-semibold shadow-sm transition",
    isEnabled
      ? isFollowing
        ? "border border-gray-200 bg-gray-100 text-gray-700 hover:bg-gray-200"
        : "bg-rose-500 text-white hover:bg-rose-600"
      : "bg-gray-100 text-gray-400"
  ].join(" ");
}

function getAuthorButtons(authorUserId) {
  return Array.from(
    document.querySelectorAll(`[data-author-follow-button="true"][data-author-user-id="${authorUserId}"]`)
  ).filter((node) => node instanceof HTMLButtonElement);
}

function updateAllButtons(authorUserId, following) {
  for (const button of getAuthorButtons(authorUserId)) {
    button.dataset.following = following ? "true" : "false";
    button.dataset.loading = "false";
    syncAuthorFollowButton(button);
  }
}

async function handleAuthorFollowButtonClick(button) {
  if (!(button instanceof HTMLButtonElement)) {
    return;
  }

  if (button.dataset.enabled !== "true" || button.dataset.loading === "true") {
    return;
  }

  const authorUserId = Number.parseInt(button.dataset.authorUserId || "", 10);
  if (!Number.isInteger(authorUserId) || authorUserId <= 0) {
    return;
  }

  const nextFollowing = button.dataset.following !== "true";
  button.dataset.loading = "true";
  syncAuthorFollowButton(button);

  try {
    const payload = await requestJson(`/api/users/${authorUserId}/follow`, {
      method: nextFollowing ? "POST" : "DELETE",
      headers: {
        Accept: "application/json"
      }
    });
    const following = payload.following === true;
    updateAllButtons(authorUserId, following);
    window.dispatchEvent(
      new CustomEvent("shortvideo:author-follow-change", {
        detail: {
          authorUserId,
          following
        }
      })
    );

    if (button.dataset.reloadOnSuccess === "true") {
      window.location.reload();
    }
  } catch (error) {
    button.dataset.loading = "false";
    syncAuthorFollowButton(button);
    console.error(error);
  }
}

for (const button of document.querySelectorAll('[data-author-follow-button="true"]')) {
  if (!(button instanceof HTMLButtonElement)) {
    continue;
  }

  syncAuthorFollowButton(button);
  button.addEventListener("click", () => {
    void handleAuthorFollowButtonClick(button);
  });
}

window.addEventListener("shortvideo:author-follow-change", (event) => {
  const authorUserId = Number.parseInt(String(event?.detail?.authorUserId || ""), 10);
  if (!Number.isInteger(authorUserId) || authorUserId <= 0) {
    return;
  }

  updateAllButtons(authorUserId, event?.detail?.following === true);
});
