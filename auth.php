<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $users = loadUsers();
    
    if ($_GET['page'] === 'login') {
        // Login logic
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
            setFlashMessage('Login successful! Welcome back.', 'success');
            header('Location: /index.php?page=dashboard');
            exit;
        } else {
            setFlashMessage('Invalid email or password.', 'error');
            header('Location: /index.php?page=login');
            exit;
        }
        
    } elseif ($_GET['page'] === 'signup') {
        // Signup logic
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        
        // Validation
        if (empty($name) || empty($email) || empty($password)) {
            setFlashMessage('All fields are required.', 'error');
            header('Location: /index.php?page=signup');
            exit;
        }
        
        if (strlen($password) < 6) {
            setFlashMessage('Password must be at least 6 characters.', 'error');
            header('Location: /index.php?page=signup');
            exit;
        }
        
        // Check if email exists
        $existingUser = array_filter($users, fn($u) => $u['email'] === $email);
        if (!empty($existingUser)) {
            setFlashMessage('Email already registered.', 'error');
            header('Location: /index.php?page=signup');
            exit;
        }
        
        // Create new user
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
        
        setFlashMessage('Account created successfully! Welcome to TicketFlow.', 'success');
        header('Location: /index.php?page=dashboard');
        exit;
    }
}
?>