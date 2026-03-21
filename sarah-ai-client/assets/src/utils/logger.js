const logger = {
  error: (context, message, data = {}) => {
    const entry = { level: 'error', context, message, data, time: new Date().toISOString() };
    console.error(`[${context}]`, message, data);
    const cfg = window.SarahAiClientConfig;
    if (!cfg) return;
    fetch(`${cfg.apiUrl}/log`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
      body: JSON.stringify(entry),
    }).catch(() => {});
  },
  warn: (context, message, data = {}) => {
    console.warn(`[${context}]`, message, data);
  },
  info: (context, message, data = {}) => {
    console.info(`[${context}]`, message, data);
  },
};

export default logger;
