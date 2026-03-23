/**
 * Sessions API — wraps the sarah-ai-client REST endpoints that internally
 * call the sarah_chat_get_sessions() and sarah_chat_get_session_history()
 * public PHP functions.
 */
import { apiFetch } from './client.js';

/**
 * Fetch the list of chat sessions for this site.
 * @param {number} limit Max sessions to return (default 20, max 100)
 * @returns {Promise<{success: boolean, data: Array}>}
 */
export async function getSessions(limit = 20) {
  return apiFetch(`sessions?limit=${limit}`);
}

/**
 * Fetch a single session's detail and full message history.
 * @param {string} uuid Session UUID
 * @returns {Promise<{success: boolean, session: object, messages: Array}>}
 */
export async function getSessionHistory(uuid) {
  return apiFetch(`sessions/${uuid}`);
}
