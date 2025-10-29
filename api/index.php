<?php
require_once '../vendor/autoload.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load data functions
function loadUsers() {
    $dataDir = __DIR__ . '/../data';
    if (!file_exists($dataDir . '/users.json')) {
        if (!is_dir($dataDir)) mkdir($dataDir, 0755, true);
        file_put_contents($dataDir . '/users.json', '[]');
    }
    return json_decode(file_get_contents($dataDir . '/users.json'), true) ?: [];
}

function loadTickets() {
    $dataDir = __DIR__ . '/../data';
    if (!file_exists($dataDir . '/tickets.json')) {
        if (!is_dir($dataDir)) mkdir($dataDir, 0755, true);
        file_put_contents($dataDir . '/tickets.json', '[]');
    }
    return json_decode(file_get_contents($dataDir . '/tickets.json'), true) ?: [];
}

function saveUsers($users) {
    file_put_contents(__DIR__ . '/../data/users.json', json_encode($users, JSON_PRETTY_PRINT));
}

function saveTickets($tickets) {
    file_put_contents(__DIR__ . '/../data/tickets.json', json_encode($tickets, JSON_PRETTY_PRINT));
}

// Setup Twig
$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../templates');
$twig = new \Twig\Environment($loader);

// Get query parameters
$query = $_GET['page'] ?? 'landing';
$method = $_SERVER['REQUEST_METHOD'];

// Routing
switch ($query) {
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
                header('Location: /?page=dashboard');
                exit;
            } else {
                header('Location: /?page=login&error=1');
                exit;
            }
        } else {
            echo $twig->render('login.twig', [
                'error' => isset($_GET['error'])
            ]);
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
                header('Location: /?page=dashboard');
                exit;
            } else {
                header('Location: /?page=signup&error=1');
                exit;
            }
        } else {
            echo $twig->render('signup.twig', [
                'error' => isset($_GET['error'])
            ]);
        }
        break;
        
    case 'dashboard':
        if (!isset($_SESSION['user'])) {
            header('Location: /?page=login');
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
            header('Location: /?page=login');
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
                header('Location: /?page=tickets&success=created');
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
                header('Location: /?page=tickets&success=updated');
                exit;
                
            } elseif ($action === 'delete') {
                $ticketId = $_POST['ticket_id'] ?? '';
                $tickets = array_filter($tickets, fn($t) => !($t['id'] === $ticketId && $t['userId'] === $_SESSION['user']['id']));
                saveTickets($tickets);
                header('Location: /?page=tickets&success=deleted');
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
        header('Location: /?page=landing');
        exit;
        
    default:
        echo $twig->render('landing.twig');
        break;
}
?>