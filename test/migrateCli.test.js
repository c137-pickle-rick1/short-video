import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";
import { execFileSync } from "node:child_process";

import Database from "better-sqlite3";

test("db:migrate CLI applies schema migrations to the configured database", () => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), "short-video-migrate-cli-"));
  const dbPath = path.join(dir, "app.db");

  try {
    const output = execFileSync(process.execPath, ["server/migrate.js"], {
      cwd: process.cwd(),
      env: {
        ...process.env,
        DB_PATH: dbPath
      },
      encoding: "utf8"
    });

    const db = new Database(dbPath, { readonly: true });

    try {
      const appliedCount = db.prepare("SELECT COUNT(*) AS count FROM schema_migrations").get().count;
      const hasTweetsTable = db
        .prepare(
          `
            SELECT name
            FROM sqlite_master
            WHERE type = 'table' AND name = 'tweets'
          `
        )
        .get();

      assert.match(output, /Applied \d+ migration\(s\)|No pending migrations/);
      assert.equal(appliedCount > 0, true);
      assert.equal(hasTweetsTable.name, "tweets");
    } finally {
      db.close();
    }
  } finally {
    fs.rmSync(dir, { recursive: true, force: true });
  }
});
