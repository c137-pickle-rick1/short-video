import Database from "better-sqlite3";
import fs from "node:fs";
import path from "node:path";

import { runDatabaseMigrations } from "./db/migrations/index.js";
import { createDatabaseReadModel } from "./db/readModel.js";
import { createDatabaseWriteRepository } from "./db/writeRepository.js";

export class AppDatabase {
  constructor(dbPath, { runMigrations = true } = {}) {
    fs.mkdirSync(path.dirname(dbPath), { recursive: true });
    this.db = new Database(dbPath);
    this.db.pragma("journal_mode = WAL");
    this.db.pragma("foreign_keys = ON");
    this.appliedMigrationIds = runMigrations ? runDatabaseMigrations(this.db) : [];
    this.readModel = createDatabaseReadModel(this.db);
    this.writeRepository = createDatabaseWriteRepository(this.db);
  }

  close() {
    this.db.close();
  }

  syncSources(sources) {
    this.writeRepository.syncSources(sources);
    return this.listSources();
  }

  listSources() {
    return this.readModel.listSources();
  }

  listEnabledSources() {
    return this.listSources().filter((source) => source.enabled);
  }

  touchSourceLastDiscovered(sourceId) {
    this.writeRepository.touchSourceLastDiscovered(sourceId);
  }

  insertDiscoveredTweet({ tweetId, sourceId, tweetUrl, rawDiscoveryPayload, durationText = null }) {
    return this.writeRepository.insertDiscoveredTweet({
      tweetId,
      sourceId,
      tweetUrl,
      rawDiscoveryPayload,
      durationText
    });
  }

  listPendingTweets(limit = null) {
    return this.readModel.listPendingTweets(limit);
  }

  listPublishedTweetsMissingAvatar(limit = null) {
    return this.readModel.listPublishedTweetsMissingAvatar(limit);
  }

  applyResolution(tweetId, resolution) {
    this.writeRepository.applyResolution(tweetId, resolution);
  }

  createCrawlRun({ phase, sourceId = null }) {
    return this.writeRepository.createCrawlRun({ phase, sourceId });
  }

  finishCrawlRun(runId, outcome = {}) {
    this.writeRepository.finishCrawlRun(runId, outcome);
  }

  getFeed({ cursor = null, sourceHandle = null, limit = 12 }) {
    return this.readModel.getFeed({ cursor, sourceHandle, limit });
  }

  getPrimaryMedia(tweetId) {
    return this.readModel.getPrimaryMedia(tweetId);
  }

  getSourcesOverview() {
    return this.readModel.getSourcesOverview();
  }

  getStats() {
    return this.readModel.getStats();
  }

  countTweetsByStatus(status) {
    return this.readModel.countTweetsByStatus(status);
  }
}
