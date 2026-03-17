async function copyText(text) {
  if (navigator.clipboard?.writeText) {
    await navigator.clipboard.writeText(text);
    return;
  }

  const textarea = document.createElement("textarea");
  textarea.value = text;
  textarea.setAttribute("readonly", "true");
  textarea.style.position = "absolute";
  textarea.style.left = "-9999px";
  document.body.append(textarea);
  textarea.select();
  document.execCommand("copy");
  textarea.remove();
}

function setupCopyButtons() {
  const buttons = document.querySelectorAll("[data-ui-copy]");

  for (const button of buttons) {
    button.addEventListener("click", async () => {
      const story = button.closest("[data-ui-story]");
      const codeNode = story?.querySelector("[data-ui-code]");
      if (!(codeNode instanceof HTMLElement)) {
        return;
      }

      const originalLabel = button.textContent || "Copy";

      try {
        await copyText(codeNode.textContent || "");
        button.textContent = "Copied";
      } catch (error) {
        console.error("Failed to copy UI story code", error);
        button.textContent = "Failed";
      }

      window.setTimeout(() => {
        button.textContent = originalLabel;
      }, 1400);
    });
  }
}

setupCopyButtons();
