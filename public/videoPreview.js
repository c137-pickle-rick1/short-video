function safeResetVideo(video) {
  video.pause();

  if (typeof video.currentTime === "number" && video.currentTime !== 0) {
    try {
      video.currentTime = 0;
    } catch {
      // Some browsers can reject currentTime writes before metadata is ready.
    }
  }
}

function setControlsVisible(video, isVisible) {
  if ("controls" in video) {
    video.controls = isVisible;
  }
}

export function installHoverVideoPreview(container, video) {
  if (!(container instanceof EventTarget) || !(video instanceof EventTarget)) {
    throw new TypeError("installHoverVideoPreview expects EventTarget instances");
  }

  const playPreview = () => {
    setControlsVisible(video, true);
    video.play().catch(() => {});
  };

  const stopPreview = () => {
    setControlsVisible(video, false);
    safeResetVideo(video);
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
  setControlsVisible(video, false);

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
      stopPreview();
    }
  };
}
