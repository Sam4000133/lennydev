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

// Includi l'autoloader di Composer per caricare la libreria QR code
require_once __DIR__ . '/../../../vendor/autoload.php';

// Importa le classi necessarie per la generazione del QR code
use chillerlan\QRCode\{QRCode, QROptions};

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

// Ottieni l'ID utente dalla sessione
$userId = $_SESSION['2fa_user_id'];
$username = $_SESSION['2fa_username'];

// Ottieni o crea la chiave segreta 2FA dell'utente
$secret = '';
$stmt = $conn->prepare("SELECT secret_key, is_configured FROM user_2fa WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Genera una nuova chiave segreta
    $ga = new PHPGangsta_GoogleAuthenticator();
    $secret = $ga->createSecret();
    
    // Inserisci la chiave nel database
    $insertStmt = $conn->prepare("INSERT INTO user_2fa (user_id, secret_key, is_configured) VALUES (?, ?, 0)");
    $insertStmt->bind_param("is", $userId, $secret);
    $insertStmt->execute();
    $insertStmt->close();
} else {
    $row = $result->fetch_assoc();
    $secret = $row['secret_key'];
    
    // Se l'utente ha già configurato 2FA, reindirizza alla verifica
    if ($row['is_configured'] == 1) {
        header("Location: verify-2fa.php");
        exit;
    }
}

$stmt->close();

// Funzione per generare il QR code utilizzando la libreria chillerlan/php-qrcode
function generateQRCode($text) {
    try {
        // Configura le opzioni per il QR code
        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_MARKUP_SVG,
            'eccLevel' => QRCode::ECC_M,
            'scale' => 5,
            'imageBase64' => false,
        ]);
        
        // Crea il QR code
        $qrcode = new QRCode($options);
        
        // Genera il SVG del QR code
        return $qrcode->render($text);
    } catch (Exception $e) {
        error_log('Errore nella generazione del QR code: ' . $e->getMessage());
        return false;
    }
}

// Crea l'URL per Google Authenticator
$ga = new PHPGangsta_GoogleAuthenticator();
$otpauthUrl = 'otpauth://totp/' . urlencode($username . '@' . $siteName) . '?secret=' . $secret . '&issuer=' . urlencode($siteName);

// Genera il QR code
$qrCodeSvg = generateQRCode($otpauthUrl);

