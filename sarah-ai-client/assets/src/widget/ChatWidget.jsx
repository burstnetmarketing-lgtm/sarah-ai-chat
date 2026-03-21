import React, { useState } from 'react';
import LauncherButton from './LauncherButton.jsx';
import ChatWindow from './ChatWindow.jsx';

export default function ChatWidget() {
  const [isOpen, setIsOpen] = useState(false);

  return (
    <>
      {isOpen && <ChatWindow onClose={() => setIsOpen(false)} />}
      {!isOpen && <LauncherButton onClick={() => setIsOpen(true)} />}
    </>
  );
}
