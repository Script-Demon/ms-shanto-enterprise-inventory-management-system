/* Service worker for the installable app.
   Deliberately a pass-through: this app shows live stock levels, dues and
   money figures, so nothing is served from cache. A stale invoice or stock
   count would be worse than simply being offline. Its job is to satisfy the
   install requirement and let the app run in its own window. */

self.addEventListener('install', function () {
  self.skipWaiting();
});

self.addEventListener('activate', function (event) {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', function () {
  // Intentionally no respondWith() — every request goes to the network.
});
