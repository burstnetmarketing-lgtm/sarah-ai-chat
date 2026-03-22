import React, { useState, useEffect } from 'react';
import { apiFetch } from '../api/client.js';

/* ── Defaults ──────────────────────────────────────────────── */

const DEFAULTS = {
  widget_width:       '360',
  widget_height:      '500',
  widget_position:    'right',
  launcher_bg_color:  '#2563eb',
  launcher_icon:      'bubble',
  launcher_image:     '',
  header_bg_color:    '#2563eb',
  header_text:        'Sarah Assistant',
  header_text_color:  '#ffffff',
  header_font_family: 'inherit',
  close_btn_color:    '#ffffff',
  close_btn_size:     '16',
  welcome_message:    'Hi 👋 How can I help you today?',
  msg_area_bg:        '#f8fafc',
  bubble_user_bg:     '#2563eb',
  bubble_user_text:   '#ffffff',
  bubble_ai_bg:       '#ffffff',
  bubble_ai_text:     '#1e293b',
  send_bg_color:      '#2563eb',
  qq_border_color:    '#2563eb',
  qq_text_color:      '#2563eb',
  qq_hover_bg:        '#2563eb',
  qq_border_radius:   '20',
};

const FONTS = [
  { value: 'inherit',                          label: 'Default (inherit)' },
  { value: 'Arial, sans-serif',                label: 'Arial' },
  { value: 'Georgia, serif',                   label: 'Georgia' },
  { value: 'Verdana, sans-serif',              label: 'Verdana' },
  { value: "'Trebuchet MS', sans-serif",       label: 'Trebuchet MS' },
  { value: "'Times New Roman', serif",         label: 'Times New Roman' },
  { value: "Tahoma, sans-serif",               label: 'Tahoma' },
];

/* ── Widget Preview ────────────────────────────────────────── */

const LAUNCHER_ICONS_SVG = {
  bubble: (
    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
      <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12z"/>
      <circle cx="8" cy="10" r="1.2"/><circle cx="12" cy="10" r="1.2"/><circle cx="16" cy="10" r="1.2"/>
    </svg>
  ),
  message: (
    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
      <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/>
    </svg>
  ),
  support: (
    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
      <path d="M12 1C5.9 1 1 5.9 1 12s4.9 11 11 11 11-4.9 11-11S18.1 1 12 1zm-1 6h2v2h-2V7zm0 4h2v6h-2v-6z"/>
    </svg>
  ),
  heart: (
    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
      <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
    </svg>
  ),
};

