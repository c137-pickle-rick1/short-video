import { Readable } from "node:stream";

export function registerMediaRoutes(app, { mediaService }) {
  app.get("/api/media/:tweetId", async (request, response, next) => {
    const requestAbortController = new AbortController();
    request.once("close", () => {
      if (!response.writableEnded) {
        requestAbortController.abort();
      }
    });

    try {
      const result = await mediaService.getMediaStream({
        tweetId: request.params.tweetId,
        rangeHeader: request.headers.range || "",
        abortSignal: requestAbortController.signal
      });

      if (result.kind === "json") {
        response.status(result.status).json(result.body);
        return;
      }

      response.status(result.status);
      for (const [headerName, value] of Object.entries(result.headers)) {
        response.setHeader(headerName, value);
      }

      if (!result.body) {
        response.end();
        return;
      }

      Readable.fromWeb(result.body).pipe(response);
    } catch (error) {
      if (error?.code === "client_aborted") {
        return;
      }

      next(error);
    }
  });
}
