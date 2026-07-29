# Repository guidance

- Keep source code, comments, documentation, and commits in English.
- Preserve the compact Slim architecture; do not introduce a large framework without an ADR.
- Use prepared statements for every dynamic SQL value.
- Protect all state-changing forms with CSRF validation.
- Keep credentials in environment variables and update `.env.example` with placeholders only.
- Run `composer verify` and `composer audit --locked` before committing.
- Use Conventional Commits.
