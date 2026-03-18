import { requestJson } from "../../shared/http.js";

const VIEW_RECORDED_EVENT = "shortvideo:video-view-recorded";
const VIEWER_SESSION_STORAGE_KEY = "shortvideo_viewer_session_id";
const READ_VISIBILITY_THRESHOLD = 0.6;
const READ_DWELL_MS = 600;

function getViewerSessionId() {
  const storageKey = VIEWER_SESSION_STORAGE_KEY;

  try {
    const existingValue = window.localStorage.getItem(storageKey);
    if (existingValue && existingValue.length >= 8) {
      return existingValue;
    }
  } catch (error) {
    console.warn("Unable to access localStorage", error);
  }

  const nextValue =
    typeof crypto !== "undefined" && typeof crypto.randomUUID === "function"
      ? crypto.randomUUID()
      : `${Date.now()}-${Math.random().toString(36).slice(2, 12)}`;

  try {
    window.localStorage.setItem(storageKey, nextValue);
  } catch (error) {
    console.warn("Unable to persist viewer session id", error);
  }

  return nextValue;
}

function normalizeUnreadCount(value) {
  const unreadCount = Number.parseInt(String(value || ""), 10);

  return Number.isInteger(unreadCount) && unreadCount > 0 ? unreadCount : 0;
}

function syncUnreadPillState(item, unreadCount) {
  if (!(item instanceof HTMLElement)) {
    return;
  }

  item.dataset.unreadCount = String(unreadCount);
  const pill = item.querySelector("[data-subscriptions-unread-badge='true']");
  if (!(pill instanceof HTMLElement)) {
    return;
  }

  pill.dataset.unreadCount = String(unreadCount);
  pill.textContent = unreadCount > 0 ? String(unreadCount) : "";
  pill.hidden = unreadCount === 0;
  pill.classList.toggle("hidden", unreadCount === 0);
  pill.classList.toggle("bg-rose-500", unreadCount > 0 && item.dataset.active === "true");
  pill.classList.toggle("text-white", unreadCount > 0 && item.dataset.active === "true");
  pill.classList.toggle("bg-rose-50", unreadCount > 0 && item.dataset.active !== "true");
  pill.classList.toggle("text-rose-600", unreadCount > 0 && item.dataset.active !== "true");
}

function updateActiveAccountUnreadCount(followList, delta = -1) {
  if (!(followList instanceof HTMLElement)) {
    return;
  }

  const activeItem = followList.querySelector("[data-subscriptions-account-item='true'][data-active='true']");
  if (!(activeItem instanceof HTMLElement)) {
    return;
  }

  const nextUnreadCount = Math.max(0, normalizeUnreadCount(activeItem.dataset.unreadCount) + delta);
  syncUnreadPillState(activeItem, nextUnreadCount);
}

function dispatchViewRecorded(detail) {
  window.dispatchEvent(
    new CustomEvent(VIEW_RECORDED_EVENT, {
      detail,
    })
  );
}

function initializeSubscriptionsReadState() {
  const followList = document.querySelector("[data-subscriptions-follow-list='true']");
  const cards = Array.from(document.querySelectorAll("[data-subscriptions-feed-card='true'][data-video-id]"));

  if (!(followList instanceof HTMLElement) || cards.length === 0) {
    return;
  }

  const seenVideoIds = new Set(
    cards
      .filter((card) => card instanceof HTMLElement && card.dataset.isNewForViewer !== "true")
      .map((card) => String(card.dataset.videoId || ""))
      .filter(Boolean)
  );
  const pendingVideoIds = new Set();
  const visibilityTimers = new Map();

  function markVideoAsRead(videoId) {
    if (!videoId || seenVideoIds.has(videoId)) {
      return;
    }

    seenVideoIds.add(videoId);
    pendingVideoIds.delete(videoId);
    const visibilityTimer = visibilityTimers.get(videoId);
    if (typeof visibilityTimer === "number") {
      window.clearTimeout(visibilityTimer);
      visibilityTimers.delete(videoId);
    }

    for (const card of cards) {
      if (!(card instanceof HTMLElement) || String(card.dataset.videoId || "") !== videoId) {
        continue;
      }

      observer.unobserve(card);
    }

    updateActiveAccountUnreadCount(followList, -1);
  }

  async function recordCardView(card) {
    if (!(card instanceof HTMLElement)) {
      return;
    }

    const videoId = String(card.dataset.videoId || "");
    if (!videoId || seenVideoIds.has(videoId) || pendingVideoIds.has(videoId)) {
      return;
    }

    pendingVideoIds.add(videoId);

    try {
      await requestJson(`/api/videos/${videoId}/views`, {
        method: "POST",
        keepalive: true,
        body: {
          sessionId: getViewerSessionId(),
        },
      });

      markVideoAsRead(videoId);
      dispatchViewRecorded({
        source: "subscriptions-feed",
        videoId,
      });
    } catch (error) {
      pendingVideoIds.delete(videoId);
      console.error(error);
    }
  }

  const observer = new IntersectionObserver(
    (entries) => {
      for (const entry of entries) {
        if (!(entry.target instanceof HTMLElement)) {
          continue;
        }

        const card = entry.target;
        const videoId = String(card.dataset.videoId || "");
        if (!videoId || seenVideoIds.has(videoId)) {
          observer.unobserve(card);
          const existingTimer = visibilityTimers.get(videoId);
          if (typeof existingTimer === "number") {
            window.clearTimeout(existingTimer);
            visibilityTimers.delete(videoId);
          }
          continue;
        }

        if (!entry.isIntersecting || entry.intersectionRatio < READ_VISIBILITY_THRESHOLD) {
          const existingTimer = visibilityTimers.get(videoId);
          if (typeof existingTimer === "number") {
            window.clearTimeout(existingTimer);
            visibilityTimers.delete(videoId);
          }
          continue;
        }

        if (visibilityTimers.has(videoId)) {
          continue;
        }

        const timerId = window.setTimeout(() => {
          visibilityTimers.delete(videoId);
          observer.unobserve(card);
          void recordCardView(card);
        }, READ_DWELL_MS);

        visibilityTimers.set(videoId, timerId);
      }
    },
    {
      threshold: [0, READ_VISIBILITY_THRESHOLD, 1],
    }
  );

  for (const card of cards) {
    if (!(card instanceof HTMLElement) || card.dataset.isNewForViewer !== "true") {
      continue;
    }

    observer.observe(card);
  }

  window.addEventListener(VIEW_RECORDED_EVENT, (event) => {
    const videoId = String(event?.detail?.videoId || "");
    if (!videoId) {
      return;
    }

    markVideoAsRead(videoId);
  });
}

initializeSubscriptionsReadState();
