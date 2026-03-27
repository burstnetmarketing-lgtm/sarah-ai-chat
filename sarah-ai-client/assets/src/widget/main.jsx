import '../../css/widget.css';
import React from 'react';
import { createRoot } from 'react-dom/client';
import ChatWidget from './ChatWidget.jsx';

function hexToRgba(hex, alpha) {
  if (!hex || hex.length < 7) return `rgba(0,0,0,${alpha})`;
  const r = parseInt(hex.slice(1, 3), 16);
  const g = parseInt(hex.slice(3, 5), 16);
  const b = parseInt(hex.slice(5, 7), 16);
  return `rgba(${r},${g},${b},${alpha})`;
}

function setVar(el, name, value) {
  if (value !== undefined && value !== '') el.style.setProperty(name, value);
}

const container = document.getElementById('sarah-chat-root');
if (container) {
  const S = window.SarahAiWidget?.settings || {};

  // Launcher
  setVar(container, '--sac-launcher-bg',      S.launcher_bg_color);
  if (S.launcher_bg_color) {
    setVar(container, '--sac-launcher-shadow',  hexToRgba(S.launcher_bg_color, 0.45));
    setVar(container, '--sac-launcher-shadow2', hexToRgba(S.launcher_bg_color, 0.55));
  }

  // Widget size
  if (S.widget_width)  setVar(container, '--sac-widget-width',  S.widget_width  + 'px');
  if (S.widget_height) setVar(container, '--sac-widget-height', S.widget_height + 'px');

  // Header
  setVar(container, '--sac-header-bg',   S.header_bg_color);
  setVar(container, '--sac-header-text', S.header_text_color);
  if (S.header_font_family && S.header_font_family !== 'inherit') {
    setVar(container, '--sac-header-font', S.header_font_family);
  }
  setVar(container, '--sac-close-color', S.close_btn_color);
  if (S.close_btn_size) setVar(container, '--sac-close-size', S.close_btn_size + 'px');

  // Messages
  setVar(container, '--sac-msg-bg',           S.msg_area_bg);
  setVar(container, '--sac-bubble-user-bg',   S.bubble_user_bg);
  setVar(container, '--sac-bubble-user-text', S.bubble_user_text);
  setVar(container, '--sac-bubble-ai-bg',     S.bubble_ai_bg);
  setVar(container, '--sac-bubble-ai-text',   S.bubble_ai_text);

  // Input / Send
  setVar(container, '--sac-send-bg', S.send_bg_color);

  // Quick Questions
  setVar(container, '--sac-qq-border', S.qq_border_color);
  setVar(container, '--sac-qq-text',   S.qq_text_color);
  setVar(container, '--sac-qq-hover',  S.qq_hover_bg);
  if (S.qq_border_radius) setVar(container, '--sac-qq-radius', S.qq_border_radius + 'px');

  // Position
  if (S.widget_position === 'left') {
    container.style.left  = '24px';
    container.style.right = 'auto';
  }

createRoot(container).render(<ChatWidget />);
}
