import React, { useState } from 'react';

const FUNCTIONS = [
  {
    name: 'sarah_chat_exists',
    signature: 'sarah_chat_exists(): bool',
    description: 'Checks whether Sarah Chat is installed and its Public API is loaded. Always call this before any other function.',
    params: [],
    returns: { type: 'bool', desc: 'true if the plugin is installed and the API is available.' },
    example: `if ( ! sarah_chat_exists() ) {
    return; // plugin not installed
}`,
    response: `// Plugin installed:
true

// Plugin missing:
false`,
  },
  {
    name: 'sarah_chat_is_ready',
    signature: 'sarah_chat_is_ready(): bool',
    description: 'Returns true when the plugin is fully configured and ready to serve chat requests (server_url, account_key, site_key, and platform_key are all set).',
    params: [],
    returns: { type: 'bool', desc: 'true if all four connection credentials are present.' },
    example: `if ( sarah_chat_is_ready() ) {
    // plugin is configured and ready
}`,
    response: `// Fully configured:
true

// Missing credentials:
false`,
  },
  {
    name: 'sarah_chat_setup',
    signature: 'sarah_chat_setup( array $payload ): array',
    description: 'Runs the Quick Setup flow programmatically. Connects to the Sarah AI Server, provisions a tenant and site, and persists the credentials locally.',
    params: [
      { name: '$payload[\'whmcs_key\']',      type: 'string', req: true,  desc: 'WHMCS license key.' },
      { name: '$payload[\'openai_api_key\']', type: 'string', req: true,  desc: 'Site-specific OpenAI API key.' },
      { name: '$payload[\'site_name\']',      type: 'string', req: false, desc: 'Site display name. Defaults to the WordPress blogname.' },
      { name: '$payload[\'site_url\']',       type: 'string', req: false, desc: 'Site URL. Defaults to home_url().' },
      { name: '$payload[\'server_url\']',     type: 'string', req: false, desc: 'Override the server URL from config.php.' },
      { name: '$payload[\'platform_key\']',   type: 'string', req: false, desc: 'Override the platform key from config.php.' },
    ],
    returns: {
      type: 'array',
      desc: '[ success: bool, ready: bool, error: string|null ]',
    },
    example: `$result = sarah_chat_setup([
    'whmcs_key'      => 'WHMCS-XXXX-XXXX',
    'openai_api_key' => 'sk-...',
    // server_url and platform_key are read automatically from config.php
]);

if ( $result['success'] && $result['ready'] ) {
    // setup successful
} else {
    error_log( $result['error'] );
}`,
    response: `// Success:
[
    'success' => true,
    'ready'   => true,
    'error'   => null,
]

// Failure (e.g. missing whmcs_key):
[
    'success' => false,
    'ready'   => false,
    'error'   => 'whmcs_key is required',
]`,
  },
  {
    name: 'sarah_chat_get_sessions',
    signature: 'sarah_chat_get_sessions( array $args = [] ): array',
    description: 'Returns a list of recent chat sessions for this site from the server.',
    params: [
      { name: '$args[\'limit\']', type: 'int', req: false, desc: 'Maximum number of results (default: 20, server cap: 100).' },
    ],
    returns: {
      type: 'array',
      desc: '[ success: bool, data: array, error: string|null ] — each item includes: uuid, status, created_at, …',
    },
    example: `$result = sarah_chat_get_sessions([ 'limit' => 10 ]);

if ( $result['success'] ) {
    foreach ( $result['data'] as $session ) {
        echo $session['uuid'] . ' — ' . $session['status'];
    }
}`,
    response: `// Success:
[
    'success' => true,
    'data'    => [
        [
            'uuid'       => '550e8400-e29b-41d4-a716-446655440000',
            'status'     => 'active',
            'created_at' => '2025-03-20 10:23:45',
        ],
        [
            'uuid'       => '6ba7b810-9dad-11d1-80b4-00c04fd430c8',
            'status'     => 'closed',
            'created_at' => '2025-03-19 08:11:02',
        ],
    ],
    'error' => null,
]

// Failure:
[
    'success' => false,
    'data'    => [],
    'error'   => 'Plugin not configured',
]`,
  },
  {
    name: 'sarah_chat_get_session_history',
    signature: 'sarah_chat_get_session_history( string $session_uuid ): array',
    description: 'Returns session metadata and the full ordered message history for a specific session.',
    params: [
      { name: '$session_uuid', type: 'string', req: true, desc: 'UUID of the session to retrieve.' },
    ],
    returns: {
      type: 'array',
      desc: '[ success: bool, session: array|null, messages: array, error: string|null ] — each message includes: role, content, created_at',
    },
    example: `$result = sarah_chat_get_session_history( '550e8400-e29b-41d4-a716-446655440000' );

if ( $result['success'] ) {
    foreach ( $result['messages'] as $msg ) {
        echo $msg['role'] . ': ' . $msg['content'];
    }
} else {
    echo $result['error']; // 'Session not found'
}`,
    response: `// Success:
[
    'success'  => true,
    'session'  => [
        'uuid'       => '550e8400-e29b-41d4-a716-446655440000',
        'status'     => 'active',
        'created_at' => '2025-03-20 10:23:45',
    ],
    'messages' => [
        [
            'role'       => 'user',
            'content'    => 'Hello, how can you help me?',
            'created_at' => '2025-03-20 10:23:46',
        ],
        [
            'role'       => 'assistant',
            'content'    => 'Hi! I\'m Sarah AI. How can I assist you today?',
            'created_at' => '2025-03-20 10:23:47',
        ],
    ],
    'error' => null,
]

// Not found:
[
    'success'  => false,
    'session'  => null,
    'messages' => [],
    'error'    => 'Session not found',
]`,
  },
  {
    name: 'sarah_chat_set_config',
    signature: 'sarah_chat_set_config( array $values ): array',
    description: 'Sets one or more plugin configuration values.',
    params: [
      { name: '$values[\'widget_enabled\']',   type: 'string', req: false, desc: '"1" or "0" — show or hide the chat widget on the frontend.' },
      { name: '$values[\'greeting_message\']', type: 'string', req: false, desc: 'Widget greeting message.' },
      { name: '$values[\'server_url\']',       type: 'string', req: false, desc: 'Sarah AI Server URL (including namespace).' },
      { name: '$values[\'account_key\']',      type: 'string', req: false, desc: 'Account key.' },
      { name: '$values[\'site_key\']',         type: 'string', req: false, desc: 'Site key.' },
      { name: '$values[\'platform_key\']',     type: 'string', req: false, desc: 'Platform key.' },
    ],
    returns: {
      type: 'array',
      desc: '[ success: bool, saved: string[], errors: string[], error: string|null ]',
    },
    example: `$result = sarah_chat_set_config([
    'widget_enabled'   => '1',
    'greeting_message' => 'Hi! How can I help you today?',
]);

if ( $result['success'] ) {
    // $result['saved'] = ['widget_enabled', 'greeting_message']
}`,
    response: `// Success:
[
    'success' => true,
    'saved'   => ['widget_enabled', 'greeting_message'],
    'errors'  => [],
    'error'   => null,
]

// Partial failure (unknown key passed):
[
    'success' => false,
    'saved'   => ['widget_enabled'],
    'errors'  => ["Key 'unknown_key' is not allowed"],
    'error'   => "Key 'unknown_key' is not allowed",
]`,
  },
];

