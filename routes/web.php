<?php

use App\Controller\API\AuthController;
use App\Controller\API\SessionController;
use App\Controller\API\AdminOpsController;
use App\Controller\API\PublicOpsController;

$router->get('/api/run-migrations', [AuthController::class, 'updateMigrations']);
$router->get('/api/create-admin-user', [AuthController::class, 'createAdminUser']);
$router->post('/api/login', [AuthController::class, 'login']);
$router->get('/api/session', [SessionController::class, 'userSession']);
$router->post('/api/create-event', [AdminOpsController::class, 'CreateEvent']);
$router->get('/api/events', [AdminOpsController::class, 'fetchEvent']);
$router->post('/api/gallery', [AdminOpsController::class, 'UploadGallery']);

$router->get('/api/public-events', [PublicOpsController::class, 'fetchEvent']);
$router->get('/api/public-get-event', [PublicOpsController::class, 'publicEvent']);