export function createAppError(code, message, details = {}) {
  const error = new Error(message);
  error.code = code;
  Object.assign(error, details);
  return error;
}

export function isBackoffErrorCode(code) {
  return code === "rate_limited" || code === "auth_required";
}
