import '../../css/widget.css';
import React from 'react';
import { createRoot } from 'react-dom/client';
import ChatWidget from './ChatWidget.jsx';

const container = document.getElementById('sarah-chat-root');
if (container) {
  const cfg = window.SarahAiWidget?.settings || {};
  const color    = cfg.primaryColor || '#2563eb';
  const position = cfg.position     || 'right';

  // Apply CSS custom property for dynamic color
  function hexToRgba(hex, alpha) {
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    return `rgba(${r},${g},${b},${alpha})`;
  }
  container.style.setProperty('--sac-color',        color);
  container.style.setProperty('--sac-color-shadow',  hexToRgba(color, 0.45));
  container.style.setProperty('--sac-color-shadow2', hexToRgba(color, 0.55));

  // Apply position
  if (position === 'left') {
    container.style.left  = '24px';
    container.style.right = 'auto';
  }

  createRoot(container).render(<ChatWidget />);
}
