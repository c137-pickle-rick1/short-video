import { requestJson } from "./http.js";

const dialog = document.querySelector("[data-profile-video-upload='true']");
const dialogPanel = document.querySelector("[data-profile-video-upload-panel='true']");
const form = document.querySelector("[data-profile-video-upload-form='true']");
const triggerButton = document.querySelector("[data-profile-video-upload-trigger='true']");
const fileInput = document.querySelector("[data-profile-video-upload-input='true']");
const titleInput = document.querySelector("[data-profile-video-upload-title-input='true']");
const tagsInput = document.querySelector("[data-profile-video-upload-tags-input='true']");
const submitButton = document.querySelector("[data-profile-video-upload-submit='true']");
const fileNameNode = document.querySelector("[data-profile-video-upload-file-name='true']");
const generalErrorNode = document.querySelector("[data-profile-video-upload-error='general']");
const videoErrorNode = document.querySelector("[data-profile-video-upload-error='video']");
const titleErrorNode = document.querySelector("[data-profile-video-upload-error='title']");
const tagsErrorNode = document.querySelector("[data-profile-video-upload-error='tags']");
const closeButtons = Array.from(document.querySelectorAll("[data-profile-video-upload-close='true']"));

if (
  dialog instanceof HTMLElement &&
  dialogPanel instanceof HTMLElement &&
  form instanceof HTMLFormElement &&
  triggerButton instanceof HTMLButtonElement &&
  fileInput instanceof HTMLInputElement &&
  titleInput instanceof HTMLInputElement &&
  tagsInput instanceof HTMLInputElement &&
  submitButton instanceof HTMLButtonElement &&
  fileNameNode instanceof HTMLElement &&
  generalErrorNode instanceof HTMLElement &&
  videoErrorNode instanceof HTMLElement &&
  titleErrorNode instanceof HTMLElement &&
  tagsErrorNode instanceof HTMLElement
) {
  let selectedFile = null;
  let isSubmitting = false;
  let previousBodyOverflow = "";
  let lastActiveElement = null;

  function normalizeValue(value) {
    return typeof value === "string" ? value.trim() : "";
  }

  function setNodeVisibility(node, visible) {
    node.hidden = !visible;
    node.classList.toggle("hidden", !visible);
  }

  function hideError(node) {
    node.textContent = "";
    setNodeVisibility(node, false);
  }

  function showError(node, message) {
    node.textContent = message;
    setNodeVisibility(node, true);
  }

  function clearErrors() {
    hideError(generalErrorNode);
    hideError(videoErrorNode);
    hideError(titleErrorNode);
    hideError(tagsErrorNode);
  }

  function syncFileName() {
    fileNameNode.textContent = selectedFile instanceof File ? selectedFile.name : "未选择视频文件";
  }

  function canSubmit() {
    return selectedFile instanceof File && normalizeValue(titleInput.value) !== "";
  }

  function syncSubmitButton() {
    submitButton.disabled = !canSubmit() || isSubmitting;
    submitButton.textContent = isSubmitting ? "上传中..." : "开始上传";
  }

  function resetForm() {
    selectedFile = null;
    fileInput.value = "";
    titleInput.value = "";
    tagsInput.value = "";
    clearErrors();
    syncFileName();
    syncSubmitButton();
  }

  function openDialog() {
    if (dialog.hidden) {
      previousBodyOverflow = document.body.style.overflow;
    }

    lastActiveElement = document.activeElement instanceof HTMLElement ? document.activeElement : triggerButton;
    resetForm();
    dialog.hidden = false;
    dialog.classList.remove("hidden");
    document.body.style.overflow = "hidden";
    titleInput.focus({ preventScroll: true });
  }

  function closeDialog() {
    if (dialog.hidden || isSubmitting) {
      return;
    }

    resetForm();
    dialog.classList.add("hidden");
    dialog.hidden = true;
    document.body.style.overflow = previousBodyOverflow;

    if (lastActiveElement instanceof HTMLElement) {
      lastActiveElement.focus({ preventScroll: true });
    }

    lastActiveElement = null;
  }

  function showValidationErrors(error) {
    const errors = error?.payload?.errors;
    let hasValidationErrors = false;

    if (!errors || typeof errors !== "object") {
      return false;
    }

    const fieldMap = {
      video: videoErrorNode,
      title: titleErrorNode,
      tags: tagsErrorNode
    };

    for (const [field, node] of Object.entries(fieldMap)) {
      const messages = errors[field];

      if (Array.isArray(messages) && typeof messages[0] === "string" && messages[0].trim() !== "") {
        showError(node, messages[0].trim());
        hasValidationErrors = true;
      } else {
        hideError(node);
      }
    }

    return hasValidationErrors;
  }

  function getGeneralErrorMessage(error) {
    if (typeof error?.message === "string" && error.message.trim() !== "") {
      return error.message.trim();
    }

    return "视频上传失败，请稍后重试。";
  }

  async function submitVideo() {
    if (!canSubmit() || isSubmitting || !(selectedFile instanceof File)) {
      return;
    }

    clearErrors();
    isSubmitting = true;
    syncSubmitButton();

    try {
      const formData = new FormData();
      formData.set("video", selectedFile);
      formData.set("title", normalizeValue(titleInput.value));

      const normalizedTags = normalizeValue(tagsInput.value);
      if (normalizedTags !== "") {
        formData.set("tags", normalizedTags);
      }

      const payload = await requestJson(String(dialog.dataset.profileVideoUploadAction || "/api/videos"), {
        method: "POST",
        body: formData
      });
      const redirectUrl = typeof payload?.redirectUrl === "string" ? payload.redirectUrl.trim() : "";

      if (redirectUrl !== "") {
        window.location.assign(redirectUrl);
        return;
      }

      window.location.reload();
    } catch (error) {
      if (!showValidationErrors(error)) {
        showError(generalErrorNode, getGeneralErrorMessage(error));
      }
    } finally {
      isSubmitting = false;
      syncSubmitButton();
    }
  }

  triggerButton.addEventListener("click", () => {
    openDialog();
  });

  for (const button of closeButtons) {
    if (button instanceof HTMLButtonElement) {
      button.addEventListener("click", () => {
        closeDialog();
      });
    }
  }

  fileInput.addEventListener("change", () => {
    const [file] = Array.from(fileInput.files || []);

    hideError(generalErrorNode);
    hideError(videoErrorNode);
    selectedFile = file instanceof File ? file : null;
    syncFileName();
    syncSubmitButton();
  });

  titleInput.addEventListener("input", () => {
    hideError(generalErrorNode);
    hideError(titleErrorNode);
    syncSubmitButton();
  });

  tagsInput.addEventListener("input", () => {
    hideError(generalErrorNode);
    hideError(tagsErrorNode);
    syncSubmitButton();
  });

  form.addEventListener("submit", (event) => {
    event.preventDefault();
    void submitVideo();
  });

  dialog.addEventListener("click", (event) => {
    if (event.target === dialog) {
      closeDialog();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && !dialog.hidden) {
      closeDialog();
    }
  });

  syncFileName();
  syncSubmitButton();
}
