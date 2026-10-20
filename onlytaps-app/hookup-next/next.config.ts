import type { NextConfig } from 'next';

const csp = [
  "default-src 'self'",
  "script-src 'self' 'unsafe-inline'",
  "style-src 'self' 'unsafe-inline'",
  "font-src 'self' data:",
  "img-src 'self' data: https: blob:",
  "media-src 'self' data: https:",
  "connect-src 'self' wss:",
  "object-src 'none'",
  "base-uri 'self'",
  "form-action 'self'",
  "frame-ancestors 'self'",
].join('; ');

// Keep legacy .php URLs working for ported pages so existing links and bookmarks don't break.
const config: NextConfig = {
  poweredByHeader: false,
  experimental: { serverActions: { bodySizeLimit: '6mb' } },
  async rewrites() {
    return [
      { source: '/choose-location.php', destination: '/choose-location' },
      { source: '/city.php', destination: '/city' },
      { source: '/listing.php', destination: '/listing' },
      { source: '/messages.php', destination: '/messages' },
      { source: '/create-listing.php', destination: '/create-listing' },
      { source: '/edit-listing.php', destination: '/edit-listing' },
      { source: '/profile.php', destination: '/profile' },
      { source: '/my-listings.php', destination: '/my-listings' },
      { source: '/favorites.php', destination: '/favorites' },
      { source: '/marketplace.php', destination: '/marketplace' },
      { source: '/membership.php', destination: '/membership' },
      { source: '/edit-profile.php', destination: '/edit-profile' },
      ...['dashboard', 'users', 'listings', 'reports', 'categories'].map((p) => ({ source: `/admin/${p}.php`, destination: `/admin/${p}` })),
    ];
  },
  async headers() {
    return [{
      source: '/:path*',
      headers: [
        { key: 'Content-Security-Policy', value: csp },
        { key: 'X-Frame-Options', value: 'SAMEORIGIN' },
        { key: 'X-Content-Type-Options', value: 'nosniff' },
        { key: 'Referrer-Policy', value: 'strict-origin-when-cross-origin' },
        { key: 'Permissions-Policy', value: 'geolocation=(self), microphone=(), camera=()' },
        { key: 'Strict-Transport-Security', value: 'max-age=31536000' },
      ],
    }];
  },
};

export default config;
