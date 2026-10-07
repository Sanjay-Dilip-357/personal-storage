/**
 * PERSONAL STORAGE — Service Worker
 * Manages caching for app shell and core assets.
 * 
 * ⚠️ SECURITY RULE: Never cache private user storage items (files, notes, admin queries).
 */

const CACHE_NAME = 'ps-static-v1';

// Only assets that do not contain private user information are cached
const ASSETS_TO_CACHE = [
  '/personal-storage/public/offline.html',
  '/personal-storage/public/assets/css/app.css',
  '/personal-storage/public/assets/css/dashboard.css',
  '/personal-storage/public/assets/css/auth.css',
  '/personal-storage/public/assets/css/landing.css',
  '/personal-storage/public/assets/js/app.js',
  '/personal-storage/public/assets/js/dashboard.js'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(ASSETS_TO_CACHE);
    }).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.map((key) => {
          if (key !== CACHE_NAME) {
            return caches.delete(key);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  // Security Override: Never intercept or cache API, Auth, Admin, or Streaming requests
  if (
    url.pathname.includes('/api/') ||
    url.pathname.includes('/admin/') ||
    url.pathname.includes('download.php') ||
    url.pathname.includes('preview.php') ||
    event.request.method !== 'GET'
  ) {
    return; // Pass through to network natively
  }

  event.respondWith(
    caches.match(event.request).then((cachedResponse) => {
      if (cachedResponse) {
        return cachedResponse; // Return cache match
      }

      return fetch(event.request).catch(() => {
        // If the network request fails and it is a navigation request, show the offline page
        if (event.request.mode === 'navigate') {
          return caches.match('/personal-storage/public/offline.html');
        }
      });
    })
  );
});