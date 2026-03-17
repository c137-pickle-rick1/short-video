import { renderFeedDetail } from "../render.js";
import { installDetailVideoPlayer } from "../videoPreview.js";
import { requestJson } from "./http.js";
import { escapeHtml, getAuthorInitial, renderAvatarMarkup } from "../shared/feed/render/templateUtils.js";

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

function getReactionConfig(type, active, enabled) {
  if (!enabled) {
    return {
      buttonClass: "border-gray-200 bg-gray-100 text-gray-400 hover:border-gray-200 hover:bg-gray-100 hover:text-gray-400",
      iconClass: type === "like" ? "ph ph-heart" : "ph ph-bookmark-simple"
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

export function createDetailModalController({ detailModal, detailModalPanel }) {
  let lastActiveElement = null;
  let lastDetailOpenInteraction = null;
  let previousBodyOverflow = "";
  let detailPlayerController = null;
  let commentsLoadToken = 0;

  function clearDetailModalNodes() {
    if (!detailModalPanel) {
      return;
    }

    for (const node of detailModalPanel.querySelectorAll("[data-detail-layout-node='true']")) {
      node.remove();
    }
  }

  function destroyDetailPlayer() {
    detailPlayerController?.destroy?.();
    detailPlayerController = null;
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

  function setupDetailPlayer(tweet) {
    if (!detailModalPanel) {
      return;
    }

    destroyDetailPlayer();
    const video = detailModalPanel.querySelector("[data-detail-player]");
    if (!(video instanceof HTMLVideoElement)) {
      return;
    }

    detailPlayerController = installDetailVideoPlayer(detailModalPanel, video, {
      onQualifiedView: () => {
        void recordQualifiedView(tweet);
      }
    });
  }

  function syncReactionButton(button, tweet) {
    const reactionType = button.dataset.detailReactionButton === "like" ? "like" : "bookmark";
    const engagement = tweet?.engagement || {};
    const isActive =
      reactionType === "like" ? engagement.likedByViewer === true : engagement.bookmarkedByViewer === true;
    const count = reactionType === "like" ? engagement.likeCount : engagement.bookmarkCount;
    const isEnabled = Number.parseInt(String(tweet?.viewerUserId || ""), 10) > 0;
    const { buttonClass, iconClass } = getReactionConfig(reactionType, isActive, isEnabled);
    const icon = button.querySelector("[data-detail-reaction-icon]");
    const countNode = button.querySelector("[data-detail-reaction-count]");

    button.dataset.active = isActive ? "true" : "false";
    button.dataset.count = String(Math.max(0, Number(count) || 0));
    button.dataset.enabled = isEnabled ? "true" : "false";
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
    if (!detailModalPanel) {
      return;
    }

    for (const button of detailModalPanel.querySelectorAll("[data-detail-reaction-button]")) {
      if (!(button instanceof HTMLButtonElement)) {
        continue;
      }

      syncReactionButton(button, tweet);
    }
  }

  function syncCommentMeta(tweet) {
    if (!detailModalPanel) {
      return;
    }

    const commentCount = Number(tweet?.engagement?.commentCount || 0);
    const badge = detailModalPanel.querySelector("[data-detail-comment-count-badge='true']");
    if (badge instanceof HTMLElement) {
      badge.textContent = `${commentCount} 条评论`;
    }

    const status = detailModalPanel.querySelector("[data-detail-comments-status='true']");
    if (status instanceof HTMLElement && status.dataset.loading !== "true") {
      status.textContent = commentCount > 0 ? `${commentCount} 条评论` : "暂无评论";
    }
  }

  function renderComments(comments) {
    if (!detailModalPanel) {
      return;
    }

    const list = detailModalPanel.querySelector("[data-detail-comments-list='true']");
    if (!(list instanceof HTMLElement)) {
      return;
    }

    list.innerHTML =
      comments.length > 0
        ? comments.map((comment) => renderCommentMarkup(comment)).join("")
        : renderEmptyCommentsMarkup("还没有评论，抢先说点什么。");
  }

  async function loadComments(tweet) {
    if (!detailModalPanel) {
      return;
    }

    const videoId = Number.parseInt(String(tweet?.videoId || ""), 10);
    if (!Number.isInteger(videoId) || videoId <= 0) {
      renderComments([]);
      return;
    }

    const token = ++commentsLoadToken;
    const status = detailModalPanel.querySelector("[data-detail-comments-status='true']");
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
      renderComments(items);

      if (status instanceof HTMLElement) {
        status.dataset.loading = "false";
        status.textContent = items.length > 0 ? `${items.length} 条评论` : "暂无评论";
      }
    } catch (error) {
      if (token !== commentsLoadToken) {
        return;
      }

      renderComments([]);
      if (status instanceof HTMLElement) {
        status.dataset.loading = "false";
        status.textContent = "评论加载失败";
      }
      console.error(error);
    }
  }

  function bindReactionButtons(tweet) {
    if (!detailModalPanel) {
      return;
    }

    for (const button of detailModalPanel.querySelectorAll("[data-detail-reaction-button]")) {
      if (!(button instanceof HTMLButtonElement)) {
        continue;
      }

      syncReactionButton(button, tweet);
      button.addEventListener("click", async () => {
        const reactionType = button.dataset.detailReactionButton === "like" ? "like" : "bookmark";
        if (Number.parseInt(String(tweet.viewerUserId || ""), 10) <= 0) {
          window.location.href = "/login";
          return;
        }

        const previousEngagement = { ...(tweet.engagement || {}) };
        const nextActive =
          reactionType === "like" ? !previousEngagement.likedByViewer : !previousEngagement.bookmarkedByViewer;

        if (reactionType === "like") {
          tweet.engagement.likedByViewer = nextActive;
          tweet.engagement.likeCount = Math.max(
            0,
            Number(previousEngagement.likeCount || 0) + (nextActive ? 1 : -1)
          );
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
      });
    }
  }

  function bindCommentForm(tweet) {
    if (!detailModalPanel) {
      return;
    }

    const form = detailModalPanel.querySelector("[data-detail-comment-form='true']");
    const input = detailModalPanel.querySelector("[data-detail-comment-input='true']");
    const submitButton = detailModalPanel.querySelector("[data-detail-comment-submit='true']");

    if (!(form instanceof HTMLFormElement) || !(input instanceof HTMLInputElement)) {
      return;
    }

    form.addEventListener("submit", async (event) => {
      event.preventDefault();

      if (Number.parseInt(String(tweet.viewerUserId || ""), 10) <= 0) {
        window.location.href = "/login";
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

        const list = detailModalPanel.querySelector("[data-detail-comments-list='true']");
        const item = payload?.item;
        if (list instanceof HTMLElement && item) {
          const isShowingEmptyState = list.querySelector("[data-empty-comments='true']");
          if (isShowingEmptyState) {
            list.innerHTML = "";
          }

          list.insertAdjacentHTML("afterbegin", renderCommentMarkup(item));
        }

        const status = detailModalPanel.querySelector("[data-detail-comments-status='true']");
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
    });
  }

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
      "inline-flex h-11 shrink-0 items-center rounded-full px-5 text-sm font-semibold shadow-sm transition",
      isEnabled
        ? isFollowing
          ? "border border-gray-200 bg-gray-100 text-gray-700 hover:bg-gray-200"
          : "bg-rose-500 text-white hover:bg-rose-600"
        : "bg-gray-100 text-gray-400"
    ].join(" ");
  }

  function bindAuthorFollowButton(tweet) {
    if (!detailModalPanel) {
      return;
    }

    const button = detailModalPanel.querySelector("[data-detail-author-follow-button='true']");
    if (!(button instanceof HTMLButtonElement)) {
      return;
    }

    syncAuthorFollowButton(button);
    button.addEventListener("click", async () => {
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

        tweet.authorFollowedByViewer = following;
        button.dataset.following = following ? "true" : "false";
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
      } finally {
        button.dataset.loading = "false";
        syncAuthorFollowButton(button);
      }
    });
  }

  function open(tweet, triggerElement, { interactionType = "pointer" } = {}) {
    if (!tweet || !detailModal || !detailModalPanel) {
      return;
    }

    destroyDetailPlayer();
    pauseVideoElements(detailModalPanel);
    clearDetailModalNodes();
    detailModalPanel.append(createContent(renderFeedDetail(tweet)));
    setupDetailPlayer(tweet);
    bindReactionButtons(tweet);
    bindCommentForm(tweet);
    bindAuthorFollowButton(tweet);
    syncCommentMeta(tweet);
    void loadComments(tweet);

    if (detailModal.hidden) {
      previousBodyOverflow = document.body.style.overflow;
    }

    detailModal.hidden = false;
    detailModal.classList.remove("hidden");
    document.body.style.overflow = "hidden";
    lastActiveElement = triggerElement instanceof HTMLElement ? triggerElement : document.activeElement;
    lastDetailOpenInteraction = interactionType === "keyboard" ? "keyboard" : "pointer";
    detailModalPanel.focus({ preventScroll: true });
  }

  function close({ restoreFocus = true } = {}) {
    if (!detailModal || detailModal.hidden) {
      return;
    }

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

    commentsLoadToken += 1;
    destroyDetailPlayer();
    pauseVideoElements(detailModal);
    detailModal.classList.add("hidden");
    detailModal.hidden = true;
    clearDetailModalNodes();
    document.body.style.overflow = previousBodyOverflow;

    if (shouldRestoreTriggerFocus) {
      lastActiveElement.focus({ preventScroll: true });
    }

    lastActiveElement = null;
    lastDetailOpenInteraction = null;
  }

  function bindDismissInteractions() {
    if (!detailModal || !detailModalPanel) {
      return;
    }

    detailModal.addEventListener("click", (event) => {
      if (event.target === detailModal) {
        close({ restoreFocus: false });
      }
    });

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && !detailModal.hidden) {
        close();
      }
    });
  }

  return {
    bindDismissInteractions,
    close,
    open
  };
}
