import { getSourceLabel } from "../sourceLabel.js";

export { getSourceLabel };

export function formatFeedSummary({ sourceHandle = "", renderedCount = 0, done = false }) {
  const sourceLabel = getSourceLabel(sourceHandle);

  if (renderedCount === 0 && done) {
    return `${sourceLabel} 暂无内容`;
  }

  if (renderedCount === 0) {
    return `${sourceLabel} 正在加载探索内容…`;
  }

  const tail = done ? "已加载完毕" : "向下滚动继续加载";
  return `${sourceLabel} · 已展示 ${renderedCount} 条 · ${tail}`;
}

export function formatFeedDate(value) {
  if (!value) {
    return "未知时间";
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return "未知时间";
  }

  const now = Date.now();
  const diffMs = now - date.getTime();

  if (diffMs < 0) {
    return "刚刚";
  }

  const minuteMs = 60 * 1000;
  const hourMs = 60 * minuteMs;
  const dayMs = 24 * hourMs;
  const weekMs = 7 * dayMs;
  const monthMs = 30 * dayMs;
  const yearMs = 365 * dayMs;

  if (diffMs < minuteMs) {
    return "刚刚";
  }

  if (diffMs < hourMs) {
    return `${Math.floor(diffMs / minuteMs)}分钟前`;
  }

  if (diffMs < dayMs) {
    return `${Math.floor(diffMs / hourMs)}小时前`;
  }

  if (diffMs < weekMs) {
    return `${Math.floor(diffMs / dayMs)}天前`;
  }

  if (diffMs < monthMs) {
    return `${Math.floor(diffMs / weekMs)}周前`;
  }

  if (diffMs < yearMs) {
    return `${Math.floor(diffMs / monthMs)}个月前`;
  }

  return `${Math.floor(diffMs / yearMs)}年前`;
}

export function formatDetailDate(value) {
  if (!value) {
    return "发布日期待更新";
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return "发布日期待更新";
  }

  return new Intl.DateTimeFormat("zh-CN", {
    year: "numeric",
    month: "long",
    day: "numeric"
  }).format(date);
}
