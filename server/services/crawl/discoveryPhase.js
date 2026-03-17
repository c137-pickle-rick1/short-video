import { isBackoffErrorCode } from "../../errors.js";

function getSkippedSummary(backoffState) {
  const snapshot = backoffState.getSnapshot();

  return {
    skipped: true,
    reason: snapshot.backoffReason,
    until: snapshot.backoffUntil
  };
}

export async function runSourceDiscovery({
  db,
  discoveryClient,
  source,
  backoffState
}) {
  const runId = db.createCrawlRun({ phase: "discovery", sourceId: source.id });

  try {
    const result = await discoveryClient.discoverSource(source.handle);
    let inserted = 0;

    for (const item of result.items) {
      const wasInserted = db.insertDiscoveredTweet({
        tweetId: item.tweetId,
        sourceId: source.id,
        tweetUrl: item.tweetUrl,
        durationText: item.durationText || null,
        rawDiscoveryPayload: item.rawDiscoveryPayload
      });

      if (wasInserted) {
        inserted += 1;
      }
    }

    db.touchSourceLastDiscovered(source.id);
    db.finishCrawlRun(runId, {
      status: "success",
      itemsSeen: result.items.length,
      itemsInserted: inserted
    });

    return {
      itemsSeen: result.items.length,
      itemsInserted: inserted
    };
  } catch (error) {
    if (isBackoffErrorCode(error.code)) {
      backoffState.set(error.code);
    }

    db.finishCrawlRun(runId, {
      status: "failed",
      errorMessage: error.message
    });

    throw error;
  }
}

export async function runDiscoveryAcrossSources({
  db,
  discoveryClient,
  backoffState
}) {
  if (backoffState.isActive()) {
    return getSkippedSummary(backoffState);
  }

  const summary = {
    itemsSeen: 0,
    itemsInserted: 0
  };

  for (const source of db.listEnabledSources()) {
    const result = await runSourceDiscovery({
      db,
      discoveryClient,
      source,
      backoffState
    });
    summary.itemsSeen += result.itemsSeen;
    summary.itemsInserted += result.itemsInserted;
  }

  return summary;
}
