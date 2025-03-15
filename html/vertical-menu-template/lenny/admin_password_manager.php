<?php
// Script di gestione password utenti (solo per amministratori)
// ATTENZIONE: RIMUOVERE QUESTO FILE IN AMBIENTE DI PRODUZIONE

// Impostazioni di sicurezza - modifica questa password per accedere allo strumento
$access_password = "admintools"; // Password per accedere a questo tool

// Controllo accesso
session_start();
$access_granted = false;

// Verifica se è già autenticato per questo tool
if (isset($_SESSION['admin_tool_access'])&&$_SESSION['admin_tool_access'] === true) {
    $access_granted = true;
}

// Verifica la password di accesso allo strumento
if (!$access_granted&&isset($_POST['tool_password'])) {
    if ($_POST['tool_password'] === $access_password) {
        $_SESSION['admin_tool_access'] = true;
        $access_granted = true;
    } else {
        $auth_error = "Password errata per l'accesso allo strumento";
    }
}

// Connessione al database (eseguito solo se autenticato)
if ($access_granted) {
    $db_host = "localhost";
    $db_user = "root";
    $db_password = "";
    $db_name = "lennytest";

    $conn = new mysqli($db_host, $db_user, $db_password, $db_name);
    if ($conn->connect_error) {
        die("Errore di connessione al database: " . $conn->connect_error);
    }

    // Imposta il set di caratteri
    $conn->set_charset("utf8mb4");
    
    // Ottieni lista utenti
    $users = [];
    $result = $conn->query("SELECT id, username, email, full_name, role_id, status FROM users ORDER BY id");
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    
    // Ottieni nomi dei ruoli
    $roles = [];
    $result = $conn->query("SELECT id, name FROM roles");
    while ($row = $result->fetch_assoc()) {
        $roles[$row['id']] = $row['name'];
    }
    
    // Gestisci reset password
    $success_message = '';
    $error_message = '';
    
    if (isset($_POST['reset_password'])) {
        $user_id = (int)$_POST['user_id'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verifica input
        if (empty($new_password)) {
            $error_message = "La password non può essere vuota";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "Le password non corrispondono";
        } else {
            // Imposta la nuova password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt->execute()) {
                // Trova il nome utente per il messaggio di successo
                $username = '';
                foreach ($users as $user) {
                    if ($user['id'] == $user_id) {
                        $username = $user['username'];
                        break;
                    }
                }
                $success_message = "Password reimpostata per l'utente: $username (ID: $user_id)";
            } else {
                $error_message = "Errore durante l'aggiornamento: " . $conn->error;
            }
            $stmt->close();
        }
    }
    
    // Gestisci password casuale
    if (isset($_POST['generate_random'])) {
        $user_id = (int)$_POST['user_id_random'];
        
        // Genera password casuale sicura
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_=+';
        $random_password = '';
        for ($i = 0; $i < 12; $i++) {
            $random_password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        // Assicurati che contenga almeno un carattere di ogni tipo richiesto
        if (!preg_match('/[A-Z]/', $random_password)) $random_password[random_int(0, 11)] = 'A';
        if (!preg_match('/[a-z]/', $random_password)) $random_password[random_int(0, 11)] = 'a';
        if (!preg_match('/[0-9]/', $random_password)) $random_password[random_int(0, 11)] = '1';
        if (!preg_match('/[!@#$%^&*()-_=+]/', $random_password)) $random_password[random_int(0, 11)] = '!';
        
        // Imposta la nuova password
        $hashed_password = password_hash($random_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            // Trova il nome utente per il messaggio di successo
            $username = '';
            foreach ($users as $user) {
                if ($user['id'] == $user_id) {
                    $username = $user['username'];
                    break;
                }
            }
            $success_message = "Password reimpostata per l'utente: $username (ID: $user_id) con la password casuale: <strong>$random_password</strong>";
        } else {
            $error_message = "Errore durante l'aggiornamento: " . $conn->error;
        }
        $stmt->close();
    }
    
    // Gestisci password standard
    if (isset($_POST['set_standard'])) {
        $user_id = (int)$_POST['user_id_standard'];
        $standard_password = "Admin123!"; // Password standard che rispetta i requisiti
        
        // Imposta la password standard
        $hashed_password = password_hash($standard_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            // Trova il nome utente per il messaggio di successo
            $username = '';
            foreach ($users as $user) {
                if ($user['id'] == $user_id) {
                    $username = $user['username'];
                    break;
                }
            }
            $success_message = "Password reimpostata per l'utente: $username (ID: $user_id) con la password standard: <strong>$standard_password</strong>";
        } else {
            $error_message = "Errore durante l'aggiornamento: " . $conn->error;
        }
        $stmt->close();
    }
    
    // Gestisci reset massivo
    if (isset($_POST['mass_reset'])) {
        $selected_users = $_POST['selected_users'] ?? [];
        $reset_type = $_POST['reset_type'];
        $custom_password = $_POST['custom_password'] ?? '';
        
        if (empty($selected_users)) {
            $error_message = "Nessun utente selezionato";
        } else {
            $success_count = 0;
            $failed_users = [];
            
            foreach ($selected_users as $user_id) {
                $user_id = (int)$user_id;
                
                // Determina la password da usare
                if ($reset_type === 'custom'&&!empty($custom_password)) {
                    $new_password = $custom_password;
                } elseif ($reset_type === 'standard') {
                    $new_password = "Admin123!";
                } else {
                    // Genera password casuale
                    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_=+';
                    $new_password = '';
                    for ($i = 0; $i < 12; $i++) {
                        $new_password .= $chars[random_int(0, strlen($chars) - 1)];
                    }
                    // Assicurati che contenga almeno un carattere di ogni tipo richiesto
                    if (!preg_match('/[A-Z]/', $new_password)) $new_password[random_int(0, 11)] = 'A';
                    if (!preg_match('/[a-z]/', $new_password)) $new_password[random_int(0, 11)] = 'a';
                    if (!preg_match('/[0-9]/', $new_password)) $new_password[random_int(0, 11)] = '1';
                    if (!preg_match('/[!@#$%^&*()-_=+]/', $new_password)) $new_password[random_int(0, 11)] = '!';
                }
                
                // Imposta la nuova password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashed_password, $user_id);
                
                if ($stmt->execute()) {
                    $success_count++;
                    
                    // Trova il nome utente per il log
                    foreach ($users as $user) {
                        if ($user['id'] == $user_id) {
                            // Salva solo per password random
                            if ($reset_type === 'random') {
                                $failed_users[] = [
                                    'id' => $user_id,
                                    'username' => $user['username'],
                                    'password' => $new_password,
                                    'success' => true
                                ];
                            }
                            break;
                        }
                    }
                } else {
                    // Trova il nome utente per il log di errore
                    $username = 'Sconosciuto';
                    foreach ($users as $user) {
                        if ($user['id'] == $user_id) {
                            $username = $user['username'];
                            break;
                        }
                    }
                    
                    $failed_users[] = [
                        'id' => $user_id,
                        'username' => $username,
                        'password' => $reset_type === 'random' ? $new_password : '',
                        'success' => false,
                        'error' => $conn->error
                    ];
                }
                $stmt->close();
            }
            
            if ($success_count > 0) {
                $success_message = "Reset completato per $success_count utenti";
                
                // Se ci sono password casuali, mostra un report
                if ($reset_type === 'random'&&count($failed_users) > 0) {
                    $success_message .= "<br><br><strong>Password generate:</strong><br>";
                    $success_message .= "<table class='table table-sm table-bordered mt-2'>";
                    $success_message .= "<thead><tr><th>ID</th><th>Username</th><th>Password</th></tr></thead><tbody>";
                    
                    foreach ($failed_users as $user) {
                        if ($user['success']) {
                            $success_message .= "<tr><td>{$user['id']}</td><td>{$user['username']}</td><td><code>{$user['password']}</code></td></tr>";
                        }
                    }
                    
                    $success_message .= "</tbody></table>";
                }
            }
            
            // Se ci sono stati errori
            $error_users = array_filter($failed_users, function($u) { return !$u['success']; });
            if (count($error_users) > 0) {
                $error_message = "Errori durante il reset per " . count($error_users) . " utenti:<br>";
                foreach ($error_users as $user) {
                    $error_message .= "- ID {$user['id']} ({$user['username']}): {$user['error']}<br>";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Password Utenti</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding: 20px;
            background-color: #f8f9fa;
        }
        .card {
            margin-bottom: 20px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .password-toggle {
            cursor: pointer;
        }
        .btn-icon {
            padding: 0.25rem 0.5rem;
        }
        .badge-status {
            width: 80px;
        }
        .security-warning {
            background-color: #ffe5e5;
            border-left: 4px solid #ff3333;
            padding: 15px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">Strumento di Gestione Password Utenti</h1>
        
        <?php if (!$access_granted): ?>
            <!-- Form di autenticazione allo strumento -->
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Autenticazione Richiesta</h5>
                        </div>
                        <div class="card-body">
                            <?php if (isset($auth_error)): ?>
                                <div class="alert alert-danger"><?php echo $auth_error; ?></div>
                            <?php endif; ?>
                            
                            <form method="post">
                                <div class="mb-3">
                                    <label for="tool_password" class="form-label">Password di Accesso</label>
                                    <input type="password" class="form-control" id="tool_password" name="tool_password" required>
                                </div>
                                <button type="submit" class="btn btn-primary">Accedi</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="security-warning">
                <h5><i class="bi bi-exclamation-triangle-fill"></i> Avviso di Sicurezza</h5>
                <p>Questo strumento permette la modifica diretta delle password degli utenti. <strong>Assicurati di rimuovere questo file dopo l'uso e di non lasciarlo in ambienti di produzione.</strong></p>
                <p><small>PHP version: <?php echo phpversion(); ?> - MySQL version: <?php echo $conn->server_info; ?></small></p>
            </div>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <ul class="nav nav-tabs mb-4" id="myTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="single-tab" data-bs-toggle="tab" data-bs-target="#single" type="button" role="tab" aria-controls="single" aria-selected="true">Reset Singolo</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="mass-tab" data-bs-toggle="tab" data-bs-target="#mass" type="button" role="tab" aria-controls="mass" aria-selected="false">Reset Massivo</button>
                </li>
            </ul>
            
            <div class="tab-content" id="myTabContent">
                <!-- Tab Reset Singolo -->
                <div class="tab-pane fade show active" id="single" role="tabpanel" aria-labelledby="single-tab">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Reset Password Personalizzata</h5>
                                </div>
                                <div class="card-body">
                                    <form method="post">
                                        <div class="mb-3">
                                            <label for="user_id" class="form-label">Seleziona Utente</label>
                                            <select class="form-select" id="user_id" name="user_id" required>
                                                <option value="">-- Seleziona --</option>
                                                <?php foreach ($users as $user): ?>
                                                    <option value="<?php echo $user['id']; ?>">
                                                        <?php echo "ID: {$user['id']} - {$user['username']} ({$user['email']})"; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="new_password" class="form-label">Nuova Password</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                                <span class="input-group-text password-toggle" data-target="new_password">
                                                    <i class="bi bi-eye"></i>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="confirm_password" class="form-label">Conferma Password</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                                <span class="input-group-text password-toggle" data-target="confirm_password">
                                                    <i class="bi bi-eye"></i>
                                                </span>
                                            </div>
                                        </div>
                                        <button type="submit" name="reset_password" class="btn btn-primary">Reset Password</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Reset con Password Preconfigurate</h5>
                                </div>
                                <div class="card-body">
                                    <form method="post" class="mb-4">
                                        <div class="mb-3">
                                            <label for="user_id_standard" class="form-label">Seleziona Utente</label>
                                            <select class="form-select" id="user_id_standard" name="user_id_standard" required>
                                                <option value="">-- Seleziona --</option>
                                                <?php foreach ($users as $user): ?>
                                                    <option value="<?php echo $user['id']; ?>">
                                                        <?php echo "ID: {$user['id']} - {$user['username']} ({$user['email']})"; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <button type="submit" name="set_standard" class="btn btn-warning">
                                            Imposta Password Standard (Admin123!)
                                        </button>
                                    </form>
                                    
                                    <form method="post">
                                        <div class="mb-3">
                                            <label for="user_id_random" class="form-label">Seleziona Utente</label>
                                            <select class="form-select" id="user_id_random" name="user_id_random" required>
                                                <option value="">-- Seleziona --</option>
                                                <?php foreach ($users as $user): ?>
                                                    <option value="<?php echo $user['id']; ?>">
                                                        <?php echo "ID: {$user['id']} - {$user['username']} ({$user['email']})"; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <button type="submit" name="generate_random" class="btn btn-success">
                                            Genera Password Casuale Sicura
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tab Reset Massivo -->
                <div class="tab-pane fade" id="mass" role="tabpanel" aria-labelledby="mass-tab">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Reset Massivo Password</h5>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label class="form-label">Seleziona gli utenti</label>
                                            <div class="mb-2">
                                                <button type="button" class="btn btn-sm btn-secondary" id="select-all">Seleziona Tutti</button>
                                                <button type="button" class="btn btn-sm btn-secondary" id="select-none">Deseleziona Tutti</button>
                                                <button type="button" class="btn btn-sm btn-secondary" id="select-active">Solo Attivi</button>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table table-striped table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th><input type="checkbox" id="check-all"></th>
                                                            <th>ID</th>
                                                            <th>Username</th>
                                                            <th>Email</th>
                                                            <th>Nome</th>
                                                            <th>Ruolo</th>
                                                            <th>Stato</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($users as $user): ?>
                                                            <tr class="user-row" data-status="<?php echo $user['status']; ?>">
                                                                <td>
                                                                    <input type="checkbox" name="selected_users[]" value="<?php echo $user['id']; ?>" class="user-checkbox">
                                                                </td>
                                                                <td><?php echo $user['id']; ?></td>
                                                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                                <td><?php echo htmlspecialchars($user['full_name'] ?? ''); ?></td>
                                                                <td><?php echo htmlspecialchars($roles[$user['role_id']] ?? 'N/A'); ?></td>
                                                                <td>
                                                                    <?php
                                                                    $status_class = 'secondary';
                                                                    if ($user['status'] === 'active') $status_class = 'success';
                                                                    elseif ($user['status'] === 'suspended') $status_class = 'danger';
                                                                    elseif ($user['status'] === 'inactive') $status_class = 'warning';
                                                                    ?>
                                                                    <span class="badge bg-<?php echo $status_class; ?> badge-status">
                                                                        <?php echo ucfirst($user['status']); ?>
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Tipo di Reset</label>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="reset_type" id="reset_type_standard" value="standard" checked>
                                                <label class="form-check-label" for="reset_type_standard">
                                                    Imposta password standard (Admin123!)
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="reset_type" id="reset_type_random" value="random">
                                                <label class="form-check-label" for="reset_type_random">
                                                    Genera password casuali uniche
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="reset_type" id="reset_type_custom" value="custom">
                                                <label class="form-check-label" for="reset_type_custom">
                                                    Usa password personalizzata
                                                </label>
                                            </div>
                                            
                                            <div class="mt-3 mb-3" id="custom_password_container" style="display: none;">
                                                <label for="custom_password" class="form-label">Password Personalizzata</label>
                                                <div class="input-group">
                                                    <input type="password" class="form-control" id="custom_password" name="custom_password">
                                                    <span class="input-group-text password-toggle" data-target="custom_password">
                                                        <i class="bi bi-eye"></i>
                                                    </span>
                                                </div>
                                                <div class="form-text">
                                                    La password deve contenere almeno 8 caratteri, una lettera maiuscola, un numero e un carattere speciale.
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="alert alert-warning">
                                            <strong>Attenzione!</strong> Questa operazione non può essere annullata. Assicurati di aver selezionato gli utenti corretti.
                                        </div>
                                        
                                        <button type="submit" name="mass_reset" class="btn btn-danger" onclick="return confirm('Sei sicuro di voler resettare le password per gli utenti selezionati?');">
                                            Esegui Reset Massivo
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle password visibility
            document.querySelectorAll('.password-toggle').forEach(function(toggle) {
                toggle.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');
                    const inputField = document.getElementById(targetId);
                    const icon = this.querySelector('i');
                    
                    if (inputField.type === 'password') {
                        inputField.type = 'text';
                        icon.classList.remove('bi-eye');
                        icon.classList.add('bi-eye-slash');
                    } else {
                        inputField.type = 'password';
                        icon.classList.remove('bi-eye-slash');
                        icon.classList.add('bi-eye');
                    }
                });
            });
            
            // Custom password toggle
            const resetTypeRadios = document.querySelectorAll('input[name="reset_type"]');
            const customPasswordContainer = document.getElementById('custom_password_container');
            
            resetTypeRadios.forEach(function(radio) {
                radio.addEventListener('change', function() {
                    if (this.value === 'custom') {
                        customPasswordContainer.style.display = 'block';
                    } else {
                        customPasswordContainer.style.display = 'none';
                    }
                });
            });
            
            // Select all/none checkboxes
            const checkAll = document.getElementById('check-all');
            const userCheckboxes = document.querySelectorAll('.user-checkbox');
            
            if (checkAll) {
                checkAll.addEventListener('change', function() {
                    userCheckboxes.forEach(function(checkbox) {
                        checkbox.checked = checkAll.checked;
                    });
                });
            }
            
            // Select all button
            document.getElementById('select-all').addEventListener('click', function() {
                userCheckboxes.forEach(function(checkbox) {
                    checkbox.checked = true;
                });
                if (checkAll) checkAll.checked = true;
            });
            
            // Select none button
            document.getElementById('select-none').addEventListener('click', function() {
                userCheckboxes.forEach(function(checkbox) {
                    checkbox.checked = false;
                });
                if (checkAll) checkAll.checked = false;
            });
            
            // Select active users
            document.getElementById('select-active').addEventListener('click', function() {
                document.querySelectorAll('.user-row').forEach(function(row) {
                    const status = row.getAttribute('data-status');
                    const checkbox = row.querySelector('.user-checkbox');
                    checkbox.checked = (status === 'active');
                });
                if (checkAll) checkAll.checked = false;
            });
        });
    </script>
</body>
</html>
<?php
// Chiudi la connessione
if (isset($conn)) {
    $conn->close();
}
?>