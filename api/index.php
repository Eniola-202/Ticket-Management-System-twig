<?php
require_once '../vendor/autoload.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set base path for file operations
define('BASE_PATH', dirname(__DIR__));

// Load data functions
function loadUsers() {
    $dataFile = BASE_PATH . '/data/users.json';
    if (!file_exists($dataFile)) {
        if (!is_dir(dirname($dataFile))) mkdir(dirname($dataFile), 0755, true);
        file_put_contents($dataFile, '[]');
    }
    return json_decode(file_get_contents($dataFile), true) ?: [];
}

function loadTickets() {
    $dataFile = BASE_PATH . '/data/tickets.json';
    if (!file_exists($dataFile)) {
        if (!is_dir(dirname($dataFile))) mkdir(dirname($dataFile), 0755, true);
        file_put_contents($dataFile, '[]');
    }
    return json_decode(file_get_contents($dataFile), true) ?: [];
}

function saveUsers($users) {
    file_put_contents(BASE_PATH . '/data/users.json', json_encode($users, JSON_PRETTY_PRINT));
}

function saveTickets($tickets) {
    file_put_contents(BASE_PATH . '/data/tickets.json', json_encode($tickets, JSON_PRETTY_PRINT));
}

// Setup Twig
$loader = new \Twig\Loader\FilesystemLoader(BASE_PATH . '/templates');
$twig = new \Twig\Environment($loader);

// Get query parameters
$page = $_GET['page'] ?? 'landing';
$method = $_SERVER['REQUEST_METHOD'];

// Handle POST data
if ($method === 'POST') {
    // Convert JSON POST data to $_POST array if needed
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input) {
            $_POST = array_merge($_POST, $input);
        }
    }
}

// Routing
switch ($page) {
    case 'landing':
        echo $twig->render('landing.twig');
        break;
        
    case 'login':
        if ($method === 'POST') {
            // Handle login
            $users = loadUsers();
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            
            $user = array_filter($users, fn($u) => $u['email'] === $email && $u['password'] === $password);
            
            if (!empty($user)) {
                $user = array_values($user)[0];
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email']
                ];
                
                // Return JSON response for API call
                if (isset($_POST['ajax'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'redirect' => '/dashboard']);
                    exit;
                }
                
                header('Location: /dashboard');
                exit;
            } else {
                if (isset($_POST['ajax'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
                    exit;
                }
                
                echo $twig->render('login.twig', ['error' => 'Invalid email or password']);
            }
        } else {
            echo $twig->render('login.twig', ['error' => $_GET['error'] ?? null]);
        }
        break;
        
    case 'signup':
        if ($method === 'POST') {
            // Handle signup
            $users = loadUsers();
            $name = $_POST['name'] ?? '';
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            
            // Check if email exists
            $existingUser = array_filter($users, fn($u) => $u['email'] === $email);
            if (empty($existingUser)) {
                $newUser = [
                    'id' => uniqid(),
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                    'createdAt' => date('c')
                ];
                
                $users[] = $newUser;
                saveUsers($users);
                
                $_SESSION['user'] = [
                    'id' => $newUser['id'],
                    'name' => $newUser['name'],
                    'email' => $newUser['email']
                ];
                
                if (isset($_POST['ajax'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'redirect' => '/dashboard']);
                    exit;
                }
                
                header('Location: /dashboard');
                exit;
            } else {
                if (isset($_POST['ajax'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'Email already registered']);
                    exit;
                }
                
                echo $twig->render('signup.twig', ['error' => 'Email already registered']);
            }
        } else {
            echo $twig->render('signup.twig', ['error' => $_GET['error'] ?? null]);
        }
        break;
        
    case 'dashboard':
        if (!isset($_SESSION['user'])) {
            header('Location: /login');
            exit;
        }
        
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
            'tickets' => array_slice(array_reverse($userTickets), 0, 5)
        ]);
        break;
        
    case 'tickets':
        if (!isset($_SESSION['user'])) {
            header('Location: /login');
            exit;
        }
        
        $tickets = loadTickets();
        $userTickets = array_filter($tickets, fn($t) => $t['userId'] === $_SESSION['user']['id']);
        
        if ($method === 'POST') {
            $action = $_POST['action'] ?? '';
            
            if ($action === 'create') {
                $newTicket = [
                    'id' => uniqid(),
                    'userId' => $_SESSION['user']['id'],
                    'title' => $_POST['title'] ?? '',
                    'description' => $_POST['description'] ?? '',
                    'status' => $_POST['status'] ?? 'open',
                    'priority' => $_POST['priority'] ?? 'medium',
                    'createdAt' => date('c'),
                    'updatedAt' => date('c')
                ];
                
                $tickets[] = $newTicket;
                saveTickets($tickets);
                
                if (isset($_POST['ajax'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Ticket created successfully']);
                    exit;
                }
                
                header('Location: /tickets?success=created');
                exit;
                
            } elseif ($action === 'update') {
                $ticketId = $_POST['ticket_id'] ?? '';
                foreach ($tickets as &$ticket) {
                    if ($ticket['id'] === $ticketId && $ticket['userId'] === $_SESSION['user']['id']) {
                        $ticket['title'] = $_POST['title'] ?? $ticket['title'];
                        $ticket['description'] = $_POST['description'] ?? $ticket['description'];
                        $ticket['status'] = $_POST['status'] ?? $ticket['status'];
                        $ticket['priority'] = $_POST['priority'] ?? $ticket['priority'];
                        $ticket['updatedAt'] = date('c');
                        break;
                    }
                }
                saveTickets($tickets);
                
                if (isset($_POST['ajax'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Ticket updated successfully']);
                    exit;
                }
                
                header('Location: /tickets?success=updated');
                exit;
                
            } elseif ($action === 'delete') {
                $ticketId = $_POST['ticket_id'] ?? '';
                $tickets = array_filter($tickets, fn($t) => !($t['id'] === $ticketId && $t['userId'] === $_SESSION['user']['id']));
                saveTickets($tickets);
                
                if (isset($_POST['ajax'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Ticket deleted successfully']);
                    exit;
                }
                
                header('Location: /tickets?success=deleted');
                exit;
            }
        }
        
        echo $twig->render('tickets.twig', [
            'user' => $_SESSION['user'],
            'tickets' => array_reverse($userTickets),
            'success' => $_GET['success'] ?? null
        ]);
        break;
        
    case 'logout':
        session_destroy();
        header('Location: /');
        exit;
        
    default:
        echo $twig->render('landing.twig');
        break;
}
?>