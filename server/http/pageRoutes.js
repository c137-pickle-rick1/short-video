import express from "express";

import { getHomePageViewModel, renderHomePage } from "../pages/home/index.js";

export function registerPageRoutes(app, { db, publicDir, sharedDir }) {
  app.use("/shared", express.static(sharedDir));
  app.use(express.static(publicDir, { index: false }));

  app.get("*", (request, response, next) => {
    if (request.path.startsWith("/api/")) {
      next();
      return;
    }

    const viewModel = getHomePageViewModel({
      db,
      sourceHandle: request.query.source
    });
    response.type("html").send(renderHomePage(viewModel));
  });
}
