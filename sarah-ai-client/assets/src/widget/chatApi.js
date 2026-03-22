/**
 * Chat API — connects the widget to the sarah-ai-server REST endpoints.
 *
 * Reads connection config from window.SarahAiWidget.connection:
 *   server_url  — base URL of the sarah-ai-server REST API
 *   account_key — tenant account key
 *   site_key    — site key
 */

function getConn() {
  return window.SarahAiWidget?.connection || {};
}

function requireConn() {
  const { server_url, account_key, site_key } = getConn();
  if (!server_url || !account_key || !site_key) {
    throw new Error('Sarah AI widget is not configured (missing server_url, account_key, or site_key).');
  }
  return { server_url, account_key, site_key };
}

/**
 * POST /chat — send a message and receive an AI reply.
 *
 * @param {string}      text        User message text
 * @param {string|null} sessionUuid Existing session UUID (null for first message)
 * @param {object|null} lead        Optional lead data {name?, phone?, email?}
 * @returns {Promise<{success, session_uuid, message, agent}>}
 */
export async function sendChatMessage(text, sessionUuid = null, lead = null) {
  const { server_url, account_key, site_key } = requireConn();

  const body = { account_key, site_key, message: text };
  if (sessionUuid) body.session_uuid = sessionUuid;
  if (lead && (lead.name || lead.phone || lead.email)) body.lead = lead;

  const res = await fetch(`${server_url}/chat`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });

  const data = await res.json();
  if (!res.ok || !data.success) {
    throw new Error(data.message || `Server error: ${res.status}`);
  }

  return data; // { success, session_uuid, message, agent }
}

/**
 * GET /chat/history — fetch message history for an existing session.
 * Used on widget reload to restore the previous conversation.
 *
 * @param {string} sessionUuid
 * @returns {Promise<Array<{role, content}>|null>}
 *   Array of messages on success, null if session not found or auth fails.
 */
export async function fetchChatHistory(sessionUuid) {
  const { server_url, account_key, site_key } = requireConn();

  const params = new URLSearchParams({ account_key, site_key, session_uuid: sessionUuid });
  const res = await fetch(`${server_url}/chat/history?${params}`);

  if (res.status === 404 || res.status === 401) {
    return null; // Session gone or invalid — caller should clear localStorage
  }

  const data = await res.json();
  return data.success ? (data.messages ?? []) : null;
}
