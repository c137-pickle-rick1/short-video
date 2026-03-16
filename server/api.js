import path from "node:path";
import { Readable } from "node:stream";

import express from "express";

export function createApiApp({ db, publicDir, crawler, logger = console }) {
  const app = express();
  app.disable("x-powered-by");
  app.use(express.json());

  app.get("/api/feed", (request, response) => {
    const limit = Math.min(Math.max(Number(request.query.limit || 12), 1), 24);
    const sourceHandle =
      typeof request.query.source === "string" && request.query.source
        ? request.query.source.toLowerCase()
        : null;
    const result = db.getFeed({
      cursor: request.query.cursor || null,
      sourceHandle,
      limit
    });
    response.json({
      items: result.items.map((item) => ({
        ...item,
        videoUrl: item.videoUrl ? `/api/media/${item.tweetId}` : null
      })),
      nextCursor: result.nextCursor
    });
  });

  app.get("/api/sources", (_request, response) => {
    response.json({
      items: db.getSourcesOverview()
    });
  });

  app.get("/api/stats", (_request, response) => {
    response.json(db.getStats());
  });

  app.get("/api/media/:tweetId", async (request, response, next) => {
    try {
      const media = db.getPrimaryMedia(request.params.tweetId);
      if (!media?.url) {
        response.status(404).json({ error: "Video not found" });
        return;
      }

      const upstream = await fetch(media.url, {
        headers: {
          Range: request.headers.range || "",
          Referer: "https://x.com/",
          "User-Agent":
            "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36"
        }
      });

      if (!upstream.ok && upstream.status !== 206) {
        response.status(upstream.status).json({
          error: `Upstream video request failed with HTTP ${upstream.status}`
        });
        return;
      }

      response.status(upstream.status);
      for (const headerName of [
        "accept-ranges",
        "cache-control",
        "content-length",
        "content-range",
        "content-type",
        "etag",
        "last-modified"
      ]) {
        const value = upstream.headers.get(headerName);
        if (value) {
          response.setHeader(headerName, value);
        }
      }

      if (!upstream.body) {
        response.end();
        return;
      }

      Readable.fromWeb(upstream.body).pipe(response);
    } catch (error) {
      next(error);
    }
  });

  app.get("/api/health", (_request, response) => {
    response.json({
      ok: true,
      backoffUntil: crawler?.backoffUntil?.toISOString?.() || null,
      backoffReason: crawler?.backoffReason || null
    });
  });

  app.use(express.static(publicDir));

  app.get("*", (request, response, next) => {
    if (request.path.startsWith("/api/")) {
      next();
      return;
    }

    response.sendFile(path.join(publicDir, "index.html"));
  });

  app.use((error, _request, response, _next) => {
    logger.error(error);
    response.status(500).json({
      error: error.message || "Unexpected error"
    });
  });

  return app;
}
