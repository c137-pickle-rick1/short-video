#!/usr/bin/env node

import { createDiscoveryClient } from "./lib/discovery/compositeDiscoveryClient.js";
import { JinaDiscoveryClient } from "./lib/discovery/jinaDiscoveryClient.js";
import {
  PlaywrightResolver,
  openAuthBrowser
} from "./lib/resolve/playwrightResolver.js";

function parseArgs(argv) {
  const [command = "", ...rest] = argv;
  const options = {};

  for (let index = 0; index < rest.length; index += 1) {
    const token = rest[index];
    if (!token.startsWith("--")) {
      continue;
    }

    const key = token.slice(2);
    const next = rest[index + 1];
    if (!next || next.startsWith("--")) {
      options[key] = true;
      continue;
    }

    options[key] = next;
    index += 1;
  }

  return {
    command,
    options
  };
}

async function readStdin() {
  const chunks = [];

  for await (const chunk of process.stdin) {
    chunks.push(chunk);
  }

  return Buffer.concat(chunks).toString("utf8");
}

function printJson(payload) {
  process.stdout.write(`${JSON.stringify(payload)}\n`);
}

function printError(error) {
  printJson({
    ok: false,
    error: {
      code: error?.code || "sidecar_failed",
      message: error?.message || "Unknown sidecar error"
    }
  });
}

async function runDiscoverSource(options) {
  const mode = String(options.mode || "hybrid").trim().toLowerCase();
  const handle = String(options.handle || "").trim().replace(/^@/, "").toLowerCase();
  const browserProfileDir = String(options["browser-profile-dir"] || "");
  const storageStatePath = String(options["storage-state-path"] || "");

  if (!handle) {
    throw Object.assign(new Error("Missing required --handle option"), { code: "invalid_arguments" });
  }

  const resolver = new PlaywrightResolver({
    browserProfileDir,
    storageStatePath
  });
  const discoveryClient = createDiscoveryClient({
    mode,
    primaryClient: new JinaDiscoveryClient(),
    fallbackClient: resolver
  });

  try {
    const result = await discoveryClient.discoverSource(handle);
    printJson({
      ok: true,
      result
    });
  } finally {
    await resolver.close().catch(() => {});
  }
}

async function runResolveTweets(options) {
  const browserProfileDir = String(options["browser-profile-dir"] || "");
  const storageStatePath = String(options["storage-state-path"] || "");
  const resolver = new PlaywrightResolver({
    browserProfileDir,
    storageStatePath
  });

  try {
    const rawInput = await readStdin();
    const tweets = rawInput.trim() ? JSON.parse(rawInput) : [];
    if (!Array.isArray(tweets)) {
      throw Object.assign(new Error("resolve-tweets expects a JSON array on stdin"), {
        code: "invalid_arguments"
      });
    }

    const results = [];

    for (const tweet of tweets) {
      const resolution = await resolver.resolveTweet(tweet);
      results.push({
        tweetId: String(tweet?.tweetId || ""),
        ...resolution
      });

      if (resolution?.errorCode === "rate_limited" || resolution?.errorCode === "auth_required") {
        break;
      }
    }

    printJson({
      ok: true,
      result: {
        results
      }
    });
  } finally {
    await resolver.close().catch(() => {});
  }
}

async function runOpenAuthBrowser(options) {
  const browserProfileDir = String(options["browser-profile-dir"] || "");
  if (!browserProfileDir) {
    throw Object.assign(new Error("Missing required --browser-profile-dir option"), {
      code: "invalid_arguments"
    });
  }

  const context = await openAuthBrowser({
    browserProfileDir
  });

  process.stdout.write("Browser profile opened. Log into X, then press Ctrl+C to close.\n");

  const shutdown = async () => {
    await context.close().catch(() => {});
    process.exit(0);
  };

  process.on("SIGINT", shutdown);
  process.on("SIGTERM", shutdown);

  await new Promise(() => {});
}

async function main() {
  const { command, options } = parseArgs(process.argv.slice(2));

  if (command === "discover-source") {
    await runDiscoverSource(options);
    return;
  }

  if (command === "resolve-tweets") {
    await runResolveTweets(options);
    return;
  }

  if (command === "open-auth-browser") {
    await runOpenAuthBrowser(options);
    return;
  }

  throw Object.assign(new Error(`Unsupported sidecar command: ${command || "<none>"}`), {
    code: "invalid_command"
  });
}

main().catch((error) => {
  printError(error);
  process.exit(1);
});
