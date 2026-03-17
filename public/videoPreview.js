function safeResetCurrentTime(target) {
  if (typeof target.currentTime === "number" && target.currentTime !== 0) {
    try {
      target.currentTime = 0;
    } catch {
      // Some browsers can reject currentTime writes before metadata is ready.
    }
  }
}

const PLYR_ICON_URL = "/vendor/plyr/plyr.svg";

function formatDurationLabel(seconds) {
  if (!Number.isFinite(seconds) || seconds <= 0) {
    return "";
  }

  const totalSeconds = Math.max(0, Math.round(seconds));
  const hours = Math.floor(totalSeconds / 3600);
  const minutes = Math.floor((totalSeconds % 3600) / 60);
  const remainingSeconds = totalSeconds % 60;

  if (hours > 0) {
    return `${hours}:${String(minutes).padStart(2, "0")}:${String(remainingSeconds).padStart(2, "0")}`;
  }

  return `${minutes}:${String(remainingSeconds).padStart(2, "0")}`;
}

function syncDurationBadge(container, durationSeconds) {
  const badge = container.querySelector("[data-video-duration]");
  if (!(badge instanceof HTMLElement)) {
    return;
  }

  const label = formatDurationLabel(durationSeconds);
  if (!label) {
    badge.classList.add("hidden");
    return;
  }

  badge.textContent = label;
  badge.classList.remove("hidden");
}

function createNativePreviewController(container, video) {
  const setControlsVisible = (isVisible) => {
    if ("controls" in video) {
      video.controls = isVisible;
    }
  };

  const playPreview = () => {
    setControlsVisible(true);
    video.play().catch(() => {});
  };

  const stopPreview = () => {
    setControlsVisible(false);
    video.pause();
    safeResetCurrentTime(video);
  };

  const handleMouseEnter = () => {
    playPreview();
  };

  const handleMouseLeave = () => {
    stopPreview();
  };

  const handleFocusIn = () => {
    playPreview();
  };

  const handleFocusOut = (event) => {
    if (event.relatedTarget && container.contains(event.relatedTarget)) {
      return;
    }

    stopPreview();
  };

  container.addEventListener("mouseenter", handleMouseEnter);
  container.addEventListener("mouseleave", handleMouseLeave);
  container.addEventListener("focusin", handleFocusIn);
  container.addEventListener("focusout", handleFocusOut);
  const handleLoadedMetadata = () => {
    syncDurationBadge(container, video.duration);
  };
  video.addEventListener("loadedmetadata", handleLoadedMetadata);
  video.addEventListener("durationchange", handleLoadedMetadata);
  syncDurationBadge(container, video.duration);
  setControlsVisible(false);

  return {
    handleVisibilityChange(isVisible) {
      if (!isVisible) {
        stopPreview();
      }
    },
    destroy() {
      container.removeEventListener("mouseenter", handleMouseEnter);
      container.removeEventListener("mouseleave", handleMouseLeave);
      container.removeEventListener("focusin", handleFocusIn);
      container.removeEventListener("focusout", handleFocusOut);
      video.removeEventListener("loadedmetadata", handleLoadedMetadata);
      video.removeEventListener("durationchange", handleLoadedMetadata);
      stopPreview();
    }
  };
}

