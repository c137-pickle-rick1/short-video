import { requestJson } from "./http.js";

const dialog = document.querySelector("[data-profile-editor='true']");
const dialogPanel = document.querySelector("[data-profile-editor-panel='true']");
const form = document.querySelector("[data-profile-editor-form='true']");
const triggerButton = document.querySelector("[data-profile-editor-trigger='true']");
const fileInput = document.querySelector("[data-profile-editor-avatar-input='true']");
const nameInput = document.querySelector("[data-profile-editor-name-input='true']");
const bioInput = document.querySelector("[data-profile-editor-bio-input='true']");
const submitButton = document.querySelector("[data-profile-editor-submit='true']");
const fileNameNode = document.querySelector("[data-profile-editor-avatar-file-name='true']");
const profileNameNode = document.querySelector("[data-profile-name='true']");
const profileBioNode = document.querySelector("[data-profile-bio='true']");
const generalErrorNode = document.querySelector("[data-profile-editor-error='general']");
const avatarErrorNode = document.querySelector("[data-profile-editor-error='avatar']");
const nameErrorNode = document.querySelector("[data-profile-editor-error='name']");
const bioErrorNode = document.querySelector("[data-profile-editor-error='bio']");
const profileSlot = document.querySelector("[data-avatar-slot='profile']");
const previewSlot = document.querySelector("[data-avatar-slot='preview']");
const navSlots = Array.from(document.querySelectorAll("[data-avatar-slot='nav']"));
const closeButtons = Array.from(document.querySelectorAll("[data-profile-editor-close='true']"));

