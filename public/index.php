<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

session_start();

$app = AppFactory::create();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

// Twig setup
$twig = Twig::create(__DIR__ . '/../templates', ['cache' => false]);
$app->add(TwigMiddleware::create($app, $twig));

/**
 * MVP hardcoded hairdressers (hiljem DB)
 */
$hairdressers = [
    ['id' => 1, 'name' => 'Katrin'],
    ['id' => 2, 'name' => 'Marko'],
];

/**
 * Bookings storage (MVP): session
 * Struktuur:
 * $_SESSION['bookings'][hairdresserId][date][] = ['start' => 'HH:MM', 'end' => 'HH:MM']
 */
if (!isset($_SESSION['bookings']) || !is_array($_SESSION['bookings'])) {
    $_SESSION['bookings'] = [];
}

/**
 * Helper: find hairdresser name by id
 */
$findHairdresserName = function (int $hairdresserId) use ($hairdressers): string {
    foreach ($hairdressers as $h) {
        if ((int)$h['id'] === $hairdresserId) return (string)$h['name'];
    }
    return 'Tundmatu';
};

/**
 * Helper: overlap check
 */
$isOverlapping = function (string $startA, string $endA, string $startB, string $endB): bool {
    // overlap if A.start < B.end AND A.end > B.start
    return ($startA < $endB) && ($endA > $startB);
};

/**
 * Helper: get bookings list
 */
$getBookingsForDay = function (int $hairdresserId, string $date): array {
    $all = $_SESSION['bookings'] ?? [];
    return $all[$hairdresserId][$date] ?? [];
};

/**
 * Helper: add booking (assumes already validated + no overlap)
 */
$addBooking = function (int $hairdresserId, string $date, string $start, string $end): void {
    if (!isset($_SESSION['bookings'][$hairdresserId])) {
        $_SESSION['bookings'][$hairdresserId] = [];
    }
    if (!isset($_SESSION['bookings'][$hairdresserId][$date])) {
        $_SESSION['bookings'][$hairdresserId][$date] = [];
    }
    $_SESSION['bookings'][$hairdresserId][$date][] = ['start' => $start, 'end' => $end];
};

/**
 * GET /
 */
$app->get('/', function (Request $req, Response $res) {
    return $res->withHeader('Location', '/booking')->withStatus(302);
});

/**
 * GET /booking
 * Avalik broneerimisvorm
 */
$app->get('/booking', function (Request $req, Response $res) use ($twig, $hairdressers) {
    $today = (new DateTime())->format('Y-m-d');

    return $twig->render($res, 'booking.twig', [
        'title' => 'Ava broneerimine',
        'today' => $today,
        'hairdressers' => $hairdressers,
    ]);
});

/**
 * GET /availability?hairdresser_id=1&date=YYYY-MM-DD
 * Kuvab vabad ajad (nuppudega)
 */
$app->get('/availability', function (Request $req, Response $res) use ($twig, $hairdressers, $findHairdresserName, $getBookingsForDay, $isOverlapping) {
    $q = $req->getQueryParams();
    $hairdresserId = (int)($q['hairdresser_id'] ?? 0);
    $date = (string)($q['date'] ?? '');

    if ($hairdresserId <= 0 || $date === '') {
        return $res->withHeader('Location', '/booking')->withStatus(302);
    }

    $dt = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dt || $dt->format('Y-m-d') !== $date) {
        $res->getBody()->write('Vale kuupäeva formaat. Ootan YYYY-MM-DD');
        return $res->withStatus(400);
    }

    // generate slots 09:00-17:00 every 30 min
    $open = new DateTime("$date 09:00");
    $close = new DateTime("$date 17:00");
    $slotMinutes = 30;

    $dayBookings = $getBookingsForDay($hairdresserId, $date);

    $slots = [];
    $cur = clone $open;
    while ($cur < $close) {
        $start = $cur->format('H:i');
        $endDt = (clone $cur)->modify("+{$slotMinutes} minutes");
        $end = $endDt->format('H:i');

        // check if occupied
        $occupied = false;
        foreach ($dayBookings as $b) {
            if ($isOverlapping($start, $end, $b['start'], $b['end'])) {
                $occupied = true;
                break;
            }
        }

        $slots[] = [
            'start' => $start,
            'end' => $end,
            'occupied' => $occupied,
        ];

        $cur = $endDt;
    }

    return $twig->render($res, 'availability.twig', [
        'title' => 'Vabad ajad',
        'hairdresser_id' => $hairdresserId,
        'hairdresser_name' => $findHairdresserName($hairdresserId),
        'date' => $date,
        'slots' => $slots,
    ]);
});

