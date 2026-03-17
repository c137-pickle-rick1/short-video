import test from "node:test";
import assert from "node:assert/strict";

import { extractStatusLinks, extractStatusLinksFromContent } from "../lib/jinaDiscovery.js";

test("extractStatusLinks filters to canonical status URLs and dedupes ids", () => {
  const links = [
    ["Tweet A", "https://x.com/JinaAI_/status/123"],
    ["Tweet A analytics", "https://x.com/JinaAI_/status/123/analytics"],
    ["Tweet B", "https://x.com/JinaAI_/status/456"],
    ["Duplicate", "https://x.com/JinaAI_/status/456"],
    ["Other user", "https://x.com/Other/status/789"]
  ];

  const result = extractStatusLinks(links, "jinaai_");
  assert.deepEqual(
    result.map((item) => item.tweetId),
    ["123", "456"]
  );
});

test("extractStatusLinksFromContent falls back to content URL extraction", () => {
  const content = `
    Latest posts:
    https://x.com/JinaAI_/status/321
    https://x.com/JinaAI_/status/654
  `;

  const result = extractStatusLinksFromContent(content, "JinaAI_");
  assert.equal(result.length, 2);
  assert.equal(result[0].tweetUrl, "https://x.com/JinaAI_/status/321");
});
