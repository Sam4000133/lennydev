<?php
// Inizia la sessione e abilita report errori
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Cancella eventuali sessioni esistenti (utile per testare)
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: login-debug.php");
    exit;
}

// Se l'utente è già loggato, mostra un messaggio
if (isset($_SESSION['user_id'])) {
    echo "<div style='background-color: #dff0d8; color: #3c763d; padding: 15px; margin: 20px; border-radius: 4px;'>";
    echo "<h2>Sei già loggato!</h2>";
    echo "<p>User ID: " . $_SESSION['user_id'] . "</p>";
    echo "<p>Username: " . $_SESSION['username'] . "</p>";
    echo "<p>Nome completo: " . $_SESSION['full_name'] . "</p>";
    echo "<p>Ruolo ID: " . $_SESSION['role_id'] . "</p>";
    echo "<p><a href='index.php'>Vai alla dashboard</a> | <a href='login-debug.php?logout=1'>Logout</a></p>";
    echo "</div>";
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
    die("<div style='background-color: #f2dede; color: #a94442; padding: 15px; margin: 20px; border-radius: 4px;'>
         <h2>Errore di connessione al database</h2>
         <p>" . $conn->connect_error . "</p>
         </div>");
}

// Variabile per i messaggi di errore o successo
$message = '';
$message_type = '';

// Gestisci il form di login quando viene inviato
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Debug: mostra i dati ricevuti
    echo "<div style='background-color: #d9edf7; color: #31708f; padding: 15px; margin: 20px; border-radius: 4px;'>";
    echo "<h3>Debug - Dati ricevuti:</h3>";
    echo "<p>Username: " . htmlspecialchars($username) . "</p>";
    echo "<p>Password: " . (empty($password) ? "Vuota" : "Inserita - " . strlen($password) . " caratteri") . "</p>";
    echo "</div>";
    
    // Validazione di base
    if (empty($username) || empty($password)) {
        $message = 'Inserisci sia username che password.';
        $message_type = 'error';
    } else {
        // Query per verificare le credenziali
        $sql = "SELECT id, username, password, email, full_name, role_id, status FROM users WHERE (username = ? OR email = ?)";
        $stmt = $conn->prepare($sql);
        
        if ($stmt === false) {
            $message = 'Errore nella preparazione della query: ' . $conn->error;
            $message_type = 'error';
        } else {
            $stmt->bind_param("ss", $username, $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            // Debug: mostra il risultato della query
            echo "<div style='background-color: #d9edf7; color: #31708f; padding: 15px; margin: 20px; border-radius: 4px;'>";
            echo "<h3>Debug - Risultato query:</h3>";
            echo "<p>Numero di righe trovate: " . $result->num_rows . "</p>";
            echo "</div>";
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // Debug: mostra i dati dell'utente
                echo "<div style='background-color: #d9edf7; color: #31708f; padding: 15px; margin: 20px; border-radius: 4px;'>";
                echo "<h3>Debug - Dati utente trovati:</h3>";
                echo "<p>ID: " . $user['id'] . "</p>";
                echo "<p>Username: " . $user['username'] . "</p>";
                echo "<p>Email: " . $user['email'] . "</p>";
                echo "<p>Hash password: " . $user['password'] . "</p>";
                echo "<p>Nome: " . $user['full_name'] . "</p>";
                echo "<p>Ruolo ID: " . $user['role_id'] . "</p>";
                echo "<p>Stato: " . $user['status'] . "</p>";
                echo "</div>";
                
                // Verifica lo stato dell'utente
                if ($user['status'] !== 'active') {
                    $message = 'Account non attivo. Contatta l\'amministratore.';
                    $message_type = 'error';
                } else {
                    // Test di verifica password
                    $password_matches = password_verify($password, $user['password']);
                    
                    // Debug: mostra risultato verifica password
                    echo "<div style='background-color: #d9edf7; color: #31708f; padding: 15px; margin: 20px; border-radius: 4px;'>";
                    echo "<h3>Debug - Verifica password:</h3>";
                    echo "<p>Risultato: " . ($password_matches ? "Password corretta" : "Password errata") . "</p>";
                    echo "</div>";
                    
                    if ($password_matches) {
                        // Password corretta, crea la sessione
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['role_id'] = $user['role_id'];
                        
                        // Ottieni i permessi del ruolo
                        $sql_perms = "
                            SELECT p.name, p.category, rp.can_read, rp.can_write, rp.can_create 
                            FROM role_permissions rp
                            JOIN permissions p ON rp.permission_id = p.id
                            WHERE rp.role_id = ? AND rp.can_read = 1
                        ";
                        $permStmt = $conn->prepare($sql_perms);
                        
                        if ($permStmt === false) {
                            echo "<div style='background-color: #f2dede; color: #a94442; padding: 15px; margin: 20px; border-radius: 4px;'>";
                            echo "<h3>Errore nella query dei permessi:</h3>";
                            echo "<p>" . $conn->error . "</p>";
                            echo "</div>";
                        } else {
                            $permStmt->bind_param("i", $user['role_id']);
                            $permStmt->execute();
                            $permResult = $permStmt->get_result();
                            
                            $permissions = [];
                            $perm_count = 0;
                            
                            while ($perm = $permResult->fetch_assoc()) {
                                $permissions[$perm['name']] = [
                                    'category' => $perm['category'],
                                    'can_read' => $perm['can_read'],
                                    'can_write' => $perm['can_write'],
                                    'can_create' => $perm['can_create']
                                ];
                                $perm_count++;
                            }
                            $permStmt->close();
                            
                            // Debug: mostra i permessi
                            echo "<div style='background-color: #d9edf7; color: #31708f; padding: 15px; margin: 20px; border-radius: 4px;'>";
                            echo "<h3>Debug - Permessi trovati (" . $perm_count . "):</h3>";
                            echo "<ul>";
                            foreach ($permissions as $name => $details) {
                                echo "<li>" . $name . " (R:" . $details['can_read'] . "|W:" . $details['can_write'] . "|C:" . $details['can_create'] . ")</li>";
                            }
                            echo "</ul>";
                            echo "</div>";
                            
                            // Salva i permessi nella sessione
                            $_SESSION['permissions'] = $permissions;
                        }
                        
                        $message = 'Login avvenuto con successo! Stai per essere reindirizzato...';
                        $message_type = 'success';
                        
                        // Reindirizza in 3 secondi
                        echo "<script>
                            setTimeout(function() {
                                window.location.href = 'index.php';
                            }, 3000);
                        </script>";
                    } else {
                        $message = 'Username o password non validi.';
                        $message_type = 'error';
                    }
                }
            } else {
                $message = 'Username o password non validi.';
                $message_type = 'error';
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Debug | Lenny Admin Panel</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f9;
            color: #566a7f;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 400px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        h1 {
            text-align: center;
            color: #696cff;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type=text], input[type=password] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            background-color: #696cff;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
        }
        button:hover {
            background-color: #5f62e8;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #dff0d8;
            color: #3c763d;
        }
        .alert-error {
            background-color: #f2dede;
            color: #a94442;
        }
        .form-check {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .form-check input {
            margin-right: 8px;
        }
        .forgot-password {
            text-align: right;
            margin-bottom: 15px;
        }
        .forgot-password a {
            color: #696cff;
            text-decoration: none;
        }
        .note {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #888;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>LENNY ADMIN PANEL</h1>
        <h2>Debug Login</h2>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Email o Username</label>
                <input type="text" id="username" name="username" placeholder="Inserisci la tua mail o username" 
                    value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>" autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Inserisci la tua password">
            </div>
            
            <div class="form-check">
                <input type="checkbox" id="remember-me" name="remember_me">
                <label for="remember-me">Ricordami</label>
            </div>
            
            <div class="forgot-password">
                <a href="#">Password Dimenticata?</a>
            </div>
            
            <button type="submit">Login</button>
        </form>
        
        <div class="note">
            <p>Le password nel database sono hashate con password_hash()</p>
            <p>Per utenti di test, la password è: <strong>password123</strong></p>
        </div>
    </div>
</body>
</html>