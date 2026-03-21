import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App.jsx';

const container = document.getElementById('sarah-chat-root');
if (container) {
  createRoot(container).render(<App />);
}
