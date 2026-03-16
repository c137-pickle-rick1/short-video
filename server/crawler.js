import { isBackoffErrorCode } from "./errors.js";

export class CrawlOrchestrator {
  constructor({ db, discoveryClient, resolverClient, logger = console } = {}) {
    this.db = db;
    this.discoveryClient = discoveryClient;
    this.resolverClient = resolverClient;
    this.logger = logger;
    this.backoffUntil = null;
    this.backoffReason = null;
  }

  setBackoff(reason, minutes = 15) {
    this.backoffUntil = new Date(Date.now() + minutes * 60 * 1000);
    this.backoffReason = reason;
  }

  inBackoff() {
    return this.backoffUntil && this.backoffUntil.getTime() > Date.now();
  }

  syncSources(sources) {
    return this.db.syncSources(sources);
  }

  async discoverSource(source) {
    const runId = this.db.createCrawlRun({ phase: "discovery", sourceId: source.id });
    try {
      const result = await this.discoveryClient.discoverSource(source.handle);
      let inserted = 0;

      for (const item of result.items) {
        const wasInserted = this.db.insertDiscoveredTweet({
          tweetId: item.tweetId,
          sourceId: source.id,
          tweetUrl: item.tweetUrl,
          rawDiscoveryPayload: item.rawDiscoveryPayload
        });
        if (wasInserted) {
          inserted += 1;
        }
      }

      this.db.touchSourceLastDiscovered(source.id);
      this.db.finishCrawlRun(runId, {
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
        this.setBackoff(error.code);
      }

      this.db.finishCrawlRun(runId, {
        status: "failed",
        errorMessage: error.message
      });

      throw error;
    }
  }

  async discoverAllSources() {
    if (this.inBackoff()) {
      return {
        skipped: true,
        reason: this.backoffReason,
        until: this.backoffUntil.toISOString()
      };
    }

    const summary = {
      itemsSeen: 0,
      itemsInserted: 0
    };

    const sources = this.db.listEnabledSources();
    for (const source of sources) {
      const result = await this.discoverSource(source);
      summary.itemsSeen += result.itemsSeen;
      summary.itemsInserted += result.itemsInserted;
    }

    return summary;
  }

  async resolvePending(limit = null) {
    if (this.inBackoff()) {
      return {
        skipped: true,
        reason: this.backoffReason,
        until: this.backoffUntil.toISOString()
      };
    }

    const runId = this.db.createCrawlRun({ phase: "resolve" });
    const pendingTweets = this.db.listPendingTweets(limit);
    let resolvedCount = 0;
    let failureMessage = null;
    let finalStatus = "success";

    try {
      for (const tweet of pendingTweets) {
        const resolution = await this.resolverClient.resolveTweet(tweet);
        this.db.applyResolution(tweet.tweetId, resolution);
        resolvedCount += 1;

        if (isBackoffErrorCode(resolution.errorCode)) {
          this.setBackoff(resolution.errorCode);
          failureMessage = resolution.errorMessage || resolution.errorCode;
          finalStatus = "failed";
          break;
        }
      }
    } catch (error) {
      failureMessage = error.message;
      finalStatus = "failed";
      if (isBackoffErrorCode(error.code)) {
        this.setBackoff(error.code);
      }
    }

    this.db.finishCrawlRun(runId, {
      status: finalStatus,
      itemsSeen: pendingTweets.length,
      itemsResolved: resolvedCount,
      errorMessage: failureMessage
    });

    return {
      itemsSeen: pendingTweets.length,
      itemsResolved: resolvedCount,
      status: finalStatus,
      errorMessage: failureMessage
    };
  }

  async crawlOnce() {
    const discovery = await this.discoverAllSources();
    const resolution = await this.resolvePending();
    return {
      discovery,
      resolution,
      backoffUntil: this.backoffUntil ? this.backoffUntil.toISOString() : null,
      backoffReason: this.backoffReason
    };
  }
}
