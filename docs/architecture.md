# Architecture

## Design goals

The project optimizes for a small operational footprint, explicit security controls, and code that a
junior engineer can explain in an interview. Slim is used as a router and middleware host rather
than as the foundation of a large application framework.

## Request flow

1. Nginx serves static assets and forwards dynamic requests to PHP-FPM.
2. Slim parses the request, applies security headers, and dispatches a route.
3. Route handlers validate input and call dedicated repository or service classes.
4. PDO prepared statements read or mutate PostgreSQL.
5. Twig renders escaped HTML; state-changing forms include a session CSRF token.

## Uptime calculation

For each service, the application reconstructs the status timeline over the previous 90 days.
Operational and Degraded Performance count as available. Partial Outage and Major Outage count as
unavailable. Maintenance is excluded from both numerator and denominator so planned work does not
artificially reduce uptime.

This result is an operator-reported availability figure. It should not be confused with independent
monitoring telemetry.

## Persistence

SQL migrations are applied in filename order and recorded in `schema_migrations`. PostgreSQL indexes
support status-page reads, incident history, per-service timelines, and login-attempt cleanup.

## Container boundaries

- `web`: public Nginx reverse proxy on port 8080.
- `php`: non-root PHP-FPM application with read-only filesystem and temporary runtime mounts.
- `database`: PostgreSQL on an internal network and named persistent volume.

Only Nginx publishes a host port.
