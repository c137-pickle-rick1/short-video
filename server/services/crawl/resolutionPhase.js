import { isBackoffErrorCode } from "../../errors.js";

function getSkippedSummary(backoffState) {
  const snapshot = backoffState.getSnapshot();

  return {
    skipped: true,
    reason: snapshot.backoffReason,
    until: snapshot.backoffUntil
  };
}

async function runResolutionBatch({
  db,
  resolverClient,
  tweets,
  backoffState,
  phase = "resolve"
}) {
  const runId = db.createCrawlRun({ phase });
  let resolvedCount = 0;
  let failureMessage = null;
  let finalStatus = "success";

  try {
    for (const tweet of tweets) {
      const resolution = await resolverClient.resolveTweet(tweet);
      db.applyResolution(tweet.tweetId, resolution);
      resolvedCount += 1;

      if (isBackoffErrorCode(resolution.errorCode)) {
        backoffState.set(resolution.errorCode);
        failureMessage = resolution.errorMessage || resolution.errorCode;
        finalStatus = "failed";
        break;
      }
    }
  } catch (error) {
    failureMessage = error.message;
    finalStatus = "failed";

    if (isBackoffErrorCode(error.code)) {
      backoffState.set(error.code);
    }
  }

  db.finishCrawlRun(runId, {
    status: finalStatus,
    itemsSeen: tweets.length,
    itemsResolved: resolvedCount,
    errorMessage: failureMessage
  });

  return {
    itemsSeen: tweets.length,
    itemsResolved: resolvedCount,
    status: finalStatus,
    errorMessage: failureMessage
  };
}

export async function runPendingResolution({
  db,
  resolverClient,
  backoffState,
  limit = null
}) {
  if (backoffState.isActive()) {
    return getSkippedSummary(backoffState);
  }

  return runResolutionBatch({
    db,
    resolverClient,
    tweets: db.listPendingTweets(limit),
    backoffState,
    phase: "resolve"
  });
}

export async function runAvatarBackfill({
  db,
  resolverClient,
  backoffState,
  limit = null
}) {
  if (backoffState.isActive()) {
    return getSkippedSummary(backoffState);
  }

  return runResolutionBatch({
    db,
    resolverClient,
    tweets: db.listPublishedTweetsMissingAvatar(limit),
    backoffState,
    phase: "resolve"
  });
}
