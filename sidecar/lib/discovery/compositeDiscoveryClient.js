export class CompositeDiscoveryClient {
  constructor({ primaryClient, fallbackClient, logger = console } = {}) {
    if (typeof primaryClient?.discoverSource !== "function") {
      throw new TypeError("CompositeDiscoveryClient requires a primary discovery client");
    }

    if (typeof fallbackClient?.discoverSource !== "function") {
      throw new TypeError("CompositeDiscoveryClient requires a fallback discovery client");
    }

    this.primaryClient = primaryClient;
    this.fallbackClient = fallbackClient;
    this.logger = logger;
  }

  async discoverSource(handle) {
    try {
      const result = await this.primaryClient.discoverSource(handle);
      if (Array.isArray(result?.items) && result.items.length > 0) {
        return result;
      }

      this.logger.info?.(
        `discovery: primary client returned no items for @${handle}, falling back to browser`
      );
    } catch (error) {
      this.logger.warn?.(
        `discovery: primary client failed for @${handle}, falling back to browser`,
        error
      );
    }

    return this.fallbackClient.discoverSource(handle);
  }
}

export function createDiscoveryClient({
  mode = "hybrid",
  primaryClient,
  fallbackClient,
  logger = console
} = {}) {
  if (mode === "browser") {
    return fallbackClient;
  }

  if (mode === "jina") {
    return primaryClient;
  }

  if (mode === "hybrid") {
    return new CompositeDiscoveryClient({
      primaryClient,
      fallbackClient,
      logger
    });
  }

  throw new Error(`Unsupported discovery mode: ${mode}`);
}
