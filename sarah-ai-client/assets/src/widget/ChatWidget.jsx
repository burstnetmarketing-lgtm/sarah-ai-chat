import React, { useState, useEffect } from 'react';
import LauncherButton from './LauncherButton.jsx';
import ChatWindow from './ChatWindow.jsx';

export default function ChatWidget() {
  const [isOpen, setIsOpen] = useState(false);

  useEffect(() => {
    const root = document.getElementById('sarah-chat-root');
    if (!root) return;

    if (window.innerWidth <= 768) {
      // Mobile: stretch root to fullscreen when open, restore when closed
      if (isOpen) {
        root.style.top    = '0';
        root.style.left   = '0';
        root.style.right  = '0';
        root.style.bottom = '0';
        root.style.width  = '100%';
        root.style.height = '100%';
        document.body.classList.add('sac-mobile-open');
      } else {
        root.style.top    = '';
        root.style.left   = '';
        root.style.right  = '24px';
        root.style.bottom = '24px';
        root.style.width  = '';
        root.style.height = '';
        document.body.classList.remove('sac-mobile-open');
      }
      return;
    }

    // Desktop: shift root so window sits just above the launcher
    const position = window.SarahAiWidget?.settings?.position || 'right';
    const isLeft   = position === 'left';
    if (isOpen) {
      root.style.bottom = '0';
      root.style[isLeft ? 'left' : 'right'] = '16px';
    } else {
      root.style.bottom = '24px';
      root.style[isLeft ? 'left' : 'right'] = '24px';
    }
  }, [isOpen]);

  useEffect(() => {
    return () => { document.body.classList.remove('sac-mobile-open'); };
  }, []);

  return (
    <>
      {isOpen && <ChatWindow onClose={() => setIsOpen(false)} />}
      {!isOpen && <LauncherButton onClick={() => setIsOpen(true)} />}
    </>
  );
}
