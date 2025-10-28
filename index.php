<?php
require_once 'vendor/autoload.php';

// Start session
session_start();

// Setup Twig
$loader = new \Twig\Loader\FilesystemLoader('templates');
$twig = new \Twig\Environment($loader);

// Load data functions
function loadUsers() {
    if (!file_exists('data/users.json')) {
        file_put_contents('data/users.json', '[]');
    }
    return json_decode(file_get_contents('data/users.json'), true) ?: [];
}

function loadTickets() {
    if (!file_exists('data/tickets.json')) {
        file_put_contents('data/tickets.json', '[]');
    }
    return json_decode(file_get_contents('data/tickets.json'), true) ?: [];
}

function saveUsers($users) {
    file_put_contents('data/users.json', json_encode($users, JSON_PRETTY_PRINT));
}

function saveTickets($tickets) {
    file_put_contents('data/tickets.json', json_encode($tickets, JSON_PRETTY_PRINT));
}

// Check authentication
function isLoggedIn() {
    return isset($_SESSION['user']);
}

function requireAuth() {
    if (!isLoggedIn()) {
        header('Location: /index.php?page=login');
        exit;
    }
}

// Flash messages
function setFlashMessage($message, $type = 'success') {
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Routing
$page = $_GET['page'] ?? 'landing';

switch ($page) {
    case 'landing':
        echo $twig->render('landing.twig', [
            'flash' => getFlashMessage()
        ]);
        break;
        
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require 'auth.php';
        } else {
            echo $twig->render('login.twig', [
                'flash' => getFlashMessage()
            ]);
        }
        break;
        
    case 'signup':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require 'auth.php';
        } else {
            echo $twig->render('signup.twig', [
                'flash' => getFlashMessage()
            ]);
        }
        break;
        
    case 'dashboard':
        requireAuth();
        
        $tickets = loadTickets();
        $userTickets = array_filter($tickets, fn($t) => $t['userId'] === $_SESSION['user']['id']);
        
        $stats = [
            'total' => count($userTickets),
            'open' => count(array_filter($userTickets, fn($t) => $t['status'] === 'open')),
            'inProgress' => count(array_filter($userTickets, fn($t) => $t['status'] === 'in-progress')),
            'resolved' => count(array_filter($userTickets, fn($t) => $t['status'] === 'closed'))
        ];
        
        echo $twig->render('dashboard.twig', [
            'user' => $_SESSION['user'],
            'stats' => $stats,
            'tickets' => array_slice(array_reverse($userTickets), 0, 5),
            'flash' => getFlashMessage()
        ]);
        break;
        
    case 'tickets':
        requireAuth();
        require 'tickets.php';
        break;
        
    case 'logout':
        session_destroy();
        setFlashMessage('You have been logged out successfully.', 'success');
        header('Location: /index.php?page=landing');
        exit;
        
    default:
        header('Location: /index.php?page=landing');
        exit;
}
?>