function createPlyrPreviewController(container, video, Plyr) {
  let player = null;
  let playerReadyPromise = null;
  let shouldPreview = false;
  let destroyed = false;

  const syncPlayerContainer = () => {
    player?.elements?.container?.classList.add("feed-card-player");
  };

  const setPreviewState = (isPreviewing) => {
    player?.elements?.container?.classList.toggle("is-previewing", isPreviewing);
  };

  const syncDuration = () => {
    syncDurationBadge(container, player?.duration || video.duration);
  };

  const handlePlayerReady = () => {
    syncPlayerContainer();
    syncDuration();
  };

  const ensurePlayer = () => {
    if (playerReadyPromise) {
      return playerReadyPromise;
    }

    video.controls = false;
    player = new Plyr(video, {
      autoplay: false,
      clickToPlay: true,
      fullscreen: {
        enabled: true,
        fallback: true,
        iosNative: false
      },
      hideControls: false,
      keyboard: {
        focused: true,
        global: false
      },
      muted: true,
      iconUrl: PLYR_ICON_URL,
      playsinline: true,
      resetOnEnd: true,
      tooltips: {
        controls: false,
        seek: true
      },
      controls: ["play", "progress", "current-time", "mute"]
    });

    syncPlayerContainer();
    player.on("ready", handlePlayerReady);
    player.on("loadedmetadata", syncDuration);
    player.on("durationchange", syncDuration);

    playerReadyPromise = new Promise((resolve) => {
      let settled = false;
      const settle = () => {
        if (settled) {
          return;
        }

        settled = true;
        syncPlayerContainer();
        syncDuration();
        resolve(player);
      };

      player.on("ready", settle);
      requestAnimationFrame(settle);
    });

    return playerReadyPromise;
  };

  const playPreview = async () => {
    shouldPreview = true;
    const activePlayer = await ensurePlayer();
    if (destroyed || !shouldPreview) {
      return;
    }

    syncPlayerContainer();
    setPreviewState(true);
    activePlayer.muted = true;
    activePlayer.play().catch(() => {});
  };

  const stopPreview = () => {
    shouldPreview = false;
    setPreviewState(false);
    if (!player) {
      video.pause();
      safeResetCurrentTime(video);
      return;
    }

    player.pause();
    safeResetCurrentTime(player);
  };

  const handleMouseEnter = () => {
    void playPreview();
  };

  const handleMouseLeave = () => {
    stopPreview();
  };

  const handleFocusIn = () => {
    void playPreview();
  };

  const handleFocusOut = (event) => {
    if (event.relatedTarget && container.contains(event.relatedTarget)) {
      return;
    }

    stopPreview();
  };

  container.addEventListener("mouseenter", handleMouseEnter);
  container.addEventListener("mouseleave", handleMouseLeave);
  container.addEventListener("focusin", handleFocusIn);
  container.addEventListener("focusout", handleFocusOut);
  video.addEventListener("loadedmetadata", syncDuration);
  video.addEventListener("durationchange", syncDuration);
  syncDuration();
  setPreviewState(false);
  video.controls = false;

  return {
    handleVisibilityChange(isVisible) {
      if (!isVisible) {
        stopPreview();
      }
    },
    destroy() {
      container.removeEventListener("mouseenter", handleMouseEnter);
      container.removeEventListener("mouseleave", handleMouseLeave);
      container.removeEventListener("focusin", handleFocusIn);
      container.removeEventListener("focusout", handleFocusOut);
      video.removeEventListener("loadedmetadata", syncDuration);
      video.removeEventListener("durationchange", syncDuration);
      destroyed = true;
      stopPreview();
      if (player) {
        player.off?.("ready", handlePlayerReady);
        player.off?.("loadedmetadata", syncDuration);
        player.off?.("durationchange", syncDuration);
        player.destroy();
      }
    }
  };
}

export function installHoverVideoPreview(container, video) {
  if (!(container instanceof HTMLElement) || !(video instanceof HTMLVideoElement)) {
    throw new TypeError("installHoverVideoPreview expects HTMLElement and HTMLVideoElement instances");
  }

  const Plyr = window.Plyr;
  if (typeof Plyr === "function") {
    return createPlyrPreviewController(container, video, Plyr);
  }

  return createNativePreviewController(container, video);
}

export function installDetailVideoPlayer(container, video) {
  if (!(container instanceof HTMLElement) || !(video instanceof HTMLVideoElement)) {
    throw new TypeError("installDetailVideoPlayer expects HTMLElement and HTMLVideoElement instances");
  }

  const Plyr = window.Plyr;
  if (typeof Plyr === "function") {
    video.controls = false;
    const player = new Plyr(video, {
      autoplay: true,
      clickToPlay: true,
      fullscreen: {
        enabled: true,
        fallback: true,
        iosNative: false
      },
      hideControls: false,
      keyboard: {
        focused: true,
        global: false
      },
      loop: {
        active: video.loop
      },
      muted: video.muted,
      iconUrl: PLYR_ICON_URL,
      playsinline: true,
      resetOnEnd: false,
      tooltips: {
        controls: false,
        seek: true
      },
      controls: ["play", "progress", "current-time", "mute", "volume", "fullscreen"]
    });

    const syncContainer = () => {
      player.elements?.container?.classList.add("detail-modal-player", "detail-modal-media-shell");
      player.elements?.container?.setAttribute("data-detail-layout-node", "true");
    };

    const handleReady = () => {
      syncContainer();
      player.muted = video.muted;
      player.play().catch(() => {});
    };

    player.on("ready", handleReady);
    requestAnimationFrame(syncContainer);

    return {
      destroy() {
        player.off?.("ready", handleReady);
        player.pause();
        player.destroy();
      }
    };
  }

  video.controls = true;
  video.play().catch(() => {});

  return {
    destroy() {
      video.pause();
      video.controls = false;
    }
  };
}
