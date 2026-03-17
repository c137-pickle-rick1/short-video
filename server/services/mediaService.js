import { createAppError } from "../errors.js";

export const DEFAULT_MEDIA_PROXY_TIMEOUT_MS = 15000;

const PROXY_RESPONSE_HEADERS = [
  "accept-ranges",
  "cache-control",
  "content-length",
  "content-range",
  "content-type",
  "etag",
  "last-modified"
];

function createRequestHeaders(rangeHeader = "") {
  return {
    Range: rangeHeader,
    Referer: "https://x.com/",
    "User-Agent":
      "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36"
  };
}

function collectResponseHeaders(headers) {
  const values = {};

  for (const headerName of PROXY_RESPONSE_HEADERS) {
    const value = headers.get(headerName);
    if (value) {
      values[headerName] = value;
    }
  }

  return values;
}

function createTimeoutController({ timeoutMs, abortSignal }) {
  const controller = new AbortController();
  let didTimeout = false;
  let disposeAbortListener = () => {};
  let rejectAbortPromise = () => {};

  const abortPromise = new Promise((_, reject) => {
    rejectAbortPromise = reject;
  });

  if (abortSignal) {
    if (abortSignal.aborted) {
      controller.abort(abortSignal.reason);
      rejectAbortPromise(createAppError("client_aborted", "Client closed media request"));
    } else {
      const onAbort = () => {
        controller.abort(abortSignal.reason);
        rejectAbortPromise(createAppError("client_aborted", "Client closed media request"));
      };
      abortSignal.addEventListener("abort", onAbort, { once: true });
      disposeAbortListener = () => abortSignal.removeEventListener("abort", onAbort);
    }
  }

  const timeoutId = setTimeout(() => {
    didTimeout = true;
    controller.abort(new Error(`media proxy timeout after ${timeoutMs}ms`));
    rejectAbortPromise(new Error(`media proxy timeout after ${timeoutMs}ms`));
  }, timeoutMs);

  return {
    signal: controller.signal,
    abortPromise,
    didTimeout() {
      return didTimeout;
    },
    dispose() {
      clearTimeout(timeoutId);
      disposeAbortListener();
    }
  };
}

export function createMediaService({
  db,
  fetchImpl = fetch,
  timeoutMs = DEFAULT_MEDIA_PROXY_TIMEOUT_MS
} = {}) {
  return {
    async getMediaStream({ tweetId, rangeHeader = "", abortSignal = null }) {
      const media = db.getPrimaryMedia(tweetId);
      if (!media?.url) {
        return {
          kind: "json",
          status: 404,
          body: { error: "Video not found" }
        };
      }

      const timeoutController = createTimeoutController({
        timeoutMs,
        abortSignal
      });

      try {
        const upstream = await Promise.race([
          fetchImpl(media.url, {
            headers: createRequestHeaders(rangeHeader),
            signal: timeoutController.signal
          }),
          timeoutController.abortPromise
        ]);

        if (!upstream.ok && upstream.status !== 206) {
          return {
            kind: "json",
            status: upstream.status,
            body: {
              error: `Upstream video request failed with HTTP ${upstream.status}`
            }
          };
        }

        return {
          kind: "stream",
          status: upstream.status,
          headers: collectResponseHeaders(upstream.headers),
          body: upstream.body
        };
      } catch (error) {
        if (timeoutController.didTimeout()) {
          return {
            kind: "json",
            status: 504,
            body: {
              error: `Upstream video request timed out after ${timeoutMs}ms`
            }
          };
        }

        if (error?.code === "client_aborted" || abortSignal?.aborted) {
          throw createAppError("client_aborted", "Client closed media request");
        }

        throw error;
      } finally {
        timeoutController.dispose();
      }
    }
  };
}
