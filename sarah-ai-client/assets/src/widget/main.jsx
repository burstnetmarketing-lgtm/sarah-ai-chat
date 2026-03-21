import '../../css/widget.css';
import React from 'react';
import { createRoot } from 'react-dom/client';
import ChatWidget from './ChatWidget.jsx';

const container = document.getElementById('sarah-chat-root');
if (container) {
  createRoot(container).render(<ChatWidget />);
}
