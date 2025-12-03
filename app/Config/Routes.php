<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// FIX: Check if REQUEST_METHOD exists before using it
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header('Access-Control-Allow-Origin: http://localhost:5173');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Allow-Credentials: true');
    exit(0);
}

// Set CORS headers for actual requests
if (isset($_SERVER['HTTP_ORIGIN'])) {
    $allowed_origins = ['http://localhost:5173', 'http://127.0.0.1:5173'];
    $origin = $_SERVER['HTTP_ORIGIN'];
    
    if (in_array($origin, $allowed_origins)) {
        header("Access-Control-Allow-Origin: $origin");
    }
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
}

// API Routes
$routes->post('register', 'AuthController::register');
$routes->post('login', 'AuthController::login');
$routes->post('logout', 'AuthController::logout');

$routes->get('users', 'UserController::index');
$routes->get('users/(:num)', 'UserController::show/$1');
$routes->delete('users/(:num)', 'UserController::delete/$1');

$routes->get('books', 'BooksController::index');
$routes->get('books/(:num)', 'BooksController::show/$1');
$routes->post('books', 'BooksController::create');
$routes->put('books/(:num)', 'BooksController::update/$1');
$routes->delete('books/(:num)', 'BooksController::delete/$1');

// Default route
$routes->get('/', 'Home::index');

// 404 Override
$routes->set404Override(function() {
    return service('response')->setJSON([
        'status' => 'error',
        'message' => 'Endpoint not found'
    ])->setStatusCode(404);
});

if (file_exists(APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php')) {
    require APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php';
}