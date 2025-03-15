<?php
// Abilita la visualizzazione degli errori per il debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inizia la sessione
session_start();

// Se l'utente è già loggato, reindirizza alla dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Includi la connessione al database
require_once 'db_connection.php';

// Funzione per ottenere un'impostazione dal database
function getSetting($conn, $key, $default = null) {
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    if ($stmt) {
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result&&$result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $stmt->close();
            return $row['setting_value'];
        }
        
        $stmt->close();
    }
    
    return $default;
}

// Recupera le impostazioni del sito
$siteName = getSetting($conn, 'site_name', 'Lenny');
$siteLogo = getSetting($conn, 'site_logo', '');
$primaryColor = getSetting($conn, 'primary_color', '#5A8DEE');

// Recupera impostazioni di sicurezza password dal database
$minLength = getSetting($conn, 'min_password_length', 8);
$requireUppercase = getSetting($conn, 'require_uppercase', '1');
$requireLowercase = getSetting($conn, 'require_lowercase', '1');
$requireNumber = getSetting($conn, 'require_number', '1');
$requireSpecial = getSetting($conn, 'require_special', '1');
$passwordExpiry = getSetting($conn, 'password_expiry', 90); // Giorni di validità password

// Verifica se esiste la tabella password_resets
$has_password_resets_table = false;
$result = $conn->query("SHOW TABLES LIKE 'password_resets'");
if ($result&&$result->num_rows > 0) {
    $has_password_resets_table = true;
}

// Variabile per i messaggi di errore/successo
$error_message = '';
$success_message = '';
$token = '';
$showResetForm = false;
$isExpired = isset($_GET['expired'])&&$_GET['expired'] == '1';

