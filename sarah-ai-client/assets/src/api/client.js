function cfg() {
  return window.ProjectNameConfig || {};
}

export async function apiFetch(path, method = 'GET', body = null) {
  const { apiUrl, nonce } = cfg();
  const response = await fetch(`${apiUrl}/${path}`, {
    method,
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': nonce,
    },
    body: body !== null ? JSON.stringify(body) : undefined,
  });
  if (!response.ok) {
    throw new Error(`API error: ${response.status}`);
  }
  return response.json();
}
