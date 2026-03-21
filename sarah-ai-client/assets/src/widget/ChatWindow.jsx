import React, { useState, useCallback } from 'react';
import Header from './Header.jsx';
import MessageArea from './MessageArea.jsx';
import InputBox from './InputBox.jsx';

let _id = 0;
function nextId() { return ++_id; }

function getMockResponse() {
  return new Promise(resolve => {
    const delay = 800 + Math.random() * 700;
    setTimeout(() => resolve('Thanks for your message! How can I help you further?'), delay);
  });
}

export default function ChatWindow({ onClose }) {
  const [messages, setMessages] = useState([]);
  const [isTyping, setIsTyping] = useState(false);

  const sendMessage = useCallback((text) => {
    const trimmed = text.trim();
    if (!trimmed || isTyping) return;

    setMessages(prev => [...prev, { id: nextId(), type: 'user', text: trimmed }]);
    setIsTyping(true);

    getMockResponse().then(response => {
      setMessages(prev => [...prev, { id: nextId(), type: 'ai', text: response }]);
      setIsTyping(false);
    });
  }, [isTyping]);

  return (
    <div className="sac-window" role="dialog" aria-label="Chat window">
      <Header onClose={onClose} />
      <MessageArea messages={messages} isTyping={isTyping} onQuickQuestion={sendMessage} />
      <InputBox onSend={sendMessage} disabled={isTyping} />
    </div>
  );
}
