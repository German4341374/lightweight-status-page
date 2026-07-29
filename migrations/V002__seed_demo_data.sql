INSERT INTO services (name, description, status, display_order) VALUES
    ('Customer Portal', 'Public customer account and billing portal.', 'operational', 10),
    ('Public API', 'REST API used by customer integrations.', 'degraded', 20),
    ('Background Jobs', 'Asynchronous imports, exports, and notifications.', 'operational', 30),
    ('File Storage', 'Document upload and download service.', 'operational', 40);

INSERT INTO service_status_history (service_id, status, recorded_at) VALUES
    (1, 'operational', CURRENT_TIMESTAMP - INTERVAL '90 days'),
    (1, 'partial_outage', CURRENT_TIMESTAMP - INTERVAL '28 days'),
    (1, 'operational', CURRENT_TIMESTAMP - INTERVAL '27 days 22 hours'),
    (2, 'operational', CURRENT_TIMESTAMP - INTERVAL '90 days'),
    (2, 'degraded', CURRENT_TIMESTAMP - INTERVAL '45 minutes'),
    (3, 'operational', CURRENT_TIMESTAMP - INTERVAL '90 days'),
    (3, 'maintenance', CURRENT_TIMESTAMP - INTERVAL '15 days'),
    (3, 'operational', CURRENT_TIMESTAMP - INTERVAL '14 days 22 hours'),
    (4, 'operational', CURRENT_TIMESTAMP - INTERVAL '90 days');

INSERT INTO incidents (title, description, severity, status, started_at, resolved_at) VALUES
    (
        'Elevated API latency',
        'Some API requests are responding more slowly than usual. The team is investigating.',
        'minor',
        'investigating',
        CURRENT_TIMESTAMP - INTERVAL '45 minutes',
        NULL
    ),
    (
        'Customer portal login errors',
        'A subset of customers experienced intermittent login failures.',
        'major',
        'resolved',
        CURRENT_TIMESTAMP - INTERVAL '28 days',
        CURRENT_TIMESTAMP - INTERVAL '27 days 22 hours'
    );

INSERT INTO incident_updates (incident_id, message, status, created_at) VALUES
    (
        1,
        'The issue has been isolated to one upstream dependency. Requests continue to succeed with increased latency.',
        'identified',
        CURRENT_TIMESTAMP - INTERVAL '20 minutes'
    ),
    (
        1,
        'Monitoring detected elevated response times and the support team began investigating.',
        'investigating',
        CURRENT_TIMESTAMP - INTERVAL '45 minutes'
    ),
    (
        2,
        'Service has returned to normal and monitoring confirms the recovery.',
        'resolved',
        CURRENT_TIMESTAMP - INTERVAL '27 days 22 hours'
    ),
    (
        2,
        'Authentication capacity was increased while the team applied a permanent fix.',
        'monitoring',
        CURRENT_TIMESTAMP - INTERVAL '27 days 23 hours'
    ),
    (
        2,
        'We are investigating intermittent login failures.',
        'investigating',
        CURRENT_TIMESTAMP - INTERVAL '28 days'
    );
