CREATE TABLE services (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    description VARCHAR(500) NOT NULL,
    status VARCHAR(32) NOT NULL,
    display_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT services_status_check CHECK (
        status IN ('operational', 'degraded', 'partial_outage', 'major_outage', 'maintenance')
    )
);

CREATE TABLE service_status_history (
    id BIGSERIAL PRIMARY KEY,
    service_id BIGINT NOT NULL REFERENCES services(id) ON DELETE CASCADE,
    status VARCHAR(32) NOT NULL,
    recorded_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT service_history_status_check CHECK (
        status IN ('operational', 'degraded', 'partial_outage', 'major_outage', 'maintenance')
    )
);

CREATE TABLE incidents (
    id BIGSERIAL PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    description TEXT NOT NULL,
    severity VARCHAR(24) NOT NULL,
    status VARCHAR(24) NOT NULL,
    started_at TIMESTAMPTZ NOT NULL,
    resolved_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT incidents_severity_check CHECK (severity IN ('minor', 'major', 'critical', 'maintenance')),
    CONSTRAINT incidents_status_check CHECK (status IN ('investigating', 'identified', 'monitoring', 'resolved'))
);

CREATE TABLE incident_updates (
    id BIGSERIAL PRIMARY KEY,
    incident_id BIGINT NOT NULL REFERENCES incidents(id) ON DELETE CASCADE,
    message TEXT NOT NULL,
    status VARCHAR(24) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT incident_updates_status_check CHECK (
        status IN ('investigating', 'identified', 'monitoring', 'resolved')
    )
);

CREATE TABLE login_attempts (
    id BIGSERIAL PRIMARY KEY,
    identifier_hash CHAR(64) NOT NULL,
    attempted_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_services_display_order ON services(display_order);
CREATE INDEX idx_service_history_service_time ON service_status_history(service_id, recorded_at DESC);
CREATE INDEX idx_incidents_started_at ON incidents(started_at DESC);
CREATE INDEX idx_incidents_status ON incidents(status);
CREATE INDEX idx_incident_updates_incident_time ON incident_updates(incident_id, created_at DESC);
CREATE INDEX idx_login_attempts_identifier_time ON login_attempts(identifier_hash, attempted_at DESC);
