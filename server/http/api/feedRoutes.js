import { getFeedPage } from "../../services/feedService.js";

export function registerFeedRoutes(app, { db }) {
  app.get("/api/feed", (request, response) => {
    const result = getFeedPage({
      db,
      cursor: request.query.cursor || null,
      sourceHandle: request.query.source || "",
      limit: request.query.limit || 12
    });
    response.json({
      items: result.items,
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
}
