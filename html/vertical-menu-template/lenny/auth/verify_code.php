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

    <title>Verifica Codice | Google Auth</title>

    <meta name="description" content="Verifica codice SMS per l'autenticazione con Google" />

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
      
      .verification-code-input {
        font-size: 1.5rem;
        letter-spacing: 0.5rem;
        text-align: center;
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
      
      .phone-display {
        font-weight: 500;
        color: #212529;
      }
      
      .verification-icon {
        font-size: 3rem;
        color: #5A8DEE;
        margin-bottom: 1rem;
        display: block;
        text-align: center;
      }
      
      .resend-code {
        font-size: 0.875rem;
        color: #6c757d;
        text-decoration: none;
      }
      
      .resend-code:hover {
        text-decoration: underline;
        color: #5A8DEE;
      }
      
      .countdown {
        font-weight: 500;
        color: #5A8DEE;
      }
    </style>
  </head>

  <body>
    <!-- Content -->
    <div class="container-xxl">
      <div class="authentication-wrapper authentication-basic container-p-y">
        <div class="authentication-inner py-4">
          <!-- Code Verification Card -->
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
              
              <i class="icon-base ti tabler-messages verification-icon"></i>
              
              <h4 class="mb-1 text-center">Verifica Codice SMS</h4>
              <p class="text-center mb-4">
                Abbiamo inviato un codice di verifica al numero<br>
                <span class="phone-display"><?= htmlspecialchars($_SESSION['phone_verification']['phone']) ?></span>
              </p>

              <?php if (isset($_SESSION['verify_error'])): ?>
                <div class="alert alert-danger d-flex align-items-center mb-3">
                  <i class="tabler-alert-circle me-2"></i>
                  <div><?= $_SESSION['verify_error'] ?></div>
                </div>
                <?php unset($_SESSION['verify_error']); ?>
              <?php endif; ?>

              <form action="google.php" method="POST">
                <div class="mb-3">
                  <label for="verify_code" class="form-label">Codice di verifica</label>
                  <input 
                    type="text" 
                    class="form-control verification-code-input" 
                    id="verify_code" 
                    name="verify_code" 
                    placeholder="······" 
                    maxlength="6" 
                    autocomplete="one-time-code"
                    inputmode="numeric"
                    required
                  >
                  <div class="d-flex justify-content-between align-items-center mt-2">
                    <span class="text-muted small">Inserisci il codice a 6 cifre</span>
                    <span class="countdown" id="countdown">Scade in: 15:00</span>
                  </div>
                </div>
                
                <div class="mb-3">
                  <button class="btn btn-primary d-grid w-100" type="submit">Verifica Codice</button>
                </div>
              </form>

              <div class="text-center mt-4">
                <p class="mb-0">Non hai ricevuto il codice?</p>
                <a href="javascript:void(0);" class="resend-code" id="resendCodeBtn">
                  <i class="ti tabler-refresh me-1"></i> Invia nuovamente
                </a>
                <p class="mt-3">
                  <a href="?start=true">
                    <i class="ti tabler-chevron-left me-1"></i> Cambia numero di telefono
                  </a>
                </p>
              </div>
            </div>
          </div>
          <!-- Code Verification Card -->
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
      // Gestione input codice di verifica
      const codeInput = document.getElementById('verify_code');
      
      if (codeInput) {
        codeInput.addEventListener('input', function(e) {
          // Consenti solo numeri
          this.value = this.value.replace(/\D/g, '');
          
          // Limita a 6 caratteri
          if (this.value.length > 6) {
            this.value = this.value.substring(0, 6);
          }
          
          // Auto-submit quando sono stati inseriti 6 caratteri
          if (this.value.length === 6) {
            // Fai un piccolo ritardo per dar tempo all'utente di vedere cosa ha digitato
            setTimeout(() => {
              this.form.submit();
            }, 500);
          }
        });
        
        // Metti il focus sull'input al caricamento della pagina
        codeInput.focus();
      }
      
      // Gestione countdown
      const countdownEl = document.getElementById('countdown');
      let timeLeft = 15 * 60; // 15 minuti in secondi
      
      function updateCountdown() {
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        
        countdownEl.textContent = `Scade in: ${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        
        if (timeLeft <= 0) {
          clearInterval(countdownInterval);
          countdownEl.textContent = 'Codice scaduto';
          countdownEl.style.color = '#dc3545';
          
          // Abilita il pulsante per rinviare il codice
          document.getElementById('resendCodeBtn').classList.remove('disabled');
        }
        
        timeLeft--;
      }
      
      // Inizializza e avvia il countdown
      updateCountdown();
      const countdownInterval = setInterval(updateCountdown, 1000);
      
      // Gestione pulsante "Invia nuovamente"
      const resendBtn = document.getElementById('resendCodeBtn');
      if (resendBtn) {
        resendBtn.addEventListener('click', function(e) {
          e.preventDefault();
          
          // Disabilita temporaneamente il pulsante
          this.classList.add('disabled');
          this.innerHTML = '<i class="ti tabler-loader ti-spin me-1"></i> Invio in corso...';
          
          // Crea un form nascosto per inviare la richiesta di rinvio codice
          const form = document.createElement('form');
          form.method = 'POST';
          form.action = 'google.php';
          
          const resendInput = document.createElement('input');
          resendInput.type = 'hidden';
          resendInput.name = 'resend_code';
          resendInput.value = '1';
          
          const typeInput = document.createElement('input');
          typeInput.type = 'hidden';
          typeInput.name = 'verification_type';
          typeInput.value = 'phone';
          
          form.appendChild(resendInput);
          form.appendChild(typeInput);
          document.body.appendChild(form);
          
          // Invia il form dopo un breve ritardo
          setTimeout(() => {
            form.submit();
          }, 1000);
        });
      }
    });
    </script>
  </body>
</html>