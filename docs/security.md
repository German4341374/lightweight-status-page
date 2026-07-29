# Security guidance

## Production checklist

- Generate a unique `APP_KEY` with at least 32 random bytes.
- Generate a unique administrator password and store only its password hash.
- Set `APP_URL` to the public HTTPS address and `SESSION_SECURE=true`.
- Keep `.env` outside version control and restrict its filesystem permissions.
- Terminate TLS at a trusted reverse proxy and redirect HTTP to HTTPS.
- Back up PostgreSQL and test restores.
- Run `composer audit --locked` before releases.
- Review container base-image updates from Dependabot.

## Authentication boundary

This project deliberately supports one environment-configured operator. It does not provide
registration, roles, recovery email, OAuth, or long-lived API credentials. Organizations needing
multiple operators should place an identity-aware proxy in front of `/admin` or extend the model
with a reviewed authentication design.

## Rate limiting

Failed login attempts are keyed by an HMAC of the remote address. The raw address is not stored.
When the application sits behind a reverse proxy, configure that proxy carefully before trusting
forwarded address headers. The default implementation uses the direct server address to avoid
spoofed `X-Forwarded-For` values.
