import { createServer } from "node:http";

import { createApiApp } from "../api.js";

export function createHttpRuntime({
  config,
  db,
  crawler,
  logger = console,
  enabled = true,
  createServerImpl = createServer
} = {}) {
  if (!enabled) {
    return {
      app: null,
      httpServer: null,
      async start() {
        return null;
      },
      async close() {}
    };
  }

  const app = createApiApp({
    db,
    publicDir: config.publicDir,
    crawler,
    mediaProxyTimeoutMs: config.mediaProxyTimeoutMs,
    logger
  });
  const httpServer = createServerImpl(app);

  return {
    app,
    httpServer,
    async start(port = config.port) {
      if (httpServer.listening) {
        return httpServer;
      }

      await new Promise((resolve, reject) => {
        httpServer.once("error", reject);
        httpServer.listen(port, () => {
          httpServer.off("error", reject);
          resolve();
        });
      });

      return httpServer;
    },
    async close() {
      if (!httpServer.listening) {
        return;
      }

      await new Promise((resolve) => httpServer.close(() => resolve()));
    }
  };
}
