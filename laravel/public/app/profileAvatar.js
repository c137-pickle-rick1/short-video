import { requestJson } from "./http.js";

const dialog = document.querySelector("[data-avatar-dialog='true']");
const dialogPanel = document.querySelector("[data-avatar-dialog-panel='true']");
const triggerButton = document.querySelector("[data-avatar-dialog-trigger='true']");
const fileInput = document.querySelector("[data-avatar-file-input='true']");
const submitButton = document.querySelector("[data-avatar-dialog-submit='true']");
const fileNameNode = document.querySelector("[data-avatar-file-name='true']");
const errorNode = document.querySelector("[data-avatar-dialog-error='true']");
const profileSlot = document.querySelector("[data-avatar-slot='profile']");
const previewSlot = document.querySelector("[data-avatar-slot='preview']");
const navSlots = Array.from(document.querySelectorAll("[data-avatar-slot='nav']"));
const closeButtons = Array.from(document.querySelectorAll("[data-avatar-dialog-close='true']"));

if (
  dialog instanceof HTMLElement &&
  dialogPanel instanceof HTMLElement &&
  triggerButton instanceof HTMLButtonElement &&
  fileInput instanceof HTMLInputElement &&
  submitButton instanceof HTMLButtonElement &&
  fileNameNode instanceof HTMLElement &&
  errorNode instanceof HTMLElement &&
  profileSlot instanceof HTMLElement &&
  previewSlot instanceof HTMLElement
) {
  let currentAvatarUrl = String(profileSlot.dataset.avatarUrl || "").trim();
  let selectedFile = null;
  let previewObjectUrl = null;
  let isSubmitting = false;
  let lastActiveElement = null;
  let previousBodyOverflow = "";

  function getInitial(slot) {
    const value = String(slot.dataset.avatarInitial || "").trim();
    return value !== "" ? value : "我";
  }

  function getLabel(slot) {
    const value = String(slot.dataset.avatarLabel || "").trim();
    return value !== "" ? value : "头像";
  }

  function createAvatarNode(kind, avatarUrl, initial, label) {
    if (avatarUrl) {
      const image = document.createElement("img");
      image.src = avatarUrl;
      image.loading = "lazy";

      if (kind === "nav") {
        image.alt = "";
        image.className = "size-6 rounded-full object-cover ring-1 ring-gray-200";
      } else if (kind === "preview") {
        image.alt = `${label} 的头像预览`;
        image.className = "h-36 w-36 rounded-full object-cover ring-1 ring-gray-200";
        image.referrerPolicy = "no-referrer";
      } else {
        image.alt = `${label} 的头像`;
        image.className = "h-20 w-20 rounded-full object-cover ring-1 ring-gray-200 sm:h-24 sm:w-24";
        image.referrerPolicy = "no-referrer";
      }

      return image;
    }

    const fallback = document.createElement("span");
    fallback.textContent = initial;

    if (kind === "nav") {
      fallback.className =
        "flex size-6 items-center justify-center rounded-full bg-gray-900 text-xs font-semibold leading-none text-white";
    } else if (kind === "preview") {
      fallback.className =
        "flex h-36 w-36 items-center justify-center rounded-full bg-gray-900 text-5xl font-semibold text-white";
    } else {
      fallback.className =
        "flex h-20 w-20 items-center justify-center rounded-full bg-gray-900 text-2xl font-semibold text-white sm:h-24 sm:w-24 sm:text-3xl";
    }

    return fallback;
  }

  function renderAvatarSlot(slot, avatarUrl) {
    const kind = String(slot.dataset.avatarKind || "").trim() || "profile";
    const label = getLabel(slot);
    const initial = getInitial(slot);

    slot.dataset.avatarUrl = avatarUrl || "";
    slot.replaceChildren(createAvatarNode(kind, avatarUrl, initial, label));
  }

  function syncAllAvatarSlots(avatarUrl) {
    renderAvatarSlot(profileSlot, avatarUrl);
    renderAvatarSlot(previewSlot, avatarUrl);

    for (const slot of navSlots) {
      if (slot instanceof HTMLElement) {
        renderAvatarSlot(slot, avatarUrl);
      }
    }
  }

  function revokePreviewObjectUrl() {
    if (previewObjectUrl) {
      URL.revokeObjectURL(previewObjectUrl);
      previewObjectUrl = null;
    }
  }

  function clearError() {
    errorNode.textContent = "";
    errorNode.hidden = true;
    errorNode.classList.add("hidden");
  }

  function showError(message) {
    errorNode.textContent = message;
    errorNode.hidden = false;
    errorNode.classList.remove("hidden");
  }

  function syncFileName() {
    fileNameNode.textContent = selectedFile ? selectedFile.name : "尚未选择图片";
  }

  function syncSubmitButton() {
    submitButton.disabled = !selectedFile || isSubmitting;
    submitButton.textContent = isSubmitting ? "保存中..." : "保存头像";
  }

  function resetSelection() {
    selectedFile = null;
    fileInput.value = "";
    syncFileName();
    syncSubmitButton();
    revokePreviewObjectUrl();
    renderAvatarSlot(previewSlot, currentAvatarUrl);
  }

  function openDialog() {
    if (dialog.hidden) {
      previousBodyOverflow = document.body.style.overflow;
    }

    clearError();
    resetSelection();
    lastActiveElement = document.activeElement instanceof HTMLElement ? document.activeElement : triggerButton;
    dialog.hidden = false;
    dialog.classList.remove("hidden");
    document.body.style.overflow = "hidden";
    dialogPanel.focus({ preventScroll: true });
  }

  function closeDialog() {
    if (dialog.hidden || isSubmitting) {
      return;
    }

    clearError();
    resetSelection();
    dialog.classList.add("hidden");
    dialog.hidden = true;
    document.body.style.overflow = previousBodyOverflow;

    if (lastActiveElement instanceof HTMLElement) {
      lastActiveElement.focus({ preventScroll: true });
    }

    lastActiveElement = null;
  }

  function getErrorMessage(error) {
    const avatarErrors = error?.payload?.errors?.avatar;
    if (Array.isArray(avatarErrors) && typeof avatarErrors[0] === "string" && avatarErrors[0].trim() !== "") {
      return avatarErrors[0].trim();
    }

    if (typeof error?.message === "string" && error.message.trim() !== "") {
      return error.message.trim();
    }

    return "头像上传失败，请稍后重试。";
  }

  async function submitAvatar() {
    if (!selectedFile || isSubmitting) {
      return;
    }

    clearError();
    isSubmitting = true;
    syncSubmitButton();

    try {
      const formData = new FormData();
      formData.set("avatar", selectedFile);

      const payload = await requestJson("/api/profile/avatar", {
        method: "POST",
        body: formData
      });

      currentAvatarUrl = String(payload?.avatarUrl || "").trim();
      syncAllAvatarSlots(currentAvatarUrl);
      closeDialog();
    } catch (error) {
      showError(getErrorMessage(error));
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
    clearError();
    revokePreviewObjectUrl();

    if (!(file instanceof File)) {
      selectedFile = null;
      syncFileName();
      syncSubmitButton();
      renderAvatarSlot(previewSlot, currentAvatarUrl);
      return;
    }

    selectedFile = file;
    previewObjectUrl = URL.createObjectURL(file);
    syncFileName();
    syncSubmitButton();
    renderAvatarSlot(previewSlot, previewObjectUrl);
  });

  submitButton.addEventListener("click", () => {
    void submitAvatar();
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
}
