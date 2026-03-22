import logger from '../utils/logger.js';

function cfg() {
  return window.SarahAiServerConfig || {};
}

export async function apiFetch(path, method = 'GET', body = null) {
  const { apiUrl, nonce } = cfg();
  let response;

  try {
    response = await fetch(`${apiUrl}/${path}`, {
      method,
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce':   nonce,
      },
      body: body !== null ? JSON.stringify(body) : undefined,
    });
  } catch (networkError) {
    logger.error('apiFetch:network', `${method} /${path} — network error`, { error: networkError.message });
    throw networkError;
  }

  if (!response.ok) {
    logger.error('apiFetch:http', `${method} /${path} — HTTP ${response.status}`, { status: response.status, body });
    throw new Error(`API error: ${response.status}`);
  }

  return response.json();
}
