<?php

declare(strict_types=1);

use App\Domain\IncidentSeverity;
use App\Domain\IncidentStatus;
use App\Domain\ServiceStatus;
use App\Repository\IncidentRepository;
use App\Repository\ServiceRepository;
use App\Security\AdminAuthenticator;
use App\Security\Csrf;
use App\Security\LoginRateLimiter;
use App\Service\StatusPageService;
use App\Service\UptimeCalculator;
use App\Support\DatabaseFactory;
use App\Support\Flash;
use App\Support\InputValidator;
use App\Support\ValidationException;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

require \dirname(__DIR__) . '/vendor/autoload.php';

$config = require __DIR__ . '/environment.php';

ini_set('session.use_strict_mode', '1');
session_name($config->sessionName);
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $config->sessionSecure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (PHP_SESSION_ACTIVE !== session_status()) {
    session_start();
}

$pdo = DatabaseFactory::create($config);
$serviceRepository = new ServiceRepository($pdo, new UptimeCalculator());
$incidentRepository = new IncidentRepository($pdo);
$statusPage = new StatusPageService($serviceRepository, $incidentRepository);
$authenticator = new AdminAuthenticator($config->adminUsername, $config->adminPasswordHash);
$rateLimiter = new LoginRateLimiter($pdo, $config->appKey);
$csrf = new Csrf();
$flash = new Flash();
$twig = Twig::create(\dirname(__DIR__) . '/templates', [
    'cache' => false,
    'autoescape' => 'html',
]);
$twig->getEnvironment()->addGlobal('app_url', $config->appUrl);

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->add(TwigMiddleware::create($app, $twig));
$app->add(static function (ServerRequestInterface $request, $handler): ResponseInterface {
    $response = $handler->handle($request);

    return $response
        ->withHeader('Content-Security-Policy', "default-src 'self'; style-src 'self'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'self'")
        ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->withHeader('X-Content-Type-Options', 'nosniff')
        ->withHeader('X-Frame-Options', 'DENY')
        ->withHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
});
$app->addErrorMiddleware($config->debug, true, true);

$common = static function () use ($csrf, $flash, $authenticator): array {
    return [
        'csrf_token' => $csrf->token(),
        'flash_messages' => $flash->consume(),
        'is_admin' => $authenticator->isAuthenticated(),
        'service_statuses' => ServiceStatus::options(),
        'incident_statuses' => IncidentStatus::options(),
        'incident_severities' => IncidentSeverity::options(),
    ];
};

$app->get('/', function (ServerRequestInterface $request, ResponseInterface $response) use ($twig, $statusPage, $common): ResponseInterface {
    return $twig->render($response, 'status.html', array_merge($common(), $statusPage->dashboard(new DateTimeImmutable())));
})->setName('status');

$app->get('/history', function (ServerRequestInterface $request, ResponseInterface $response) use ($twig, $statusPage, $common): ResponseInterface {
    return $twig->render($response, 'history.html', array_merge($common(), $statusPage->dashboard(new DateTimeImmutable())));
})->setName('history');

