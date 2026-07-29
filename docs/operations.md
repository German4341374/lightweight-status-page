# Operations

## Health check

`GET /health` runs `SELECT 1` and returns HTTP 200 with JSON when both the application and database
are available. Docker checks PHP-FPM separately and Nginx through the public health route.

## Publishing an incident

1. Sign in to `/admin/login`.
2. Enter a concise title, customer-facing description, and severity.
3. Publish updates whenever the incident state changes.
4. Resolve the incident only after service recovery is confirmed.
5. Review `/history` and `/feed.xml` to confirm the public timeline.

## Correcting a service status

Use the service row in `/admin`. Re-submitting the current value is idempotent and does not create a
duplicate history entry.

## Database reset for local development

```bash
docker compose down --volumes
docker compose up --build --detach
```

This removes all local incidents and status history. Do not use this operation on production data.
