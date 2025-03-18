<!doctype html>
<html
  lang="it"
  class="layout-wide customizer-hide"
  dir="ltr"
  data-skin="default"
  data-assets-path="../../../../assets/"
  data-template="vertical-menu-template"
  data-bs-theme="light">
  <head>
    <meta charset="utf-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>Verifica Numero di Telefono | Google Auth</title>

    <meta name="description" content="Verifica numero di telefono per l'autenticazione con Google" />

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
    <script src="../../../../assets/vendor/js/template-customizer.js"></script>
    <script src="../../../../assets/js/config.js"></script>
    
    <!-- Custom Styles -->
    <style>
      .verification-container {
        margin-top: 1.5rem;
        padding: 1.5rem;
        border: 1px solid #e9ecef;
        border-radius: 0.375rem;
        background-color: #f8f9fa;
      }
      
      .phone-input {
        font-size: 1.1rem;
        letter-spacing: 0.05rem;
      }
      
      .international-prefix {
        font-weight: 500;
      }
      
      .btn-primary {
        background-color: #5A8DEE;
        border-color: #5A8DEE;
      }
      
      .btn-primary:hover {
        background-color: #4a7cdd;
        border-color: #4a7cdd;
      }
      
      .info-icon {
        color: #5A8DEE;
        margin-right: 0.5rem;
      }
      
      .info-text {
        font-size: 0.875rem;
        color: #6c757d;
        margin-top: 0.5rem;
      }
    </style>
  </head>

  <body>
    <!-- Content -->
    <div class="container-xxl">
      <div class="authentication-wrapper authentication-basic container-p-y">
        <div class="authentication-inner py-4">
          <!-- Phone Verification Card -->
          <div class="card">
            <div class="card-body">
              <!-- Logo -->
              <div class="app-brand justify-content-center mb-4">
                <a href="index.php" class="app-brand-link">
                  <span class="app-brand-logo demo">
                    <div class="rounded-circle bg-primary p-2">
                      <i class="icon-base ti tabler-brand-google text-white"></i>
                    </div>
                  </span>
                  <span class="app-brand-text demo text-heading fw-bold">Autenticazione</span>
                </a>
              </div>
              <!-- /Logo -->
              
              <h4 class="mb-1">Verifica il tuo numero 📱</h4>
              <p class="mb-4">Per completare la registrazione con Google, inserisci il tuo numero di telefono</p>

              <?php if (isset($_SESSION['phone_error'])): ?>
                <div class="alert alert-danger d-flex align-items-center mb-3">
                  <i class="tabler-alert-circle me-2"></i>
                  <div><?= $_SESSION['phone_error'] ?></div>
                </div>
                <?php unset($_SESSION['phone_error']); ?>
              <?php endif; ?>

              <form action="google.php" method="POST">
                <div class="mb-3">
                  <label for="phone_number" class="form-label">Numero di Telefono</label>
                  <div class="input-group">
                    <span class="input-group-text"><i class="icon-base ti tabler-device-mobile"></i></span>
                    <input type="tel" class="form-control phone-input" id="phone_number" name="phone_number" placeholder="+39 XXXXXXXXXX" required>
                  </div>
                  <div class="d-flex align-items-start mt-1">
                    <i class="icon-base ti tabler-info-circle info-icon"></i>
                    <div class="info-text">
                      Inserisci il tuo numero di telefono completo di prefisso internazionale (es. +39 per l'Italia).<br>
                      Ti invieremo un codice di verifica via SMS per confermare la tua identità.
                    </div>
                  </div>
                </div>
                <div class="mb-3">
                  <button class="btn btn-primary d-grid w-100" type="submit">Invia Codice di Verifica</button>
                </div>
              </form>

              <p class="text-center mt-4">
                <a href="../login.php">
                  <i class="ti tabler-chevron-left me-1"></i> Torna al login
                </a>
              </p>
            </div>
          </div>
          <!-- Phone Verification Card -->
        </div>
      </div>
    </div>

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
      // Gestione del campo telefono per formattazione automatica
      const phoneInput = document.getElementById('phone_number');
      
      if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
          let value = e.target.value.replace(/\s+/g, '');
          
          // Assicurati che inizi con "+"
          if (!value.startsWith('+')&&value.length > 0) {
            value = '+' + value;
          }
          
          // Formatta il numero con spazi dopo il prefisso
          if (value.length > 3) {
            // Trova dove finisce il prefisso (dopo il +XX o +XXX)
            const prefixMatch = value.match(/^\+\d{1,4}/);
            if (prefixMatch) {
              const prefix = prefixMatch[0];
              const rest = value.substring(prefix.length);
              
              // Inserisci uno spazio dopo il prefisso
              value = prefix + ' ' + rest.replace(/(.{3})/g, '$1 ').trim();
            }
          }
          
          e.target.value = value;
        });
      }
    });
    </script>
  </body>
</html>