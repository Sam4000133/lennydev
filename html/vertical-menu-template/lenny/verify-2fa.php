<?php
// Abilita la visualizzazione degli errori per il debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inizia la sessione
session_start();

// Verifica se l'utente ha completato la prima fase di autenticazione
if (!isset($_SESSION['2fa_pending']) || !isset($_SESSION['2fa_user_id'])) {
    header("Location: login.php");
    exit;
}

// Includi la connessione al database
require_once 'db_connection.php';

// Includi la libreria Google Authenticator
require_once 'GoogleAuthenticator.php';

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

// Recupera impostazioni del sito
$siteName = getSetting($conn, 'site_name', 'Lenny');
$siteLogo = getSetting($conn, 'site_logo', '');
$primaryColor = getSetting($conn, 'primary_color', '#5A8DEE');

// Verifica se esiste la tabella login_logs
$has_login_logs_table = false;
$result = $conn->query("SHOW TABLES LIKE 'login_logs'");
if ($result&&$result->num_rows > 0) {
    $has_login_logs_table = true;
}

$error_message = '';
$success_message = '';

// Ottieni la chiave segreta 2FA dell'utente
$userId = $_SESSION['2fa_user_id'];
$secret = '';

$stmt = $conn->prepare("SELECT secret_key FROM user_2fa WHERE user_id = ? AND is_configured = 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $row = $result->fetch_assoc();
    $secret = $row['secret_key'];
} else {
    // Se l'utente non ha configurato 2FA, reindirizza alla pagina di configurazione
    header("Location: setup-2fa.php");
    exit;
}

$stmt->close();

// Gestisci la verifica del codice 2FA
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    
    if (empty($code)) {
        $error_message = 'Inserisci il codice di verifica.';
    } elseif (strlen($code) !== 6 || !ctype_digit($code)) {
        $error_message = 'Il codice deve essere composto da 6 cifre.';
    } else {
        // Verifica il codice 2FA
        $ga = new PHPGangsta_GoogleAuthenticator();
        $checkResult = $ga->verifyCode($secret, $code, 2);    // 2 = permetti un discrepanza di 2*30 secondi
        
        if ($checkResult) {
            // Codice valido, completa il login
            // Ottieni i dati utente completi
            $stmt = $conn->prepare("SELECT id, username, email, full_name, role_id FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // Imposta la sessione
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role_id'] = $user['role_id'];
                
                // Ottieni i permessi del ruolo
                $permissions = [];
                $permStmt = $conn->prepare("
                    SELECT p.name, p.category, rp.can_read, rp.can_write, rp.can_create 
                    FROM role_permissions rp
                    JOIN permissions p ON rp.permission_id = p.id
                    WHERE rp.role_id = ?
                ");
                
                if ($permStmt) {
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
                }
                
                // Salva i permessi nella sessione
                $_SESSION['permissions'] = $permissions;
                
                // Gestisci "Ricordami" se era stato selezionato nel login iniziale
                if (isset($_SESSION['2fa_remember_me'])&&$_SESSION['2fa_remember_me'] === true) {
                    // Genera un token per il cookie "ricordami"
                    $token = bin2hex(random_bytes(32));
                    $expires = time() + 86400 * 30; // 30 giorni
                    
                    // Verifica se esiste la tabella user_sessions
                    $result = $conn->query("SHOW TABLES LIKE 'user_sessions'");
                    if ($result->num_rows > 0) {
                        // Rimuovi eventuali token precedenti
                        $clean_stmt = $conn->prepare("DELETE FROM user_sessions WHERE user_id = ?");
                        $clean_stmt->bind_param("i", $user['id']);
                        $clean_stmt->execute();
                        $clean_stmt->close();
                        
                        // Salva il token nel database
                        $tokenStmt = $conn->prepare("INSERT INTO user_sessions (user_id, token, expires_at) VALUES (?, ?, FROM_UNIXTIME(?))");
                        $tokenStmt->bind_param("isi", $user['id'], $token, $expires);
                        $tokenStmt->execute();
                        $tokenStmt->close();
                    }
                    
                    // Imposta il cookie
                    setcookie('remember_token', $token, $expires, '/', '', false, true);
                    setcookie('remember_user', $user['id'], $expires, '/', '', false, true);
                }
                
                // Pulisci le variabili di sessione temporanee
                unset($_SESSION['2fa_pending']);
                unset($_SESSION['2fa_user_id']);
                unset($_SESSION['2fa_username']);
                unset($_SESSION['2fa_user_email']);
                unset($_SESSION['2fa_remember_me']);
                unset($_SESSION['2fa_from_remember_me']);
                
                // Log dell'accesso riuscito (solo se la tabella esiste)
                if ($has_login_logs_table) {
                    $log_ip = $_SERVER['REMOTE_ADDR'];
                    $log_agent = $_SERVER['HTTP_USER_AGENT'];
                    $log_stmt = $conn->prepare("INSERT INTO login_logs (user_id, ip_address, user_agent, success, notes, created_at) VALUES (?, ?, ?, 1, 'Login completato con 2FA', NOW())");
                    
                    if ($log_stmt) {
                        $log_stmt->bind_param("iss", $user['id'], $log_ip, $log_agent);
                        $log_stmt->execute();
                        $log_stmt->close();
                    }
                }
                
                // Reindirizza alla dashboard
                header("Location: index.php");
                exit;
            }
            
            $stmt->close();
        } else {
            $error_message = 'Codice non valido. Riprova.';
            
            // Log del tentativo fallito (solo se la tabella esiste)
            if ($has_login_logs_table) {
                $log_ip = $_SERVER['REMOTE_ADDR'];
                $log_agent = $_SERVER['HTTP_USER_AGENT'];
                $log_stmt = $conn->prepare("INSERT INTO login_logs (user_id, ip_address, user_agent, success, notes, created_at) VALUES (?, ?, ?, 0, 'Verifica 2FA fallita', NOW())");
                
                if ($log_stmt) {
                    $log_stmt->bind_param("iss", $userId, $log_ip, $log_agent);
                    $log_stmt->execute();
                    $log_stmt->close();
                }
            }
        }
    }
}