// Gestione del submit del form di reset password
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = filter_input(INPUT_POST, 'token', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['password-confirm'] ?? '';
    
    // Verifica se tutti i campi sono compilati
    if (empty($token) || empty($password) || empty($confirmPassword)) {
        $error_message = 'Tutti i campi sono obbligatori.';
        $showResetForm = true;
    } 
    // Verifica se le password coincidono
    else if ($password !== $confirmPassword) {
        $error_message = 'Le password non coincidono.';
        $showResetForm = true;
    } 
    else if ($has_password_resets_table) {
        // Verifica la sicurezza della password
        $passwordErrors = [];
        
        // Verifica lunghezza minima
        if (strlen($password) < $minLength) {
            $passwordErrors[] = "La password deve contenere almeno $minLength caratteri.";
        }
        
        // Verifica maiuscole
        if ($requireUppercase === '1'&&!preg_match('/[A-Z]/', $password)) {
            $passwordErrors[] = "La password deve contenere almeno una lettera maiuscola.";
        }
        
        // Verifica minuscole
        if ($requireLowercase === '1'&&!preg_match('/[a-z]/', $password)) {
            $passwordErrors[] = "La password deve contenere almeno una lettera minuscola.";
        }
        
        // Verifica numeri
        if ($requireNumber === '1'&&!preg_match('/[0-9]/', $password)) {
            $passwordErrors[] = "La password deve contenere almeno un numero.";
        }
        
        // Verifica caratteri speciali
        if ($requireSpecial === '1'&&!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $passwordErrors[] = "La password deve contenere almeno un carattere speciale.";
        }
        
        // Se ci sono errori di validazione, mostrali all'utente
        if (!empty($passwordErrors)) {
            $error_message = implode('<br>', $passwordErrors);
            $showResetForm = true;
        } else {
            try {
                // Verifica se il token è valido e non scaduto
                $stmt = $conn->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()");
                $stmt->bind_param("s", $token);
                $stmt->execute();
                $result = $stmt->get_result();
                $resetInfo = $result->fetch_assoc();
                $stmt->close();
                
                if (!$resetInfo) {
                    $error_message = 'Il link di recupero non è valido o è scaduto.';
                } else {
                    // Memorizza l'email prima di modificare il database
                    $user_email = $resetInfo['email'];
                    
                    // Determina se la password deve scadere 
                    $passwordExpires = 1; // Default: sì, scade
                    if (intval($passwordExpiry) == 0) {
                        $passwordExpires = 0; // Se impostato a 0 giorni, non scade mai
                    }
                    
                    // Verifica se esiste la colonna password_changed_at nella tabella users
                    $columnExists = false;
                    $columnCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'password_changed_at'");
                    if ($columnCheck&&$columnCheck->num_rows > 0) {
                        $columnExists = true;
                    }
                    
                    // Se la colonna non esiste, la aggiungiamo
                    if (!$columnExists) {
                        $conn->query("ALTER TABLE users ADD COLUMN password_changed_at DATETIME DEFAULT CURRENT_TIMESTAMP");
                        $conn->query("ALTER TABLE users ADD COLUMN password_expires TINYINT(1) DEFAULT 1");
                    }
                    
                    // Aggiorna la password dell'utente e la data di modifica
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    if ($columnExists) {
                        // Se le colonne esistono, aggiorna anche i campi relativi alla scadenza
                        $stmt = $conn->prepare("UPDATE users SET password = ?, password_changed_at = NOW(), password_expires = ? WHERE email = ?");
                        $stmt->bind_param("sis", $hashedPassword, $passwordExpires, $user_email);
                    } else {
                        // Altrimenti aggiorna solo la password
                        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
                        $stmt->bind_param("ss", $hashedPassword, $user_email);
                    }
                    
                    $result = $stmt->execute();
                    $stmt->close();
                    
                    if (!$result) {
                        $error_message = "Si è verificato un errore durante l'aggiornamento della password.";
                        $showResetForm = true;
                    } else {
                        // Elimina il token di reset
                        $stmt = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
                        $stmt->bind_param("s", $token);
                        $stmt->execute();
                        $stmt->close();
                        
                        // Log del reset password
                        $stmt = $conn->prepare("INSERT INTO system_logs (level, message, ip_address) VALUES (?, ?, ?)");
                        if ($stmt) {
                            $logMessage = "Password reimpostata con successo per " . $user_email;
                            $logLevel = "info";
                            $ipAddress = $_SERVER['REMOTE_ADDR'];
                            $stmt->bind_param("sss", $logLevel, $logMessage, $ipAddress);
                            $stmt->execute();
                            $stmt->close();
                        }
                        
                        // Messaggio di scadenza password
                        $expiryMessage = "";
                        if ($passwordExpires&&intval($passwordExpiry) > 0) {
                            $expiryMessage = " La tua password scadrà tra " . intval($passwordExpiry) . " giorni.";
                        }
                        
                        $success_message = 'La tua password è stata reimpostata con successo.' . $expiryMessage;
                        // Importante: nascondi il form dopo il successo
                        $showResetForm = false;
                        
                        // Se era una password scaduta, rimuovi la sessione corrispondente
                        if (isset($_SESSION['password_expired'])) {
                            unset($_SESSION['password_expired']);
                            unset($_SESSION['reset_token']);
                        }
                        
                        // Redirect con un messaggio di successo dopo 3 secondi
                        header("refresh:3;url=login.php");
                    }
                }
            } catch (Exception $e) {
                error_log("Errore nel reset password: " . $e->getMessage());
                $error_message = "Si è verificato un errore. Riprova più tardi.";
                $showResetForm = true;
            }
        }
    } else {
        $error_message = 'Funzionalità di reset password non disponibile.';
    }
}
// Verifica che sia presente un token valido - SOLO SE NON È UNA RICHIESTA POST
else if (isset($_GET['token'])) {
    $token = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    
    if ($has_password_resets_table) {
        // Verifica se il token è valido e non scaduto
        $stmt = $conn->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()");
        if ($stmt) {
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result&&$result->num_rows > 0) {
                $resetInfo = $result->fetch_assoc();
                $showResetForm = true;
            } else {
                $error_message = 'Il link di recupero non è valido o è scaduto.';
            }
            
            $stmt->close();
        }
    } else {
        $error_message = 'Funzionalità di reset password non disponibile.';
    }
} else {
    $error_message = 'Per reimpostare la password utilizza il link ricevuto via email o richiedi un nuovo link dalla pagina di recupero password.';
}
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Reimposta Password | <?php echo htmlspecialchars($siteName); ?></title>
    <meta name="description" content="Reimposta la tua password" />
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../../../assets/img/favicon/favicon.ico" />
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet" />
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.44.0/tabler-icons.min.css" />
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: <?php echo $primaryColor; ?>;
        }
        
        body {
            font-family: 'Public Sans', sans-serif;
            background-color: #f8f7fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        
        .authentication-wrapper {
            max-width: 400px;
            width: 100%;
            margin: 0 auto;
            padding: 1.5rem;
        }
        
        .card {
            border: none;
            box-shadow: 0 2px 6px 0 rgba(67, 89, 113, 0.12);
            border-radius: 0.5rem;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        .app-brand {
            margin-bottom: 2rem;
            display: flex;
            justify-content: center;
        }
        
        .app-brand-link {
            display: flex;
            align-items: center;
            text-decoration: none;
        }
        
        .app-brand-logo {
            display: flex;
            margin-right: 0.5rem;
        }
        
        .app-brand-text {
            font-size: 1.5rem;
            font-weight: 700;
            color: #566a7f;
        }
        
        .text-primary {
            color: var(--primary-color) !important;
        }
        
        .btn-primary {
            background-color: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
            opacity: 0.9;
        }
        
        .input-group-text {
            cursor: pointer;
            background-color: transparent;
        }
        
        .mb-6 {
            margin-bottom: 1.5rem;
        }
        
        .form-control-invalid {
            border-color: #ff5b5c !important;
        }
        
        .invalid-feedback {
            display: block;
        }
        
        .eye-icon {
            cursor: pointer;
            color: #a1acb8;
        }
        
        .cursor-pointer {
            cursor: pointer;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="authentication-wrapper">
            <div class="card">
                <div class="card-body">
                    <!-- Logo -->
                    <div class="app-brand">
                        <a href="index.php" class="app-brand-link">
                            <span class="app-brand-logo">
                                <span class="text-primary">
                                    <?php if (!empty($siteLogo)): ?>
                                        <img src="<?php echo htmlspecialchars($siteLogo); ?>" alt="<?php echo htmlspecialchars($siteName); ?> Logo" height="32">
                                    <?php else: ?>
                                        <svg width="32" height="22" viewBox="0 0 32 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M0.00172773 0V6.85398C0.00172773 6.85398 -0.133178 9.01207 1.98092 10.8388L13.6912 21.9964L19.7809 21.9181L18.8042 9.88248L16.4951 7.17289L9.23799 0H0.00172773Z" fill="currentColor" />
                                            <path opacity="0.06" fill-rule="evenodd" clip-rule="evenodd" d="M7.69824 16.4364L12.5199 3.23696L16.5541 7.25596L7.69824 16.4364Z" fill="#161616" />
                                            <path opacity="0.06" fill-rule="evenodd" clip-rule="evenodd" d="M8.07751 15.9175L13.9419 4.63989L16.5849 7.28475L8.07751 15.9175Z" fill="#161616" />
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M7.77295 16.3566L23.6563 0H32V6.88383C32 6.88383 31.8262 9.17836 30.6591 10.4057L19.7824 22H13.6938L7.77295 16.3566Z" fill="currentColor" />
                                        </svg>
                                    <?php endif; ?>
                                </span>
                            </span>
                            <span class="app-brand-text"><?php echo htmlspecialchars($siteName); ?></span>
                        </a>
                    </div>
                    <!-- /Logo -->
                    
                    <h4 class="mb-1">Reimposta la tua password 🔒</h4>
                    <p class="mb-6">La tua nuova password deve essere diversa dalle password precedenti</p>
                    
                    <?php if ($isExpired): ?>
                        <div class="alert alert-warning mb-4" role="alert">
                            <strong>La tua password è scaduta.</strong> Per motivi di sicurezza, devi impostare una nuova password per continuare.
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger mb-4" role="alert">
                            <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success mb-4" role="alert">
                            <?php echo $success_message; ?>
                            <br>
                            Sarai reindirizzato alla pagina di login tra pochi secondi...
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($showResetForm&&empty($success_message)): ?>
                        <form id="resetPasswordForm" class="mb-6" action="reset-password.php" method="POST">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                            
                            <div class="mb-6">
                                <label class="form-label" for="password">Nuova Password</label>
                                <div class="input-group">
                                    <input type="password" id="password" class="form-control" name="password" placeholder="••••••••">
                                    <span class="input-group-text" id="togglePassword">
                                        <i class="ti ti-eye-off eye-icon"></i>
                                    </span>
                                </div>
                                <div id="passwordError" class="invalid-feedback" style="display: none;"></div>
                                <small class="form-text text-muted">
                                    Requisiti: 
                                    <?php if ($requireUppercase === '1'): ?>una lettera maiuscola, <?php endif; ?>
                                    <?php if ($requireLowercase === '1'): ?>una lettera minuscola, <?php endif; ?>
                                    <?php if ($requireNumber === '1'): ?>un numero, <?php endif; ?>
                                    <?php if ($requireSpecial === '1'): ?>un carattere speciale, <?php endif; ?>
                                    minimo <?php echo $minLength; ?> caratteri.
                                    <?php if (intval($passwordExpiry) > 0): ?>
                                        <br>Secondo le politiche di sicurezza, la password scadrà dopo <?php echo intval($passwordExpiry); ?> giorni.
                                    <?php else: ?>
                                        <br>La password non ha scadenza.
                                    <?php endif; ?>
                                </small>
                            </div>
                            
                            <div class="mb-6">
                                <label class="form-label" for="password-confirm">Conferma Password</label>
                                <div class="input-group">
                                    <input type="password" id="password-confirm" class="form-control" name="password-confirm" placeholder="••••••••">
                                    <span class="input-group-text" id="toggleConfirmPassword">
                                        <i class="ti ti-eye-off eye-icon"></i>
                                    </span>
                                </div>
                                <div id="confirmPasswordError" class="invalid-feedback" style="display: none;"></div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary d-grid w-100">Imposta nuova password</button>
                        </form>
                    <?php endif; ?>
                    
                    <div class="text-center mt-4">
                        <?php if (empty($success_message)&&!$isExpired): ?>
                            <a href="forgot-psw.php" class="btn btn-outline-primary mb-3">Richiedi nuovo link</a>
                            <br>
                        <?php endif; ?>
                        <a href="login.php" class="d-flex justify-content-center align-items-center">
                            <i class="ti ti-chevron-left me-2"></i>
                            Torna alla pagina di login
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Funzioni per mostrare/nascondere password
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        
        if (togglePassword&&password) {
            togglePassword.addEventListener('click', function() {
                // Toggle type attribute
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                
                // Toggle icon
                const icon = togglePassword.querySelector('i');
                icon.classList.toggle('ti-eye');
                icon.classList.toggle('ti-eye-off');
            });
        }
        
        // Funzioni per mostrare/nascondere conferma password
        const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
        const confirmPassword = document.getElementById('password-confirm');
        
        if (toggleConfirmPassword&&confirmPassword) {
            toggleConfirmPassword.addEventListener('click', function() {
                // Toggle type attribute
                const type = confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
                confirmPassword.setAttribute('type', type);
                
                // Toggle icon
                const icon = toggleConfirmPassword.querySelector('i');
                icon.classList.toggle('ti-eye');
                icon.classList.toggle('ti-eye-off');
            });
        }
        
        // Gestione validazione form
        const form = document.getElementById('resetPasswordForm');
        
        if (form) {
            form.addEventListener('submit', function(e) {
                let isValid = true;
                
                // Reset previous errors
                resetErrors();
                
                // Validate password - requisiti minimi dal server
                if (!password.value.trim()) {
                    showError(password, 'passwordError', 'La password è obbligatoria');
                    isValid = false;
                } else {
                    // Verificare i criteri di sicurezza con controlli lato client
                    const minLength = <?php echo intval($minLength); ?>;
                    const requireUppercase = <?php echo $requireUppercase === '1' ? 'true' : 'false'; ?>;
                    const requireLowercase = <?php echo $requireLowercase === '1' ? 'true' : 'false'; ?>;
                    const requireNumber = <?php echo $requireNumber === '1' ? 'true' : 'false'; ?>;
                    const requireSpecial = <?php echo $requireSpecial === '1' ? 'true' : 'false'; ?>;
                    
                    const errors = [];
                    
                    if (password.value.length < minLength) {
                        errors.push(`La password deve contenere almeno ${minLength} caratteri`);
                    }
                    
                    if (requireUppercase&&!/[A-Z]/.test(password.value)) {
                        errors.push('La password deve contenere almeno una lettera maiuscola');
                    }
                    
                    if (requireLowercase&&!/[a-z]/.test(password.value)) {
                        errors.push('La password deve contenere almeno una lettera minuscola');
                    }
                    
                    if (requireNumber&&!/[0-9]/.test(password.value)) {
                        errors.push('La password deve contenere almeno un numero');
                    }
                    
                    if (requireSpecial&&!/[^a-zA-Z0-9]/.test(password.value)) {
                        errors.push('La password deve contenere almeno un carattere speciale');
                    }
                    
                    if (errors.length > 0) {
                        showError(password, 'passwordError', errors.join('<br>'));
                        isValid = false;
                    }
                }
                
                // Validate confirm password
                if (!confirmPassword.value.trim()) {
                    showError(confirmPassword, 'confirmPasswordError', 'La conferma password è obbligatoria');
                    isValid = false;
                } else if (password.value !== confirmPassword.value) {
                    showError(confirmPassword, 'confirmPasswordError', 'Le password non coincidono');
                    isValid = false;
                }
                
                if (!isValid) {
                    e.preventDefault();
                }
            });
        }
        
        // Helper function to show error message
        function showError(inputElement, errorId, message) {
            inputElement.classList.add('form-control-invalid');
            const errorElement = document.getElementById(errorId);
            if (errorElement) {
                errorElement.innerHTML = message;
                errorElement.style.display = 'block';
            }
        }
        
        // Helper function to reset all errors
        function resetErrors() {
            // Solo se il form esiste
            if (!form) return;
            
            // Reset all inputs
            const inputs = form.querySelectorAll('input');
            inputs.forEach(input => {
                input.classList.remove('form-control-invalid');
            });
            
            // Hide all error messages
            const errorElements = form.querySelectorAll('.invalid-feedback');
            errorElements.forEach(error => {
                error.style.display = 'none';
            });
        }
        
        // Imposta automaticamente il focus sul campo password
        if (password) {
            password.focus();
        }
    });
    </script>
</body>
</html>