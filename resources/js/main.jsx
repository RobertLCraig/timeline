import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import App from './App.jsx';
import './index.css';

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <App />
  </StrictMode>,
);

// Root worker (public/sw.js) so its scope is the whole site; lets phones install it.
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/sw.js');
}
