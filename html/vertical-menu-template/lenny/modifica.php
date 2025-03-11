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

// Imposta il set di caratteri per la connessione
$conn->set_charset("utf8mb4");

// Variabili per i messaggi
$error_message = '';
$success_message = '';
$selected_user = null;
$password_preview = '';

// Gestione dell'aggiornamento della password
if ($_SERVER['REQUEST_METHOD'] === 'POST'&&isset($_POST['update_password'])) {
    $user_id = $_POST['user_id'] ?? 0;
    $new_password = $_POST['new_password'] ?? '';
    
    // Validazione minima
    if (empty($new_password)) {
        $error_message = 'La password non può essere vuota.';
    } else {
        try {
            // Hash della nuova password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Aggiorna la password nel database
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Errore nella preparazione della query: " . $conn->error);
            }
            
            $stmt->bind_param("si", $hashed_password, $user_id);
            if ($stmt->execute()) {
                $success_message = 'Password aggiornata con successo per l\'utente ID: ' . $user_id;
                // Mostra il hash generato
                $password_preview = $hashed_password;
            } else {
                throw new Exception("Errore nell'esecuzione della query: " . $stmt->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $error_message = 'Errore durante l\'aggiornamento della password: ' . $e->getMessage();
        }
    }
}

// Gestione dell'aggiornamento di password semplificata (testo in chiaro)
if ($_SERVER['REQUEST_METHOD'] === 'POST'&&isset($_POST['update_password_plain'])) {
    $user_id = $_POST['user_id'] ?? 0;
    $new_password = $_POST['new_password'] ?? '';
    
    // Valida e crea un hash manualmente
    if (empty($new_password)) {
        $error_message = 'La password non può essere vuota.';
    } else {
        try {
            // Usa lo stesso hash per ogni utente (password123)
            $hashed_password = '$2y$10$xtskTnXUJAy/iEiDkvj2c.D.1CMKvTsWkqUmNZeZQTBw4hRF7ZBme';
            
            // Aggiorna la password nel database
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Errore nella preparazione della query: " . $conn->error);
            }
            
            $stmt->bind_param("si", $hashed_password, $user_id);
            if ($stmt->execute()) {
                $success_message = 'Password aggiornata con successo per l\'utente ID: ' . $user_id . ' con password fixed: password123';
            } else {
                throw new Exception("Errore nell'esecuzione della query: " . $stmt->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $error_message = 'Errore durante l\'aggiornamento della password: ' . $e->getMessage();
        }
    }
}

// Gestione della selezione di un utente specifico
if (isset($_GET['user_id'])&&is_numeric($_GET['user_id'])) {
    $user_id = $_GET['user_id'];
    
    // Ottieni i dettagli dell'utente
    $stmt = $conn->prepare("SELECT id, username, email, password, full_name, status, role_id FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $selected_user = $result->fetch_assoc();
        }
        
        $stmt->close();
    }
}

// Ottieni tutti gli utenti per la lista
$users = [];
$query = "SELECT u.id, u.username, u.email, u.password, u.full_name, u.status, r.name as role_name 
          FROM users u 
          LEFT JOIN roles r ON u.role_id = r.id 
          ORDER BY u.id ASC";

$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifica Password Utenti</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">
    
    <style>
        .password-toggle {
            cursor: pointer;
        }
        .hash-preview {
            word-break: break-all;
            font-family: monospace;
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
        }
        .status-active {
            color: #198754;
        }
        .status-inactive {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4 text-center">
                    <i class="fas fa-key me-2"></i>Modifica Password Utenti
                </h1>
                
                <!-- Alert per messaggi -->
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($password_preview)): ?>
                    <div class="alert alert-info">
                        <h5><i class="fas fa-info-circle me-2"></i>Hash della password generato:</h5>
                        <div class="hash-preview"><?php echo htmlspecialchars($password_preview); ?></div>
                    </div>
                <?php endif; ?>
                
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">Utenti del Sistema</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="usersTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Nome Completo</th>
                                        <th>Ruolo</th>
                                        <th>Stato</th>
                                        <th>Password Hash</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['id']); ?></td>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['role_name']); ?></td>
                                            <td>
                                                <?php if ($user['status'] === 'active'): ?>
                                                    <span class="badge bg-success">Attivo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inattivo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="text-truncate" style="max-width: 150px;" title="<?php echo htmlspecialchars($user['password']); ?>">
                                                    <?php echo htmlspecialchars($user['password']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="?user_id=<?php echo $user['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-edit me-1"></i> Modifica
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-success set-password123" data-user-id="<?php echo $user['id']; ?>">
                                                        <i class="fas fa-key me-1"></i> password123
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Form modifica password -->
                <?php if ($selected_user): ?>
                    <div class="card mt-4 shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">
                                Modifica Password per: <?php echo htmlspecialchars($selected_user['username']); ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <p><strong>Username:</strong> <?php echo htmlspecialchars($selected_user['username']); ?></p>
                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($selected_user['email']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Nome:</strong> <?php echo htmlspecialchars($selected_user['full_name']); ?></p>
                                    <p><strong>Stato:</strong> 
                                        <?php if ($selected_user['status'] === 'active'): ?>
                                            <span class="badge bg-success">Attivo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inattivo</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <form method="POST" action="" id="passwordForm">
                                <input type="hidden" name="user_id" value="<?php echo $selected_user['id']; ?>">
                                
                                <div class="mb-3">
                                    <label for="currentPasswordHash" class="form-label">Hash Password Attuale</label>
                                    <input type="text" class="form-control bg-light" id="currentPasswordHash" value="<?php echo htmlspecialchars($selected_user['password']); ?>" readonly>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">Nuova Password</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="new_password" name="new_password" placeholder="Inserisci la nuova password" required>
                                        <button class="btn btn-outline-secondary generate-password" type="button">
                                            <i class="fas fa-dice me-1"></i> Genera
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <button type="submit" name="update_password" class="btn btn-primary w-100">
                                            <i class="fas fa-save me-1"></i> Salva con Hash Password
                                        </button>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <button type="submit" name="update_password_plain" class="btn btn-success w-100">
                                            <i class="fas fa-key me-1"></i> Imposta "password123"
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <a href="modifica.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left me-1"></i> Torna alla lista
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
                
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // Inizializza DataTable
        $('#usersTable').DataTable({
            language: {
                search: "Cerca:",
                lengthMenu: "Mostra _MENU_ utenti per pagina",
                info: "Visualizzazione da _START_ a _END_ di _TOTAL_ utenti",
                infoEmpty: "Nessun utente disponibile",
                infoFiltered: "(filtrati da _MAX_ utenti totali)",
                zeroRecords: "Nessun utente trovato",
                paginate: {
                    first: "Primo",
                    last: "Ultimo",
                    next: "Successivo",
                    previous: "Precedente"
                }
            },
            order: [[0, 'asc']],
            pageLength: 10
        });
        
        // Gestione pulsante Genera Password
        $('.generate-password').click(function() {
            const length = 10;
            const charset = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*";
            let password = "";
            
            for (let i = 0; i < length; i++) {
                const randomIndex = Math.floor(Math.random() * charset.length);
                password += charset.charAt(randomIndex);
            }
            
            $('#new_password').val(password);
        });
        
        // Pulsante per impostare password123
        $('.set-password123').click(function() {
            const userId = $(this).data('user-id');
            
            if (confirm("Sei sicuro di voler impostare la password standard (password123) per l'utente ID: " + userId + "?")) {
                // Crea e invia un form
                const form = $('<form></form>').attr('method', 'post').attr('action', '');
                form.append($('<input>').attr('type', 'hidden').attr('name', 'user_id').val(userId));
                form.append($('<input>').attr('type', 'hidden').attr('name', 'new_password').val('password123'));
                form.append($('<input>').attr('type', 'hidden').attr('name', 'update_password_plain').val('1'));
                form.appendTo('body').submit();
            }
        });
    });
    </script>
</body>
</html>