# Changelog

All notable changes to `laravel-uber` will be documented in this file.

## 0.1.0 - 2026-10-05

First release.

- `Uber` facade covering the Riders, Guest Rides, Health, Uber Direct, Uber Eats Marketplace, Uber for Business, Identity, Drivers, Vehicle Suppliers, Ads, AI Solutions and Uber Pay APIs.
- OAuth: authorization code with PKCE, pushed authorization requests, refresh, revoke and JWKS. Client-credentials app tokens are cached per scope and replaced when Uber rejects them.
- Per-API sandbox hosts: `sandbox-api` for rides, guest rides, health and business; `test-api` with `sandbox-login` tokens for Eats.
- `Response` with dot-path access, item collections, and `next()` / `lazy()` for offset, cursor, `next_href` and page-token pagination.
- Typed exceptions for Uber's error shapes, including surge confirmations and `Retry-After`.
- Webhook route that verifies HMAC signatures under the client secret or any configured signing key, de-duplicates retries and dispatches `WebhookReceived` and `uber.{event}` events.
- `uber:install` and `uber:status` commands.
- Tests for every wrapped endpoint, and GitHub Actions CI on PHP 8.2–8.5 with Laravel 12 and 13.
