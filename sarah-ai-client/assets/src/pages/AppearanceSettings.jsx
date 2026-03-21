import React, { useState, useEffect } from 'react';
import { apiFetch } from '../api/client.js';

const DEFAULTS = {
  chat_title:      'Sarah Assistant',
  welcome_message: 'Hi 👋 How can I help you today?',
  primary_color:   '#2563eb',
  widget_position: 'right',
};

/* ── Live Preview ──────────────────────────────────────────── */

function WidgetPreview({ settings }) {
  const {
    chat_title:      title    = DEFAULTS.chat_title,
    welcome_message: welcome  = DEFAULTS.welcome_message,
    primary_color:   color    = DEFAULTS.primary_color,
    widget_position: position = DEFAULTS.widget_position,
  } = settings;

  const isLeft = position === 'left';

  const s = {
    wrap: {
      position: 'relative',
      width: '280px',
      height: '460px',
      margin: '0 auto',
      fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
      fontSize: '13px',
    },
    window: {
      position: 'absolute',
      bottom: '64px',
      [isLeft ? 'left' : 'right']: 0,
      width: '280px',
      height: '390px',
      background: '#fff',
      borderRadius: '14px',
      boxShadow: '0 8px 32px rgba(0,0,0,0.14)',
      display: 'flex',
      flexDirection: 'column',
      overflow: 'hidden',
    },
    header: {
      background: color,
      color: '#fff',
      padding: '0 14px',
      height: '54px',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between',
      flexShrink: 0,
    },
    headerInfo: { display: 'flex', alignItems: 'center', gap: '9px' },
    avatar: {
      width: '30px', height: '30px', borderRadius: '50%',
      background: 'rgba(255,255,255,0.25)',
      display: 'flex', alignItems: 'center', justifyContent: 'center',
      fontWeight: '700', fontSize: '13px',
    },
    headerTitle: { fontWeight: '600', fontSize: '13px' },
    closeBtn: { opacity: 0.75, fontSize: '18px', lineHeight: 1, cursor: 'default' },
    messages: {
      flex: 1,
      background: '#f8fafc',
      display: 'flex',
      flexDirection: 'column',
      alignItems: 'center',
      justifyContent: 'center',
      padding: '12px',
    },
    welcomeEmoji: { fontSize: '28px', marginBottom: '5px' },
    welcomeTitle: { fontWeight: '700', fontSize: '14px', color: '#1e293b', margin: '0 0 3px' },
    welcomeSub: { fontSize: '12px', color: '#64748b', margin: 0, textAlign: 'center' },
    inputArea: {
      padding: '10px',
      borderTop: '1px solid #cbd5e1',
      display: 'flex',
      gap: '7px',
      background: '#fff',
      flexShrink: 0,
    },
    inputBox: {
      flex: 1,
      border: '1.5px solid #cbd5e1',
      borderRadius: '8px',
      padding: '7px 10px',
      fontSize: '12px',
      color: '#94a3b8',
      background: '#fff',
    },
    sendBtn: {
      width: '34px', height: '34px', borderRadius: '8px',
      background: color,
      display: 'flex', alignItems: 'center', justifyContent: 'center',
      color: '#fff', opacity: 0.4, flexShrink: 0,
    },
    launcher: {
      position: 'absolute',
      bottom: 0,
      [isLeft ? 'left' : 'right']: 0,
      width: '50px', height: '50px', borderRadius: '50%',
      background: color,
      display: 'flex', alignItems: 'center', justifyContent: 'center',
      color: '#fff', fontSize: '20px',
      boxShadow: '0 4px 14px rgba(0,0,0,0.2)',
    },
  };

  return (
    <div style={s.wrap}>
      <div style={s.window}>
        <div style={s.header}>
          <div style={s.headerInfo}>
            <div style={s.avatar}>S</div>
            <span style={s.headerTitle}>{title || '—'}</span>
          </div>
          <span style={s.closeBtn}>×</span>
        </div>
        <div style={s.messages}>
          <div style={s.welcomeEmoji}>👋</div>
          <p style={s.welcomeTitle}>Hi there!</p>
          <p style={s.welcomeSub}>{welcome || '—'}</p>
        </div>
        <div style={s.inputArea}>
          <div style={s.inputBox}>Type a message...</div>
          <div style={s.sendBtn}>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
          </div>
        </div>
      </div>
      <div style={s.launcher}>💬</div>
    </div>
  );
}

/* ── Main Page ─────────────────────────────────────────────── */

