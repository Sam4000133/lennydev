<?php
// Abilita la visualizzazione degli errori per il debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Includi namespace di PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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

// Verifica se esiste la tabella password_resets
$has_password_resets_table = false;
$result = $conn->query("SHOW TABLES LIKE 'password_resets'");
if ($result&&$result->num_rows > 0) {
    $has_password_resets_table = true;
} else {
    // Se la tabella non esiste, creala
    $sql = "CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        token VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    if ($conn->query($sql) === TRUE) {
        $has_password_resets_table = true;
    } else {
        error_log("Errore nella creazione della tabella password_resets: " . $conn->error);
    }
}

// Variabile per verificare se PHPMailer è disponibile
$phpmailer_available = false;

// Percorsi possibili per PHPMailer
$possible_paths = [
    '../../../vendor/phpmailer/phpmailer/src/Exception.php',
    '../../../../vendor/phpmailer/phpmailer/src/Exception.php',
    '../../../../../vendor/phpmailer/phpmailer/src/Exception.php',
    'vendor/phpmailer/phpmailer/src/Exception.php',
];

foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        require_once str_replace('Exception.php', 'Exception.php', $path);
        require_once str_replace('Exception.php', 'PHPMailer.php', $path);
        require_once str_replace('Exception.php', 'SMTP.php', $path);
        $phpmailer_available = true;
        break;
    }
}

// Verifica se l'autoloader di Composer è disponibile
if (!$phpmailer_available) {
    $autoloader_paths = [
        '../../../vendor/autoload.php',
        '../../../../vendor/autoload.php',
        '../../../../../vendor/autoload.php',
        'vendor/autoload.php',
    ];
    
    foreach ($autoloader_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $phpmailer_available = true;
            break;
        }
    }
}

// Variabile per i messaggi di errore/successo
$error_message = '';
$success_message = '';

