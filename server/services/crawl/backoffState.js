export function createBackoffState() {
  let backoffUntil = null;
  let backoffReason = null;

  return {
    set(reason, minutes = 15) {
      backoffUntil = new Date(Date.now() + minutes * 60 * 1000);
      backoffReason = reason;
    },
    isActive() {
      return Boolean(backoffUntil) && backoffUntil.getTime() > Date.now();
    },
    getState() {
      return {
        backoffUntil,
        backoffReason
      };
    },
    getSnapshot() {
      return {
        backoffUntil: backoffUntil ? backoffUntil.toISOString() : null,
        backoffReason
      };
    }
  };
}
