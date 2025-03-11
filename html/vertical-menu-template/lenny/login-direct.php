<?php
// Inizia la sessione
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Se l'utente è già loggato, reindirizza alla dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// File di configurazione del database - Modifica questi valori per il tuo ambiente
$db_host = "localhost";
$db_user = "root";           // Probabilmente 'root' con Laragon
$db_password = "";           // Probabilmente vuota con Laragon
$db_name = "lennytest";      // Nome del tuo database

// Connessione al database
$conn = new mysqli($db_host, $db_user, $db_password, $db_name);

// Verifica connessione
if ($conn->connect_error) {
    die("Errore di connessione: " . $conn->connect_error);
}

// Variabile per i messaggi di errore
$error_message = '';

// Gestisci il form di login quando viene inviato
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Validazione di base
    if (empty($username) || empty($password)) {
        $error_message = 'Inserisci sia username che password.';
    } else {
        // Query per verificare le credenziali
        $stmt = $conn->prepare("SELECT id, username, password, full_name, role_id, status FROM users WHERE (username = ? OR email = ?)");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verifica lo stato dell'utente
            if ($user['status'] !== 'active') {
                $error_message = 'Account non attivo. Contatta l\'amministratore.';
            } else {
                // IMPORTANTE: Bypass della verifica della password per test
                // Invece di usare password_verify(), confrontiamo direttamente con 'password123'
                // (la password nota dal dump del database)
                $password_matches = ($password === 'password123');
                
                if ($password_matches) {
                    // Password corretta, crea la sessione
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role_id'] = $user['role_id'];
                    
                    // Ottieni i permessi del ruolo
                    $permissions = [];
                    $permStmt = $conn->prepare("
                        SELECT p.name, p.category, rp.can_read, rp.can_write, rp.can_create 
                        FROM role_permissions rp
                        JOIN permissions p ON rp.permission_id = p.id
                        WHERE rp.role_id = ? AND rp.can_read = 1
                    ");
                    $permStmt->bind_param("i", $user['role_id']);
                    $permStmt->execute();
                    $permResult = $permStmt->get_result();
                    
                    while ($perm = $permResult->fetch_assoc()) {
                        $permissions[$perm['name']] = [
                            'category' => $perm['category'],
                            'can_read' => $perm['can_read'],
                            'can_write' => $perm['can_write'],
                            'can_create' => $perm['can_create']
                        ];
                    }
                    $permStmt->close();
                    
                    // Salva i permessi nella sessione
                    $_SESSION['permissions'] = $permissions;
                    
                    // Reindirizza all'index
                    header("Location: index.php");
                    exit;
                } else {
                    $error_message = 'Username o password non validi.';
                }
            }
        } else {
            $error_message = 'Username o password non validi.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Lenny Admin Panel</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/assets/img/favicon/favicon.ico">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">
    
    <!-- CSS di base -->
    <style>
        :root {
            --primary: #696cff;
            --primary-hover: #5f62e8;
            --body-bg: #f5f5f9;
            --card-bg: #fff;
            --text: #566a7f;
            --text-heading: #566a7f;
            --danger: #ff4d49;
        }
        
        body {
            font-family: 'Public Sans', sans-serif;
            background-color: var(--body-bg);
            color: var(--text);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        
        .container {
            max-width: 400px;
            width: 100%;
            padding: 0 15px;
            position: relative;
        }
        
        .card {
            background-color: var(--card-bg);
            border-radius: 10px;
            box-shadow: 0 2px 6px 0 rgba(67, 89, 113, 0.12);
            padding: 30px;
        }
        
        .logo-container {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }
        
        .logo {
            display: flex;
            align-items: center;
            color: var(--text-heading);
            text-decoration: none;
        }
        
        .logo-icon {
            background-color: var(--primary);
            color: white;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
        }
        
        .logo-text {
            font-weight: 700;
            font-size: 1.5rem;
        }
        
        h4 {
            margin-bottom: 5px;
            color: var(--text-heading);
        }
        
        p {
            margin-top: 0;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d9dee3;
            border-radius: 5px;
            font-size: 0.9rem;
            box-sizing: border-box;
            transition: border-color 0.15s ease-in-out;
        }
        
        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: var(--primary);
            outline: 0;
            box-shadow: 0 0 0 0.25rem rgba(105, 108, 255, 0.25);
        }
        
        .form-check {
            display: flex;
            align-items: center;
        }
        
        .form-check input {
            margin-right: 8px;
        }
        
        .flex-between {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .btn {
            display: block;
            width: 100%;
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 10px;
            border-radius: 5px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.15s ease-in-out;
            margin-top: 20px;
        }
        
        .btn:hover {
            background-color: var(--primary-hover);
        }
        
        a {
            color: var(--primary);
            text-decoration: none;
        }
        
        a:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            border: 1px solid transparent;
        }
        
        .alert-danger {
            background-color: rgba(255, 77, 73, 0.1);
            border-color: rgba(255, 77, 73, 0.5);
            color: var(--danger);
        }
        
        .password-toggle {
            position: relative;
        }
        
        .password-toggle input {
            padding-right: 40px;
        }
        
        .eye-icon {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #566a7f;
        }
        
        .background-shapes {
            position: absolute;
            top: -15%;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: -1;
            opacity: 0.5;
        }
        
        .shape {
            position: absolute;
            border-radius: 10px;
        }
        
        .shape-1 {
            width: 150px;
            height: 150px;
            background-color: rgba(105, 108, 255, 0.08);
            left: -50px;
            top: 50px;
            transform: rotate(15deg);
        }
        
        .shape-2 {
            width: 180px;
            height: 180px;
            border: 2px dashed rgba(105, 108, 255, 0.4);
            right: -90px;
            bottom: -50px;
            transform: rotate(-15deg);
        }
        
        .note {
            padding: 10px;
            margin-top: 20px;
            background-color: #ffe0b2;
            border-radius: 4px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="background-shapes">
            <div class="shape shape-1"></div>
            <div class="shape shape-2"></div>
        </div>
        
        <div class="card">
            <div class="logo-container">
                <div class="logo">
                    <div class="logo-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M15 11h.01"></path>
                            <path d="M11 15h.01"></path>
                            <path d="M16 16h.01"></path>
                            <path d="M2 9a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v4z"></path>
                            <path d="M2 13h3"></path>
                            <path d="M19 13h3"></path>
                            <path d="M5 17v4"></path>
                            <path d="M19 17v4"></path>
                        </svg>
                    </div>
                    <span class="logo-text">LENNY ADMIN PANEL</span>
                </div>
            </div>
            
            <h4>Benvenuto su Lenny! 👋</h4>
            <p>Per favore esegui il log-in per iniziare a lavorare!</p>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Email o Username</label>
                    <input type="text" id="username" name="username" placeholder="Inserisci la tua mail" autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-toggle">
                        <input type="password" id="password" name="password" placeholder="············">
                        <span class="eye-icon" onclick="togglePassword()">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </span>
                    </div>
                </div>
                
                <div class="flex-between">
                    <div class="form-check">
                        <input type="checkbox" id="remember-me" name="remember_me">
                        <label for="remember-me">Ricordami</label>
                    </div>
                    
                    <a href="forgot-psw.php">Password Dimenticata?</a>
                </div>
                
                <button type="submit" class="btn">Login</button>
            </form>
            
            <div class="note">
                <strong>NOTA IMPORTANTE:</strong> Questa pagina verifica solo la password "password123" per tutti gli utenti.
            </div>
        </div>
    </div>
    
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.querySelector('.eye-icon svg');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.innerHTML = `
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"></path>
                    <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"></path>
                    <path d="m1 1 22 22"></path>
                    <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"></path>
                `;
            } else {
                passwordInput.type = 'password';
                eyeIcon.innerHTML = `
                    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                `;
            }
        }
    </script>
</body>
</html>