function FunctionCard({ fn }) {
  const [open, setOpen] = useState(false);

  return (
    <div className="card border-0 shadow-sm mb-3">
      <div
        className="card-header bg-white border-bottom d-flex align-items-center justify-content-between"
        style={{ cursor: 'pointer' }}
        onClick={() => setOpen(o => !o)}
      >
        <div className="d-flex align-items-center gap-2">
          <span className="badge bg-primary-subtle text-primary border border-primary-subtle" style={{ fontFamily: 'monospace', fontSize: '0.7rem' }}>PHP</span>
          <code style={{ fontSize: '0.88rem', color: '#1a3460', fontWeight: 600 }}>{fn.signature}</code>
        </div>
        <span className="text-muted" style={{ fontSize: '0.8rem' }}>{open ? '▲' : '▼'}</span>
      </div>

      {open && (
        <div className="card-body p-4">
          <p className="text-secondary small mb-4" style={{ lineHeight: 1.7 }}>{fn.description}</p>

          {fn.params.length > 0 && (
            <div className="mb-4">
              <div className="fw-semibold small text-dark mb-2">Parameters</div>
              <table className="table table-sm table-bordered" style={{ fontSize: '0.8rem' }}>
                <thead className="table-light">
                  <tr>
                    <th>Parameter</th>
                    <th>Type</th>
                    <th>Required</th>
                    <th>Description</th>
                  </tr>
                </thead>
                <tbody>
                  {fn.params.map(p => (
                    <tr key={p.name}>
                      <td><code>{p.name}</code></td>
                      <td><span className="badge bg-secondary-subtle text-secondary">{p.type}</span></td>
                      <td>{p.req ? <span className="text-danger">Yes</span> : <span className="text-muted">No</span>}</td>
                      <td className="text-secondary">{p.desc}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          <div className="mb-4">
            <div className="fw-semibold small text-dark mb-2">Returns</div>
            <div className="d-flex align-items-start gap-2">
              <code className="badge bg-success-subtle text-success border border-success-subtle" style={{ fontSize: '0.75rem' }}>{fn.returns.type}</code>
              <span className="text-secondary small">{fn.returns.desc}</span>
            </div>
          </div>

          <div className="mb-4">
            <div className="fw-semibold small text-dark mb-2">Example</div>
            <pre
              style={{
                background: '#0f172a',
                color: '#e2e8f0',
                borderRadius: 8,
                padding: '1rem 1.25rem',
                fontSize: '0.78rem',
                lineHeight: 1.7,
                overflowX: 'auto',
                margin: 0,
              }}
            >{fn.example}</pre>
          </div>

          {fn.response && (
            <div>
              <div className="fw-semibold small text-dark mb-2">Sample Response</div>
              <pre
                style={{
                  background: '#0f1e0f',
                  color: '#86efac',
                  borderRadius: 8,
                  padding: '1rem 1.25rem',
                  fontSize: '0.78rem',
                  lineHeight: 1.7,
                  overflowX: 'auto',
                  margin: 0,
                }}
              >{fn.response}</pre>
            </div>
          )}
        </div>
      )}
    </div>
  );
}

export default function PublicApi() {
  return (
    <>
      <div className="mb-4">
        <h1 className="h5 fw-semibold text-dark mb-1">PHP Public API</h1>
        <p className="text-muted small mb-0">
          Global PHP functions callable from any WordPress plugin — no direct dependency on internal classes required.
        </p>
      </div>

      {/* Adapter notice */}
      <div className="alert alert-info border-0 small mb-4 py-2 px-3" style={{ background: '#eff6ff', color: '#1e40af' }}>
        <strong>Integration Adapter:</strong> To use these functions from another plugin, copy{' '}
        <code>integration/SarahChatAdapter.php</code>. It wraps all calls with availability checks and won't crash if the plugin is missing.
      </div>

      {/* Quick example */}
      <div className="card border-0 shadow-sm mb-4">
        <div className="card-header bg-white border-bottom">
          <span className="fw-semibold small text-dark">Quick Example — Parent Product</span>
        </div>
        <div className="card-body p-0">
          <pre
            style={{
              background: '#0f172a',
              color: '#e2e8f0',
              borderRadius: '0 0 8px 8px',
              padding: '1rem 1.25rem',
              fontSize: '0.78rem',
              lineHeight: 1.7,
              overflowX: 'auto',
              margin: 0,
            }}
          >{`// 1. Check availability
if ( ! sarah_chat_exists() ) {
    return;
}

// 2. Run setup once (e.g. on your plugin's activation hook)
if ( ! sarah_chat_is_ready() ) {
    sarah_chat_setup([
        'whmcs_key'      => 'WHMCS-XXXX-XXXX',
        'openai_api_key' => 'sk-...',
    ]);
}

// 3. Fetch sessions
$sessions = sarah_chat_get_sessions([ 'limit' => 5 ]);

// 4. Fetch history
$history = sarah_chat_get_session_history( $sessions['data'][0]['uuid'] ?? '' );`}</pre>
        </div>
      </div>

      {/* Function reference */}
      <div className="fw-semibold small text-dark mb-3">Function Reference <span className="text-muted fw-normal">(click to expand)</span></div>
      {FUNCTIONS.map(fn => <FunctionCard key={fn.name} fn={fn} />)}
    </>
  );
}
