<?php

// ------------------------------------------------------------
// CORS CONFIGURATION
// ------------------------------------------------------------

$allowedOrigins = [
    'http://localhost:3000',
    'https://wisegen.ca',
    'https://www.wisegen.ca',
    'https://dashboard.wisegen.ca',
    'https://www.dashboard.wisegen.ca',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Max-Age: 86400');
    header('Vary: Origin');
}

// Handle preflight before starting the session or loading the app.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (!in_array($origin, $allowedOrigins, true)) {
        http_response_code(403);
        exit('Origin not allowed');
    }

    http_response_code(204);
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);


// ------------------------------------------------------------
// SESSION
// ------------------------------------------------------------

session_start();

// Create guest session if user not logged in.
if (!isset($_SESSION['user'])) {
    $_SESSION['guest'] = [
        'id' => session_id(),
        'started_at' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    ];
}

// ------------------------------------------------------------
// BOOTSTRAP APPLICATION
// ------------------------------------------------------------

header('Content-Type: application/json');

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

$router = new App\Core\Router();

// Load route definitions.
require_once __DIR__ . '/../routes/web.php';

// Resolve and handle request.
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

$router->resolve($requestUri, $method);
