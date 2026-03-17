import assert from "node:assert/strict";
import test from "node:test";

import { installHoverVideoPreview } from "../laravel/public/videoPreview.js";

if (typeof globalThis.HTMLElement === "undefined") {
  globalThis.HTMLElement = class HTMLElement extends EventTarget {};
}

if (typeof globalThis.HTMLVideoElement === "undefined") {
  globalThis.HTMLVideoElement = class HTMLVideoElement extends EventTarget {};
}

if (typeof globalThis.window === "undefined") {
  globalThis.window = {};
}

class FakeTarget extends globalThis.HTMLElement {
  constructor() {
    super();
    this.nodes = new Set();
  }

  contains(node) {
    return this.nodes.has(node);
  }

  querySelector() {
    return null;
  }
}

class FakeVideo extends globalThis.HTMLVideoElement {
  constructor() {
    super();
    this.controls = true;
    this.currentTime = 7;
    this.pauseCount = 0;
    this.playCount = 0;
  }

  play() {
    this.playCount += 1;
    return Promise.resolve();
  }

  pause() {
    this.pauseCount += 1;
  }
}

function createFocusLikeEvent(type, relatedTarget) {
  const event = new Event(type);
  Object.defineProperty(event, "relatedTarget", {
    configurable: true,
    enumerable: true,
    value: relatedTarget ?? null
  });
  return event;
}

async function flushPreviewWork() {
  await new Promise((resolve) => setTimeout(resolve, 0));
  await new Promise((resolve) => setTimeout(resolve, 0));
}

test("installHoverVideoPreview plays on hover and resets on leave", async () => {
  const container = new FakeTarget();
  const video = new FakeVideo();
  const controller = installHoverVideoPreview(container, video);

  assert.equal(video.controls, false);

  container.dispatchEvent(new Event("mouseenter"));
  await flushPreviewWork();
  assert.equal(video.controls, true);
  assert.equal(video.playCount, 1);

  container.dispatchEvent(new Event("mouseleave"));
  assert.equal(video.controls, false);
  assert.equal(video.pauseCount, 1);
  assert.equal(video.currentTime, 0);

  controller.destroy();
});

test("installHoverVideoPreview keeps playing while focus stays inside the card", async () => {
  const container = new FakeTarget();
  const childNode = {};
  container.nodes.add(childNode);
  const video = new FakeVideo();
  const controller = installHoverVideoPreview(container, video);

  container.dispatchEvent(createFocusLikeEvent("focusin"));
  await flushPreviewWork();
  assert.equal(video.controls, true);
  assert.equal(video.playCount, 1);

  container.dispatchEvent(createFocusLikeEvent("focusout", childNode));
  assert.equal(video.controls, true);
  assert.equal(video.pauseCount, 0);

  container.dispatchEvent(createFocusLikeEvent("focusout"));
  assert.equal(video.controls, false);
  assert.equal(video.pauseCount, 1);
  assert.equal(video.currentTime, 0);

  controller.destroy();
});

test("installHoverVideoPreview pauses when the card leaves the viewport", async () => {
  const container = new FakeTarget();
  const video = new FakeVideo();
  const controller = installHoverVideoPreview(container, video);

  container.dispatchEvent(new Event("mouseenter"));
  await flushPreviewWork();
  assert.equal(video.controls, true);
  assert.equal(video.playCount, 1);

  controller.handleVisibilityChange(false);
  assert.equal(video.controls, false);
  assert.equal(video.pauseCount, 1);
  assert.equal(video.currentTime, 0);

  controller.destroy();
});
