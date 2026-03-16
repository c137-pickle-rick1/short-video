import fs from "node:fs";
import path from "node:path";

function normalizeHandle(handle) {
  return String(handle || "")
    .trim()
    .replace(/^@/, "")
    .toLowerCase();
}

function parseBoolean(value) {
  if (typeof value === "boolean") {
    return value;
  }

  return !["0", "false", "no", "off"].includes(String(value || "").toLowerCase());
}

export function resolveAppConfig(env = process.env) {
  const cwd = process.cwd();
  const port = Number(env.PORT || 3000);
  const dbPath = path.resolve(cwd, env.DB_PATH || "./data/app.db");
  const browserProfileDir = path.resolve(
    cwd,
    env.BROWSER_PROFILE_DIR || "./data/browser-profile"
  );
  const storageStatePath = path.resolve(
    cwd,
    env.X_STORAGE_STATE_PATH || "./data/x-storage-state.json"
  );
  const scrapeIntervalMinutes = Number(env.SCRAPE_INTERVAL_MINUTES || 10);
  const sourceConfigPath = path.resolve(cwd, "./config/sources.json");
  const publicDir = path.resolve(cwd, "./public");

  return {
    cwd,
    port,
    dbPath,
    browserProfileDir,
    storageStatePath,
    scrapeIntervalMinutes,
    sourceConfigPath,
    publicDir
  };
}

export function ensureAppDirectories(config) {
  fs.mkdirSync(path.dirname(config.dbPath), { recursive: true });
  fs.mkdirSync(config.browserProfileDir, { recursive: true });
  fs.mkdirSync(path.dirname(config.sourceConfigPath), { recursive: true });
}

export function loadSourceConfig(config) {
  if (!fs.existsSync(config.sourceConfigPath)) {
    return [];
  }

  const raw = fs.readFileSync(config.sourceConfigPath, "utf8");
  const parsed = JSON.parse(raw);

  if (!Array.isArray(parsed)) {
    throw new Error("config/sources.json must contain an array");
  }

  return parsed
    .map((source) => ({
      handle: normalizeHandle(source.handle),
      enabled: parseBoolean(source.enabled)
    }))
    .filter((source) => source.handle);
}

export function normalizeSourceHandle(handle) {
  return normalizeHandle(handle);
}
