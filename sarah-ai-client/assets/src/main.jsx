import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.css';
import '../css/admin.css';
import 'bootstrap/dist/js/bootstrap.bundle.min.js';
import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App.jsx';

const container = document.getElementById('app');
if (container) {
  createRoot(container).render(<App />);
}