/**
 * POST /book
 * Loob broneeringu (serveripoolne kontroll topeltbronni vastu)
 */
$app->post('/book', function (Request $req, Response $res) use ($twig, $findHairdresserName, $getBookingsForDay, $addBooking, $isOverlapping) {
    $data = (array)$req->getParsedBody();

    $hairdresserId = (int)($data['hairdresser_id'] ?? 0);
    $date = (string)($data['date'] ?? '');
    $start = (string)($data['start'] ?? '');
    $end = (string)($data['end'] ?? '');

    // Basic validation
    if ($hairdresserId <= 0 || $date === '' || $start === '' || $end === '') {
        $res->getBody()->write('Puudulik päring.');
        return $res->withStatus(400);
    }

    $dt = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dt || $dt->format('Y-m-d') !== $date) {
        $res->getBody()->write('Vale kuupäev.');
        return $res->withStatus(400);
    }

    // Validate time format HH:MM
    if (!preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end)) {
        $res->getBody()->write('Vale kellaaja formaat.');
        return $res->withStatus(400);
    }

    // Ensure within business hours and slot size
    if ($start < '09:00' || $end > '17:00' || $start >= $end) {
        $res->getBody()->write('Aeg väljaspool tööaega või vale vahemik.');
        return $res->withStatus(400);
    }

    // Re-check occupied (prevents double booking)
    $dayBookings = $getBookingsForDay($hairdresserId, $date);
    foreach ($dayBookings as $b) {
        if ($isOverlapping($start, $end, $b['start'], $b['end'])) {
            // someone already took it
            return $res->withHeader('Location', "/availability?hairdresser_id={$hairdresserId}&date={$date}")->withStatus(302);
        }
    }

    // Save booking
    $addBooking($hairdresserId, $date, $start, $end);

    return $res->withHeader('Location', "/confirmed?hairdresser_id={$hairdresserId}&date={$date}&start={$start}&end={$end}")->withStatus(302);
});

/**
 * GET /confirmed
 */
$app->get('/confirmed', function (Request $req, Response $res) use ($twig, $findHairdresserName) {
    $q = $req->getQueryParams();
    $hairdresserId = (int)($q['hairdresser_id'] ?? 0);
    $date = (string)($q['date'] ?? '');
    $start = (string)($q['start'] ?? '');
    $end = (string)($q['end'] ?? '');

    if ($hairdresserId <= 0 || $date === '' || $start === '' || $end === '') {
        return $res->withHeader('Location', '/booking')->withStatus(302);
    }

    return $twig->render($res, 'confirmed.twig', [
        'title' => 'Broneering kinnitatud',
        'hairdresser_name' => $findHairdresserName($hairdresserId),
        'date' => $date,
        'start' => $start,
        'end' => $end,
    ]);
});

/**
 * GET /admin (Basic Auth)
 * user: admin
 * pass: admin
 */
$app->get('/admin', function (Request $req, Response $res) use ($twig, $hairdressers) {

    $user = $_SERVER['PHP_AUTH_USER'] ?? null;
    $pass = $_SERVER['PHP_AUTH_PW'] ?? null;

    if ($user !== 'admin' || $pass !== 'admin') {
        return $res
            ->withHeader('WWW-Authenticate', 'Basic realm="Admin"')
            ->withStatus(401);
    }

    $bookings = $_SESSION['bookings'] ?? [];

    return $twig->render($res, 'admin.twig', [
        'title' => 'Admin – broneeringud',
        'hairdressers' => $hairdressers,
        'bookings' => $bookings,
    ]);
});

$app->run();
