import '../scss/app.scss';
import './styles/custom/logo.css';
import './styles/custom/background.css';
import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App.jsx';

const container = document.getElementById('app');
if (container) {
  createRoot(container).render(<App />);
}
