import React from 'react';

const ICONS = {
  bubble: (
    <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
      <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12z"/>
      <circle cx="8" cy="10" r="1.2"/><circle cx="12" cy="10" r="1.2"/><circle cx="16" cy="10" r="1.2"/>
    </svg>
  ),
  message: (
    <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
      <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/>
    </svg>
  ),
  support: (
    <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
      <path d="M12 1C5.9 1 1 5.9 1 12s4.9 11 11 11 11-4.9 11-11S18.1 1 12 1zm-1 6h2v2h-2V7zm0 4h2v6h-2v-6z"/>
    </svg>
  ),
  heart: (
    <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
      <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
    </svg>
  ),
};

const cfg          = window.SarahAiWidget?.settings || {};
const launcherIcon  = cfg.launcher_icon  || 'bubble';
const launcherImage = cfg.launcher_image || '';

export default function LauncherButton({ onClick }) {
  return (
    <button className="sac-launcher" onClick={onClick} aria-label="Open chat">
      {launcherImage
        ? <img src={launcherImage} alt="" />
        : (ICONS[launcherIcon] || ICONS.bubble)
      }
    </button>
  );
}
