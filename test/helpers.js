import fs from "node:fs";
import os from "node:os";
import path from "node:path";

import { AppDatabase } from "../server/db.js";

export function createTempDb() {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), "short-video-"));
  const dbPath = path.join(dir, "app.db");
  const db = new AppDatabase(dbPath);

  return {
    db,
    dbPath,
    cleanup() {
      db.close();
      fs.rmSync(dir, { recursive: true, force: true });
    }
  };
}
