import { renderFeedDetail, renderMobileCommentsDrawer, renderMobileFeedSlide } from "../render.js";
import { installDetailVideoPlayer } from "../videoPreview.js";
import { requestJson } from "./http.js";
import { escapeHtml, getAuthorInitial, getDisplayText, renderAvatarMarkup } from "../shared/feed/render/templateUtils.js";

const MOBILE_BREAKPOINT_QUERY = "(max-width: 1023px)";
const COMMENT_DRAWER_CLOSE_DELAY_MS = 220;
const scheduleTask =
  typeof requestAnimationFrame === "function" ? requestAnimationFrame : (callback) => setTimeout(callback, 0);

function createContent(markup) {
  const template = document.createElement("template");
  template.innerHTML = markup.trim();
  return template.content;
}

function pauseVideoElements(root) {
  for (const video of root?.querySelectorAll?.("video") || []) {
    if (!(video instanceof HTMLVideoElement)) {
      continue;
    }

    video.pause();
  }
}

function formatReactionCount(value) {
  const numericValue = Math.max(0, Number(value) || 0);
  if (numericValue < 1000) {
    return String(numericValue);
  }

  const compactValue = Math.round((numericValue / 1000) * 10) / 10;
  return `${compactValue.toFixed(compactValue >= 10 ? 0 : 1)}k`;
}

function formatPlaybackTime(value) {
  const totalSeconds = Math.max(0, Math.floor(Number(value) || 0));
  const hours = Math.floor(totalSeconds / 3600);
  const minutes = Math.floor((totalSeconds % 3600) / 60);
  const seconds = totalSeconds % 60;

  if (hours > 0) {
    return `${String(hours).padStart(2, "0")}:${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}`;
  }

  return `${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}`;
}

function getReactionConfig(type, active, enabled, tone = "light") {
  if (!enabled) {
    return {
      buttonClass:
        tone === "dark"
          ? "border-white/10 bg-white/10 text-white/35 hover:border-white/10 hover:bg-white/10 hover:text-white/35"
          : "border-gray-200 bg-gray-100 text-gray-400 hover:border-gray-200 hover:bg-gray-100 hover:text-gray-400",
      iconClass: type === "like" ? "ph ph-heart" : "ph ph-bookmark-simple"
    };
  }

  if (tone === "dark") {
    if (type === "like") {
      return {
        buttonClass: active
          ? "border-rose-300/60 bg-rose-500/20 text-rose-100"
          : "border-white/15 bg-black/25 text-white/85 hover:border-white/30 hover:bg-black/40 hover:text-white",
        iconClass: active ? "ph-fill ph-heart" : "ph ph-heart"
      };
    }

    return {
      buttonClass: active
        ? "border-amber-300/60 bg-amber-500/20 text-amber-100"
        : "border-white/15 bg-black/25 text-white/85 hover:border-white/30 hover:bg-black/40 hover:text-white",
      iconClass: active ? "ph-fill ph-bookmark-simple" : "ph ph-bookmark-simple"
    };
  }

  if (type === "like") {
    return {
      buttonClass: active
        ? "border-rose-200 bg-rose-50 text-rose-600"
        : "border-gray-200 bg-white text-gray-500 hover:border-gray-300 hover:bg-gray-100 hover:text-gray-700",
      iconClass: active ? "ph-fill ph-heart" : "ph ph-heart"
    };
  }

  return {
    buttonClass: active
      ? "border-amber-200 bg-amber-50 text-amber-700"
      : "border-gray-200 bg-white text-gray-500 hover:border-gray-300 hover:bg-gray-100 hover:text-gray-700",
    iconClass: active ? "ph-fill ph-bookmark-simple" : "ph ph-bookmark-simple"
  };
}

function formatCommentDate(value) {
  if (!value) {
    return "刚刚";
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return "刚刚";
  }

  return new Intl.DateTimeFormat("zh-CN", {
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit"
  })
    .format(date)
    .replaceAll("/", "-");
}

function renderCommentMarkup(comment) {
  const author = comment?.author || {};
  const authorName = String(author.name || author.username || "匿名用户");
  const authorUsername = String(author.username || "");
  const body = String(comment?.body || "");

  return `
    <article class="grid gap-3">
      <div class="flex items-start gap-3">
        ${renderAvatarMarkup({
          imageUrl: author.avatarUrl || null,
          label: authorName,
          initial: getAuthorInitial(authorName),
          sizeClass: "h-10 w-10",
          fallbackClass: "bg-gray-100 text-gray-700"
        })}
        <div class="min-w-0 flex-1">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="truncate text-sm font-semibold text-gray-900">${escapeHtml(authorName)}</p>
              <p class="mt-1 truncate text-xs text-gray-400">${escapeHtml(authorUsername ? `@${authorUsername}` : "")}</p>
              <p class="mt-2 text-sm leading-6 text-gray-700">${escapeHtml(body)}</p>
            </div>
          </div>
          <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-gray-400">
            <span>${escapeHtml(formatCommentDate(comment?.createdAt))}</span>
          </div>
        </div>
      </div>
    </article>
  `;
}

function renderEmptyCommentsMarkup(message) {
  return `
    <article
      class="rounded-3xl border border-dashed border-gray-200 bg-gray-50 px-4 py-5 text-sm leading-6 text-gray-500"
      data-empty-comments="true"
    >
      ${escapeHtml(message)}
    </article>
  `;
}

function getViewerSessionId() {
  const storageKey = "shortvideo_viewer_session_id";
  try {
    const existing = window.localStorage.getItem(storageKey);
    if (existing) {
      return existing;
    }

    const generated =
      window.crypto?.randomUUID?.() ||
      `sv_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 12)}`;
    window.localStorage.setItem(storageKey, generated);
    return generated;
  } catch {
    return `sv_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 12)}`;
  }
}

