function getCsrfToken() {
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content");
  return typeof token === "string" ? token.trim() : "";
}

function normalizeHeaders(headers = {}) {
  return headers instanceof Headers ? headers : new Headers(headers);
}

export async function requestJson(url, options = {}) {
  const headers = normalizeHeaders(options.headers);
  if (!headers.has("Accept")) {
    headers.set("Accept", "application/json");
  }

  const method = String(options.method || "GET").toUpperCase();
  const hasJsonBody =
    options.body !== undefined &&
    options.body !== null &&
    !(options.body instanceof FormData) &&
    !(options.body instanceof Blob) &&
    typeof options.body !== "string";

  if (hasJsonBody && !headers.has("Content-Type")) {
    headers.set("Content-Type", "application/json");
  }

  if (!["GET", "HEAD"].includes(method)) {
    const csrfToken = getCsrfToken();
    if (csrfToken && !headers.has("X-CSRF-TOKEN")) {
      headers.set("X-CSRF-TOKEN", csrfToken);
    }
  }

  const response = await fetch(url, {
    credentials: "same-origin",
    ...options,
    method,
    headers,
    body: hasJsonBody ? JSON.stringify(options.body) : options.body
  });

  const contentType = response.headers.get("content-type") || "";
  const payload = contentType.includes("application/json") ? await response.json() : null;

  if (!response.ok) {
    const message =
      (payload && typeof payload.message === "string" && payload.message.trim() !== ""
        ? payload.message
        : `Request failed: ${response.status}`) || `Request failed: ${response.status}`;
    const error = new Error(message);
    error.status = response.status;
    error.payload = payload;
    throw error;
  }

  return payload;
}
