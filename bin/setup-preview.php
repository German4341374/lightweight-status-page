<?php

declare(strict_types=1);

require \dirname(__DIR__) . '/vendor/autoload.php';

$path = \dirname(__DIR__) . '/storage/preview.sqlite';
if (!is_dir(\dirname($path))) {
    mkdir(\dirname($path), 0775, true);
}
if (is_file($path)) {
    unlink($path);
}

$pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec(
    <<<'SQL'
CREATE TABLE services (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    description TEXT NOT NULL,
    status TEXT NOT NULL,
    display_order INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE service_status_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    service_id INTEGER NOT NULL REFERENCES services(id) ON DELETE CASCADE,
    status TEXT NOT NULL,
    recorded_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE incidents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT NOT NULL,
    severity TEXT NOT NULL,
    status TEXT NOT NULL,
    started_at TEXT NOT NULL,
    resolved_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE incident_updates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    incident_id INTEGER NOT NULL REFERENCES incidents(id) ON DELETE CASCADE,
    message TEXT NOT NULL,
    status TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    identifier_hash TEXT NOT NULL,
    attempted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
SQL,
);

$now = new DateTimeImmutable();
$services = [
    ['Customer Portal', 'Public customer account and billing portal.', 'operational', 10],
    ['Public API', 'REST API used by customer integrations.', 'degraded', 20],
    ['Background Jobs', 'Asynchronous imports, exports, and notifications.', 'operational', 30],
    ['File Storage', 'Document upload and download service.', 'operational', 40],
];
$serviceInsert = $pdo->prepare(
    'INSERT INTO services (name, description, status, display_order) VALUES (?, ?, ?, ?)',
);
foreach ($services as $service) {
    $serviceInsert->execute($service);
}

$historyInsert = $pdo->prepare(
    'INSERT INTO service_status_history (service_id, status, recorded_at) VALUES (?, ?, ?)',
);
$history = [
    [1, 'operational', '-90 days'],
    [1, 'partial_outage', '-28 days'],
    [1, 'operational', '-27 days -22 hours'],
    [2, 'operational', '-90 days'],
    [2, 'degraded', '-45 minutes'],
    [3, 'operational', '-90 days'],
    [3, 'maintenance', '-15 days'],
    [3, 'operational', '-14 days -22 hours'],
    [4, 'operational', '-90 days'],
];
foreach ($history as [$serviceId, $status, $relative]) {
    $historyInsert->execute([$serviceId, $status, $now->modify($relative)->format(DATE_ATOM)]);
}

$incidentInsert = $pdo->prepare(
    'INSERT INTO incidents (title, description, severity, status, started_at, resolved_at)
     VALUES (?, ?, ?, ?, ?, ?)',
);
$incidentInsert->execute([
    'Elevated API latency',
    'Some API requests are responding more slowly than usual. The team is investigating.',
    'minor',
    'identified',
    $now->modify('-45 minutes')->format(DATE_ATOM),
    null,
]);
$incidentInsert->execute([
    'Customer portal login errors',
    'A subset of customers experienced intermittent login failures.',
    'major',
    'resolved',
    $now->modify('-28 days')->format(DATE_ATOM),
    $now->modify('-27 days -22 hours')->format(DATE_ATOM),
]);

$updateInsert = $pdo->prepare(
    'INSERT INTO incident_updates (incident_id, message, status, created_at) VALUES (?, ?, ?, ?)',
);
$updates = [
    [1, 'The issue has been isolated to one upstream dependency. Requests continue to succeed with increased latency.', 'identified', '-20 minutes'],
    [1, 'Monitoring detected elevated response times and the support team began investigating.', 'investigating', '-45 minutes'],
    [2, 'Service has returned to normal and monitoring confirms the recovery.', 'resolved', '-27 days -22 hours'],
    [2, 'Authentication capacity was increased while the team applied a permanent fix.', 'monitoring', '-27 days -23 hours'],
    [2, 'We are investigating intermittent login failures.', 'investigating', '-28 days'],
];
foreach ($updates as [$incidentId, $message, $status, $relative]) {
    $updateInsert->execute([$incidentId, $message, $status, $now->modify($relative)->format(DATE_ATOM)]);
}

fwrite(STDOUT, \sprintf("Preview database created at %s\n", $path));