function WidgetPreview({ settings: S }) {
  const isLeft   = S.widget_position === 'left';
  const closeSz  = parseInt(S.close_btn_size) || 16;
  const qqRadius = (parseInt(S.qq_border_radius) || 20) + 'px';
  const ff       = S.header_font_family && S.header_font_family !== 'inherit' ? S.header_font_family : undefined;

  const side    = isLeft ? 'left' : 'right';
  const antiSide = isLeft ? 'right' : 'left';

  return (
    <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '20px' }}>
      {/* Chat Window */}
      <div style={{
        width: '270px', height: '380px',
        background: '#fff',
        borderRadius: '14px',
        boxShadow: '0 6px 28px rgba(0,0,0,0.14)',
        display: 'flex', flexDirection: 'column', overflow: 'hidden',
        fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
        fontSize: '12px',
      }}>
        {/* Header */}
        <div style={{
          background: S.header_bg_color,
          color: S.header_text_color,
          padding: '0 14px', height: '52px',
          display: 'flex', alignItems: 'center', justifyContent: 'space-between',
          flexShrink: 0, fontFamily: ff,
        }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
            <div style={{
              width: '28px', height: '28px', borderRadius: '50%',
              background: 'rgba(255,255,255,0.25)',
              display: 'flex', alignItems: 'center', justifyContent: 'center',
              fontWeight: 700, fontSize: '12px', overflow: 'hidden', flexShrink: 0,
            }}>
              {S.launcher_image ? <img src={S.launcher_image} alt="" style={{ width: '100%', height: '100%', objectFit: 'cover' }} /> : 'S'}
            </div>
            <span style={{ fontWeight: 600, fontSize: '12px' }}>{S.header_text || '—'}</span>
          </div>
          <div style={{ color: S.close_btn_color, opacity: 0.8, fontSize: `${closeSz}px`, lineHeight: 1 }}>✕</div>
        </div>

        {/* Messages */}
        <div style={{
          flex: 1, background: S.msg_area_bg,
          display: 'flex', flexDirection: 'column',
          alignItems: 'center', justifyContent: 'center', padding: '12px',
          gap: '6px',
        }}>
          <div style={{ fontSize: '26px', lineHeight: 1, marginBottom: '4px' }}>👋</div>
          <div style={{ fontWeight: 700, fontSize: '13px', color: '#1e293b', margin: 0 }}>Hi there!</div>
          <div style={{ fontSize: '11px', color: '#64748b', textAlign: 'center', margin: 0 }}>
            {S.welcome_message || '—'}
          </div>
          <div style={{ display: 'flex', flexDirection: 'column', gap: '5px', width: '100%', marginTop: '8px' }}>
            {['Sample question 1', 'Sample question 2'].map((q, i) => (
              <div key={i} style={{
                border: `1.5px solid ${S.qq_border_color}`,
                borderRadius: qqRadius,
                color: S.qq_text_color,
                padding: '5px 10px',
                fontSize: '11px',
                background: '#fff',
                textAlign: 'left',
              }}>{q}</div>
            ))}
          </div>
        </div>

        {/* Input */}
        <div style={{
          padding: '8px 10px', borderTop: '1px solid #cbd5e1',
          display: 'flex', gap: '6px', background: '#fff', flexShrink: 0,
        }}>
          <div style={{
            flex: 1, border: '1.5px solid #cbd5e1', borderRadius: '8px',
            padding: '6px 10px', fontSize: '11px', color: '#94a3b8', background: '#fff',
          }}>Type a message...</div>
          <div style={{
            width: '30px', height: '30px', borderRadius: '7px',
            background: S.send_bg_color,
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            color: '#fff', flexShrink: 0, alignSelf: 'flex-end', opacity: 0.4,
          }}>
            <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
          </div>
        </div>
      </div>

      {/* Launcher */}
      <div style={{ display: 'flex', justifyContent: isLeft ? 'flex-start' : 'flex-end', width: '270px' }}>
        <div style={{
          width: '46px', height: '46px', borderRadius: '50%',
          background: S.launcher_bg_color,
          display: 'flex', alignItems: 'center', justifyContent: 'center',
          color: '#fff', boxShadow: '0 4px 14px rgba(0,0,0,0.18)', overflow: 'hidden',
        }}>
          {S.launcher_image
            ? <img src={S.launcher_image} alt="" style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
            : (LAUNCHER_ICONS_SVG[S.launcher_icon] || LAUNCHER_ICONS_SVG.bubble)
          }
        </div>
      </div>
    </div>
  );
}

/* ── Helpers ───────────────────────────────────────────────── */

function ColorRow({ label, value, onChange, note }) {
  return (
    <div className="d-flex align-items-center justify-content-between py-2 border-bottom">
      <div>
        <div className="small fw-semibold">{label}</div>
        {note && <div className="text-muted" style={{ fontSize: '11px' }}>{note}</div>}
      </div>
      <div className="d-flex align-items-center gap-2">
        <input type="color" className="form-control form-control-color"
          style={{ width: '36px', height: '30px', padding: '2px', cursor: 'pointer', border: '1px solid #dee2e6' }}
          value={value} onChange={e => onChange(e.target.value)} />
        <input type="text" className="form-control form-control-sm"
          style={{ width: '82px', fontFamily: 'monospace', fontSize: '12px' }}
          value={value} onChange={e => onChange(e.target.value)} maxLength={7} />
      </div>
    </div>
  );
}

function NumberRow({ label, value, onChange, min, max, unit = 'px' }) {
  return (
    <div className="d-flex align-items-center justify-content-between py-2 border-bottom">
      <div className="small fw-semibold">{label}</div>
      <div className="d-flex align-items-center gap-2">
        <input type="number" className="form-control form-control-sm"
          style={{ width: '70px' }}
          value={value} min={min} max={max}
          onChange={e => onChange(e.target.value)} />
        <span className="text-muted small">{unit}</span>
      </div>
    </div>
  );
}

