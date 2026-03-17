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
const PLYR_BLANK_VIDEO_URL = "/vendor/plyr/blank.mp4";
const HLS_MANIFEST_CONTENT_TYPES = new Set(["application/x-mpegurl", "application/vnd.apple.mpegurl"]);
const scheduleTask =
  typeof requestAnimationFrame === "function" ? requestAnimationFrame : (callback) => setTimeout(callback, 0);
let activeHoverPreviewController = null;

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

function getMediaDatasetValue(video, key) {
  const value = video?.dataset?.[key];
  return typeof value === "string" && value.trim() !== "" ? value.trim() : "";
}

function getAttributeValue(node, name) {
  return typeof node?.getAttribute === "function" ? node.getAttribute(name) || "" : "";
}

function setNoReferrerPolicy(video) {
  video.referrerPolicy = "no-referrer";
  video.setAttribute?.("referrerpolicy", "no-referrer");
}

function canUseNativeHls(video) {
  if (typeof video.canPlayType !== "function") {
    return false;
  }

  return Array.from(HLS_MANIFEST_CONTENT_TYPES).some((contentType) => video.canPlayType(contentType) !== "");
}

function assignVideoSource(video, url) {
  if (!url) {
    return;
  }

  setNoReferrerPolicy(video);
  if (getAttributeValue(video, "src") !== url) {
    video.setAttribute?.("src", url);
  }
  video.load?.();
}

function clearAssignedVideoSource(video) {
  if (typeof video.removeAttribute === "function") {
    video.removeAttribute("src");
  } else if (typeof video.setAttribute === "function") {
    video.setAttribute("src", "");
  }

  video.load?.();
}

function claimActiveHoverPreview(controller) {
  if (activeHoverPreviewController === controller) {
    return;
  }

  const previousController = activeHoverPreviewController;
  activeHoverPreviewController = controller;
  previousController?.deactivate?.({ releaseActive: false });
}

function releaseActiveHoverPreview(controller) {
  if (activeHoverPreviewController === controller) {
    activeHoverPreviewController = null;
  }
}

function createPlaybackSession(video) {
  const hlsUrl = getMediaDatasetValue(video, "hlsUrl");
  const fallbackUrl =
    getMediaDatasetValue(video, "fallbackUrl") ||
    getAttributeValue(video, "src") ||
    video.querySelector?.("source")?.getAttribute?.("src") ||
    "";
  let hls = null;
  let currentMode = "";
  let preparePromise = null;
  let fallbackPinned = false;

  const destroyHls = () => {
    hls?.destroy?.();
    hls = null;
    if (currentMode === "managed-hls") {
      currentMode = "";
    }
  };

  const useFallback = (pinFallback = true) => {
    if (!fallbackUrl) {
      return Promise.resolve(null);
    }

    fallbackPinned = pinFallback;
    destroyHls();
    currentMode = "fallback";
    assignVideoSource(video, fallbackUrl);
    return Promise.resolve(currentMode);
  };

  const prepareDirectHls = () => {
    if (fallbackPinned) {
      return useFallback();
    }

    if (!hlsUrl) {
      return useFallback();
    }

    if (currentMode === "native-hls" && getAttributeValue(video, "src") === hlsUrl) {
      return Promise.resolve(currentMode);
    }

    if (currentMode === "managed-hls" && hls) {
      return Promise.resolve(currentMode);
    }

    if (canUseNativeHls(video)) {
      destroyHls();
      currentMode = "native-hls";
      assignVideoSource(video, hlsUrl);
      return Promise.resolve(currentMode);
    }

    const Hls = window.Hls;
    if (
      typeof Hls !== "function" ||
      typeof Hls.isSupported !== "function" ||
      !Hls.isSupported()
    ) {
      return useFallback();
    }

    if (preparePromise) {
      return preparePromise;
    }

    currentMode = "managed-hls";
    destroyHls();
    preparePromise = new Promise((resolve) => {
      const manifestEvent = Hls.Events?.MANIFEST_PARSED || "manifestParsed";
      const errorEvent = Hls.Events?.ERROR || "error";
      let settled = false;
      const settle = (mode) => {
        if (settled) {
          return;
        }

        settled = true;
        resolve(mode);
      };

      hls = new Hls({
        enableWorker: true
      });

      hls.on?.(manifestEvent, () => {
        settle(currentMode);
      });

      hls.on?.(errorEvent, async (_event, data = {}) => {
        if (!data?.fatal) {
          return;
        }

        await useFallback();
        settle(currentMode);
        scheduleTask(() => {
          video.play().catch(() => {});
        });
      });

      setNoReferrerPolicy(video);
      hls.loadSource(hlsUrl);
      hls.attachMedia(video);
      setTimeout(() => {
        settle(currentMode);
      }, 1500);
    }).finally(() => {
      preparePromise = null;
    });

    return preparePromise;
  };

  return {
    async prepare() {
      return prepareDirectHls();
    },
    async fallback() {
      return useFallback();
    },
    reset() {
      destroyHls();
      currentMode = "";
      clearAssignedVideoSource(video);
    },
    get mode() {
      return currentMode;
    },
    get hasDirectHls() {
      return hlsUrl !== "";
    },
    destroy() {
      destroyHls();
    }
  };
}

