import test from "node:test";
import assert from "node:assert/strict";

import { CompositeDiscoveryClient, createDiscoveryClient } from "../lib/discovery/compositeDiscoveryClient.js";

test("CompositeDiscoveryClient prefers primary discovery results when items exist", async () => {
  const primaryCalls = [];
  const fallbackCalls = [];
  const client = new CompositeDiscoveryClient({
    primaryClient: {
      async discoverSource(handle) {
        primaryCalls.push(handle);
        return {
          items: [{ tweetId: "1000", tweetUrl: `https://x.com/${handle}/status/1000` }]
        };
      }
    },
    fallbackClient: {
      async discoverSource(handle) {
        fallbackCalls.push(handle);
        return { items: [] };
      }
    }
  });

  const result = await client.discoverSource("demo");

  assert.equal(result.items.length, 1);
  assert.deepEqual(primaryCalls, ["demo"]);
  assert.deepEqual(fallbackCalls, []);
});

test("CompositeDiscoveryClient falls back when the primary client throws", async () => {
  const fallbackCalls = [];
  const client = new CompositeDiscoveryClient({
    primaryClient: {
      async discoverSource() {
        throw new Error("primary failed");
      }
    },
    fallbackClient: {
      async discoverSource(handle) {
        fallbackCalls.push(handle);
        return {
          items: [{ tweetId: "2000", tweetUrl: `https://x.com/${handle}/status/2000` }]
        };
      }
    },
    logger: {
      warn() {}
    }
  });

  const result = await client.discoverSource("demo");

  assert.equal(result.items[0].tweetId, "2000");
  assert.deepEqual(fallbackCalls, ["demo"]);
});

test("CompositeDiscoveryClient falls back when the primary client returns no items", async () => {
  const fallbackCalls = [];
  const client = new CompositeDiscoveryClient({
    primaryClient: {
      async discoverSource() {
        return {
          items: []
        };
      }
    },
    fallbackClient: {
      async discoverSource(handle) {
        fallbackCalls.push(handle);
        return {
          items: [{ tweetId: "3000", tweetUrl: `https://x.com/${handle}/status/3000` }]
        };
      }
    },
    logger: {
      info() {}
    }
  });

  const result = await client.discoverSource("demo");

  assert.equal(result.items[0].tweetId, "3000");
  assert.deepEqual(fallbackCalls, ["demo"]);
});

test("createDiscoveryClient returns the expected client for each mode", () => {
  const primaryClient = { discoverSource() {} };
  const fallbackClient = { discoverSource() {} };
  const apiClient = { discoverSource() {} };

  assert.equal(
    createDiscoveryClient({
      mode: "jina",
      primaryClient,
      fallbackClient
    }),
    primaryClient
  );

  assert.equal(
    createDiscoveryClient({
      mode: "browser",
      primaryClient,
      fallbackClient
    }),
    fallbackClient
  );

  assert.ok(
    createDiscoveryClient({
      mode: "hybrid",
      primaryClient,
      fallbackClient
    }) instanceof CompositeDiscoveryClient
  );

  assert.equal(
    createDiscoveryClient({
      mode: "api",
      primaryClient,
      fallbackClient,
      apiClient
    }),
    apiClient
  );

  assert.ok(
    createDiscoveryClient({
      mode: "api_hybrid",
      primaryClient,
      fallbackClient,
      apiClient
    }) instanceof CompositeDiscoveryClient
  );
});