// Gestione del form di recupero password
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recupera l'email dal form
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    
    // Verifica se l'email è valida
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Inserisci un indirizzo email valido.';
    } else {
        // Verifica se l'email esiste nel database
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $email_exists = ($result->num_rows > 0);
            $stmt->close(); // Chiudi lo statement qui
            
            if (!$email_exists) {
                // Non mostriamo all'utente se l'email esiste o meno per sicurezza
                $success_message = "Se l'indirizzo email è registrato nel nostro sistema, riceverai un'email con le istruzioni per recuperare la password.";
            } else {
                // L'email esiste, generiamo un token di recupero
                try {
                    // Genera un token di recupero
                    $token = bin2hex(random_bytes(32));
                    $expiryDate = date('Y-m-d H:i:s', strtotime('+24 hours'));
                    
                    if ($has_password_resets_table) {
                        // Controlla se esiste già un token per questo utente
                        $check_stmt = $conn->prepare("SELECT id FROM password_resets WHERE email = ?");
                        $check_stmt->bind_param("s", $email);
                        $check_stmt->execute();
                        $check_result = $check_stmt->get_result();
                        $token_exists = ($check_result->num_rows > 0);
                        $check_stmt->close(); // Chiudi lo statement
                        
                        if ($token_exists) {
                            // Aggiorna il token esistente
                            $update_stmt = $conn->prepare("UPDATE password_resets SET token = ?, expires_at = ?, created_at = NOW() WHERE email = ?");
                            $update_stmt->bind_param("sss", $token, $expiryDate, $email);
                            $update_stmt->execute();
                            $update_stmt->close();
                        } else {
                            // Inserisci un nuovo token
                            $insert_stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                            $insert_stmt->bind_param("sss", $email, $token, $expiryDate);
                            $insert_stmt->execute();
                            $insert_stmt->close();
                        }
                    }
                    
                    // Recupera le impostazioni SMTP dal database
                    $mailHost = getSetting($conn, 'mail_host');
                    $mailPort = getSetting($conn, 'mail_port');
                    $mailUsername = getSetting($conn, 'mail_username');
                    $mailPassword = getSetting($conn, 'mail_password');
                    $mailFromAddress = getSetting($conn, 'mail_from_address');
                    $mailFromName = getSetting($conn, 'mail_from_name');
                    $mailEncryption = getSetting($conn, 'mail_encryption');
                    
                    // Costruisci l'URL di reset
                    $baseUrl = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
                    $resetUrl = $baseUrl . dirname($_SERVER['PHP_SELF']) . "/reset-password.php?token=" . $token;
                    
                    if ($phpmailer_available) {
                        // Utilizzo PHPMailer
                        $mail = new PHPMailer(true);
                        
                        try {
                            // Impostazioni server
                            $mail->isSMTP();
                            $mail->Host = $mailHost;
                            $mail->SMTPAuth = true;
                            $mail->Username = $mailUsername;
                            $mail->Password = $mailPassword;
                            $mail->SMTPSecure = $mailEncryption;
                            $mail->Port = $mailPort;
                            $mail->CharSet = 'UTF-8';
                            
                            // Mittente e destinatario
                            $mail->setFrom($mailFromAddress, $mailFromName);
                            $mail->addAddress($email);
                            
                            // Contenuto
                            $mail->isHTML(true);
                            $mail->Subject = "$siteName - Recupero Password";
                            
                            // Crea il template dell'email
                            $mail->Body = '
                            <!DOCTYPE html>
                            <html>
                            <head>
                                <style>
                                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                                    .header { background-color: ' . $primaryColor . '; color: white; padding: 20px; text-align: center; }
                                    .content { padding: 20px; border: 1px solid #ddd; }
                                    .footer { font-size: 12px; text-align: center; margin-top: 20px; color: #777; }
                                    .button { display: inline-block; background-color: ' . $primaryColor . '; color: white; text-decoration: none; padding: 10px 20px; border-radius: 4px; }
                                </style>
                            </head>
                            <body>
                                <div class="container">
                                    <div class="header">
                                        <h2>' . $siteName . '</h2>
                                    </div>
                                    <div class="content">
                                        <p>Gentile utente,</p>
                                        <p>Abbiamo ricevuto una richiesta di recupero password per il tuo account.</p>
                                        <p>Per reimpostare la tua password, clicca sul pulsante qui sotto:</p>
                                        <p style="text-align: center;">
                                            <a href="' . $resetUrl . '" class="button">Reimposta Password</a>
                                        </p>
                                        <p>Oppure copia e incolla il seguente link nel tuo browser:</p>
                                        <p>' . $resetUrl . '</p>
                                        <p>Se non hai richiesto il recupero della password, ignora questa email.</p>
                                        <p>Il link scadrà tra 24 ore.</p>
                                        <p>Grazie,<br>' . $siteName . ' Team</p>
                                    </div>
                                    <div class="footer">&copy; ' . date('Y') . ' ' . $siteName . '. Tutti i diritti riservati.
                                    </div>
                                </div>
                            </body>
                            </html>';
                            
                            $mail->AltBody = "Gentile utente,\n\n" .
                                           "Abbiamo ricevuto una richiesta di recupero password per il tuo account.\n\n" .
                                           "Per reimpostare la tua password, visita il seguente link:\n" .
                                           $resetUrl . "\n\n" .
                                           "Se non hai richiesto il recupero della password, ignora questa email.\n\n" .
                                           "Il link scadrà tra 24 ore.\n\n" .
                                           "Grazie,\n" . $siteName . " Team";
                            
                            $mail->send();
                            
                            // Log dell'invio email
                            $log_stmt = $conn->prepare("INSERT INTO system_logs (level, message, ip_address) VALUES (?, ?, ?)");
                            if ($log_stmt) {
                                $logMessage = "Email di recupero password inviata a $email";
                                $logLevel = "info";
                                $ipAddress = $_SERVER['REMOTE_ADDR'];
                                $log_stmt->bind_param("sss", $logLevel, $logMessage, $ipAddress);
                                $log_stmt->execute();
                                $log_stmt->close();
                            }
                            
                        } catch (Exception $e) {
                            error_log("Errore nell'invio email: " . $e->getMessage());
                        }
                    } else {
                        // PHPMailer non disponibile, registra l'errore nei log
                        error_log("PHPMailer non disponibile per l'invio dell'email di recupero password a: $email");
                    }
                    
                    // Mostriamo un messaggio di successo a prescindere per ragioni di sicurezza
                    $success_message = "Se l'indirizzo email è registrato nel nostro sistema, riceverai un'email con le istruzioni per recuperare la password.";
                    
                } catch (Exception $e) {
                    // Log dell'errore
                    error_log("Errore nel recupero password: " . $e->getMessage());
                    $error_message = "Si è verificato un errore durante l'invio dell'email. Riprova più tardi.";
                }
            }
        }
    }
}
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

    <title>Recupera Password | <?php echo htmlspecialchars($siteName); ?></title>

    <meta name="description" content="Recupera la tua password dimenticata" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../../../assets/img/favicon/favicon.ico" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&ampdisplay=swap"
      rel="stylesheet" />

    <link rel="stylesheet" href="../../../assets/vendor/fonts/iconify-icons.css" />

    <!-- Core CSS -->
    <!-- build:css assets/vendor/css/theme.css  -->
    <link rel="stylesheet" href="../../../assets/vendor/libs/node-waves/node-waves.css" />
    <link rel="stylesheet" href="../../../assets/vendor/libs/pickr/pickr-themes.css" />
    <link rel="stylesheet" href="../../../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../../../assets/css/demo.css" />
    <!-- Vendors CSS -->
    <link rel="stylesheet" href="../../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <!-- endbuild -->

    <!-- Page CSS -->
    <!-- Page -->
    <link rel="stylesheet" href="../../../assets/vendor/css/pages/page-auth.css" />

    <!-- Helpers -->
    <script src="../../../assets/vendor/js/helpers.js"></script>
    <!--! Template customizer&Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->
    <!--? Template customizer: To hide customizer set displayCustomizer value false in config.js.  -->
    <script src="../../../assets/vendor/js/template-customizer.js"></script>
    <!--? Config:  Mandatory theme config file contain global vars&default theme options, Set your preferred theme option in this file.  -->
    <script src="../../../assets/js/config.js"></script>
    
    <style>
      .text-primary {
        color: <?php echo $primaryColor; ?> !important;
      }
      .btn-primary {
        background-color: <?php echo $primaryColor; ?> !important;
        border-color: <?php echo $primaryColor; ?> !important;
      }
    </style>
  </head>

  <body>
    <!-- Content -->
    <div class="container-xxl">
      <div class="authentication-wrapper authentication-basic container-p-y">
        <div class="authentication-inner py-6">
          <!-- Forgot Password -->
          <div class="card">
            <div class="card-body">
              <!-- Logo -->
              <div class="app-brand justify-content-center mb-6">
                <a href="index.php" class="app-brand-link">
                  <span class="app-brand-logo demo">
                    <span class="text-primary">
                      <?php if (!empty($siteLogo)): ?>
                        <img src="<?php echo htmlspecialchars($siteLogo); ?>" alt="<?php echo htmlspecialchars($siteName); ?> Logo" height="32">
                      <?php else: ?>
                        <svg width="32" height="22" viewBox="0 0 32 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                          <path
                            fill-rule="evenodd"
                            clip-rule="evenodd"
                            d="M0.00172773 0V6.85398C0.00172773 6.85398 -0.133178 9.01207 1.98092 10.8388L13.6912 21.9964L19.7809 21.9181L18.8042 9.88248L16.4951 7.17289L9.23799 0H0.00172773Z"
                            fill="currentColor" />
                          <path
                            opacity="0.06"
                            fill-rule="evenodd"
                            clip-rule="evenodd"
                            d="M7.69824 16.4364L12.5199 3.23696L16.5541 7.25596L7.69824 16.4364Z"
                            fill="#161616" />
                          <path
                            opacity="0.06"
                            fill-rule="evenodd"
                            clip-rule="evenodd"
                            d="M8.07751 15.9175L13.9419 4.63989L16.5849 7.28475L8.07751 15.9175Z"
                            fill="#161616" />
                          <path
                            fill-rule="evenodd"
                            clip-rule="evenodd"
                            d="M7.77295 16.3566L23.6563 0H32V6.88383C32 6.88383 31.8262 9.17836 30.6591 10.4057L19.7824 22H13.6938L7.77295 16.3566Z"
                            fill="currentColor" />
                        </svg>
                      <?php endif; ?>
                    </span>
                  </span>
                  <span class="app-brand-text demo text-heading fw-bold"><?php echo htmlspecialchars($siteName); ?></span>
                </a>
              </div>
              <!-- /Logo -->
              <h4 class="mb-1">Password dimenticata? 🔒</h4>
              <p class="mb-6">Inserisci la tua email e ti invieremo le istruzioni per reimpostare la password</p>
              
              <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger mb-4" role="alert">
                  <?php echo $error_message; ?>
                </div>
              <?php endif; ?>
              
              <?php if (!empty($success_message)): ?>
                <div class="alert alert-success mb-4" role="alert">
                  <?php echo $success_message; ?>
                </div>
              <?php endif; ?>
              
              <form id="formAuthentication" class="mb-6" action="forgot-psw.php" method="POST">
                <div class="mb-6 form-control-validation">
                  <label for="email" class="form-label">Email</label>
                  <input
                    type="text"
                    class="form-control"
                    id="email"
                    name="email"
                    placeholder="Inserisci la tua email"
                    autofocus />
                </div>
                <button type="submit" class="btn btn-primary d-grid w-100">Invia Link di Reset</button>
              </form>
              <div class="text-center">
                <a href="login.php" class="d-flex justify-content-center">
                  <i class="icon-base ti tabler-chevron-left scaleX-n1-rtl me-1_5"></i>
                  Torna alla pagina di login
                </a>
              </div>
            </div>
          </div>
          <!-- /Forgot Password -->
        </div>
      </div>
    </div>
    <!-- / Content -->

    <!-- Core JS -->
    <!-- build:js assets/vendor/js/theme.js -->
    <script src="../../../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../../../assets/vendor/libs/popper/popper.js"></script>
    <script src="../../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../../assets/vendor/libs/node-waves/node-waves.js"></script>
    <script src="../../../assets/vendor/libs/pickr/pickr.js"></script>
    <script src="../../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../../../assets/vendor/libs/hammer/hammer.js"></script>
    <script src="../../../assets/vendor/libs/i18n/i18n.js"></script>
    <script src="../../../assets/vendor/js/menu.js"></script>
    <!-- endbuild -->

    <!-- Main JS -->
    <script src="../../../assets/js/main.js"></script>

    <!-- Page JS -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Form validation
      const form = document.getElementById('formAuthentication');
      if (form) {
        form.addEventListener('submit', function(e) {
          const emailInput = document.getElementById('email');
          if (!emailInput.value.trim()) {
            e.preventDefault();
            alert('Per favore, inserisci la tua email.');
          } else if (!isValidEmail(emailInput.value.trim())) {
            e.preventDefault();
            alert('Per favore, inserisci un indirizzo email valido.');
          }
        });
      }
      
      // Email validation function
      function isValidEmail(email) {
        const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        return re.test(email);
      }
    });
    </script>
  </body>
</html>