import React, { useState, useEffect } from 'react';
import LauncherButton from './LauncherButton.jsx';
import ChatWindow from './ChatWindow.jsx';

export default function ChatWidget() {
  const [isOpen, setIsOpen] = useState(false);

  useEffect(() => {
    const root = document.getElementById('sarah-chat-root');
    if (!root) return;
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

  return (
    <>
      {isOpen && <ChatWindow onClose={() => setIsOpen(false)} />}
      {!isOpen && <LauncherButton onClick={() => setIsOpen(true)} />}
    </>
  );
}
