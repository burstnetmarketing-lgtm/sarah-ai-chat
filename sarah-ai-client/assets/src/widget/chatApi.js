/**
 * Chat API — connects the widget to the sarah-ai-server POST /chat endpoint.
 *
 * Reads connection config from window.SarahAiWidget.connection:
 *   server_url  — base URL of the sarah-ai-server REST API
 *   account_key — tenant account key
 *   site_key    — site key
 *
 * Returns: { success, session_uuid, message, agent }
 */
export async function sendChatMessage(text, sessionUuid = null) {
  const conn = window.SarahAiWidget?.connection || {};
  const { server_url, account_key, site_key } = conn;

  if (!server_url || !account_key || !site_key) {
    throw new Error('Sarah AI widget is not configured (missing server_url, account_key, or site_key).');
  }

  const body = { account_key, site_key, message: text };
  if (sessionUuid) body.session_uuid = sessionUuid;

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
