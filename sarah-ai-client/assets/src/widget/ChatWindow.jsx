import React from 'react';
import Header from './Header.jsx';
import MessageArea from './MessageArea.jsx';
import InputBox from './InputBox.jsx';

export default function ChatWindow({ onClose }) {
  return (
    <div className="sac-window" role="dialog" aria-label="Chat window">
      <Header onClose={onClose} />
      <MessageArea />
      <InputBox />
    </div>
  );
}
