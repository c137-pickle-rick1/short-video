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

  async discoverSource(handle, options = {}) {
    try {
      const result = await this.primaryClient.discoverSource(handle, options);
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

    return this.fallbackClient.discoverSource(handle, options);
  }
}

export function createDiscoveryClient({
  mode = "hybrid",
  primaryClient,
  fallbackClient,
  apiClient = null,
  logger = console
} = {}) {
  if (mode === "browser") {
    return fallbackClient;
  }

  if (mode === "jina") {
    return primaryClient;
  }

  if (mode === "api") {
    if (typeof apiClient?.discoverSource !== "function") {
      throw new TypeError("API discovery mode requires an apiClient");
    }

    return apiClient;
  }

  if (mode === "hybrid") {
    return new CompositeDiscoveryClient({
      primaryClient,
      fallbackClient,
      logger
    });
  }

  if (mode === "api_hybrid") {
    if (typeof apiClient?.discoverSource !== "function") {
      throw new TypeError("API hybrid discovery mode requires an apiClient");
    }

    return new CompositeDiscoveryClient({
      primaryClient: apiClient,
      fallbackClient,
      logger
    });
  }

  throw new Error(`Unsupported discovery mode: ${mode}`);
}