export default function AppearanceSettings() {
  const [form, setForm]             = useState(DEFAULTS);
  const [savedDraft, setSavedDraft] = useState(DEFAULTS);
  const [published, setPublished]   = useState(DEFAULTS);
  const [canPublish, setCanPublish] = useState(false);
  const [loading, setLoading]       = useState(true);
  const [saving, setSaving]         = useState(false);
  const [publishing, setPublishing] = useState(false);

  useEffect(() => {
    apiFetch('appearance')
      .then(res => {
        if (!res.success) return;
        const { draft, published: pub, can_publish } = res.data;
        setForm(draft);
        setSavedDraft(draft);
        setPublished(pub);
        setCanPublish(can_publish);
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  function update(key, value) {
    setForm(prev => ({ ...prev, [key]: value }));
  }

  const isDirty = JSON.stringify(form) !== JSON.stringify(savedDraft);

  function handleSaveDraft() {
    setSaving(true);
    apiFetch('appearance/draft', 'POST', form)
      .then(res => {
        if (!res.success) return;
        setSavedDraft(form);
        setCanPublish(res.data.can_publish);
      })
      .catch(() => {})
      .finally(() => setSaving(false));
  }

  function handlePublish() {
    setPublishing(true);
    apiFetch('appearance/publish', 'POST')
      .then(res => {
        if (!res.success) return;
        setPublished(res.data.published);
        setCanPublish(false);
      })
      .catch(() => {})
      .finally(() => setPublishing(false));
  }

  function handleDiscard() {
    apiFetch('appearance/discard', 'POST')
      .then(res => {
        if (!res.success) return;
        const { published: pub } = res.data;
        setForm(pub);
        setSavedDraft(pub);
        setPublished(pub);
        setCanPublish(false);
      })
      .catch(() => {});
  }

  if (loading) return <p className="text-muted small p-3">Loading...</p>;

  return (
    <>
      <div className="mb-3">
        <h1 className="h5 fw-semibold text-dark mb-1">Appearance</h1>
        <p className="text-muted small mb-0">Customize the chat widget look and content. Changes are saved as draft until published.</p>
      </div>

      <div className="d-flex gap-4 align-items-start">

        {/* ── Left: Form ── */}
        <div style={{ flex: '0 0 56%' }}>

          {/* General */}
          <fieldset className="card border-0 shadow-sm mb-3">
            <legend className="card-header bg-white border-bottom px-3 py-2 fw-semibold small float-none w-auto rounded-0">
              General
            </legend>
            <div className="card-body d-flex flex-column gap-3">

              <div>
                <label className="form-label small fw-semibold mb-1">Chat Title</label>
                <input
                  type="text"
                  className="form-control form-control-sm"
                  value={form.chat_title}
                  onChange={e => update('chat_title', e.target.value)}
                  placeholder={DEFAULTS.chat_title}
                />
              </div>

              <div>
                <label className="form-label small fw-semibold mb-1">Welcome Message</label>
                <textarea
                  className="form-control form-control-sm"
                  rows={2}
                  value={form.welcome_message}
                  onChange={e => update('welcome_message', e.target.value)}
                  placeholder={DEFAULTS.welcome_message}
                  style={{ resize: 'none' }}
                />
              </div>

            </div>
          </fieldset>

          {/* Style */}
          <fieldset className="card border-0 shadow-sm mb-3">
            <legend className="card-header bg-white border-bottom px-3 py-2 fw-semibold small float-none w-auto rounded-0">
              Style
            </legend>
            <div className="card-body d-flex flex-column gap-3">

              <div>
                <label className="form-label small fw-semibold mb-1">Primary Color</label>
                <div className="d-flex align-items-center gap-2">
                  <input
                    type="color"
                    className="form-control form-control-color"
                    style={{ width: '44px', height: '34px', padding: '2px', cursor: 'pointer' }}
                    value={form.primary_color}
                    onChange={e => update('primary_color', e.target.value)}
                  />
                  <input
                    type="text"
                    className="form-control form-control-sm"
                    style={{ width: '100px', fontFamily: 'monospace' }}
                    value={form.primary_color}
                    onChange={e => update('primary_color', e.target.value)}
                    maxLength={7}
                  />
                </div>
              </div>

              <div>
                <label className="form-label small fw-semibold mb-1">Widget Position</label>
                <div className="d-flex gap-3">
                  {['right', 'left'].map(pos => (
                    <div key={pos} className="form-check">
                      <input
                        className="form-check-input"
                        type="radio"
                        name="widget_position"
                        id={`pos-${pos}`}
                        value={pos}
                        checked={form.widget_position === pos}
                        onChange={() => update('widget_position', pos)}
                        style={{ cursor: 'pointer' }}
                      />
                      <label className="form-check-label small" htmlFor={`pos-${pos}`} style={{ textTransform: 'capitalize', cursor: 'pointer' }}>
                        {pos}
                      </label>
                    </div>
                  ))}
                </div>
              </div>

            </div>
          </fieldset>

          {/* Action Bar */}
          <div className="d-flex align-items-center gap-2 mt-1">
            <button
              className="btn btn-sm btn-outline-secondary"
              onClick={handleDiscard}
              disabled={!canPublish}
            >
              Discard
            </button>
            <button
              className="btn btn-sm btn-outline-primary"
              onClick={handleSaveDraft}
              disabled={!isDirty || saving}
            >
              {saving ? 'Saving...' : 'Save Draft'}
            </button>
            <button
              className="btn btn-sm btn-primary"
              onClick={handlePublish}
              disabled={!canPublish || publishing}
            >
              {publishing ? 'Publishing...' : 'Publish'}
            </button>
            {canPublish && (
              <span className="text-warning small ms-1">● Unpublished changes</span>
            )}
            {!canPublish && !isDirty && (
              <span className="text-success small ms-1">✓ Up to date</span>
            )}
          </div>

        </div>

        {/* ── Right: Preview ── */}
        <div style={{ flex: 1, position: 'sticky', top: '20px' }}>
          <div className="card border-0 shadow-sm">
            <div className="card-header bg-white border-bottom py-2">
              <span className="small fw-semibold">Live Preview</span>
              <span className="text-muted small ms-2">updates as you type</span>
            </div>
            <div className="card-body d-flex justify-content-center py-4" style={{ background: '#f1f5f9', minHeight: '520px', alignItems: 'center' }}>
              <WidgetPreview settings={form} />
            </div>
          </div>
        </div>

      </div>
    </>
  );
}
