// Install-only service worker (card 0015). It exists so phones offer
// "Install"; Chromium wants a fetch handler for that. It caches nothing:
// /api and /mcp answers are per user, and offline use is out of scope.
self.addEventListener('fetch', () => {});