// Gestisci la verifica del codice 2FA
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    
    if (empty($code)) {
        $error_message = 'Inserisci il codice di verifica.';
    } elseif (strlen($code) !== 6 || !ctype_digit($code)) {
        $error_message = 'Il codice deve essere composto da 6 cifre.';
    } else {
        // Verifica il codice 2FA
        $checkResult = $ga->verifyCode($secret, $code, 2);    // 2 = permetti una discrepanza di 2*30 secondi
        
        if ($checkResult) {
            // Codice valido, completa la configurazione
            $updateStmt = $conn->prepare("UPDATE user_2fa SET is_configured = 1 WHERE user_id = ?");
            $updateStmt->bind_param("i", $userId);
            $updateStmt->execute();
            $updateStmt->close();
            
            // Genera codici di backup
            $backupCodes = [];
            for ($i = 0; $i < 8; $i++) {
                $backupCodes[] = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 10));
            }
            
            // Salva i codici di backup
            $backupCodesJson = json_encode($backupCodes);
            $backupStmt = $conn->prepare("UPDATE user_2fa SET backup_codes = ? WHERE user_id = ?");
            $backupStmt->bind_param("si", $backupCodesJson, $userId);
            $backupStmt->execute();
            $backupStmt->close();
            
            // Log della configurazione completata
            if ($has_login_logs_table) {
                $log_ip = $_SERVER['REMOTE_ADDR'];
                $log_agent = $_SERVER['HTTP_USER_AGENT'];
                $log_stmt = $conn->prepare("INSERT INTO login_logs (user_id, ip_address, user_agent, success, notes, created_at) VALUES (?, ?, ?, 1, 'Configurazione 2FA completata', NOW())");
                
                if ($log_stmt) {
                    $log_stmt->bind_param("iss", $userId, $log_ip, $log_agent);
                    $log_stmt->execute();
                    $log_stmt->close();
                }
            }
            
            $success_message = 'Autenticazione a due fattori configurata con successo!';
            
            // Salva i codici di backup in sessione per mostrarli nella pagina
            $_SESSION['backup_codes'] = $backupCodes;
            
            // Reindirizza alla pagina di verifica 2FA per completare il login
            header("Location: verify-2fa.php");
            exit;
        } else {
            $error_message = 'Codice non valido. Riprova.';
            
            // Log del tentativo fallito
            if ($has_login_logs_table) {
                $log_ip = $_SERVER['REMOTE_ADDR'];
                $log_agent = $_SERVER['HTTP_USER_AGENT'];
                $log_stmt = $conn->prepare("INSERT INTO login_logs (user_id, ip_address, user_agent, success, notes, created_at) VALUES (?, ?, ?, 0, 'Configurazione 2FA fallita', NOW())");
                
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

    <title>Configura autenticazione | <?php echo htmlspecialchars($siteName); ?></title>

    <meta name="description" content="Configura autenticazione a due fattori" />

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
      
      .qr-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        margin-bottom: 20px;
      }
      
      .qr-code-svg {
        width: 200px;
        height: 200px;
        margin: 0 auto;
      }
      
      .secret-key {
        font-family: monospace;
        background: #f5f5f5;
        padding: 8px 12px;
        border-radius: 4px;
        font-size: 16px;
        letter-spacing: 2px;
        margin: 15px 0;
        word-break: break-all;
        text-align: center;
      }
      
      .setup-steps {
        margin-bottom: 20px;
      }
      
      .setup-steps ol {
        padding-left: 20px;
      }
      
      .setup-steps li {
        margin-bottom: 8px;
      }
    </style>
  </head>

  <body>
    <!-- Content -->
    <div class="container-xxl">
      <div class="authentication-wrapper authentication-basic container-p-y">
        <div class="authentication-inner py-4">
          <!-- 2FA Setup -->
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
              <h4 class="mb-1 pt-2">Configura Google Authenticator 🔐</h4>
              <p class="mb-4">È necessario configurare l'autenticazione a due fattori per il tuo account amministratore.</p>

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

              <div class="setup-steps mb-4">
                <ol>
                  <li>Scarica l'app <strong>Google Authenticator</strong> sul tuo smartphone: 
                    <a href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2" target="_blank">Android</a> | 
                    <a href="https://apps.apple.com/it/app/google-authenticator/id388497605" target="_blank">iOS</a>
                  </li>
                  <li>Apri l'app e scansiona il codice QR sottostante</li>
                  <li>In alternativa, inserisci manualmente il codice segreto</li>
                  <li>Inserisci il codice a 6 cifre generato dall'app per completare la configurazione</li>
                </ol>
              </div>

              <div class="qr-container">
                <!-- Mostra il QR code SVG -->
                <div class="qr-code-svg">
                  <?php if ($qrCodeSvg): ?>
                    <?php echo $qrCodeSvg; ?>
                  <?php else: ?>
                    <div class="alert alert-warning">
                      <i class="ti tabler-alert-triangle me-2"></i>
                      Impossibile generare il QR code. Usa il codice segreto riportato sotto.
                    </div>
                  <?php endif; ?>
                </div>
                
                <p class="mt-3 mb-1">Codice segreto (per inserimento manuale):</p>
                <div class="secret-key"><?php echo chunk_split($secret, 4, ' '); ?></div>
              </div>

              <form id="twoFactorSetupForm" class="mb-3" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
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
                
                <button class="btn btn-primary d-grid w-100 mb-3" type="submit">
                  <span class="d-flex align-items-center justify-content-center">
                    <i class="ti tabler-lock-check me-2"></i>
                    Verifica e Completa
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
          <!-- /2FA Setup -->
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
      const setupForm = document.getElementById('twoFactorSetupForm');
      const codeInput = document.getElementById('code');
      
      if (setupForm&&codeInput) {
        // Accetta solo numeri nel campo codice
        codeInput.addEventListener('input', function(e) {
          this.value = this.value.replace(/[^0-9]/g, '');
        });
        
        setupForm.addEventListener('submit', function(e) {
          const code = codeInput.value.trim();
          
          if (!code || code.length !== 6 || !/^\d+$/.test(code)) {
            e.preventDefault();
            alert('Inserisci un codice di verifica valido a 6 cifre.');
          }
        });
      }
    });
    </script>
  </body>
</html>