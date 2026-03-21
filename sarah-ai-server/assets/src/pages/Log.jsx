import React, { useState, useEffect, useCallback } from 'react';
import { apiFetch } from '../api/client.js';

const LEVEL_STYLE = {
  ERROR: { badge: 'bg-danger-subtle text-danger',   icon: 'bi-x-circle' },
  WARN:  { badge: 'bg-warning-subtle text-warning', icon: 'bi-exclamation-triangle' },
  INFO:  { badge: 'bg-info-subtle text-info',       icon: 'bi-info-circle' },
};

function parseLine(line) {
  const m = line.match(/^\[(.+?)\]\s+\[(\w+)\]\s+\[(.+?)\]\s+(.*)$/);
  if (!m) return { raw: line };
  return { time: m[1], level: m[2].toUpperCase(), context: m[3], message: m[4] };
}

export default function Log() {
  const [lines, setLines]   = useState([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter]  = useState('ALL');

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await apiFetch('log');
      if (res.success) setLines(res.data.lines.map(parseLine));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const filtered = filter === 'ALL' ? lines : lines.filter(l => l.level === filter);

  return (
    <>
      <div className="d-flex align-items-center justify-content-between mb-3">
        <div>
          <h1 className="h5 fw-semibold text-dark mb-1">Log Viewer</h1>
          <p className="text-muted small mb-0">Last 500 entries — newest first.</p>
        </div>
        <div className="d-flex gap-2">
          {['ALL','ERROR','WARN','INFO'].map(l => (
            <button key={l}
              className={`btn btn-sm ${filter === l ? 'btn-dark' : 'btn-outline-secondary'}`}
              onClick={() => setFilter(l)}>{l}</button>
          ))}
          <button className="btn btn-sm btn-outline-primary" onClick={load}>
            <i className="bi bi-arrow-clockwise"></i>
          </button>
        </div>
      </div>

      {loading ? (
        <p className="text-muted small">Loading...</p>
      ) : filtered.length === 0 ? (
        <p className="text-muted small">No log entries.</p>
      ) : (
        <div className="font-monospace small" style={{ fontSize: '0.78rem' }}>
          {filtered.map((entry, i) => {
            if (entry.raw) return (
              <div key={i} className="border-bottom py-1 px-2 text-muted">{entry.raw}</div>
            );
            const s = LEVEL_STYLE[entry.level] || LEVEL_STYLE.INFO;
            return (
              <div key={i} className="d-flex align-items-start gap-2 border-bottom py-1 px-2">
                <span className={`badge ${s.badge} mt-1 flex-shrink-0`} style={{ width: '48px', textAlign: 'center' }}>
                  {entry.level}
                </span>
                <span className="text-muted flex-shrink-0" style={{ width: '140px' }}>{entry.time}</span>
                <span className="text-secondary flex-shrink-0" style={{ width: '160px' }}>{entry.context}</span>
                <span className="flex-grow-1">{entry.message}</span>
              </div>
            );
          })}
        </div>
      )}
    </>
  );
}
