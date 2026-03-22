import React from 'react';

const cfg         = window.SarahAiWidget?.settings || {};
const chatTitle   = cfg.header_text   || 'Sarah Assistant';
const logoImage   = cfg.launcher_image || '';
const closeBtnSize = parseInt(cfg.close_btn_size) || 16;

export default function Header({ onClose }) {
  return (
    <div className="sac-header">
      <div className="sac-header-info">
        <div className="sac-header-avatar">
          {logoImage ? <img src={logoImage} alt="" /> : 'S'}
        </div>
        <span className="sac-header-title">{chatTitle}</span>
      </div>
      <button className="sac-close" onClick={onClose} aria-label="Close chat">
        <svg width={closeBtnSize} height={closeBtnSize} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round">
          <line x1="18" y1="6" x2="6" y2="18" />
          <line x1="6" y1="6" x2="18" y2="18" />
        </svg>
      </button>
    </div>
  );
}
