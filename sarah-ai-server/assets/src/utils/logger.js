function send(level, context, message, data) {
  const cfg = window.SarahAiServerConfig;
  if (!cfg) return;
  fetch(`${cfg.apiUrl}/log`, {
    method:  'POST',
    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
    body:    JSON.stringify({ level, context, message, data }),
  }).catch(() => {}); // never let logger itself throw
}

const logger = {
  error: (context, message, data = {}) => {
    console.error(`[${context}]`, message, data);
    send('error', context, message, data);
  },
  warn: (context, message, data = {}) => {
    console.warn(`[${context}]`, message, data);
    send('warn', context, message, data);
  },
  info: (context, message, data = {}) => {
    console.info(`[${context}]`, message, data);
    send('info', context, message, data);
  },
};

export default logger;
