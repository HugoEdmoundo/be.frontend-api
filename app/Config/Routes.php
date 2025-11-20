<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// ==================== CORS HANDLING ====================
// Handle CORS preflight requests
if (isset($_SERVER['HTTP_ORIGIN'])) {
    $allowed_origins = [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:3000',
        'http://127.0.0.1:3000'
    ];
    
    $origin = $_SERVER['HTTP_ORIGIN'];
    if (in_array($origin, $allowed_origins)) {
        header("Access-Control-Allow-Origin: $origin");
    }
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');    // cache for 1 day
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE, PATCH");
    }
    
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    }
    
    exit(0);
}

// ==================== API ROUTES ====================

// Auth Routes
$routes->post('register', 'AuthController::register');
$routes->post('login', 'AuthController::login');
$routes->post('logout', 'AuthController::logout');

// Books CRUD Routes
$routes->get('books', 'BooksController::index');
$routes->get('books/(:num)', 'BooksController::show/$1');
$routes->post('books', 'BooksController::create');
$routes->put('books/(:num)', 'BooksController::update/$1');
$routes->delete('books/(:num)', 'BooksController::delete/$1');

// Additional book routes if needed
$routes->get('books/search/(:segment)', 'BooksController::search/$1');
$routes->get('books/author/(:segment)', 'BooksController::getByAuthor/$1');

// User routes (if needed later)
$routes->get('profile', 'UserController::profile');
$routes->put('profile', 'UserController::updateProfile');

// ==================== DEFAULT ROUTES ====================
$routes->get('/', 'Home::index');

// Catch all - Show 404 for undefined routes
$routes->set404Override(function() {
    return service('response')->setJSON([
        'status' => 'error',
        'message' => 'Endpoint not found'
    ])->setStatusCode(404);
});

/**
 * --------------------------------------------------------------------
 * Additional Routing
 * --------------------------------------------------------------------
 */
if (file_exists(APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php')) {
    require APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php';
}