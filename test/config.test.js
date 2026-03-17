import test from "node:test";
import assert from "node:assert/strict";

import { resolveAppConfig } from "../server/config.js";

test("resolveAppConfig defaults discoveryMode to hybrid", () => {
  const config = resolveAppConfig({});
  assert.equal(config.discoveryMode, "hybrid");
  assert.equal(config.mediaProxyTimeoutMs, 15000);
  assert.equal(config.runMigrationsOnBoot, true);
});

test("resolveAppConfig accepts explicit discovery modes", () => {
  assert.equal(resolveAppConfig({ DISCOVERY_MODE: "jina" }).discoveryMode, "jina");
  assert.equal(resolveAppConfig({ DISCOVERY_MODE: "browser" }).discoveryMode, "browser");
  assert.equal(resolveAppConfig({ DISCOVERY_MODE: "hybrid" }).discoveryMode, "hybrid");
});

test("resolveAppConfig rejects unsupported discovery modes", () => {
  assert.throws(() => resolveAppConfig({ DISCOVERY_MODE: "invalid" }), {
    message: /DISCOVERY_MODE must be one of/
  });
});

test("resolveAppConfig accepts an explicit media proxy timeout", () => {
  const config = resolveAppConfig({ MEDIA_PROXY_TIMEOUT_MS: "9000" });
  assert.equal(config.mediaProxyTimeoutMs, 9000);
});

test("resolveAppConfig rejects invalid media proxy timeouts", () => {
  assert.throws(() => resolveAppConfig({ MEDIA_PROXY_TIMEOUT_MS: "0" }), {
    message: /MEDIA_PROXY_TIMEOUT_MS must be a positive integer/
  });
});

test("resolveAppConfig allows disabling boot-time migrations", () => {
  assert.equal(resolveAppConfig({ RUN_MIGRATIONS_ON_BOOT: "false" }).runMigrationsOnBoot, false);
  assert.equal(resolveAppConfig({ RUN_MIGRATIONS_ON_BOOT: "0" }).runMigrationsOnBoot, false);
  assert.equal(resolveAppConfig({ RUN_MIGRATIONS_ON_BOOT: "true" }).runMigrationsOnBoot, true);
});