if (
  dialog instanceof HTMLElement &&
  dialogPanel instanceof HTMLElement &&
  form instanceof HTMLFormElement &&
  triggerButton instanceof HTMLButtonElement &&
  fileInput instanceof HTMLInputElement &&
  nameInput instanceof HTMLInputElement &&
  bioInput instanceof HTMLTextAreaElement &&
  submitButton instanceof HTMLButtonElement &&
  fileNameNode instanceof HTMLElement &&
  profileNameNode instanceof HTMLElement &&
  profileBioNode instanceof HTMLElement &&
  generalErrorNode instanceof HTMLElement &&
  avatarErrorNode instanceof HTMLElement &&
  nameErrorNode instanceof HTMLElement &&
  bioErrorNode instanceof HTMLElement &&
  profileSlot instanceof HTMLElement &&
  previewSlot instanceof HTMLElement
) {
  let currentState = {
    avatarUrl: String(profileSlot.dataset.avatarUrl || "").trim(),
    name: normalizeNameValue(nameInput.value),
    bio: normalizeBioValue(bioInput.value)
  };
  let selectedFile = null;
  let previewObjectUrl = null;
  let isSubmitting = false;
  let lastActiveElement = null;
  let previousBodyOverflow = "";

  function normalizeNameValue(value) {
    return typeof value === "string" ? value.trim() : "";
  }

  function normalizeBioValue(value) {
    return typeof value === "string" ? value.trim() : "";
  }

  function computeInitial(label) {
    const normalizedLabel = String(label || "").trim().replace(/^@+/, "");
    const [firstCharacter = "我"] = Array.from(normalizedLabel);

    return String(firstCharacter).toUpperCase();
  }

  function getInitial(slot) {
    const value = String(slot.dataset.avatarInitial || "").trim();

    return value !== "" ? value : "我";
  }

  function getLabel(slot) {
    const value = String(slot.dataset.avatarLabel || "").trim();

    return value !== "" ? value : "头像";
  }

  function setAvatarMetadata(slot, label) {
    if (!(slot instanceof HTMLElement)) {
      return;
    }

    slot.dataset.avatarLabel = label;
    slot.dataset.avatarInitial = computeInitial(label);
  }

  function syncAvatarMetadata(label) {
    setAvatarMetadata(profileSlot, label);
    setAvatarMetadata(previewSlot, label);

    for (const slot of navSlots) {
      if (slot instanceof HTMLElement) {
        setAvatarMetadata(slot, label);
      }
    }
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
    hideError(avatarErrorNode);
    hideError(nameErrorNode);
    hideError(bioErrorNode);
  }

  function revokePreviewObjectUrl() {
    if (previewObjectUrl) {
      URL.revokeObjectURL(previewObjectUrl);
      previewObjectUrl = null;
    }
  }

  function syncFileName() {
    fileNameNode.textContent = selectedFile ? selectedFile.name : "未选择新图片";
  }

  function isDirty() {
    return (
      selectedFile instanceof File ||
      normalizeNameValue(nameInput.value) !== currentState.name ||
      normalizeBioValue(bioInput.value) !== currentState.bio
    );
  }

  function syncSubmitButton() {
    submitButton.disabled = !isDirty() || isSubmitting;
    submitButton.textContent = isSubmitting ? "保存中..." : "保存资料";
  }

  function syncPreviewSlot() {
    if (selectedFile instanceof File && previewObjectUrl) {
      renderAvatarSlot(previewSlot, previewObjectUrl);
      return;
    }

    renderAvatarSlot(previewSlot, currentState.avatarUrl);
  }

  function resetForm() {
    selectedFile = null;
    fileInput.value = "";
    nameInput.value = currentState.name;
    bioInput.value = currentState.bio;
    syncAvatarMetadata(currentState.name);
    syncFileName();
    revokePreviewObjectUrl();
    clearErrors();
    syncPreviewSlot();
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
    nameInput.focus({ preventScroll: true });
    nameInput.setSelectionRange(nameInput.value.length, nameInput.value.length);
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

  function syncProfileBioNode(bio) {
    profileBioNode.textContent = bio;
    setNodeVisibility(profileBioNode, bio !== "");
  }

  function syncProfileView(nextName, nextBio, nextAvatarUrl) {
    currentState = {
      name: nextName,
      bio: nextBio,
      avatarUrl: nextAvatarUrl
    };

    profileNameNode.textContent = nextName;
    syncProfileBioNode(nextBio);
    syncAvatarMetadata(nextName);
    syncAllAvatarSlots(nextAvatarUrl);
  }

  function showValidationErrors(error) {
    const errors = error?.payload?.errors;
    let hasValidationErrors = false;

    if (!errors || typeof errors !== "object") {
      return false;
    }

    const fieldMap = {
      avatar: avatarErrorNode,
      name: nameErrorNode,
      bio: bioErrorNode
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

    return "资料保存失败，请稍后重试。";
  }

  async function submitProfile() {
    if (!isDirty() || isSubmitting) {
      return;
    }

    clearErrors();
    isSubmitting = true;
    syncSubmitButton();

    try {
      const normalizedName = normalizeNameValue(nameInput.value);
      const normalizedBio = normalizeBioValue(bioInput.value);
      const formData = new FormData();

      formData.set("name", normalizedName);
      formData.set("bio", normalizedBio);

      if (selectedFile instanceof File) {
        formData.set("avatar", selectedFile);
      }

      const payload = await requestJson("/api/profile", {
        method: "POST",
        body: formData
      });
      const nextName = normalizeNameValue(String(payload?.name || normalizedName));
      const nextBio = typeof payload?.bio === "string" ? normalizeBioValue(payload.bio) : "";
      const nextAvatarUrl = String(payload?.avatarUrl || "").trim();

      syncProfileView(nextName, nextBio, nextAvatarUrl);
      isSubmitting = false;
      syncSubmitButton();
      closeDialog();
      return;
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
    hideError(avatarErrorNode);
    revokePreviewObjectUrl();

    if (!(file instanceof File)) {
      selectedFile = null;
      syncFileName();
      syncPreviewSlot();
      syncSubmitButton();
      return;
    }

    selectedFile = file;
    previewObjectUrl = URL.createObjectURL(file);
    syncFileName();
    syncPreviewSlot();
    syncSubmitButton();
  });

  nameInput.addEventListener("input", () => {
    hideError(generalErrorNode);
    hideError(nameErrorNode);
    syncSubmitButton();

    if (!selectedFile && currentState.avatarUrl === "") {
      const draftName = normalizeNameValue(nameInput.value) || currentState.name;

      setAvatarMetadata(previewSlot, draftName);
      renderAvatarSlot(previewSlot, "");
    }
  });

  bioInput.addEventListener("input", () => {
    hideError(generalErrorNode);
    hideError(bioErrorNode);
    syncSubmitButton();
  });

  form.addEventListener("submit", (event) => {
    event.preventDefault();
    void submitProfile();
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

  syncProfileBioNode(currentState.bio);
  syncAvatarMetadata(currentState.name);
  syncSubmitButton();
}
