import React, { useState, useCallback, useEffect } from 'react';
import { getSessions, getSessionHistory } from '../api/sessionsApi.js';

// ─── Helpers ─────────────────────────────────────────────────────────────────

const RTL_RE = /[\u0591-\u07FF\uFB1D-\uFDFD\uFE70-\uFEFC]/;
function detectDir(text) {
  const plain = (text || '').replace(/<[^>]+>/g, '').trimStart();
  return RTL_RE.test(plain.slice(0, 120)) ? 'rtl' : 'ltr';
}

function formatDate(str) {
  if (!str) return '—';
  const d = new Date(str.replace(' ', 'T'));
  return d.toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' })
    + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function visitorLabel(session) {
  return session.visitor_name  || session.visitor_email
    || session.visitor_phone || 'Anonymous';
}

// ─── SessionCard ─────────────────────────────────────────────────────────────

function SessionCard({ session, active, onClick }) {
  return (
    <button
      type="button"
      className={`list-group-item list-group-item-action border-0 border-bottom px-3 py-3 text-start${active ? ' active' : ''}`}
      onClick={onClick}
    >
      <div className="d-flex justify-content-between align-items-start mb-1">
        <span className="fw-semibold small text-truncate me-2" style={{ maxWidth: 140 }}>
          {visitorLabel(session)}
        </span>
        <span className={`badge rounded-pill ${session.status === 'active' ? 'bg-success' : 'bg-secondary'}`} style={{ fontSize: 10 }}>
          {session.status}
        </span>
      </div>
      <div className="text-muted" style={{ fontSize: 11 }}>{formatDate(session.created_at)}</div>
      {(session.visitor_email || session.visitor_phone) && (
        <div className="text-muted text-truncate mt-1" style={{ fontSize: 11 }}>
          {session.visitor_email || session.visitor_phone}
        </div>
      )}
    </button>
  );
}

// ─── Message bubble ──────────────────────────────────────────────────────────

function MessageBubble({ msg }) {
  const isAi  = msg.role !== 'customer';
  const dir   = detectDir(msg.content);
  const isRtl = dir === 'rtl';

  return (
    <div className={`d-flex mb-3 ${isAi ? 'justify-content-start' : 'justify-content-end'}`}>
      <div
        dir={dir}
        className="rounded-3 px-3 py-2"
        style={{
          maxWidth:   '75%',
          background: isAi ? '#f8fafc' : '#f5c518',
          color:      isAi ? '#1e293b' : '#1a3460',
          border:     isAi ? '1px solid #e2e8f0' : 'none',
          fontSize:   13,
          lineHeight: 1.55,
          wordBreak:  'break-word',
          textAlign:  isRtl ? 'right' : 'left',
          borderRadius: isAi
            ? (isRtl ? '14px 14px 4px 14px' : '14px 14px 14px 4px')
            : (isRtl ? '14px 14px 14px 4px' : '14px 14px 4px 14px'),
        }}
      >
        {isAi
          ? <div dangerouslySetInnerHTML={{ __html: msg.content }} />
          : msg.content}
        <div
          className="text-muted mt-1"
          style={{ fontSize: 10, textAlign: isRtl ? 'left' : 'right' }}
        >
          {formatDate(msg.created_at)}
        </div>
      </div>
    </div>
  );
}

// ─── ConversationPane ────────────────────────────────────────────────────────

function ConversationPane({ session, messages, loading }) {
  if (loading) {
    return (
      <div className="d-flex align-items-center justify-content-center" style={{ height: 300 }}>
        <div className="spinner-border spinner-border-sm text-secondary" role="status" />
        <span className="ms-2 text-muted small">Loading conversation…</span>
      </div>
    );
  }

  if (!session) {
    return (
      <div className="d-flex flex-column align-items-center justify-content-center text-muted" style={{ height: 300 }}>
        <i className="bi bi-chat-left-text fs-1 mb-2 opacity-25" />
        <p className="small mb-0">Select a session from the list to view the conversation.</p>
      </div>
    );
  }

  return (
    <div className="d-flex flex-column" style={{ height: '100%', overflow: 'hidden' }}>
      {/* Session header */}
      <div className="border-bottom px-4 py-3 bg-light flex-shrink-0">
        <div className="fw-semibold text-dark">{visitorLabel(session)}</div>
        <div className="text-muted small d-flex gap-3 mt-1 flex-wrap">
          {session.visitor_email && <span><i className="bi bi-envelope me-1" />{session.visitor_email}</span>}
          {session.visitor_phone && <span><i className="bi bi-telephone me-1" />{session.visitor_phone}</span>}
          <span><i className="bi bi-clock me-1" />{formatDate(session.created_at)}</span>
          <span className={`badge ${session.status === 'active' ? 'bg-success' : 'bg-secondary'}`}>{session.status}</span>
        </div>
      </div>

      {/* Messages — scrollable area */}
      <div className="px-4 py-3" style={{ overflowY: 'auto', flex: 1 }}>
        {messages.length === 0 ? (
          <p className="text-muted small text-center my-4">No messages in this session.</p>
        ) : (
          messages.map(msg => <MessageBubble key={msg.uuid ?? msg.id} msg={msg} />)
        )}
      </div>
    </div>
  );
}

// ─── ChatHistory page ────────────────────────────────────────────────────────

export default function ChatHistory() {
  const [sessions,        setSessions]        = useState([]);
  const [loading,         setLoading]         = useState(true);
  const [error,           setError]           = useState(null);
  const [selectedUuid,    setSelectedUuid]    = useState(null);
  const [activeSession,   setActiveSession]   = useState(null);
  const [messages,        setMessages]        = useState([]);
  const [loadingHistory,  setLoadingHistory]  = useState(false);
  const [historyError,    setHistoryError]    = useState(null);

  // Load sessions on mount
  const loadSessions = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await getSessions(50);
      setSessions(res.data ?? []);
    } catch (e) {
      setError(e.message || 'Failed to load sessions.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { loadSessions(); }, [loadSessions]);

  // Load conversation when a session is selected
  const selectSession = useCallback(async (uuid) => {
    if (uuid === selectedUuid) return;
    setSelectedUuid(uuid);
    setActiveSession(null);
    setMessages([]);
    setHistoryError(null);
    setLoadingHistory(true);
    try {
      const res = await getSessionHistory(uuid);
      setActiveSession(res.session ?? null);
      setMessages(res.messages ?? []);
    } catch (e) {
      setHistoryError(e.message || 'Failed to load conversation.');
    } finally {
      setLoadingHistory(false);
    }
  }, [selectedUuid]);

  return (
    <>
      {/* Page header */}
      <div className="mb-3">
        <h1 className="h5 fw-semibold text-dark mb-1">Chat History</h1>
      </div>

      {error && (
        <div className="alert alert-danger small py-2">{error}</div>
      )}

      <div className="row g-3" style={{ height: 'calc(100vh - 140px)' }}>
        {/* ── Session list ── */}
        <div className="col-md-4 d-flex flex-column" style={{ height: '100%' }}>
          <div className="card border-0 shadow-sm d-flex flex-column" style={{ height: '100%', overflow: 'hidden' }}>
            <div className="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-2 px-3 flex-shrink-0">
              <span className="fw-semibold small text-dark">Sessions</span>
              <button
                className="btn btn-sm btn-outline-secondary py-0 px-2"
                style={{ fontSize: 11 }}
                onClick={loadSessions}
                disabled={loading}
              >
                {loading ? '…' : '↺ Refresh'}
              </button>
            </div>

            <div style={{ overflowY: 'auto', flex: 1 }}>
              {loading ? (
                <div className="text-center text-muted py-5 small">
                  <div className="spinner-border spinner-border-sm mb-2" role="status" />
                  <br />Loading sessions…
                </div>
              ) : sessions.length === 0 ? (
                <div className="text-center text-muted py-5 small">
                  <i className="bi bi-chat-left fs-2 d-block mb-2 opacity-25" />
                  No sessions found.
                </div>
              ) : (
                <div className="list-group list-group-flush">
                  {sessions.map(s => (
                    <SessionCard
                      key={s.uuid}
                      session={s}
                      active={s.uuid === selectedUuid}
                      onClick={() => selectSession(s.uuid)}
                    />
                  ))}
                </div>
              )}
            </div>
          </div>
        </div>

        {/* ── Conversation view ── */}
        <div className="col-md-8 d-flex flex-column" style={{ height: '100%' }}>
          <div className="card border-0 shadow-sm d-flex flex-column" style={{ height: '100%', overflow: 'hidden' }}>
            {historyError && (
              <div className="alert alert-danger m-3 small py-2 mb-0 flex-shrink-0">{historyError}</div>
            )}
            <ConversationPane
              session={activeSession}
              messages={messages}
              loading={loadingHistory}
            />
          </div>
        </div>
      </div>
    </>
  );
}
