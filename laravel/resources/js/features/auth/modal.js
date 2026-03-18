import { requestJson } from "../../shared/http.js";

const dialog = document.querySelector("[data-auth-modal='true']");
const dialogPanel = document.querySelector("[data-auth-modal-panel-shell='true']");

if (dialog instanceof HTMLElement && dialogPanel instanceof HTMLElement) {
  const closeButtons = Array.from(document.querySelectorAll("[data-auth-modal-close='true']"));
  const triggers = Array.from(document.querySelectorAll("[data-auth-modal-trigger='true']"));
  const tabButtons = Array.from(document.querySelectorAll("[data-auth-tab='true']"));
  const panelSwitches = Array.from(document.querySelectorAll("[data-auth-panel-switch]"));
  const forms = Array.from(document.querySelectorAll("[data-auth-form]"));
  const sendCodeButtons = Array.from(document.querySelectorAll("[data-auth-send-code-button]"));
  const statusNodes = new Map();
  const errorNodes = new Map();
  const cooldownTimers = new Map();
  let activePanel = String(dialog.dataset.authDefaultPanel || "login");
  let lastActiveElement = null;
  let previousBodyOverflow = "";

  for (const node of document.querySelectorAll("[data-auth-status]")) {
    if (node instanceof HTMLElement) {
      statusNodes.set(node.dataset.authStatus || "", node);
    }
  }

  for (const node of document.querySelectorAll("[data-auth-error]")) {
    if (node instanceof HTMLElement) {
      errorNodes.set(node.dataset.authError || "", node);
    }
  }

  function setNodeMessage(node, message) {
    if (!(node instanceof HTMLElement)) {
      return;
    }

    const normalizedMessage = typeof message === "string" ? message.trim() : "";
    node.textContent = normalizedMessage;
    node.hidden = normalizedMessage === "";
    node.classList.toggle("hidden", normalizedMessage === "");
  }

  function clearPanelFeedback(panel) {
    setNodeMessage(statusNodes.get(panel), "");
    setNodeMessage(errorNodes.get(panel), "");
  }

  function clearAllFeedback() {
    for (const panel of ["login", "register", "password_reset"]) {
      clearPanelFeedback(panel);
    }
  }

  function getPanelNode(panel) {
    return document.querySelector(`[data-auth-panel="${panel}"]`);
  }

  function setActivePanel(panel) {
    activePanel = ["login", "register", "password_reset"].includes(panel) ? panel : "login";
    dialog.dataset.authDefaultPanel = activePanel;

    for (const button of tabButtons) {
      if (!(button instanceof HTMLButtonElement)) {
        continue;
      }

      const isActive = button.dataset.authPanelSwitch === activePanel;
      button.setAttribute("aria-pressed", String(isActive));
      button.classList.toggle("bg-white", isActive);
      button.classList.toggle("text-gray-950", isActive);
      button.classList.toggle("shadow-sm", isActive);
      button.classList.toggle("text-gray-500", !isActive);
    }

    for (const panelName of ["login", "register", "password_reset"]) {
      const panelNode = getPanelNode(panelName);
      if (!(panelNode instanceof HTMLElement)) {
        continue;
      }

      const visible = panelName === activePanel;
      panelNode.hidden = !visible;
      panelNode.classList.toggle("hidden", !visible);
    }
  }

  function openModal(panel = activePanel) {
    if (dialog.hidden) {
      previousBodyOverflow = document.body.style.overflow;
    }

    lastActiveElement = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    setActivePanel(panel);
    dialog.hidden = false;
    dialog.classList.remove("hidden");
    document.body.style.overflow = "hidden";
    dialogPanel.focus({ preventScroll: true });
  }

  function closeModal() {
    const closeUrl = String(dialog.dataset.authCloseUrl || "").trim();
    if (closeUrl !== "") {
      window.location.assign(closeUrl);
      return;
    }

    dialog.classList.add("hidden");
    dialog.hidden = true;
    document.body.style.overflow = previousBodyOverflow;
    clearAllFeedback();

    if (lastActiveElement instanceof HTMLElement) {
      lastActiveElement.focus({ preventScroll: true });
    }
  }

  function findForm(panel) {
    return document.querySelector(`[data-auth-form="${panel}"]`);
  }

  function setFormBusy(panel, isBusy, buttonText) {
    const form = findForm(panel);
    if (!(form instanceof HTMLFormElement)) {
      return;
    }

    for (const field of form.elements) {
      if (field instanceof HTMLInputElement || field instanceof HTMLButtonElement) {
        if (field.dataset.authSendCodeButton === panel && !isBusy) {
          continue;
        }

        if (field.type !== "button") {
          field.disabled = isBusy;
        }
      }
    }

    const submitButton = form.querySelector(`[data-auth-submit="${panel}"]`);
    if (submitButton instanceof HTMLButtonElement) {
      submitButton.disabled = isBusy;
      submitButton.textContent = isBusy ? buttonText : submitButton.dataset.defaultLabel || submitButton.textContent;
    }
  }

  function getPrimaryMessage(error, fallback = "请求失败，请稍后重试。") {
    const payloadErrors = error?.payload?.errors;
    if (payloadErrors && typeof payloadErrors === "object") {
      for (const messages of Object.values(payloadErrors)) {
        if (Array.isArray(messages) && typeof messages[0] === "string" && messages[0].trim() !== "") {
          return messages[0].trim();
        }
      }
    }

    if (typeof error?.message === "string" && error.message.trim() !== "") {
      return error.message.trim();
    }

    return fallback;
  }

  function startCooldown(panel, seconds) {
    const button = document.querySelector(`[data-auth-send-code-button="${panel}"]`);
    if (!(button instanceof HTMLButtonElement)) {
      return;
    }

    const totalSeconds = Math.max(1, Number(seconds) || 60);
    const endTime = Date.now() + totalSeconds * 1000;

    const syncLabel = () => {
      const remainingSeconds = Math.max(0, Math.ceil((endTime - Date.now()) / 1000));
      if (remainingSeconds <= 0) {
        button.disabled = false;
        button.textContent = "重新发送验证码";
        window.clearInterval(cooldownTimers.get(panel));
        cooldownTimers.delete(panel);
        return;
      }

      button.disabled = true;
      button.textContent = `${remainingSeconds}s 后重发`;
    };

    if (cooldownTimers.has(panel)) {
      window.clearInterval(cooldownTimers.get(panel));
    }

    syncLabel();
    cooldownTimers.set(panel, window.setInterval(syncLabel, 1000));
  }

  async function sendCode(panel) {
    const form = findForm(panel);
    if (!(form instanceof HTMLFormElement)) {
      return;
    }

    clearPanelFeedback(panel);

    const formData = new FormData();
    formData.set("email", String(new FormData(form).get("email") || "").trim());
    formData.set("purpose", panel === "register" ? "register" : "password_reset");

    try {
      const payload = await requestJson(String(dialog.dataset.authEmailCodeAction || "/auth/email-codes"), {
        method: "POST",
        headers: {
          Accept: "application/json"
        },
        body: formData
      });

      setNodeMessage(statusNodes.get(panel), String(payload?.message || "验证码已发送，请查看邮箱。"));
      startCooldown(panel, Number(payload?.cooldownSeconds || 60));
    } catch (error) {
      setNodeMessage(errorNodes.get(panel), getPrimaryMessage(error));
    }
  }

  async function submitForm(panel, form) {
    clearPanelFeedback(panel);
    const formData = new FormData(form);
    setFormBusy(panel, true, panel === "login" ? "登录中..." : panel === "register" ? "注册中..." : "重置中...");

    try {
      const payload = await requestJson(form.action, {
        method: form.method || "POST",
        headers: {
          Accept: "application/json"
        },
        body: formData
      });

      if (panel === "password_reset") {
        const loginForm = findForm("login");
        if (loginForm instanceof HTMLFormElement) {
          const email = String(formData.get("email") || "").trim();
          const loginEmailInput = loginForm.elements.namedItem("email");
          if (loginEmailInput instanceof HTMLInputElement) {
            loginEmailInput.value = email;
          }
        }

        form.reset();
        setActivePanel("login");
        setNodeMessage(statusNodes.get("login"), String(payload?.message || "密码已重置，请使用新密码登录。"));
        return;
      }

      window.location.reload();
    } catch (error) {
      setNodeMessage(errorNodes.get(panel), getPrimaryMessage(error));
    } finally {
      setFormBusy(panel, false, "");
    }
  }

  for (const form of forms) {
    if (!(form instanceof HTMLFormElement)) {
      continue;
    }

    const panel = String(form.dataset.authForm || "");
    const submitButton = form.querySelector(`[data-auth-submit="${panel}"]`);
    if (submitButton instanceof HTMLButtonElement) {
      submitButton.dataset.defaultLabel = submitButton.textContent || "";
    }

    form.addEventListener("submit", (event) => {
      event.preventDefault();
      void submitForm(panel, form);
    });
  }

  for (const button of sendCodeButtons) {
    if (!(button instanceof HTMLButtonElement)) {
      continue;
    }

    button.addEventListener("click", () => {
      void sendCode(String(button.dataset.authSendCodeButton || ""));
    });
  }

  for (const trigger of triggers) {
    if (!(trigger instanceof HTMLElement)) {
      continue;
    }

    trigger.addEventListener("click", (event) => {
      event.preventDefault();
      openModal(String(trigger.dataset.authModalPanel || "login"));
    });
  }

  window.addEventListener("shortvideo:auth-open", (event) => {
    const panel = String(event?.detail?.panel || activePanel || "login");
    openModal(panel);
  });

  for (const button of closeButtons) {
    if (button instanceof HTMLButtonElement) {
      button.addEventListener("click", () => {
        closeModal();
      });
    }
  }

  for (const button of panelSwitches) {
    if (button instanceof HTMLButtonElement) {
      button.addEventListener("click", () => {
        clearAllFeedback();
        setActivePanel(String(button.dataset.authPanelSwitch || "login"));
      });
    }
  }

  dialog.addEventListener("click", (event) => {
    if (event.target === dialog) {
      closeModal();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && !dialog.hidden) {
      closeModal();
    }
  });

  if (dialog.dataset.authModalStartOpen === "true") {
    openModal(activePanel);
  } else {
    setActivePanel(activePanel);
  }
}
