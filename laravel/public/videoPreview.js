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
const PLYR_BLANK_VIDEO_URL =
  "data:video/mp4;base64,AAAAIGZ0eXBpc29tAAACAGlzb21pc28yYXZjMW1wNDEAAAAIZnJlZQAAAuVtZGF0AAACrgYF//+q3EXpvebZSLeWLNgg2SPu73gyNjQgLSBjb3JlIDE2NSByMzIyMiBiMzU2MDVhIC0gSC4yNjQvTVBFRy00IEFWQyBjb2RlYyAtIENvcHlsZWZ0IDIwMDMtMjAyNSAtIGh0dHA6Ly93d3cudmlkZW9sYW4ub3JnL3gyNjQuaHRtbCAtIG9wdGlvbnM6IGNhYmFjPTEgcmVmPTMgZGVibG9jaz0xOjA6MCBhbmFseXNlPTB4MzoweDExMyBtZT1oZXggc3VibWU9NyBwc3k9MSBwc3lfcmQ9MS4wMDowLjAwIG1peGVkX3JlZj0xIG1lX3JhbmdlPTE2IGNocm9tYV9tZT0xIHRyZWxsaXM9MSA4eDhkY3Q9MSBjcW09MCBkZWFkem9uZT0yMSwxMSBmYXN0X3Bza2lwPTEgY2hyb21hX3FwX29mZnNldD0tMiB0aHJlYWRzPTEgbG9va2FoZWFkX3RocmVhZHM9MSBzbGljZWRfdGhyZWFkcz0wIG5yPTAgZGVjaW1hdGU9MSBpbnRlcmxhY2VkPTAgYmx1cmF5X2NvbXBhdD0wIGNvbnN0cmFpbmVkX2ludHJhPTAgYmZyYW1lcz0zIGJfcHlyYW1pZD0yIGJfYWRhcHQ9MSBiX2JpYXM9MCBkaXJlY3Q9MSB3ZWlnaHRiPTEgb3Blbl9nb3A9MCB3ZWlnaHRwPTIga2V5aW50PTI1MCBrZXlpbnRfbWluPTI1IHNjZW5lY3V0PTQwIGludHJhX3JlZnJlc2g9MCByY19sb29rYWhlYWQ9NDAgcmM9Y3JmIG1idHJlZT0xIGNyZj0yMy4wIHFjb21wPTAuNjAgcXBtaW49MCBxcG1heD02OSBxcHN0ZXA9NCBpcF9yYXRpbz0xLjQwIGFxPTE6MS4wMACAAAAAD2WIhAAz//727L4FNhTIwQAAAAhBmiJsQr/+wAAAAAgBnkF5Cv/EgQAAA1xtb292AAAAbG12aGQAAAAAAAAAAAAAAAAAAAPoAAAAeAABAAABAAAAAAAAAAAAAAAAAQAAAAAAAAAAAAAAAAAAAAEAAAAAAAAAAAAAAAAAAEAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACAAACh3RyYWsAAABcdGtoZAAAAAMAAAAAAAAAAAAAAAEAAAAAAAAAeAAAAAAAAAAAAAAAAAAAAAAAAQAAAAAAAAAAAAAAAAAAAAEAAAAAAAAAAAAAAAAAAEAAAAAAEAAAABAAAAAAACRlZHRzAAAAHGVsc3QAAAAAAAAAAQAAAHgAAAQAAAEAAAAAAf9tZGlhAAAAIG1kaGQAAAAAAAAAAAAAAAAAADIAAAAIAFXEAAAAAAAtaGRscgAAAAAAAAAAdmlkZQAAAAAAAAAAAAAAAFZpZGVvSGFuZGxlcgAAAAGqbWluZgAAABR2bWhkAAAAAQAAAAAAAAAAAAAAJGRpbmYAAAAcZHJlZgAAAAAAAAABAAAADHVybCAAAAABAAABanN0YmwAAAC+c3RzZAAAAAAAAAABAAAArmF2YzEAAAAAAAAAAQAAAAAAAAAAAAAAAAAAAAAAEAAQAEgAAABIAAAAAAAAAAEVTGF2YzYyLjExLjEwMCBsaWJ4MjY0AAAAAAAAAAAAAAAY//8AAAA0YXZjQwFkAAr/4QAXZ2QACqzZXsBEAAADAAQAAAMAyDxIllgBAAZo6+PLIsD9+PgAAAAAEHBhc3AAAAABAAAAAQAAABRidHJ0AAAAAAAAvuIAAAAAAAAAGHN0dHMAAAAAAAAAAQAAAAMAAAIAAAAAFHN0c3MAAAAAAAAAAQAAAAEAAAAoY3R0cwAAAAAAAAADAAAAAQAABAAAAAABAAAGAAAAAAEAAAIAAAAAHHN0c2MAAAAAAAAAAQAAAAEAAAADAAAAAQAAACBzdHN6AAAAAAAAAAAAAAADAAACxQAAAAwAAAAMAAAAFHN0Y28AAAAAAAAAAQAAADAAAABhdWR0YQAAAFltZXRhAAAAAAAAACFoZGxyAAAAAAAAAABtZGlyYXBwbAAAAAAAAAAAAAAAACxpbHN0AAAAJKl0b28AAAAcZGF0YQAAAAEAAAAATGF2ZjYyLjMuMTAw";
const HLS_MANIFEST_CONTENT_TYPES = new Set(["application/x-mpegurl", "application/vnd.apple.mpegurl"]);
const scheduleTask =
  typeof requestAnimationFrame === "function" ? requestAnimationFrame : (callback) => setTimeout(callback, 0);
let activeHoverPreviewController = null;

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

function createQualifiedViewTracker(video, onQualifiedView) {
  let qualified = false;
  let timerId = 0;

  const clearTimer = () => {
    if (timerId) {
      window.clearTimeout(timerId);
      timerId = 0;
    }
  };

  const schedule = () => {
    if (qualified || typeof onQualifiedView !== "function") {
      return;
    }

    clearTimer();
    timerId = window.setTimeout(() => {
      qualified = true;
      timerId = 0;
      onQualifiedView();
    }, 3000);
  };

  const cancelIfPending = () => {
    if (!qualified) {
      clearTimer();
    }
  };

  video.addEventListener("play", schedule);
  video.addEventListener("playing", schedule);
  video.addEventListener("pause", cancelIfPending);
  video.addEventListener("waiting", cancelIfPending);
  video.addEventListener("seeking", cancelIfPending);
  video.addEventListener("ended", cancelIfPending);

  return {
    destroy() {
      clearTimer();
      video.removeEventListener("play", schedule);
      video.removeEventListener("playing", schedule);
      video.removeEventListener("pause", cancelIfPending);
      video.removeEventListener("waiting", cancelIfPending);
      video.removeEventListener("seeking", cancelIfPending);
      video.removeEventListener("ended", cancelIfPending);
    }
  };
}

export function installDetailVideoPlayer(container, video, { onQualifiedView } = {}) {
  if (!(container instanceof HTMLElement) || !(video instanceof HTMLVideoElement)) {
    throw new TypeError("installDetailVideoPlayer expects HTMLElement and HTMLVideoElement instances");
  }

  const playbackSession = createPlaybackSession(video);
  const qualifiedViewTracker = createQualifiedViewTracker(video, onQualifiedView);
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
        qualifiedViewTracker.destroy();
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
      qualifiedViewTracker.destroy();
      playbackSession.destroy();
      video.pause();
      video.controls = false;
    }
  };
}