function matchesMobileViewport() {
  if (typeof window.matchMedia === "function") {
    return window.matchMedia(MOBILE_BREAKPOINT_QUERY).matches;
  }

  return window.innerWidth < 1024;
}

function clampIndex(value, max) {
  if (max <= 0) {
    return 0;
  }

  return Math.max(0, Math.min(value, max - 1));
}

function getDetailSearchQuery(tweet) {
  const displayText = String(getDisplayText(tweet) || "")
    .replace(/\s+/gu, " ")
    .trim();
  const hashtagMatch = displayText.match(/#([^\s#]+)/u);
  if (hashtagMatch?.[1]) {
    return hashtagMatch[1];
  }

  if (displayText && displayText !== "未填写内容文案") {
    return displayText.slice(0, 18);
  }

  return String(tweet?.authorName || tweet?.authorHandle || "短视频");
}

function getDetailShareUrl(tweet) {
  const candidate = String(tweet?.tweetUrl || "").trim();
  return candidate || window.location.href;
}

function openAuthModal(panel = "login") {
  window.dispatchEvent(
    new CustomEvent("shortvideo:auth-open", {
      detail: {
        panel
      }
    })
  );
}

function buildMobileViewerMarkup() {
  return `
    <div
      class="relative flex h-[100dvh] w-full flex-col bg-black"
      data-detail-layout-node="true"
      data-mobile-detail-root="true"
    >
      <div
        class="detail-mobile-scroller relative h-full w-full overflow-y-auto overscroll-contain snap-y snap-mandatory"
        data-mobile-detail-scroller="true"
      ></div>
    </div>
  `;
}

export function createDetailModalController({
  detailModal,
  detailModalPanel,
  getOrderedTweets,
  onNeedMore,
  onOpen,
  onClose
} = {}) {
  let lastActiveElement = null;
  let lastDetailOpenInteraction = null;
  let previousBodyOverflow = "";
  let previousBodyOverscrollBehavior = "";
  let desktopPlayerController = null;
  let mobilePlayerController = null;
  let mobileProgressController = null;
  let currentMode = null;
  let currentTweetId = "";
  let currentMobileIndex = -1;
  let commentsLoadToken = 0;
  let mobileScrollContainer = null;
  let mobileCommentsLayer = null;
  let mobileCommentsDrawer = null;
  let mobileCommentsHideTimer = 0;
  let activeCommentsTweetId = "";
  let mobileScrollSyncScheduled = false;

  function getOrderedFeedItems() {
    const items = typeof getOrderedTweets === "function" ? getOrderedTweets() : [];
    return Array.isArray(items) ? items.filter(Boolean) : [];
  }

  function getTweetById(tweetId) {
    const normalizedTweetId = String(tweetId || "");
    return getOrderedFeedItems().find((tweet) => String(tweet?.tweetId || "") === normalizedTweetId) || null;
  }

  function getTweetIndex(tweetId) {
    const normalizedTweetId = String(tweetId || "");
    return getOrderedFeedItems().findIndex((tweet) => String(tweet?.tweetId || "") === normalizedTweetId);
  }

  function getCurrentTweet() {
    return getTweetById(currentTweetId);
  }

  function isOpen() {
    return !!detailModal && detailModal.hidden === false;
  }

  function isMobileMode() {
    return isOpen() && currentMode === "mobile";
  }

  function isDesktopMode() {
    return isOpen() && currentMode === "desktop";
  }

  function clearDetailModalNodes() {
    if (!detailModalPanel) {
      return;
    }

    for (const node of detailModalPanel.querySelectorAll("[data-detail-layout-node='true']")) {
      node.remove();
    }
  }

  function destroyDesktopPlayer() {
    desktopPlayerController?.destroy?.();
    desktopPlayerController = null;
  }

  function destroyMobilePlayer() {
    mobilePlayerController?.destroy?.();
    mobilePlayerController = null;
  }

  function destroyMobileProgress() {
    mobileProgressController?.destroy?.();
    mobileProgressController = null;
  }

  function clearCommentDrawerHideTimer() {
    if (mobileCommentsHideTimer) {
      window.clearTimeout(mobileCommentsHideTimer);
      mobileCommentsHideTimer = 0;
    }
  }

  function resetMobileReferences() {
    clearCommentDrawerHideTimer();
    mobileScrollContainer = null;
    mobileCommentsLayer = null;
    mobileCommentsDrawer = null;
    activeCommentsTweetId = "";
    currentMobileIndex = -1;
    mobileScrollSyncScheduled = false;
  }

  function resetPanelState() {
    destroyDesktopPlayer();
    destroyMobilePlayer();
    destroyMobileProgress();
    commentsLoadToken += 1;
    resetMobileReferences();
    pauseVideoElements(detailModalPanel);
    clearDetailModalNodes();
  }

  function applyPanelAccessibility(mode) {
    if (!detailModalPanel) {
      return;
    }

    if (mode === "mobile") {
      detailModalPanel.removeAttribute("aria-labelledby");
      detailModalPanel.setAttribute("aria-label", "移动端短视频详情");
      return;
    }

    detailModalPanel.removeAttribute("aria-label");
    detailModalPanel.setAttribute("aria-labelledby", "detail-modal-title");
  }

  function setBodyLocked(locked) {
    if (locked) {
      previousBodyOverflow = document.body.style.overflow;
      previousBodyOverscrollBehavior = document.body.style.overscrollBehavior;
      document.body.style.overflow = "hidden";
      document.body.style.overscrollBehavior = "none";
      return;
    }

    document.body.style.overflow = previousBodyOverflow;
    document.body.style.overscrollBehavior = previousBodyOverscrollBehavior;
  }

  function updateTweetEngagement(tweet, engagement) {
    tweet.engagement = {
      likeCount: Number(engagement?.likeCount || 0),
      bookmarkCount: Number(engagement?.bookmarkCount || 0),
      commentCount: Number(engagement?.commentCount || 0),
      viewCount: Number(engagement?.viewCount || 0),
      likedByViewer: engagement?.likedByViewer === true,
      bookmarkedByViewer: engagement?.bookmarkedByViewer === true
    };
  }

  function updateAuthorFollowState(authorUserId, following) {
    for (const tweet of getOrderedFeedItems()) {
      if (Number(tweet?.authorUserId || 0) === Number(authorUserId)) {
        tweet.authorFollowedByViewer = following;
      }
    }
  }

  async function recordQualifiedView(tweet) {
    const videoId = Number.parseInt(String(tweet?.videoId || ""), 10);
    if (!Number.isInteger(videoId) || videoId <= 0) {
      return;
    }

    try {
      const payload = await requestJson(`/api/videos/${videoId}/views`, {
        method: "POST",
        keepalive: true,
        body: {
          sessionId: getViewerSessionId()
        }
      });
      updateTweetEngagement(tweet, payload?.engagement || {});
      syncAllReactionButtons(tweet);
      syncCommentMeta(tweet);
    } catch (error) {
      console.error(error);
    }
  }

  function getCurrentSlideNodes() {
    if (!detailModalPanel) {
      return [];
    }

    return Array.from(detailModalPanel.querySelectorAll("[data-mobile-detail-slide='true']"));
  }

  function getCurrentSlideNode(tweetId = currentTweetId) {
    return (
      getCurrentSlideNodes().find((node) => String(node?.dataset?.tweetId || "") === String(tweetId || "")) || null
    );
  }

  function isNodeForTweet(node, tweetId) {
    if (!(node instanceof HTMLElement)) {
      return false;
    }

    const slide = node.closest("[data-mobile-detail-slide='true']");
    if (!(slide instanceof HTMLElement)) {
      return true;
    }

    return String(slide.dataset.tweetId || "") === String(tweetId || "");
  }

  function collectTweetScopedNodes(selector, tweetId) {
    if (!detailModalPanel) {
      return [];
    }

    return Array.from(detailModalPanel.querySelectorAll(selector)).filter((node) => isNodeForTweet(node, tweetId));
  }

  function setupDesktopPlayer(tweet) {
    if (!detailModalPanel) {
      return;
    }

    destroyDesktopPlayer();
    const video = detailModalPanel.querySelector("[data-detail-player]");
    if (!(video instanceof HTMLVideoElement)) {
      return;
    }

    desktopPlayerController = installDetailVideoPlayer(detailModalPanel, video, {
      onQualifiedView: () => {
        void recordQualifiedView(tweet);
      }
    });
  }

  function setupMobilePlayer(tweet) {
    destroyMobilePlayer();

    const slide = getCurrentSlideNode(tweet?.tweetId);
    if (!(slide instanceof HTMLElement)) {
      return;
    }

    const video = slide.querySelector("[data-detail-player]");
    if (!(video instanceof HTMLVideoElement)) {
      return;
    }

    mobilePlayerController = installDetailVideoPlayer(slide, video, {
      onQualifiedView: () => {
        void recordQualifiedView(tweet);
      }
    });
  }

  function setupMobileProgress(tweet) {
    destroyMobileProgress();

    const slide = getCurrentSlideNode(tweet?.tweetId);
    if (!(slide instanceof HTMLElement)) {
      return;
    }

    const video = slide.querySelector("[data-detail-player]");
    const range = slide.querySelector("[data-detail-progress-range='true']");
    const timeLabel = slide.querySelector("[data-detail-progress-time='true']");
    const shell = slide.querySelector("[data-detail-progress-shell='true']");

    if (
      !(video instanceof HTMLVideoElement) ||
      !(range instanceof HTMLInputElement) ||
      !(timeLabel instanceof HTMLElement) ||
      !(shell instanceof HTMLElement)
    ) {
      return;
    }

    let isScrubbing = false;
    let wasPlayingBeforeScrub = false;
    let scrubTime = 0;

    const getDuration = () => {
      const duration = Number(video.duration);
      return Number.isFinite(duration) && duration > 0 ? duration : 0;
    };

    const setRangeValue = (currentTime) => {
      const duration = getDuration();
      const progress = duration > 0 ? Math.min(1000, Math.max(0, Math.round((currentTime / duration) * 1000))) : 0;
      range.value = String(progress);
      range.style.setProperty("--detail-progress-value", `${duration > 0 ? (progress / 10).toFixed(2) : "0"}%`);
    };

    const setTimeLabel = (currentTime) => {
      timeLabel.textContent = `${formatPlaybackTime(currentTime)} / ${formatPlaybackTime(getDuration())}`;
    };

    const syncUi = () => {
      const duration = getDuration();
      const currentTime = isScrubbing ? scrubTime : Number(video.currentTime) || 0;
      range.disabled = duration <= 0;
      shell.classList.toggle("opacity-60", duration <= 0);
      setRangeValue(currentTime);
      setTimeLabel(currentTime);
    };

    const beginScrub = () => {
      if (isScrubbing) {
        return;
      }

      isScrubbing = true;
      wasPlayingBeforeScrub = !video.paused && !video.ended;
      scrubTime = Number(video.currentTime) || 0;
      timeLabel.classList.remove("hidden");
      video.pause();
      syncUi();
    };

    const updateScrubTime = () => {
      const duration = getDuration();
      scrubTime = duration > 0 ? (Number(range.value) / 1000) * duration : 0;
      setRangeValue(scrubTime);
      setTimeLabel(scrubTime);
    };

    const commitScrub = () => {
      if (getDuration() > 0) {
        video.currentTime = scrubTime;
      }
    };

    const endScrub = () => {
      if (!isScrubbing) {
        return;
      }

      commitScrub();
      isScrubbing = false;
      timeLabel.classList.add("hidden");
      syncUi();

      if (wasPlayingBeforeScrub) {
        video.play().catch(() => {});
      }
    };

    const handleInput = () => {
      if (!isScrubbing) {
        beginScrub();
      }

      updateScrubTime();
    };

    const handlePointerUp = () => {
      endScrub();
    };

    const handleLoadedMetadata = () => {
      syncUi();
    };

    const handleTimeUpdate = () => {
      if (!isScrubbing) {
        syncUi();
      }
    };

    range.addEventListener("pointerdown", beginScrub);
    range.addEventListener("input", handleInput);
    range.addEventListener("blur", endScrub);
    window.addEventListener("pointerup", handlePointerUp);
    window.addEventListener("pointercancel", handlePointerUp);
    video.addEventListener("loadedmetadata", handleLoadedMetadata);
    video.addEventListener("durationchange", handleLoadedMetadata);
    video.addEventListener("timeupdate", handleTimeUpdate);
    video.addEventListener("ended", handleTimeUpdate);
    video.addEventListener("emptied", handleLoadedMetadata);
    syncUi();

    mobileProgressController = {
      destroy() {
        window.removeEventListener("pointerup", handlePointerUp);
        window.removeEventListener("pointercancel", handlePointerUp);
        range.removeEventListener("pointerdown", beginScrub);
        range.removeEventListener("input", handleInput);
        range.removeEventListener("blur", endScrub);
        video.removeEventListener("loadedmetadata", handleLoadedMetadata);
        video.removeEventListener("durationchange", handleLoadedMetadata);
        video.removeEventListener("timeupdate", handleTimeUpdate);
        video.removeEventListener("ended", handleTimeUpdate);
        video.removeEventListener("emptied", handleLoadedMetadata);
      }
    };
  }

  function syncReactionButton(button, tweet) {
    const reactionType = button.dataset.detailReactionButton === "like" ? "like" : "bookmark";
    const engagement = tweet?.engagement || {};
    const isActive =
      reactionType === "like" ? engagement.likedByViewer === true : engagement.bookmarkedByViewer === true;
    const count = reactionType === "like" ? engagement.likeCount : engagement.bookmarkCount;
    const isEnabled = true;
    const tone = button.dataset.tone === "dark" ? "dark" : "light";
    const layout = button.dataset.detailReactionLayout === "mobile-stat" ? "mobile-stat" : "pill";
    const { buttonClass, iconClass } = getReactionConfig(reactionType, isActive, isEnabled, tone);
    const icon = button.querySelector("[data-detail-reaction-icon]");
    const countNode = button.querySelector("[data-detail-reaction-count]");

    button.dataset.active = isActive ? "true" : "false";
    button.dataset.count = String(Math.max(0, Number(count) || 0));
    button.dataset.enabled = isEnabled ? "true" : "false";

    if (layout === "mobile-stat") {
      const accentClass = !isEnabled
        ? "text-white/35"
        : !isActive
          ? "text-white"
          : reactionType === "like"
            ? "text-rose-300"
            : "text-amber-200";

      button.className = `inline-flex min-w-[3.75rem] items-center justify-center gap-2 text-white transition ${isEnabled ? "opacity-100" : "opacity-40"}`;
      button.setAttribute("aria-pressed", String(isActive));
      button.setAttribute("aria-disabled", String(!isEnabled));

      if (icon instanceof HTMLElement) {
        icon.className = `${iconClass} ${accentClass} text-[1.85rem] leading-none`;
      }

      if (countNode instanceof HTMLElement) {
        countNode.className = `text-[0.95rem] font-semibold leading-none tabular-nums ${isEnabled ? "text-white/95" : "text-white/35"}`;
        countNode.textContent = formatReactionCount(count);
      }
      return;
    }

    button.className = `inline-flex h-11 shrink-0 items-center gap-2.5 rounded-full border px-4 text-sm font-semibold transition ${buttonClass}`;
    button.setAttribute("aria-pressed", String(isActive));
    button.setAttribute("aria-disabled", String(!isEnabled));

    if (icon instanceof HTMLElement) {
      icon.className = `${iconClass} text-[1.05rem] leading-none`;
    }

    if (countNode instanceof HTMLElement) {
      countNode.textContent = formatReactionCount(count);
    }
  }

  function syncAllReactionButtons(tweet) {
    for (const button of collectTweetScopedNodes("[data-detail-reaction-button]", tweet?.tweetId)) {
      if (!(button instanceof HTMLButtonElement)) {
        continue;
      }

      syncReactionButton(button, tweet);
    }
  }

  function syncCommentMeta(tweet) {
    const commentCount = Number(tweet?.engagement?.commentCount || 0);

    for (const badge of collectTweetScopedNodes("[data-detail-comment-count-badge='true']", tweet?.tweetId)) {
      if (badge instanceof HTMLElement) {
        badge.textContent = `${commentCount} 条评论`;
      }
    }

    for (const status of collectTweetScopedNodes("[data-detail-comment-count='true']", tweet?.tweetId)) {
      if (status instanceof HTMLElement) {
        status.textContent = `${commentCount} 条评论`;
      }
    }

    for (const buttonCount of collectTweetScopedNodes("[data-detail-comment-button-count]", tweet?.tweetId)) {
      if (buttonCount instanceof HTMLElement) {
        buttonCount.textContent = formatReactionCount(commentCount);
      }
    }

    for (const status of collectTweetScopedNodes("[data-detail-comments-status='true']", tweet?.tweetId)) {
      if (status instanceof HTMLElement && status.dataset.loading !== "true") {
        status.textContent = commentCount > 0 ? `${commentCount} 条评论` : "暂无评论";
      }
    }
  }

  function getCommentsRoot() {
    if (isMobileMode()) {
      return mobileCommentsLayer;
    }

    return detailModalPanel;
  }

  function renderComments(comments, tweetId = activeCommentsTweetId || currentTweetId) {
    const root = getCommentsRoot();
    const list = root?.querySelector?.("[data-detail-comments-list='true']");
    if (!(list instanceof HTMLElement)) {
      return;
    }

    if (isMobileMode() && activeCommentsTweetId && String(activeCommentsTweetId) !== String(tweetId || "")) {
      return;
    }

    list.innerHTML =
      comments.length > 0
        ? comments.map((comment) => renderCommentMarkup(comment)).join("")
        : renderEmptyCommentsMarkup("还没有评论，抢先说点什么。");
  }

  async function loadComments(tweet) {
    const root = getCommentsRoot();
    if (!root) {
      return;
    }

    const videoId = Number.parseInt(String(tweet?.videoId || ""), 10);
    if (!Number.isInteger(videoId) || videoId <= 0) {
      renderComments([], tweet?.tweetId);
      return;
    }

    const token = ++commentsLoadToken;
    const status = root.querySelector("[data-detail-comments-status='true']");
    if (status instanceof HTMLElement) {
      status.dataset.loading = "true";
      status.textContent = "加载中";
    }

    try {
      const payload = await requestJson(`/api/videos/${videoId}/comments`);
      if (token !== commentsLoadToken) {
        return;
      }

      const items = Array.isArray(payload?.items) ? payload.items : [];
      renderComments(items, tweet?.tweetId);

      if (status instanceof HTMLElement) {
        status.dataset.loading = "false";
        status.textContent = items.length > 0 ? `${items.length} 条评论` : "暂无评论";
      }
    } catch (error) {
      if (token !== commentsLoadToken) {
        return;
      }

      renderComments([], tweet?.tweetId);
      if (status instanceof HTMLElement) {
        status.dataset.loading = "false";
        status.textContent = "评论加载失败";
      }
      console.error(error);
    }
  }

  function syncAuthorFollowButton(button) {
    if (!(button instanceof HTMLButtonElement)) {
      return;
    }

    const isEnabled = button.dataset.enabled === "true";
    const authRequired = button.dataset.authRequired === "true";
    const isFollowing = button.dataset.following === "true";
    const isLoading = button.dataset.loading === "true";
    const tone = button.dataset.tone === "dark" ? "dark" : "light";
    const label = isEnabled
      ? isLoading
        ? "处理中..."
        : isFollowing
          ? button.dataset.labelFollowing || "已关注"
          : button.dataset.labelFollow || "关注"
      : button.dataset.labelDisabled || "暂不可关注";

    const buttonClass =
      tone === "dark"
        ? isEnabled
          ? isFollowing
            ? "border border-white/20 bg-white/10 text-white hover:bg-white/20"
            : "bg-rose-500 text-white hover:bg-rose-600"
          : "border border-white/10 bg-white/10 text-white/40"
        : isEnabled
          ? isFollowing
            ? "border border-gray-200 bg-gray-100 text-gray-700 hover:bg-gray-200"
            : "bg-rose-500 text-white hover:bg-rose-600"
          : "bg-gray-100 text-gray-400";
    const sizeClass = button.dataset.size === "compact" ? "h-10 px-4 text-[0.8125rem]" : "h-11 px-5 text-sm";

    button.disabled = (!isEnabled && !authRequired) || isLoading;
    button.textContent = label;
    button.setAttribute("aria-pressed", String(isFollowing));
    button.className = [
      "inline-flex shrink-0 items-center justify-center rounded-full font-semibold shadow-sm transition",
      sizeClass,
      buttonClass
    ].join(" ");
  }

  function syncAllAuthorFollowButtons(authorUserId, following) {
    if (!detailModalPanel) {
      return;
    }

    for (const button of detailModalPanel.querySelectorAll("[data-detail-author-follow-button='true']")) {
      if (!(button instanceof HTMLButtonElement)) {
        continue;
      }

      if (Number(button.dataset.authorUserId || 0) !== Number(authorUserId)) {
        continue;
      }

      button.dataset.following = following ? "true" : "false";
      button.dataset.loading = "false";
      syncAuthorFollowButton(button);
    }
  }

  function createOrReplaceMobileCommentsLayer(tweet) {
    if (!detailModalPanel) {
      return;
    }

    mobileCommentsLayer?.remove();
    mobileCommentsDrawer = null;
    activeCommentsTweetId = String(tweet?.tweetId || "");

    const root = detailModalPanel.querySelector("[data-mobile-detail-root='true']");
    if (!(root instanceof HTMLElement)) {
      return;
    }

    root.append(createContent(renderMobileCommentsDrawer(tweet)));
    mobileCommentsLayer = root.querySelector("[data-mobile-comments-layer='true']");
    mobileCommentsDrawer = root.querySelector("[data-mobile-comments-drawer='true']");
  }

  function closeCommentsDrawer({ immediate = false } = {}) {
    if (!(mobileCommentsLayer instanceof HTMLElement) || !(mobileCommentsDrawer instanceof HTMLElement)) {
      return;
    }

    clearCommentDrawerHideTimer();
    commentsLoadToken += 1;
    mobileCommentsLayer.setAttribute("aria-hidden", "true");

    const backdrop = mobileCommentsLayer.querySelector("[data-mobile-comments-backdrop='true']");
    backdrop?.classList.remove("opacity-100");
    backdrop?.classList.add("opacity-0");
    mobileCommentsDrawer.classList.remove("translate-y-0");
    mobileCommentsDrawer.classList.add("translate-y-full");

    const hideLayer = () => {
      mobileCommentsLayer.hidden = true;
      mobileCommentsLayer.classList.add("hidden");
    };

    if (immediate) {
      hideLayer();
      return;
    }

    mobileCommentsHideTimer = window.setTimeout(() => {
      mobileCommentsHideTimer = 0;
      hideLayer();
    }, COMMENT_DRAWER_CLOSE_DELAY_MS);
  }

  function openCommentsDrawer(tweet) {
    if (!isMobileMode()) {
      return;
    }

    createOrReplaceMobileCommentsLayer(tweet);
    if (!(mobileCommentsLayer instanceof HTMLElement) || !(mobileCommentsDrawer instanceof HTMLElement)) {
      return;
    }

    clearCommentDrawerHideTimer();
    mobileCommentsLayer.hidden = false;
    mobileCommentsLayer.classList.remove("hidden");
    mobileCommentsLayer.setAttribute("aria-hidden", "false");
    const backdrop = mobileCommentsLayer.querySelector("[data-mobile-comments-backdrop='true']");
    backdrop?.classList.remove("opacity-0");
    backdrop?.classList.add("opacity-100");
    mobileCommentsDrawer.classList.remove("translate-y-full");
    mobileCommentsDrawer.classList.add("translate-y-0");
    syncCommentMeta(tweet);
    void loadComments(tweet);
  }

  function appendMissingMobileSlides() {
    if (!(mobileScrollContainer instanceof HTMLElement)) {
      return;
    }

    const existingTweetIds = new Set(
      Array.from(mobileScrollContainer.querySelectorAll("[data-mobile-detail-slide='true']")).map((node) =>
        String(node?.dataset?.tweetId || "")
      )
    );

    for (const tweet of getOrderedFeedItems()) {
      const tweetId = String(tweet?.tweetId || "");
      if (!tweetId || existingTweetIds.has(tweetId)) {
        continue;
      }

      mobileScrollContainer.append(createContent(renderMobileFeedSlide(tweet)));
      existingTweetIds.add(tweetId);
    }

    getCurrentSlideNodes().forEach((slide, index) => {
      slide.dataset.index = String(index);
    });
  }

  function updateMobilePositionIndicator() {
    const indicator = detailModalPanel?.querySelector?.("[data-mobile-detail-position='true']");
    if (!(indicator instanceof HTMLElement)) {
      return;
    }

    const total = getOrderedFeedItems().length;
    indicator.textContent = `${Math.max(1, currentMobileIndex + 1)} / ${Math.max(1, total)}`;
  }

  function scrollMobileToIndex(index, behavior = "auto") {
    if (!(mobileScrollContainer instanceof HTMLElement)) {
      return;
    }

    const slides = getCurrentSlideNodes();
    const slide = slides[index];
    if (!(slide instanceof HTMLElement)) {
      return;
    }

    mobileScrollContainer.scrollTo({
      top: slide.offsetTop,
      behavior
    });
  }

  function maybeLoadMoreForIndex(index) {
    const items = getOrderedFeedItems();
    if (index >= Math.max(0, items.length - 2)) {
      void onNeedMore?.();
    }
  }

  function activateMobileIndex(index, { alignScroll = false, force = false } = {}) {
    const items = getOrderedFeedItems();
    if (!items.length) {
      return;
    }

    const nextIndex = clampIndex(index, items.length);
    const nextTweet = items[nextIndex];
    if (!nextTweet) {
      return;
    }

    if (nextIndex === currentMobileIndex && !force) {
      if (alignScroll) {
        scrollMobileToIndex(nextIndex);
      }
      maybeLoadMoreForIndex(nextIndex);
      updateMobilePositionIndicator();
      return;
    }

    currentMobileIndex = nextIndex;
    currentTweetId = String(nextTweet.tweetId || "");
    closeCommentsDrawer({ immediate: true });
    createOrReplaceMobileCommentsLayer(nextTweet);

    for (const [slideIndex, slide] of getCurrentSlideNodes().entries()) {
      const isActive = slideIndex === nextIndex;
      slide.dataset.active = isActive ? "true" : "false";
      slide.classList.toggle("is-active", isActive);
    }

    syncAllReactionButtons(nextTweet);
    syncCommentMeta(nextTweet);
    syncAllAuthorFollowButtons(nextTweet.authorUserId, nextTweet.authorFollowedByViewer === true);
    setupMobilePlayer(nextTweet);
    setupMobileProgress(nextTweet);
    updateMobilePositionIndicator();
    maybeLoadMoreForIndex(nextIndex);

    if (alignScroll) {
      scheduleTask(() => {
        scrollMobileToIndex(nextIndex);
      });
    }
  }

  function syncActiveMobileSlideFromScroll() {
    if (!(mobileScrollContainer instanceof HTMLElement)) {
      return;
    }

    const viewportHeight = Math.max(1, mobileScrollContainer.clientHeight);
    const nextIndex = clampIndex(Math.round(mobileScrollContainer.scrollTop / viewportHeight), getOrderedFeedItems().length);
    activateMobileIndex(nextIndex);
  }

  function scheduleMobileScrollSync() {
    if (mobileScrollSyncScheduled) {
      return;
    }

    mobileScrollSyncScheduled = true;
    scheduleTask(() => {
      mobileScrollSyncScheduled = false;
      syncActiveMobileSlideFromScroll();
    });
  }

  function renderDesktopView(tweet) {
    if (!detailModalPanel) {
      return;
    }

    applyPanelAccessibility("desktop");
    detailModalPanel.append(createContent(renderFeedDetail(tweet)));
    setupDesktopPlayer(tweet);
    syncAllReactionButtons(tweet);
    syncCommentMeta(tweet);
    syncAllAuthorFollowButtons(tweet.authorUserId, tweet.authorFollowedByViewer === true);
    void loadComments(tweet);
  }

  function renderMobileView(tweet) {
    if (!detailModalPanel) {
      return;
    }

    applyPanelAccessibility("mobile");
    detailModalPanel.append(createContent(buildMobileViewerMarkup()));
    mobileScrollContainer = detailModalPanel.querySelector("[data-mobile-detail-scroller='true']");
    if (!(mobileScrollContainer instanceof HTMLElement)) {
      return;
    }

    appendMissingMobileSlides();
    mobileScrollContainer.addEventListener("scroll", scheduleMobileScrollSync, { passive: true });
    createOrReplaceMobileCommentsLayer(tweet);

    const initialIndex = getTweetIndex(tweet?.tweetId);
    activateMobileIndex(initialIndex >= 0 ? initialIndex : 0, { force: true });
    scheduleTask(() => {
      scrollMobileToIndex(currentMobileIndex, "auto");
      scheduleMobileScrollSync();
    });
  }

  function resolveTweetFromElement(element) {
    if (!(element instanceof Element)) {
      return getCurrentTweet();
    }

    const slide = element.closest("[data-mobile-detail-slide='true']");
    if (slide instanceof HTMLElement) {
      return getTweetById(slide.dataset.tweetId);
    }

    if (activeCommentsTweetId) {
      return getTweetById(activeCommentsTweetId);
    }

    return getCurrentTweet();
  }

  function openSearchResults(trigger) {
    const tweet = resolveTweetFromElement(trigger);
    if (!tweet) {
      return;
    }

    window.location.href = `/explore?q=${encodeURIComponent(getDetailSearchQuery(tweet))}`;
  }

  async function handleShareAction(trigger) {
    const tweet = resolveTweetFromElement(trigger);
    if (!tweet) {
      return;
    }

    const authorName = String(tweet.authorName || tweet.authorHandle || "短视频");
    const shareUrl = getDetailShareUrl(tweet);
    const shareText = `${authorName}：${String(getDisplayText(tweet) || "").trim()}`.slice(0, 120);

    if (typeof navigator.share === "function") {
      try {
        await navigator.share({
          title: authorName,
          text: shareText,
          url: shareUrl
        });
        return;
      } catch (error) {
        if (error?.name === "AbortError") {
          return;
        }
      }
    }

    try {
      if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(shareUrl);
        return;
      }
    } catch {
      // Fall through to prompt fallback.
    }

    window.prompt("复制链接", shareUrl);
  }

  async function handleReactionButtonClick(button) {
    const tweet = resolveTweetFromElement(button);
    if (!tweet) {
      return;
    }

    const reactionType = button.dataset.detailReactionButton === "like" ? "like" : "bookmark";
    if (Number.parseInt(String(tweet.viewerUserId || ""), 10) <= 0) {
      openAuthModal("login");
      return;
    }

    const previousEngagement = { ...(tweet.engagement || {}) };
    const nextActive =
      reactionType === "like" ? !previousEngagement.likedByViewer : !previousEngagement.bookmarkedByViewer;

    if (reactionType === "like") {
      tweet.engagement.likedByViewer = nextActive;
      tweet.engagement.likeCount = Math.max(0, Number(previousEngagement.likeCount || 0) + (nextActive ? 1 : -1));
    } else {
      tweet.engagement.bookmarkedByViewer = nextActive;
      tweet.engagement.bookmarkCount = Math.max(
        0,
        Number(previousEngagement.bookmarkCount || 0) + (nextActive ? 1 : -1)
      );
    }

    syncAllReactionButtons(tweet);

    try {
      const payload = await requestJson(
        `/api/videos/${tweet.videoId}/${reactionType === "like" ? "likes" : "bookmarks"}`,
        {
          method: nextActive ? "POST" : "DELETE"
        }
      );
      updateTweetEngagement(tweet, payload?.engagement || {});
      syncAllReactionButtons(tweet);
      syncCommentMeta(tweet);
    } catch (error) {
      tweet.engagement = previousEngagement;
      syncAllReactionButtons(tweet);
      console.error(error);
    }
  }

  async function handleCommentSubmit(form) {
    const tweet = resolveTweetFromElement(form);
    if (!tweet) {
      return;
    }

    const input = form.querySelector("[data-detail-comment-input='true']");
    const submitButton = form.querySelector("[data-detail-comment-submit='true']");
    if (!(input instanceof HTMLInputElement)) {
      return;
    }

    if (Number.parseInt(String(tweet.viewerUserId || ""), 10) <= 0) {
      openAuthModal("login");
      return;
    }

    const body = input.value.trim();
    if (!body) {
      input.focus();
      return;
    }

    input.disabled = true;
    if (submitButton instanceof HTMLButtonElement) {
      submitButton.disabled = true;
      submitButton.textContent = "发布中...";
    }

    try {
      const payload = await requestJson(`/api/videos/${tweet.videoId}/comments`, {
        method: "POST",
        body: {
          body
        }
      });

      updateTweetEngagement(tweet, payload?.engagement || {});
      syncAllReactionButtons(tweet);
      syncCommentMeta(tweet);

      const list = getCommentsRoot()?.querySelector?.("[data-detail-comments-list='true']");
      const item = payload?.item;
      if (list instanceof HTMLElement && item) {
        const isShowingEmptyState = list.querySelector("[data-empty-comments='true']");
        if (isShowingEmptyState) {
          list.innerHTML = "";
        }

        list.insertAdjacentHTML("afterbegin", renderCommentMarkup(item));
      }

      const status = getCommentsRoot()?.querySelector?.("[data-detail-comments-status='true']");
      if (status instanceof HTMLElement) {
        status.dataset.loading = "false";
        status.textContent = `${tweet.engagement.commentCount} 条评论`;
      }

      input.value = "";
    } catch (error) {
      console.error(error);
    } finally {
      input.disabled = false;
      if (submitButton instanceof HTMLButtonElement) {
        submitButton.disabled = false;
        submitButton.textContent = "发布";
      }
    }
  }

  async function handleAuthorFollowClick(button) {
    if (button.dataset.authRequired === "true") {
      openAuthModal("login");
      return;
    }

    if (button.dataset.enabled !== "true") {
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
        method: nextFollowing ? "POST" : "DELETE"
      });
      const following = payload.following === true;

      updateAuthorFollowState(authorUserId, following);
      syncAllAuthorFollowButtons(authorUserId, following);
      window.dispatchEvent(
        new CustomEvent("shortvideo:author-follow-change", {
          detail: {
            authorUserId,
            following
          }
        })
      );
    } catch (error) {
      console.error(error);
      button.dataset.loading = "false";
      syncAuthorFollowButton(button);
    }
  }

  function open(tweet, triggerElement, { interactionType = "pointer" } = {}) {
    if (!tweet || !detailModal || !detailModalPanel) {
      return;
    }

    const nextMode = matchesMobileViewport() ? "mobile" : "desktop";
    resetPanelState();
    currentMode = nextMode;
    currentTweetId = String(tweet.tweetId || "");

    if (detailModal.hidden) {
      lastActiveElement = triggerElement instanceof HTMLElement ? triggerElement : document.activeElement;
      lastDetailOpenInteraction = interactionType === "keyboard" ? "keyboard" : "pointer";
    }

    if (nextMode === "mobile") {
      renderMobileView(tweet);
    } else {
      renderDesktopView(tweet);
    }

    detailModal.hidden = false;
    detailModal.classList.remove("hidden");
    setBodyLocked(true);
    onOpen?.({
      mode: nextMode,
      tweetId: currentTweetId
    });

    if (nextMode === "desktop") {
      detailModalPanel.focus({ preventScroll: true });
    }
  }

  function close({ restoreFocus = true } = {}) {
    if (!detailModal || detailModal.hidden) {
      return;
    }

    const closedMode = currentMode;
    const closedTweetId = currentTweetId;
    const shouldRestoreTriggerFocus =
      restoreFocus &&
      lastDetailOpenInteraction === "keyboard" &&
      lastActiveElement instanceof HTMLElement;

    if (!shouldRestoreTriggerFocus) {
      const activeElement = document.activeElement;
      if (activeElement instanceof HTMLElement && detailModal.contains(activeElement)) {
        activeElement.blur();
      }
    }

    closeCommentsDrawer({ immediate: true });
    resetPanelState();
    detailModal.classList.add("hidden");
    detailModal.hidden = true;
    setBodyLocked(false);

    if (shouldRestoreTriggerFocus) {
      lastActiveElement.focus({ preventScroll: true });
    }

    lastActiveElement = null;
    lastDetailOpenInteraction = null;
    currentMode = null;
    currentTweetId = "";

    onClose?.({
      mode: closedMode,
      tweetId: closedTweetId
    });
  }

  function syncFeedItems() {
    if (!isMobileMode()) {
      return;
    }

    appendMissingMobileSlides();
    const currentIndex = getTweetIndex(currentTweetId);
    activateMobileIndex(currentIndex >= 0 ? currentIndex : currentMobileIndex, { force: true });
  }

  function bindDismissInteractions() {
    if (!detailModal || !detailModalPanel) {
      return;
    }

    detailModal.addEventListener("click", (event) => {
      if (currentMode === "desktop" && event.target === detailModal) {
        close({ restoreFocus: false });
      }
    });

    detailModalPanel.addEventListener("click", (event) => {
      const target = event.target;
      if (!(target instanceof Element)) {
        return;
      }

      const reactionButton = target.closest("[data-detail-reaction-button]");
      if (reactionButton instanceof HTMLButtonElement) {
        void handleReactionButtonClick(reactionButton);
        return;
      }

      const searchAction = target.closest("[data-detail-search-action='true']");
      if (searchAction instanceof HTMLElement) {
        openSearchResults(searchAction);
        return;
      }

      const shareAction = target.closest("[data-detail-share-action='true']");
      if (shareAction instanceof HTMLButtonElement) {
        void handleShareAction(shareAction);
        return;
      }

      const followButton = target.closest("[data-detail-author-follow-button='true']");
      if (followButton instanceof HTMLButtonElement) {
        void handleAuthorFollowClick(followButton);
        return;
      }

      const openCommentsButton = target.closest("[data-detail-comments-open='true']");
      if (openCommentsButton instanceof HTMLButtonElement) {
        const tweet = resolveTweetFromElement(openCommentsButton);
        if (tweet && Number.parseInt(String(tweet.viewerUserId || ""), 10) <= 0) {
          openAuthModal("login");
          return;
        }

        if (tweet) {
          openCommentsDrawer(tweet);
        }
        return;
      }

      const closeCommentsButton = target.closest("[data-mobile-comments-close='true']");
      if (closeCommentsButton instanceof HTMLButtonElement) {
        event.preventDefault();
        event.stopPropagation();
        closeCommentsDrawer();
        return;
      }

      const closeCommentsBackdrop = target.closest("[data-mobile-comments-backdrop='true']");
      if (closeCommentsBackdrop instanceof HTMLButtonElement) {
        event.preventDefault();
        event.stopPropagation();
        closeCommentsDrawer();
        return;
      }

      const closeMobileViewer = target.closest("[data-mobile-detail-close='true']");
      if (closeMobileViewer instanceof HTMLButtonElement) {
        close({ restoreFocus: false });
      }
    });

    detailModalPanel.addEventListener("submit", (event) => {
      const form = event.target;
      if (!(form instanceof HTMLFormElement) || form.dataset.detailCommentForm !== "true") {
        return;
      }

      event.preventDefault();
      void handleCommentSubmit(form);
    });

    document.addEventListener("keydown", (event) => {
      if (event.key !== "Escape" || detailModal.hidden) {
        return;
      }

      if (isMobileMode() && mobileCommentsLayer instanceof HTMLElement && mobileCommentsLayer.hidden === false) {
        closeCommentsDrawer();
        return;
      }

      close();
    });

    window.addEventListener("resize", () => {
      if (!isMobileMode()) {
        return;
      }

      scheduleTask(() => {
        scrollMobileToIndex(currentMobileIndex, "auto");
      });
    });

    window.addEventListener("shortvideo:author-follow-change", (event) => {
      const authorUserId = Number(event?.detail?.authorUserId);
      if (!Number.isInteger(authorUserId)) {
        return;
      }

      const following = event?.detail?.following === true;
      updateAuthorFollowState(authorUserId, following);
      syncAllAuthorFollowButtons(authorUserId, following);
    });
  }

  return {
    bindDismissInteractions,
    close,
    isMobileMode,
    isOpen,
    open,
    syncFeedItems
  };
}
