export function registerSystemRoutes(app, { crawler }) {
  app.get("/api/health", (_request, response) => {
    const backoffState = crawler?.getBackoffState?.() || {
      backoffUntil: crawler?.backoffUntil?.toISOString?.() || null,
      backoffReason: crawler?.backoffReason || null
    };

    response.json({
      ok: true,
      backoffUntil: backoffState.backoffUntil,
      backoffReason: backoffState.backoffReason
    });
  });
}