function SelectRow({ label, value, onChange, options }) {
  return (
    <div className="d-flex align-items-center justify-content-between py-2 border-bottom">
      <div className="small fw-semibold">{label}</div>
      <select className="form-select form-select-sm" style={{ width: '160px' }}
        value={value} onChange={e => onChange(e.target.value)}>
        {options.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
      </select>
    </div>
  );
}

/* ── Tab Content Components ────────────────────────────────── */

function TabGeneral({ form, update, canPublish, isDirty, saving, publishing, onSaveDraft, onPublish, onDiscard }) {
  return (
    <>
      <div className="card border-0 shadow-sm mb-3">
        <div className="card-header bg-white border-bottom py-2 fw-semibold small">Widget Size</div>
        <div className="card-body py-1 px-3">
          <NumberRow label="Width"    value={form.widget_width}  onChange={v => update('widget_width', v)}  min={280} max={600} />
          <NumberRow label="Height"   value={form.widget_height} onChange={v => update('widget_height', v)} min={350} max={800} />
        </div>
      </div>

      <div className="card border-0 shadow-sm mb-3">
        <div className="card-header bg-white border-bottom py-2 fw-semibold small">Position</div>
        <div className="card-body py-2 px-3">
          <div className="d-flex gap-3">
            {['right', 'left'].map(pos => (
              <div key={pos} className="form-check">
                <input className="form-check-input" type="radio" name="widget_position"
                  id={`pos-${pos}`} value={pos}
                  checked={form.widget_position === pos}
                  onChange={() => update('widget_position', pos)}
                  style={{ cursor: 'pointer' }} />
                <label className="form-check-label small" htmlFor={`pos-${pos}`} style={{ textTransform: 'capitalize', cursor: 'pointer' }}>
                  {pos}
                </label>
              </div>
            ))}
          </div>
        </div>
      </div>

      <div className="card border-0 shadow-sm mb-3">
        <div className="card-header bg-white border-bottom py-2 fw-semibold small">Publish</div>
        <div className="card-body py-3 px-3">
          <div className="mb-3">
            {canPublish
              ? <span className="badge bg-warning text-dark">● Unpublished changes</span>
              : <span className="badge bg-success">✓ Up to date</span>
            }
          </div>
          <div className="d-flex gap-2 flex-wrap">
            <button className="btn btn-sm btn-outline-secondary" onClick={onDiscard} disabled={!canPublish}>
              Discard
            </button>
            <button className="btn btn-sm btn-outline-primary" onClick={onSaveDraft} disabled={!isDirty || saving}>
              {saving ? 'Saving...' : 'Save Draft'}
            </button>
            <button className="btn btn-sm btn-primary" onClick={onPublish} disabled={!canPublish || publishing}>
              {publishing ? 'Publishing...' : '▲ Publish'}
            </button>
          </div>
          <p className="text-muted mt-2 mb-0" style={{ fontSize: '11px' }}>
            Save Draft stores changes. Publish makes them visible to visitors.
          </p>
        </div>
      </div>
    </>
  );
}

function TabLauncher({ form, update }) {
  return (
    <>
      <div className="card border-0 shadow-sm mb-3">
        <div className="card-header bg-white border-bottom py-2 fw-semibold small">Color</div>
        <div className="card-body py-1 px-3">
          <ColorRow label="Background" value={form.launcher_bg_color} onChange={v => update('launcher_bg_color', v)} />
        </div>
      </div>

      <div className="card border-0 shadow-sm mb-3">
        <div className="card-header bg-white border-bottom py-2 fw-semibold small">Icon</div>
        <div className="card-body py-2 px-3">
          <div className="d-flex gap-2 flex-wrap">
            {Object.entries(LAUNCHER_ICONS_SVG).map(([key, svg]) => (
              <button key={key} onClick={() => update('launcher_icon', key)}
                style={{
                  width: '46px', height: '46px', borderRadius: '50%',
                  background: form.launcher_icon === key ? form.launcher_bg_color : '#f1f5f9',
                  border: `2px solid ${form.launcher_icon === key ? form.launcher_bg_color : '#e2e8f0'}`,
                  color: form.launcher_icon === key ? '#fff' : '#64748b',
                  display: 'flex', alignItems: 'center', justifyContent: 'center',
                  cursor: 'pointer', transition: 'all 0.15s',
                }}
                title={key}
              >{svg}</button>
            ))}
          </div>
          <p className="text-muted mt-2 mb-0" style={{ fontSize: '11px' }}>Ignored if a custom image is set below.</p>
        </div>
      </div>

      <div className="card border-0 shadow-sm mb-3">
        <div className="card-header bg-white border-bottom py-2 fw-semibold small">Custom Image</div>
        <div className="card-body py-2 px-3">
          <label className="form-label small text-muted mb-1">Image URL (overrides icon)</label>
          <input type="text" className="form-control form-control-sm"
            placeholder="https://example.com/logo.png"
            value={form.launcher_image}
            onChange={e => update('launcher_image', e.target.value)} />
          <p className="text-muted mt-1 mb-0" style={{ fontSize: '11px' }}>
            Also shown as avatar in chat header. Leave blank to use icon.
          </p>
        </div>
      </div>
    </>
  );
}

function TabHeader({ form, update }) {
  return (
    <>
      <div className="card border-0 shadow-sm mb-3">
        <div className="card-header bg-white border-bottom py-2 fw-semibold small">Header Bar</div>
        <div className="card-body py-1 px-3">
          <ColorRow label="Background Color" value={form.header_bg_color}   onChange={v => update('header_bg_color', v)} />
          <ColorRow label="Text Color"        value={form.header_text_color} onChange={v => update('header_text_color', v)} />
        </div>
      </div>

      <div className="card border-0 shadow-sm mb-3">
        <div className="card-header bg-white border-bottom py-2 fw-semibold small">Title</div>
        <div className="card-body py-2 px-3">
          <input type="text" className="form-control form-control-sm mb-2"
            placeholder="Sarah Assistant"
            value={form.header_text}
            onChange={e => update('header_text', e.target.value)} />
          <SelectRow label="Font Family" value={form.header_font_family}
            onChange={v => update('header_font_family', v)}
            options={FONTS} />
        </div>
      </div>

      <div className="card border-0 shadow-sm mb-3">
        <div className="card-header bg-white border-bottom py-2 fw-semibold small">Close Button (×)</div>
        <div className="card-body py-1 px-3">
          <ColorRow  label="Color"        value={form.close_btn_color} onChange={v => update('close_btn_color', v)} />
          <NumberRow label="Icon Size"    value={form.close_btn_size}  onChange={v => update('close_btn_size', v)} min={10} max={28} />
        </div>
      </div>
    </>
  );
}

function TabMessages({ form, update }) {
  return (
    <>
      <div className="card border-0 shadow-sm mb-3">
        <div className="card-header bg-white border-bottom py-2 fw-semibold small">Welcome State</div>
        <div className="card-body py-2 px-3">
          <label className="form-label small fw-semibold mb-1">Welcome Message</label>
          <textarea className="form-control form-control-sm" rows={2}
            value={form.welcome_message}
            onChange={e => update('welcome_message', e.target.value)}
            style={{ resize: 'none' }} />
          <ColorRow label="Message Area Background" value={form.msg_area_bg} onChange={v => update('msg_area_bg', v)} />
        </div>
      </div>

      <div className="card border-0 shadow-sm mb-3">
        <div className="card-header bg-white border-bottom py-2 fw-semibold small">User Bubble</div>
        <div className="card-body py-1 px-3">
          <ColorRow label="Background" value={form.bubble_user_bg}   onChange={v => update('bubble_user_bg', v)} />
          <ColorRow label="Text Color" value={form.bubble_user_text} onChange={v => update('bubble_user_text', v)} />
        </div>
      </div>

      <div className="card border-0 shadow-sm mb-3">
        <div className="card-header bg-white border-bottom py-2 fw-semibold small">AI Bubble</div>
        <div className="card-body py-1 px-3">
          <ColorRow label="Background" value={form.bubble_ai_bg}   onChange={v => update('bubble_ai_bg', v)} />
          <ColorRow label="Text Color" value={form.bubble_ai_text} onChange={v => update('bubble_ai_text', v)} />
        </div>
      </div>
    </>
  );
}

function TabInput({ form, update }) {
  return (
    <div className="card border-0 shadow-sm mb-3">
      <div className="card-header bg-white border-bottom py-2 fw-semibold small">Send Button</div>
      <div className="card-body py-1 px-3">
        <ColorRow label="Background Color" value={form.send_bg_color} onChange={v => update('send_bg_color', v)} />
      </div>
    </div>
  );
}

function TabQuickQuestions({ form, update }) {
  return (
    <div className="card border-0 shadow-sm mb-3">
      <div className="card-header bg-white border-bottom py-2 fw-semibold small">Quick Question Buttons</div>
      <div className="card-body py-1 px-3">
        <ColorRow  label="Border Color"    value={form.qq_border_color}  onChange={v => update('qq_border_color', v)} />
        <ColorRow  label="Text Color"      value={form.qq_text_color}    onChange={v => update('qq_text_color', v)} />
        <ColorRow  label="Hover Background" value={form.qq_hover_bg}     onChange={v => update('qq_hover_bg', v)} />
        <NumberRow label="Border Radius"   value={form.qq_border_radius} onChange={v => update('qq_border_radius', v)} min={0} max={50} />
      </div>
    </div>
  );
}

/* ── Main Page ─────────────────────────────────────────────── */

const TABS = [
  { id: 'general',  label: 'General'         },
  { id: 'launcher', label: 'Launcher'        },
  { id: 'header',   label: 'Header'          },
  { id: 'messages', label: 'Messages'        },
  { id: 'input',    label: 'Input'           },
  { id: 'quick',    label: 'Quick Questions' },
];

export default function AppearanceSettings() {
  const [form, setForm]             = useState(DEFAULTS);
  const [savedDraft, setSavedDraft] = useState(DEFAULTS);
  const [published, setPublished]   = useState(DEFAULTS);
  const [canPublish, setCanPublish] = useState(false);
  const [activeTab, setActiveTab]   = useState('general');
  const [loading, setLoading]       = useState(true);
  const [saving, setSaving]         = useState(false);
  const [publishing, setPublishing] = useState(false);

  useEffect(() => {
    apiFetch('appearance')
      .then(res => {
        if (!res.success) return;
        const { draft, published: pub, can_publish } = res.data;
        const mergedDraft = { ...DEFAULTS, ...draft };
        const mergedPub   = { ...DEFAULTS, ...pub };
        setForm(mergedDraft);
        setSavedDraft(mergedDraft);
        setPublished(mergedPub);
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
        setSavedDraft({ ...form });
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
        const pub = { ...DEFAULTS, ...res.data.published };
        setForm(pub);
        setSavedDraft(pub);
        setPublished(pub);
        setCanPublish(false);
      })
      .catch(() => {})
      .finally(() => setPublishing(false));
  }

  function handleDiscard() {
    apiFetch('appearance/discard', 'POST')
      .then(res => {
        if (!res.success) return;
        const pub = { ...DEFAULTS, ...res.data.published };
        setForm(pub);
        setSavedDraft(pub);
        setPublished(pub);
        setCanPublish(false);
      })
      .catch(() => {});
  }

  if (loading) return <p className="text-muted small p-3">Loading...</p>;

  const tabProps = { form, update, canPublish, isDirty, saving, publishing, onSaveDraft: handleSaveDraft, onPublish: handlePublish, onDiscard: handleDiscard };

  return (
    <div style={{ display: 'flex', alignItems: 'flex-start', gap: 0, margin: '-20px -20px -20px 0' }}>

      {/* ── Left: Tabs ── */}
      <div style={{ flex: 1, padding: '20px 20px 40px 20px', overflowY: 'auto', minHeight: 0 }}>
        <h1 className="h5 fw-semibold text-dark mb-3">Appearance</h1>

        {/* Tab Nav */}
        <ul className="nav nav-tabs mb-3" style={{ flexWrap: 'nowrap', overflowX: 'auto', borderBottom: '2px solid #dee2e6' }}>
          {TABS.map(t => (
            <li key={t.id} className="nav-item" style={{ flexShrink: 0 }}>
              <button
                className={`nav-link px-3 py-2 border-0 ${activeTab === t.id ? 'active fw-semibold' : 'text-muted'}`}
                style={{ fontSize: '13px', background: 'none', borderBottom: activeTab === t.id ? '2px solid #0d6efd' : 'none' }}
                onClick={() => setActiveTab(t.id)}
              >
                {t.label}
              </button>
            </li>
          ))}
        </ul>

        {/* Tab Content */}
        {activeTab === 'general'  && <TabGeneral  {...tabProps} />}
        {activeTab === 'launcher' && <TabLauncher {...tabProps} />}
        {activeTab === 'header'   && <TabHeader   {...tabProps} />}
        {activeTab === 'messages' && <TabMessages {...tabProps} />}
        {activeTab === 'input'    && <TabInput    {...tabProps} />}
        {activeTab === 'quick'    && <TabQuickQuestions {...tabProps} />}
      </div>

      {/* ── Right: Live Preview Sidebar ── */}
      <div style={{
        width: '340px',
        flexShrink: 0,
        position: 'sticky',
        top: '-20px',
        height: 'calc(100vh - 12px)',
        display: 'flex',
        flexDirection: 'column',
        borderLeft: '1px solid #dee2e6',
        background: '#f1f5f9',
        overflow: 'hidden',
      }}>
        <div style={{
          padding: '14px 16px 10px',
          borderBottom: '1px solid #dee2e6',
          background: '#fff',
          flexShrink: 0,
        }}>
          <div className="fw-semibold small">Live Preview</div>
          <div className="text-muted" style={{ fontSize: '11px' }}>Updates as you type</div>
        </div>
        <div style={{
          flex: 1,
          overflowY: 'auto',
          display: 'flex',
          alignItems: 'flex-start',
          justifyContent: 'center',
          padding: '24px 16px',
        }}>
          <WidgetPreview settings={form} />
        </div>
      </div>

    </div>
  );
}