function createNativePreviewController(container, video) {
  const playbackSession = createPlaybackSession(video);
  let isDestroyed = false;
  const setControlsVisible = (isVisible) => {
    if ("controls" in video) {
      video.controls = isVisible;
    }
  };

  const warmPreview = async () => {
    await playbackSession.prepare();
  };

  const playPreview = async () => {
    setControlsVisible(true);
    await warmPreview();

    if (isDestroyed || activeHoverPreviewController !== controllerApi) {
      return;
    }

    video.play().catch(() => {});
  };

  const stopPreview = ({ releaseActive = true } = {}) => {
    if (releaseActive) {
      releaseActiveHoverPreview(controllerApi);
    }

    setControlsVisible(false);
    video.pause();
    safeResetCurrentTime(video);
    playbackSession.reset();
  };

  const handleMouseEnter = () => {
    void controllerApi.activate({ play: true });
  };

  const handleMouseLeave = () => {
    stopPreview();
  };

  const handleFocusIn = () => {
    void controllerApi.activate({ play: true });
  };

  const handleFocusOut = (event) => {
    if (event.relatedTarget && container.contains(event.relatedTarget)) {
      return;
    }

    stopPreview();
  };

  const handlePlaybackError = () => {
    if (!playbackSession.hasDirectHls || playbackSession.mode === "fallback") {
      return;
    }

    void playbackSession.fallback();
  };

  container.addEventListener("mouseenter", handleMouseEnter);
  container.addEventListener("mouseleave", handleMouseLeave);
  container.addEventListener("focusin", handleFocusIn);
  container.addEventListener("focusout", handleFocusOut);
  video.addEventListener("error", handlePlaybackError);
  const handleLoadedMetadata = () => {
    syncDurationBadge(container, video.duration);
  };
  video.addEventListener("loadedmetadata", handleLoadedMetadata);
  video.addEventListener("durationchange", handleLoadedMetadata);
  syncDurationBadge(container, video.duration);
  setControlsVisible(false);

  const controllerApi = {
    async activate({ play = false } = {}) {
      if (isDestroyed) {
        return null;
      }

      claimActiveHoverPreview(controllerApi);
      if (!play) {
        return warmPreview();
      }

      return playPreview();
    },
    preload() {
      if (!playbackSession.hasDirectHls) {
        return Promise.resolve(null);
      }

      return controllerApi.activate();
    },
    handleVisibilityChange(isVisible) {
      if (!isVisible) {
        stopPreview();
      }
    },
    deactivate(options) {
      stopPreview(options);
    },
    destroy() {
      isDestroyed = true;
      releaseActiveHoverPreview(controllerApi);
      container.removeEventListener("mouseenter", handleMouseEnter);
      container.removeEventListener("mouseleave", handleMouseLeave);
      container.removeEventListener("focusin", handleFocusIn);
      container.removeEventListener("focusout", handleFocusOut);
      video.removeEventListener("error", handlePlaybackError);
      video.removeEventListener("loadedmetadata", handleLoadedMetadata);
      video.removeEventListener("durationchange", handleLoadedMetadata);
      stopPreview({ releaseActive: false });
      playbackSession.destroy();
    }
  };

  return controllerApi;
}

export function installHoverVideoPreview(container, video) {
  if (!(container instanceof HTMLElement) || !(video instanceof HTMLVideoElement)) {
    throw new TypeError("installHoverVideoPreview expects HTMLElement and HTMLVideoElement instances");
  }

  return createNativePreviewController(container, video);
}

export function installDetailVideoPlayer(container, video) {
  if (!(container instanceof HTMLElement) || !(video instanceof HTMLVideoElement)) {
    throw new TypeError("installDetailVideoPlayer expects HTMLElement and HTMLVideoElement instances");
  }

  const playbackSession = createPlaybackSession(video);
  const handlePlaybackError = () => {
    if (!playbackSession.hasDirectHls || playbackSession.mode === "fallback") {
      return;
    }

    void playbackSession.fallback();
  };
  video.addEventListener("error", handlePlaybackError);
  const Plyr = window.Plyr;
  if (typeof Plyr === "function") {
    video.controls = false;
    const player = new Plyr(video, {
      autoplay: true,
      blankVideo: PLYR_BLANK_VIDEO_URL,
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
      void playbackSession.prepare().then(() => {
        player.play().catch(() => {});
      });
    };

    player.on("ready", handleReady);
    requestAnimationFrame(syncContainer);

    return {
      destroy() {
        player.off?.("ready", handleReady);
        video.removeEventListener("error", handlePlaybackError);
        playbackSession.destroy();
        player.pause();
        player.destroy();
      }
    };
  }

  video.controls = true;
  void playbackSession.prepare().then(() => {
    video.play().catch(() => {});
  });

  return {
    destroy() {
      video.removeEventListener("error", handlePlaybackError);
      playbackSession.destroy();
      video.pause();
      video.controls = false;
    }
  };
}