// Chiudi la connessione al database
$conn->close();
?>
<!doctype html>
<html
  lang="it"
  class="layout-wide customizer-hide"
  dir="ltr"
  data-skin="default"
  data-assets-path="../../../assets/"
  data-template="vertical-menu-template"
  data-bs-theme="light">
  <head>
    <meta charset="utf-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>Verifica autenticazione | <?php echo htmlspecialchars($siteName); ?></title>

    <meta name="description" content="Verifica autenticazione a due fattori" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../../../assets/img/favicon/favicon.ico" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
      rel="stylesheet" />

    <link rel="stylesheet" href="../../../assets/vendor/fonts/iconify-icons.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="../../../assets/vendor/libs/node-waves/node-waves.css" />
    <link rel="stylesheet" href="../../../assets/vendor/libs/pickr/pickr-themes.css" />
    <link rel="stylesheet" href="../../../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../../../assets/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="../../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <!-- Page CSS -->
    <link rel="stylesheet" href="../../../assets/vendor/css/pages/page-auth.css" />

    <!-- Helpers -->
    <script src="../../../assets/vendor/js/helpers.js"></script>
    <script src="../../../assets/vendor/js/template-customizer.js"></script>
    <script src="../../../assets/js/config.js"></script>
    
    <!-- Custom Styles -->
    <style>
      :root {
        --primary-color: <?php echo $primaryColor; ?>;
      }
      
      .btn-primary {
        background-color: var(--primary-color) !important;
        border-color: var(--primary-color) !important;
      }
      .btn-primary:hover {
        opacity: 0.9;
      }
      .text-primary, .app-brand-text {
        color: var(--primary-color) !important;
      }
      
      .code-input {
        letter-spacing: 10px;
        font-size: 24px;
        text-align: center;
        font-weight: 600;
      }
    </style>
  </head>

  <body>
    <!-- Content -->
    <div class="container-xxl">
      <div class="authentication-wrapper authentication-basic container-p-y">
        <div class="authentication-inner py-4">
          <!-- Two-Factor Authentication -->
          <div class="card">
            <div class="card-body">
              <!-- Logo -->
              <div class="app-brand justify-content-center mb-4">
                <a href="index.php" class="app-brand-link">
                  <span class="app-brand-logo demo">
                    <?php if (!empty($siteLogo)): ?>
                      <img src="<?php echo htmlspecialchars($siteLogo); ?>" alt="<?php echo htmlspecialchars($siteName); ?> Logo" height="32">
                    <?php else: ?>
                      <div class="rounded-circle bg-primary p-2">
                        <i class="ti tabler-tools-kitchen-2 text-white"></i>
                      </div>
                    <?php endif; ?>
                  </span>
                  <span class="app-brand-text demo text-heading fw-bold"><?php echo strtoupper(htmlspecialchars($siteName)); ?> ADMIN PANEL</span>
                </a>
              </div>
              <!-- /Logo -->
              <h4 class="mb-1 pt-2">Verifica autenticazione 👨‍💻</h4>
              <p class="mb-4">Inserisci il codice a 6 cifre generato dall'app Google Authenticator</p>

              <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger d-flex align-items-center mb-3">
                  <i class="ti tabler-alert-circle me-2"></i>
                  <div><?php echo htmlspecialchars($error_message); ?></div>
                </div>
              <?php endif; ?>
              
              <?php if (!empty($success_message)): ?>
                <div class="alert alert-success d-flex align-items-center mb-3">
                  <i class="ti tabler-check-circle me-2"></i>
                  <div><?php echo htmlspecialchars($success_message); ?></div>
                </div>
              <?php endif; ?>

              <form id="twoFactorForm" class="mb-3" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <div class="mb-3">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="form-label" for="code">Codice di verifica</label>
                  </div>
                  <input
                    type="text"
                    class="form-control code-input"
                    id="code"
                    name="code"
                    placeholder="000000"
                    maxlength="6"
                    autocomplete="off"
                    autofocus
                    required />
                </div>
                
                <div class="mb-3">
                  <div class="d-flex justify-content-center">
                    <div id="countdown" class="text-center">
                      <small>Il codice verrà aggiornato tra <span id="timer">30</span> secondi</small>
                    </div>
                  </div>
                </div>
                
                <button class="btn btn-primary d-grid w-100 mb-3" type="submit">
                  <span class="d-flex align-items-center justify-content-center">
                    <i class="ti tabler-lock-check me-2"></i>
                    Verifica
                  </span>
                </button>
                
                <div class="text-center">
                  <a href="logout.php" class="d-flex align-items-center justify-content-center">
                    <i class="ti tabler-arrow-left me-2"></i>
                    Torna al login
                  </a>
                </div>
              </form>
            </div>
          </div>
          <!-- /Two-Factor Authentication -->
        </div>
      </div>
    </div>
    <!-- / Content -->

    <!-- Core JS -->
    <script src="../../../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../../../assets/vendor/libs/popper/popper.js"></script>
    <script src="../../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../../assets/vendor/libs/node-waves/node-waves.js"></script>
    <script src="../../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../../../assets/vendor/js/menu.js"></script>
    
    <!-- Main JS -->
    <script src="../../../assets/js/main.js"></script>

    <!-- Validation JS -->
    <script src="../../../assets/vendor/libs/@form-validation/popular.js"></script>
    <script src="../../../assets/vendor/libs/@form-validation/bootstrap5.js"></script>
    <script src="../../../assets/vendor/libs/@form-validation/auto-focus.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Validazione del form
      const twoFactorForm = document.getElementById('twoFactorForm');
      const codeInput = document.getElementById('code');
      
      if (twoFactorForm&&codeInput) {
        // Accetta solo numeri nel campo codice
        codeInput.addEventListener('input', function(e) {
          this.value = this.value.replace(/[^0-9]/g, '');
        });
        
        twoFactorForm.addEventListener('submit', function(e) {
          const code = codeInput.value.trim();
          
          if (!code || code.length !== 6 || !/^\d+$/.test(code)) {
            e.preventDefault();
            alert('Inserisci un codice di verifica valido a 6 cifre.');
          }
        });
      }
      
      // Timer per il codice
      const timerElement = document.getElementById('timer');
      let seconds = 30;
      
      function updateTimer() {
        timerElement.textContent = seconds;
        
        if (seconds <= 0) {
          seconds = 30;
        } else {
          seconds--;
          setTimeout(updateTimer, 1000);
        }
      }
      
      updateTimer();
    });
    </script>
  </body>
</html>