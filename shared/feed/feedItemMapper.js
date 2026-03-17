export function mapFeedItemsForPresentation(items = []) {
  return items.map((item) => ({
    ...item,
    videoUrl: item.videoUrl ? `/api/media/${item.tweetId}` : null
  }));
}
