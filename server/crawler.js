import { createBackoffState } from "./services/crawl/backoffState.js";
import {
  runDiscoveryAcrossSources,
  runSourceDiscovery
} from "./services/crawl/discoveryPhase.js";
import { runAvatarBackfill, runPendingResolution } from "./services/crawl/resolutionPhase.js";

export class CrawlOrchestrator {
  constructor({ db, discoveryClient, resolverClient, logger = console } = {}) {
    this.db = db;
    this.discoveryClient = discoveryClient;
    this.resolverClient = resolverClient;
    this.logger = logger;
    this.backoffState = createBackoffState();
  }

  get backoffUntil() {
    return this.backoffState.getState().backoffUntil;
  }

  get backoffReason() {
    return this.backoffState.getState().backoffReason;
  }

  setBackoff(reason, minutes = 15) {
    this.backoffState.set(reason, minutes);
  }

  inBackoff() {
    return this.backoffState.isActive();
  }

  getBackoffState() {
    return this.backoffState.getSnapshot();
  }

  syncSources(sources) {
    return this.db.syncSources(sources);
  }

  async discoverSource(source) {
    return runSourceDiscovery({
      db: this.db,
      discoveryClient: this.discoveryClient,
      source,
      backoffState: this.backoffState
    });
  }

  async discoverAllSources() {
    return runDiscoveryAcrossSources({
      db: this.db,
      discoveryClient: this.discoveryClient,
      backoffState: this.backoffState
    });
  }

  async resolvePending(limit = null) {
    return runPendingResolution({
      db: this.db,
      resolverClient: this.resolverClient,
      backoffState: this.backoffState,
      limit
    });
  }

  async backfillMissingAvatars(limit = null) {
    return runAvatarBackfill({
      db: this.db,
      resolverClient: this.resolverClient,
      backoffState: this.backoffState,
      limit
    });
  }

  async crawlOnce() {
    const discovery = await this.discoverAllSources();
    const resolution = await this.resolvePending();
    const backoff = this.getBackoffState();

    return {
      discovery,
      resolution,
      backoffUntil: backoff.backoffUntil,
      backoffReason: backoff.backoffReason
    };
  }
}
