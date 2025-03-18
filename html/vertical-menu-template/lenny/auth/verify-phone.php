<?php
// Inizia la sessione
session_start();

// Includi la connessione al database
require_once '../db_connection.php';

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

// Controlla se l'utente è in un processo di verifica Facebook
if (!isset($_SESSION['fb_need_phone_verification']) || !isset($_SESSION['fb_user_id'])) {
    // Reindirizza alla pagina di login se non è in corso una verifica
    header("Location: ../login.php");
    exit;
}
?>
<!doctype html>
<html
  lang="it"
  class="layout-wide customizer-hide"
  dir="ltr"
  data-skin="default"
  data-assets-path="../../../../assets/"
  data-template="vertical-menu-template">
  <head>
    <meta charset="utf-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>Verifica Telefono | <?php echo htmlspecialchars(getSetting($conn, 'site_name', 'Lenny')); ?></title>

    <meta name="description" content="Verifica del numero di telefono" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../../../../assets/img/favicon/favicon.ico" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
      rel="stylesheet" />

    <link rel="stylesheet" href="../../../../assets/vendor/fonts/iconify-icons.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="../../../../assets/vendor/libs/node-waves/node-waves.css" />
    <link rel="stylesheet" href="../../../../assets/vendor/libs/pickr/pickr-themes.css" />
    <link rel="stylesheet" href="../../../../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../../../../assets/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="../../../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <!-- Page CSS -->
    <link rel="stylesheet" href="../../../../assets/vendor/css/pages/page-auth.css" />

    <!-- Helpers -->
    <script src="../../../../assets/vendor/js/helpers.js"></script>
    <script src="../../../../assets/js/config.js"></script>
    
    <style>
      .phone-group {
        display: flex;
        align-items: center;
      }
      .phone-prefix {
        min-width: 60px;
      }
    </style>
  </head>

  <body>
    <!-- Content -->
    <div class="container-xxl">
      <div class="authentication-wrapper authentication-basic container-p-y">
        <div class="authentication-inner py-4">
          <!-- Verify Phone -->
          <div class="card">
            <div class="card-body">
              <!-- Logo -->
              <div class="app-brand justify-content-center mb-4">
                <a href="../index.php" class="app-brand-link">
                  <span class="app-brand-logo demo">
                    <div class="rounded-circle bg-primary p-2">
                      <i class="ti tabler-tools-kitchen-2 text-white"></i>
                    </div>
                  </span>
                  <span class="app-brand-text demo text-heading fw-bold"><?php echo strtoupper(htmlspecialchars(getSetting($conn, 'site_name', 'Lenny'))); ?></span>
                </a>
              </div>
              <!-- /Logo -->

              <h4 class="mb-2">Verifica il tuo numero di telefono 📱</h4>
              <p class="mb-4">Per completare la registrazione con Facebook, inserisci il tuo numero di telefono.</p>

              <?php if (isset($_SESSION['auth_error'])): ?>
                <div class="alert alert-danger d-flex align-items-center">
                  <i class="tabler-alert-circle me-2"></i>
                  <div><?php echo htmlspecialchars($_SESSION['auth_error']); ?></div>
                </div>
                <?php unset($_SESSION['auth_error']); ?>
              <?php endif; ?>

              <form id="formVerifyPhone" class="mb-3" method="POST" action="facebook.php">
                <input type="hidden" name="verify_phone_action" value="1">

                <div class="mb-3">
                  <label for="phone" class="form-label">Numero di telefono</label>
                  <div class="input-group">
                    <span class="input-group-text phone-prefix">+39</span>
                    <input
                      type="tel"
                      class="form-control"
                      id="phone"
                      name="phone"
                      placeholder="Inserisci il tuo numero di telefono"
                      autofocus 
                      required />
                  </div>
                  <div class="form-text">
                    Ti invieremo un codice SMS di verifica.
                  </div>
                </div>

                <button class="btn btn-primary d-grid w-100" type="submit">
                  <span class="d-flex align-items-center justify-content-center">
                    <i class="tabler-send me-2"></i>
                    Invia codice di verifica
                  </span>
                </button>
              </form>
            </div>
          </div>
          <!-- /Verify Phone -->
        </div>
      </div>
    </div>
    <!-- / Content -->

    <!-- Core JS -->
    <script src="../../../../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../../../../assets/vendor/libs/popper/popper.js"></script>
    <script src="../../../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../../../assets/vendor/libs/node-waves/node-waves.js"></script>
    <script src="../../../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../../../../assets/vendor/js/menu.js"></script>

    <!-- Main JS -->
    <script src="../../../../assets/js/main.js"></script>

    <!-- Page JS -->
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        // Validazione del numero di telefono
        const phoneInput = document.getElementById('phone');
        const form = document.getElementById('formVerifyPhone');
        
        if (form) {
          form.addEventListener('submit', function(event) {
            const phoneValue = phoneInput.value.trim();
            
            // Validazione base
            if (phoneValue === '') {
              event.preventDefault();
              alert('Inserisci un numero di telefono valido.');
              return;
            }
          });
        }
      });
    </script>
  </body>
</html>