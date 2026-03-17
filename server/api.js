import path from "node:path";

import express from "express";

import { registerFeedRoutes } from "./http/api/feedRoutes.js";
import { registerMediaRoutes } from "./http/api/mediaRoutes.js";
import { registerSystemRoutes } from "./http/api/systemRoutes.js";
import { registerPageRoutes } from "./http/pageRoutes.js";
import { createMediaService } from "./services/mediaService.js";

export function createApiApp({
  db,
  publicDir,
  sharedDir = path.resolve(publicDir, "..", "shared"),
  crawler,
  logger = console,
  fetchImpl,
  mediaProxyTimeoutMs,
  mediaService = createMediaService({
    db,
    fetchImpl,
    timeoutMs: mediaProxyTimeoutMs
  })
}) {
  const app = express();
  app.disable("x-powered-by");
  app.use(express.json());

  registerFeedRoutes(app, { db });
  registerMediaRoutes(app, { mediaService });
  registerSystemRoutes(app, { crawler });
  registerPageRoutes(app, { db, publicDir, sharedDir });

  app.use((error, _request, response, _next) => {
    logger.error(error);
    response.status(500).json({
      error: error.message || "Unexpected error"
    });
  });

  return app;
}