$app->get('/feed.xml', function (ServerRequestInterface $request, ResponseInterface $response) use ($incidentRepository, $config): ResponseInterface {
    $incidents = $incidentRepository->recent((new DateTimeImmutable())->modify('-90 days'));
    $items = '';
    foreach ($incidents as $incident) {
        $title = htmlspecialchars((string) $incident['title'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars((string) $incident['description'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $link = \sprintf('%s/history#incident-%d', $config->appUrl, (int) $incident['id']);
        $published = (new DateTimeImmutable((string) $incident['started_at']))->format(DATE_RSS);
        $items .= "<item><title>{$title}</title><link>{$link}</link><guid>{$link}</guid><pubDate>{$published}</pubDate><description>{$description}</description></item>";
    }
    $xml = \sprintf(
        '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>Acme Systems Status</title><link>%s</link><description>Service incident updates</description>%s</channel></rss>',
        htmlspecialchars($config->appUrl, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
        $items,
    );
    $response->getBody()->write($xml);

    return $response->withHeader('Content-Type', 'application/rss+xml; charset=utf-8');
})->setName('feed');

$app->get('/health', function (ServerRequestInterface $request, ResponseInterface $response) use ($pdo): ResponseInterface {
    $healthQuery = $pdo->query('SELECT 1');
    if (false === $healthQuery) {
        throw new RuntimeException('Database health query failed.');
    }
    $healthQuery->fetchColumn();
    $response->getBody()->write((string) json_encode([
        'status' => 'ok',
        'database' => 'reachable',
        'timestamp' => (new DateTimeImmutable())->format(DATE_ATOM),
    ], JSON_THROW_ON_ERROR));

    return $response->withHeader('Content-Type', 'application/json');
})->setName('health');

$app->map(['GET', 'POST'], '/admin/login', function (ServerRequestInterface $request, ResponseInterface $response) use ($twig, $authenticator, $rateLimiter, $csrf, $flash, $common): ResponseInterface {
    if ('POST' === $request->getMethod()) {
        $body = (array) $request->getParsedBody();
        $csrf->validate($body['_csrf'] ?? null);
        $address = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
        if ($rateLimiter->isBlocked($address)) {
            return $twig->render(
                $response->withStatus(429)->withHeader('Retry-After', (string) $rateLimiter->retryAfterSeconds()),
                'admin/login.html',
                array_merge($common(), ['error' => 'Too many failed attempts. Try again in 15 minutes.']),
            );
        }
        $username = InputValidator::text($body['username'] ?? null, 'Username', 120);
        $password = InputValidator::text($body['password'] ?? null, 'Password', 500);
        if ($authenticator->verify($username, $password)) {
            $rateLimiter->clear($address);
            $authenticator->login();
            $flash->add('success', 'Signed in successfully.');

            return $response->withHeader('Location', '/admin')->withStatus(303);
        }
        $rateLimiter->recordFailure($address);

        return $twig->render(
            $response->withStatus(401),
            'admin/login.html',
            array_merge($common(), ['error' => 'Invalid username or password.']),
        );
    }

    return $twig->render($response, 'admin/login.html', array_merge($common(), ['error' => null]));
})->setName('admin.login');

$requireAdmin = static function (ServerRequestInterface $request, $handler) use ($authenticator): ResponseInterface {
    if (!$authenticator->isAuthenticated()) {
        return AppFactory::determineResponseFactory()
            ->createResponse(303)
            ->withHeader('Location', '/admin/login');
    }

    return $handler->handle($request);
};

$app->group('/admin', function (RouteCollectorProxy $group) use ($twig, $statusPage, $common, $csrf, $flash, $serviceRepository, $incidentRepository, $authenticator): void {
    $group->get('', function (ServerRequestInterface $request, ResponseInterface $response) use ($twig, $statusPage, $common): ResponseInterface {
        return $twig->render($response, 'admin/dashboard.html', array_merge($common(), $statusPage->dashboard(new DateTimeImmutable())));
    })->setName('admin.dashboard');

    $group->post('/logout', function (ServerRequestInterface $request, ResponseInterface $response) use ($csrf, $authenticator): ResponseInterface {
        $body = (array) $request->getParsedBody();
        $csrf->validate($body['_csrf'] ?? null);
        $authenticator->logout();

        return $response->withHeader('Location', '/')->withStatus(303);
    });

    $group->post('/services', function (ServerRequestInterface $request, ResponseInterface $response) use ($csrf, $flash, $serviceRepository): ResponseInterface {
        try {
            $body = (array) $request->getParsedBody();
            $csrf->validate($body['_csrf'] ?? null);
            $serviceRepository->create(
                InputValidator::text($body['name'] ?? null, 'Name', 120),
                InputValidator::text($body['description'] ?? null, 'Description', 500),
                ServiceStatus::from(InputValidator::text($body['status'] ?? null, 'Status', 32)),
                InputValidator::integer($body['display_order'] ?? null, 'Display order', 0, 10000),
            );
            $flash->add('success', 'Service created.');
        } catch (ValueError | ValidationException $exception) {
            $flash->add('error', $exception->getMessage());
        }

        return $response->withHeader('Location', '/admin')->withStatus(303);
    });

    $group->post('/services/{id}/status', function (ServerRequestInterface $request, ResponseInterface $response, array $args) use ($csrf, $flash, $serviceRepository): ResponseInterface {
        try {
            $body = (array) $request->getParsedBody();
            $csrf->validate($body['_csrf'] ?? null);
            $serviceRepository->updateStatus(
                (int) $args['id'],
                ServiceStatus::from(InputValidator::text($body['status'] ?? null, 'Status', 32)),
            );
            $flash->add('success', 'Service status updated.');
        } catch (ValueError | ValidationException | RuntimeException $exception) {
            $flash->add('error', $exception->getMessage());
        }

        return $response->withHeader('Location', '/admin')->withStatus(303);
    });

    $group->post('/incidents', function (ServerRequestInterface $request, ResponseInterface $response) use ($csrf, $flash, $incidentRepository): ResponseInterface {
        try {
            $body = (array) $request->getParsedBody();
            $csrf->validate($body['_csrf'] ?? null);
            $id = $incidentRepository->create(
                InputValidator::text($body['title'] ?? null, 'Title', 180),
                InputValidator::text($body['description'] ?? null, 'Description', 4000),
                IncidentSeverity::from(InputValidator::text($body['severity'] ?? null, 'Severity', 24)),
                IncidentStatus::Investigating,
                new DateTimeImmutable(),
            );
            $flash->add('success', 'Incident published.');

            return $response->withHeader('Location', \sprintf('/admin/incidents/%d', $id))->withStatus(303);
        } catch (ValueError | ValidationException $exception) {
            $flash->add('error', $exception->getMessage());

            return $response->withHeader('Location', '/admin')->withStatus(303);
        }
    });

    $group->get('/incidents/{id}', function (ServerRequestInterface $request, ResponseInterface $response, array $args) use ($twig, $common, $incidentRepository): ResponseInterface {
        return $twig->render(
            $response,
            'admin/incident.html',
            array_merge($common(), ['incident' => $incidentRepository->find((int) $args['id'])]),
        );
    });

    $group->post('/incidents/{id}/updates', function (ServerRequestInterface $request, ResponseInterface $response, array $args) use ($csrf, $flash, $incidentRepository): ResponseInterface {
        try {
            $body = (array) $request->getParsedBody();
            $csrf->validate($body['_csrf'] ?? null);
            $incidentRepository->addUpdate(
                (int) $args['id'],
                InputValidator::text($body['message'] ?? null, 'Message', 4000),
                IncidentStatus::from(InputValidator::text($body['status'] ?? null, 'Status', 24)),
            );
            $flash->add('success', 'Incident update published.');
        } catch (ValueError | ValidationException | RuntimeException $exception) {
            $flash->add('error', $exception->getMessage());
        }

        return $response->withHeader('Location', \sprintf('/admin/incidents/%d', (int) $args['id']))->withStatus(303);
    });

    $group->post('/incidents/{id}/resolve', function (ServerRequestInterface $request, ResponseInterface $response, array $args) use ($csrf, $flash, $incidentRepository): ResponseInterface {
        try {
            $body = (array) $request->getParsedBody();
            $csrf->validate($body['_csrf'] ?? null);
            $incidentRepository->resolve(
                (int) $args['id'],
                InputValidator::text($body['message'] ?? 'The incident has been resolved.', 'Message', 4000),
            );
            $flash->add('success', 'Incident resolved.');
        } catch (ValidationException | RuntimeException $exception) {
            $flash->add('error', $exception->getMessage());
        }

        return $response->withHeader('Location', \sprintf('/admin/incidents/%d', (int) $args['id']))->withStatus(303);
    });
})->add($requireAdmin);

return $app;
