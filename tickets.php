<?php
$tickets = loadTickets();
$userTickets = array_filter($tickets, fn($t) => $t['userId'] === $_SESSION['user']['id']);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        setFlashMessage('Ticket created successfully!', 'success');
        
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
        setFlashMessage('Ticket updated successfully!', 'success');
        
    } elseif ($action === 'delete') {
        $ticketId = $_POST['ticket_id'] ?? '';
        $tickets = array_filter($tickets, fn($t) => !($t['id'] === $ticketId && $t['userId'] === $_SESSION['user']['id']));
        saveTickets($tickets);
        setFlashMessage('Ticket deleted successfully!', 'success');
    }
    
    header('Location: /index.php?page=tickets');
    exit;
}

// Render tickets page
$userTickets = array_filter($tickets, fn($t) => $t['userId'] === $_SESSION['user']['id']);
$userTickets = array_reverse($userTickets); // Show newest first

echo $twig->render('tickets.twig', [
    'user' => $_SESSION['user'],
    'tickets' => $userTickets,
    'flash' => getFlashMessage()
]);
?>