<?php
// Abilita la visualizzazione degli errori per il debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Connessione diretta al database
$db_host = "localhost";
$db_user = "root";
$db_password = "";
$db_name = "lennytest";

$conn = new mysqli($db_host, $db_user, $db_password, $db_name);
if ($conn->connect_error) {
    die("Errore di connessione al database: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// Variabili di stato
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$error_message = '';
$success_message = '';
$diagnostics = [];
$verification_result = null;
$debug_info = [];
$user_data = null;

// Test di verifica della password
if ($_SERVER['REQUEST_METHOD'] === 'POST'&&isset($_POST['test_login'])) {
    // Verificare se l'utente esiste
    $stmt = $conn->prepare("SELECT id, username, email, password, full_name, status FROM users WHERE (username = ? OR email = ?)");
    
    if (!$stmt) {
        $error_message = "Errore nella preparazione della query: " . $conn->error;
    } else {
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $user_data = $user;
            
            // 1. Test con password_verify
            $verification_result = password_verify($password, $user['password']);
            $diagnostics[] = "Test con password_verify(): " . ($verification_result ? "SUCCESSO" : "FALLITO");
            
            // 2. Informazioni sul hash
            $hash_info = password_get_info($user['password']);
            $debug_info['hash_info'] = $hash_info;
            
            // 3. Controllo tipo di hash
            if ($hash_info['algoName'] !== 'bcrypt') {
                $diagnostics[] = "ATTENZIONE: Il hash non sembra essere bcrypt, ma " . ($hash_info['algoName'] ?: 'sconosciuto');
            }
            
            // 4. Lunghezza hash
            $hash_length = strlen($user['password']);
            $diagnostics[] = "Lunghezza hash: " . $hash_length . " caratteri" . ($hash_length >= 60 ? " (OK)" : " (Troppo corto per bcrypt!)");
            
            // 5. Generare nuovo hash per confronto
            $new_hash = password_hash($password, PASSWORD_DEFAULT);
            $debug_info['new_hash'] = $new_hash;
            $debug_info['original_hash'] = $user['password'];
            
            // 6. Test con hash specifico
            if ($password === 'password123'&&$user['password'] === '$2y$10$xtskTnXUJAy/iEiDkvj2c.D.1CMKvTsWkqUmNZeZQTBw4hRF7ZBme') {
                $diagnostics[] = "Il hash standard per 'password123' è corretto.";
            }
            
            // 7. Suggerimenti in base all'esito
            if ($verification_result) {
                $success_message = "Autenticazione riuscita per l'utente: " . htmlspecialchars($user['username']);
            } else {
                $error_message = "Autenticazione fallita. La password non corrisponde all'hash memorizzato.";
                
                // Suggerimenti
                $diagnostics[] = "Possibili problemi:";
                $diagnostics[] = "- Caratteri speciali nella password che vengono modificati durante l'invio";
                $diagnostics[] = "- Spazi aggiuntivi all'inizio o alla fine della password";
                $diagnostics[] = "- Hash danneggiato o in formato non compatibile con password_verify()";
                $diagnostics[] = "- Funzione password_hash() che usa algoritmi/opzioni diverse";
            }
        } else {
            $error_message = "Utente non trovato: " . htmlspecialchars($username);
        }
        
        $stmt->close();
    }
}

// Aggiorna password manualmente con hash noto
if ($_SERVER['REQUEST_METHOD'] === 'POST'&&isset($_POST['fix_password'])) {
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $fix_type = isset($_POST['fix_type']) ? $_POST['fix_type'] : '';
    
    if ($user_id <= 0) {
        $error_message = "ID utente non valido.";
    } else {
        // Determina l'hash da usare
        $new_hash = '';
        if ($fix_type === 'password123') {
            // Hash noto per 'password123'
            $new_hash = '$2y$10$xtskTnXUJAy/iEiDkvj2c.D.1CMKvTsWkqUmNZeZQTBw4hRF7ZBme';
        } elseif ($fix_type === 'custom'&&!empty($_POST['custom_password'])) {
            // Genera hash per la password personalizzata
            $custom_password = $_POST['custom_password'];
            $new_hash = password_hash($custom_password, PASSWORD_DEFAULT);
        } else {
            $error_message = "Tipo di correzione non valido.";
        }
        
        if (!empty($new_hash)) {
            // Aggiorna il database
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            if (!$stmt) {
                $error_message = "Errore nella preparazione della query: " . $conn->error;
            } else {
                $stmt->bind_param("si", $new_hash, $user_id);
                if ($stmt->execute()) {
                    $success_message = "Password aggiornata con successo per l'utente ID: " . $user_id;
                    if ($fix_type === 'password123') {
                        $success_message .= " (password: 'password123')";
                    } else {
                        $success_message .= " (password personalizzata)";
                    }
                } else {
                    $error_message = "Errore nell'aggiornamento della password: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

// Ottenere la lista degli utenti
$users = [];
$query = "SELECT id, username, email, password, full_name, status FROM users ORDER BY id ASC";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostica Password</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .hash-preview {
            font-family: monospace;
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            word-break: break-all;
        }
        .diagnostic-section {
            background-color: #f1f8ff;
            border-left: 4px solid #0d6efd;
            padding: 15px;
        }
        .diagnostic-item {
            margin-bottom: 8px;
            padding-left: 10px;
        }
        .diagnostic-item.success {
            border-left: 3px solid #198754;
        }
        .diagnostic-item.warning {
            border-left: 3px solid #ffc107;
        }
        .diagnostic-item.danger {
            border-left: 3px solid #dc3545;
        }
        .code-block {
            font-family: monospace;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            border-left: 3px solid #6c757d;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <h1 class="mb-4 text-center">
            <i class="fas fa-key me-2"></i>Diagnostica Problemi di Password
        </h1>
        
        <!-- Messaggi di alert -->
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Sezione 1: Tester di password -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">Test di Password</h5>
                    </div>
                    <div class="card-body">
                        <form action="" method="post">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username o Email</label>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password da Testare</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="password" name="password" value="<?php echo htmlspecialchars($password); ?>" required>
                                    <button class="btn btn-outline-secondary" type="button" id="set-password123">password123</button>
                                </div>
                                <div class="form-text">Lascia la password visibile per evitare errori di digitazione.</div>
                            </div>
                            <button type="submit" name="test_login" class="btn btn-primary">
                                <i class="fas fa-vial me-1"></i> Testa Autenticazione
                            </button>
                        </form>
                        
                        <?php if (count($diagnostics) > 0): ?>
                            <div class="diagnostic-section mt-4">
                                <h5><i class="fas fa-stethoscope me-2"></i>Risultati Diagnostici</h5>
                                <ul class="list-unstyled">
                                    <?php foreach ($diagnostics as $index => $diagnostic): ?>
                                        <?php
                                        $class = '';
                                        if (strpos($diagnostic, 'SUCCESSO') !== false) {
                                            $class = 'success';
                                        } elseif (strpos($diagnostic, 'FALLITO') !== false || strpos($diagnostic, 'ATTENZIONE') !== false) {
                                            $class = 'danger';
                                        }
                                        ?>
                                        <li class="diagnostic-item <?php echo $class; ?>"><?php echo htmlspecialchars($diagnostic); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                
                                <?php if (!empty($debug_info)): ?>
                                    <div class="mt-3">
                                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#debugInfo">
                                            Mostra Dettagli Tecnici
                                        </button>
                                        <div class="collapse mt-2" id="debugInfo">
                                            <div class="hash-preview">
                                                <h6>Hash Originale:</h6>
                                                <?php echo htmlspecialchars($debug_info['original_hash'] ?? ''); ?>
                                                
                                                <h6 class="mt-3">Hash Nuovo (generato ora):</h6>
                                                <?php echo htmlspecialchars($debug_info['new_hash'] ?? ''); ?>
                                                
                                                <h6 class="mt-3">Informazioni Hash:</h6>
                                                <pre><?php echo htmlspecialchars(print_r($debug_info['hash_info'] ?? [], true)); ?></pre>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Sezione soluzione -->
                        <?php if ($verification_result === false&&!empty($user_data)): ?>
                            <div class="card mt-4 border-warning">
                                <div class="card-header bg-warning text-dark">
                                    <h5 class="card-title mb-0">Correggi Password</h5>
                                </div>
                                <div class="card-body">
                                    <p>La verifica è fallita. Vuoi reimpostare la password per <strong><?php echo htmlspecialchars($user_data['username']); ?></strong>?</p>
                                    
                                    <form action="" method="post">
                                        <input type="hidden" name="user_id" value="<?php echo $user_data['id']; ?>">
                                        
                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="fix_type" id="fix_standard" value="password123" checked>
                                                <label class="form-check-label" for="fix_standard">
                                                    Imposta password standard: "password123"
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="fix_type" id="fix_custom" value="custom">
                                                <label class="form-check-label" for="fix_custom">
                                                    Imposta password personalizzata:
                                                </label>
                                            </div>
                                            <div class="mt-2">
                                                <input type="text" class="form-control" id="custom_password" name="custom_password" placeholder="Inserisci password personalizzata">
                                            </div>
                                        </div>
                                        
                                        <button type="submit" name="fix_password" class="btn btn-warning">
                                            <i class="fas fa-wrench me-1"></i> Correggi Password
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Sezione 2: Guide e Utenti -->
            <div class="col-md-6">
                <!-- Istruzioni -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="card-title mb-0">Guida alla Risoluzione</h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text">Questa pagina ti aiuta a diagnosticare problemi di autenticazione. Ecco cosa fare:</p>
                        
                        <ol>
                            <li class="mb-2">Inserisci username e password esattamente come faresti nel login normale.</li>
                            <li class="mb-2">Clicca "Testa Autenticazione" per verificare se la password è valida.</li>
                            <li class="mb-2">Se il test fallisce, la sezione diagnostica mostrerà possibili cause.</li>
                            <li class="mb-2">Utilizza l'opzione "Correggi Password" per reimpostare la password dell'utente.</li>
                        </ol>
                        
                        <h6 class="mt-3">Codice di Login Corretto</h6>
                        <div class="code-block">
<pre>if (password_verify($password, $user['password'])) {
    // Login corretto
    $_SESSION['user_id'] = $user['id'];
    // ... altre operazioni ...
} else {
    // Password non corretta
    $error_message = 'Username o password non validi.';
}</pre>
                        </div>
                        
                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Importante:</strong> Se gli hash sono in un formato non standard o generati con metodi diversi da <code>password_hash()</code>, la funzione <code>password_verify()</code> non funzionerà correttamente.
                        </div>
                    </div>
                </div>
                
                <!-- Lista utenti -->
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="card-title mb-0">Utenti del Sistema</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Status</th>
                                        <th>Hash Inizio</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo $user['id']; ?></td>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td>
                                                <?php if ($user['status'] === 'active'): ?>
                                                    <span class="badge bg-success">Attivo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inattivo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span title="<?php echo htmlspecialchars($user['password']); ?>">
                                                    <?php echo htmlspecialchars(substr($user['password'], 0, 15)) . '...'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Pulsante per impostare "password123"
        document.getElementById('set-password123').addEventListener('click', function() {
            document.getElementById('password').value = 'password123';
        });
        
        // Gestione radio buttons
        document.getElementById('fix_standard').addEventListener('change', function() {
            document.getElementById('custom_password').disabled = this.checked;
        });
        
        document.getElementById('fix_custom').addEventListener('change', function() {
            document.getElementById('custom_password').disabled = !this.checked;
            if (this.checked) {
                document.getElementById('custom_password').focus();
            }
        });
        
        // Stato iniziale
        document.getElementById('custom_password').disabled = document.getElementById('fix_standard').checked;
    });
    </script>
</body>
</html>