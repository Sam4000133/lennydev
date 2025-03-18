<?php
// Include il controllo dell'autenticazione
require_once 'check_auth.php';

// Abilita visualizzazione errori per debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
require_once 'db_connection.php';

// Ottieni i dati dell'utente corrente
$user_id = $_SESSION['user_id'] ?? 0;
$user = null;
$role = null;

if ($user_id) {
    $stmt = $conn->prepare("SELECT u.*, r.name as role_name FROM users u 
                           LEFT JOIN roles r ON u.role_id = r.id 
                           WHERE u.id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Ottieni i permessi
        $stmt = $conn->prepare("SELECT p.name FROM permissions p 
                               JOIN role_permissions rp ON p.id = rp.permission_id 
                               WHERE rp.role_id = ? AND rp.can_read = 1");
        $stmt->bind_param("i", $user['role_id']);
        $stmt->execute();
        $permissions_result = $stmt->get_result();
        $permissions = [];
        while ($row = $permissions_result->fetch_assoc()) {
            $permissions[] = $row['name'];
        }
        $user['permissions'] = $permissions;
    }
}

// Gestione dell'invio del form
$successMessage = '';
$errorMessage = '';

// Funzione per ridimensionare l'immagine a 100x100 pixel
function resizeImage($source_path, $destination_path, $max_width = 100, $max_height = 100, $quality = 80) {
    // Ottieni informazioni sull'immagine
    list($width, $height, $type) = getimagesize($source_path);
    
    // Imposta le dimensioni del resize mantenendo le proporzioni
    if ($width > $height) {
        $new_width = $max_width;
        $new_height = intval($height * $new_width / $width);
    } else {
        $new_height = $max_height;
        $new_width = intval($width * $new_height / $height);
    }
    
    // Crea l'immagine ridimensionata
    $thumb = imagecreatetruecolor($max_width, $max_height);
    
    // Sfondo trasparente per PNG
    imagealphablending($thumb, false);
    imagesavealpha($thumb, true);
    $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
    imagefilledrectangle($thumb, 0, 0, $max_width, $max_height, $transparent);
    
    // Crea l'immagine sorgente in base al tipo
    switch ($type) {
        case IMAGETYPE_JPEG:
            $source = imagecreatefromjpeg($source_path);
            break;
        case IMAGETYPE_PNG:
            $source = imagecreatefrompng($source_path);
            imagealphablending($source, true);
            imagesavealpha($source, true);
            break;
        case IMAGETYPE_GIF:
            $source = imagecreatefromgif($source_path);
            break;
        default:
            return false;
    }
    
    // Calcola la posizione per centrare l'immagine
    $offset_x = ($max_width - $new_width) / 2;
    $offset_y = ($max_height - $new_height) / 2;
    
    // Copia e ridimensiona l'immagine sorgente
    imagecopyresampled(
        $thumb, 
        $source, 
        $offset_x, $offset_y, 0, 0,
        $new_width, $new_height, 
        $width, $height
    );
    
    // Salva l'immagine ridimensionata
    switch ($type) {
        case IMAGETYPE_JPEG:
            imagejpeg($thumb, $destination_path, $quality);
            break;
        case IMAGETYPE_PNG:
            $png_quality = 9 - (int)round(($quality / 100) * 9); // Converte la qualità JPG (0-100) a PNG (0-9)
            imagepng($thumb, $destination_path, $png_quality);
            break;
        case IMAGETYPE_GIF:
            imagegif($thumb, $destination_path);
            break;
    }
    
    // Libera la memoria
    imagedestroy($source);
    imagedestroy($thumb);
    
    return true;
}

// Gestione reset avatar (rimuove l'avatar e usa quello predefinito)
if ($_SERVER['REQUEST_METHOD'] === 'POST'&&isset($_POST['reset_avatar'])) {
    $current_avatar = $user['avatar'] ?? '';
    
    // Elimina il file avatar esistente se non è l'avatar predefinito
    if (!empty($current_avatar)&&file_exists('../../../' . $current_avatar)) {
        // Verifica che il file sia nella cartella avatars
        if (strpos($current_avatar, 'assets/img/avatars/avatar_') !== false) {
            unlink('../../../' . $current_avatar);
        }
    }
    
    // Aggiorna il database impostando l'avatar a NULL
    $stmt = $conn->prepare("UPDATE users SET avatar = NULL, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        $successMessage = "Avatar reimpostato con successo!";
        // Aggiorna l'avatar nell'oggetto utente
        $user['avatar'] = null;
        // Aggiorna anche in sessione
        $_SESSION['avatar'] = null;
    } else {
        $errorMessage = "Errore durante il reset dell'avatar nel database.";
    }
}

// Gestione upload avatar
if ($_SERVER['REQUEST_METHOD'] === 'POST'&&isset($_FILES['avatar'])&&$_FILES['avatar']['error'] == 0) {
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $filename = $_FILES['avatar']['name'];
    $filetype = pathinfo($filename, PATHINFO_EXTENSION);
    
    if (in_array(strtolower($filetype), $allowed)) {
        // Crea la directory se non esiste
        $upload_dir = '../../../assets/img/avatars/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $new_filename = 'avatar_' . $user_id . '.' . $filetype;
        $temp_path = $_FILES['avatar']['tmp_name'];
        $upload_path = $upload_dir . $new_filename;
        
        // Rimuovi il file precedente se esiste
        if (!empty($user['avatar'])) {
            $current_avatar_path = '../../../' . $user['avatar'];
            if (file_exists($current_avatar_path)&&strpos($current_avatar_path, 'assets/img/avatars/avatar_') !== false) {
                unlink($current_avatar_path);
            }
        }
        
        // Ridimensiona e salva l'immagine
        if (resizeImage($temp_path, $upload_path, 100, 100)) {
            // Aggiorna il percorso dell'avatar nel database
            $avatar_path = 'assets/img/avatars/' . $new_filename;
            $stmt = $conn->prepare("UPDATE users SET avatar = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("si", $avatar_path, $user_id);
            
            if ($stmt->execute()) {
                $successMessage = "Avatar aggiornato con successo!";
                // Aggiorna l'avatar nell'oggetto utente
                $user['avatar'] = $avatar_path;
                // Aggiorna anche in sessione
                $_SESSION['avatar'] = $avatar_path;
            } else {
                $errorMessage = "Errore durante l'aggiornamento dell'avatar nel database.";
            }
        } else {
            $errorMessage = "Errore durante il ridimensionamento dell'immagine.";
        }
    } else {
        $errorMessage = "Il formato del file non è supportato. Utilizzare solo JPG, PNG o GIF.";
    }
}

// Gestione aggiornamento profilo
if ($_SERVER['REQUEST_METHOD'] === 'POST'&&isset($_POST['update_profile'])) {
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $email = $conn->real_escape_string($_POST['email']);
    
    // Verifica se l'email esiste già per altri utenti
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->bind_param("si", $email, $user_id);
    $stmt->execute();
    $email_check = $stmt->get_result();
    
    if ($email_check->num_rows > 0) {
        $errorMessage = "L'email è già in uso da un altro account.";
    } else {
        // Aggiorna il profilo
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("ssi", $full_name, $email, $user_id);
        
        if ($stmt->execute()) {
            $successMessage = "Profilo aggiornato con successo!";
            
            // Aggiorna i dati nella sessione
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;
            
            // Ricarica i dati utente
            $stmt = $conn->prepare("SELECT u.*, r.name as role_name FROM users u 
                                   LEFT JOIN roles r ON u.role_id = r.id 
                                   WHERE u.id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
            }
        } else {
            $errorMessage = "Errore durante l'aggiornamento del profilo: " . $conn->error;
        }
    }
}

// Ottieni statistiche di login
$login_stats = [];
if ($user_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as login_count, 
                           MAX(created_at) as last_login,
                           DATE_FORMAT(MIN(created_at), '%d/%m/%Y') as first_login 
                           FROM login_logs 
                           WHERE user_id = ? AND success = 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $login_stats = $stmt->get_result()->fetch_assoc();
}

// Determina il percorso dell'avatar
$avatar_url = '../../../' . ($user['avatar'] ?: 'assets/img/avatars/default.png');
?>
<!doctype html>
<html lang="en" class="layout-navbar-fixed layout-menu-fixed layout-compact" dir="ltr"
  data-skin="default" data-template="vertical-menu-template" data-bs-theme="light">
  
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Profilo Utente</title>
    <meta name="description" content="Gestione profilo utente" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../../../assets/img/favicon/favicon.ico" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&ampdisplay=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../../../assets/vendor/fonts/iconify-icons.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="../../../assets/vendor/libs/node-waves/node-waves.css" />
    <link rel="stylesheet" href="../../../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../../../assets/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="../../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="../../../assets/vendor/libs/sweetalert2/sweetalert2.css" />

    <!-- Helpers -->
    <script src="../../../assets/vendor/js/helpers.js"></script>
    <script src="../../../assets/js/config.js"></script>
</head>

<body>
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <!-- Menu -->
            <?php include 'sidebar.php'; ?>
            <!-- / Menu -->

            <!-- Layout container -->
            <div class="layout-page">
                <!-- Navbar -->
                <?php include 'navbar.php'; ?>
                <!-- / Navbar -->

                <!-- Content wrapper -->
                <div class="content-wrapper">
                    <!-- Content -->
                    <div class="container-xxl flex-grow-1 container-p-y">
                        <div class="row">
                            <div class="col-md-12">
                                <?php if($successMessage): ?>
                                <div class="alert alert-success alert-dismissible mb-4" role="alert">
                                    <?php echo $successMessage; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                <?php endif; ?>
                                
                                <?php if($errorMessage): ?>
                                <div class="alert alert-danger alert-dismissible mb-4" role="alert">
                                    <?php echo $errorMessage; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                <?php endif; ?>
                                
                                <div class="nav-align-top">
                                    <ul class="nav nav-pills flex-column flex-md-row mb-6 gap-md-0 gap-2">
                                        <li class="nav-item">
                                            <a class="nav-link active" href="#account-tab" data-bs-toggle="tab">
                                                <i class="icon-base ti tabler-users icon-sm me-1_5"></i> Account
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" href="#security-tab" data-bs-toggle="tab">
                                                <i class="icon-base ti tabler-lock icon-sm me-1_5"></i> Sicurezza
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" href="#activity-tab" data-bs-toggle="tab">
                                                <i class="icon-base ti tabler-history icon-sm me-1_5"></i> Attività
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" href="#permissions-tab" data-bs-toggle="tab">
                                                <i class="icon-base ti tabler-shield-check icon-sm me-1_5"></i> Permessi
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                                
                                <div class="tab-content">
                                    <!-- Tab Account -->
                                    <div class="tab-pane fade show active" id="account-tab">
                                        <div class="card mb-6">
                                            <div class="card-body">
                                                <div class="d-flex align-items-start align-items-sm-center gap-6">
                                                    <img src="<?php echo $avatar_url; ?>" 
                                                         alt="avatar utente" 
                                                         class="d-block w-px-100 h-px-100 rounded" 
                                                         id="uploadedAvatar" />
                                                    <div class="button-wrapper">
                                                        <form action="" method="post" enctype="multipart/form-data" id="avatar-form">
                                                            <label for="upload" class="btn btn-primary me-3 mb-4" tabindex="0">
                                                                <span class="d-none d-sm-block">Carica nuova foto</span>
                                                                <i class="icon-base ti tabler-upload d-block d-sm-none"></i>
                                                                <input type="file" id="upload" name="avatar" class="account-file-input" hidden accept="image/png, image/jpeg, image/gif" />
                                                            </label>
                                                            <button type="button" id="reset-avatar-btn" class="btn btn-label-secondary mb-4">
                                                                <i class="icon-base ti tabler-reset d-block d-sm-none"></i>
                                                                <span class="d-none d-sm-block">Reset</span>
                                                            </button>
                                                            <div>Formati consentiti: JPG, GIF o PNG.</div>
                                                            
                                                            <!-- Form nascosto per il reset dell'avatar -->
                                                            <input type="hidden" name="reset_avatar" id="reset_avatar_input" value="0">
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="card-body pt-2">
                                                <form id="formAccountSettings" method="POST" action="">
                                                    <div class="row gy-4 gx-6 mb-4">
                                                        <div class="col-md-6 form-control-validation">
                                                            <label for="full_name" class="form-label">Nome Completo</label>
                                                            <input class="form-control" type="text" id="full_name" name="full_name" 
                                                                value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" autofocus />
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="username" class="form-label">Username</label>
                                                            <input class="form-control" type="text" id="username" name="username" 
                                                                value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" 
                                                                readonly />
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="email" class="form-label">E-mail</label>
                                                            <input class="form-control" type="email" id="email" name="email" 
                                                                value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" />
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="role" class="form-label">Ruolo</label>
                                                            <input type="text" class="form-control" id="role" 
                                                                value="<?php echo htmlspecialchars($user['role_name'] ?? ''); ?>" 
                                                                readonly />
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="status" class="form-label">Status Account</label>
                                                            <input type="text" class="form-control" id="status" 
                                                                value="<?php echo htmlspecialchars($user['status'] ?? ''); ?>" 
                                                                readonly />
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="created_at" class="form-label">Data Registrazione</label>
                                                            <input type="text" class="form-control" id="created_at" 
                                                                value="<?php echo date('d/m/Y H:i', strtotime($user['created_at'] ?? 'now')); ?>" 
                                                                readonly />
                                                        </div>
                                                    </div>
                                                    <div class="mt-4">
                                                        <input type="hidden" name="update_profile" value="1">
                                                        <button type="submit" class="btn btn-primary me-3">Salva modifiche</button>
                                                        <button type="reset" class="btn btn-label-secondary">Annulla</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Tab Sicurezza -->
                                    <div class="tab-pane fade" id="security-tab">
                                        <div class="card mb-6">
                                            <h5 class="card-header">Modifica Password</h5>
                                            <div class="card-body">
                                                <div id="passwordUpdateAlert" class="alert" style="display: none;" role="alert"></div>
                                                
                                                <form id="formChangePassword">
                                                    <div class="row mb-4">
                                                        <div class="col-md-4 mb-3">
                                                            <label for="current_password" class="form-label">Password Attuale</label>
                                                            <div class="input-group input-group-merge">
                                                                <input type="password" class="form-control" id="current_password" 
                                                                    name="current_password" placeholder="••••••••" required />
                                                                <span class="input-group-text cursor-pointer"><i class="ti tabler-eye-off"></i></span>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4 mb-3">
                                                            <label for="new_password" class="form-label">Nuova Password</label>
                                                            <div class="input-group input-group-merge">
                                                                <input type="password" class="form-control" id="new_password" 
                                                                    name="new_password" placeholder="••••••••" 
                                                                    minlength="8" required />
                                                                <span class="input-group-text cursor-pointer"><i class="ti tabler-eye-off"></i></span>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4 mb-3">
                                                            <label for="confirm_password" class="form-label">Conferma Password</label>
                                                            <div class="input-group input-group-merge">
                                                                <input type="password" class="form-control" id="confirm_password" 
                                                                    name="confirm_password" placeholder="••••••••" 
                                                                    minlength="8" required />
                                                                <span class="input-group-text cursor-pointer"><i class="ti tabler-eye-off"></i></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-12">
                                                            <p class="mb-4">La password deve:</p>
                                                            <ul class="ps-3 mb-4">
                                                                <li class="mb-1">Essere lunga almeno 8 caratteri</li>
                                                                <li class="mb-1">Contenere almeno un numero</li>
                                                                <li class="mb-1">Contenere almeno una lettera maiuscola</li>
                                                                <li class="mb-1">Contenere almeno un carattere speciale</li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                    <button type="submit" class="btn btn-primary">Aggiorna Password</button>
                                                </form>
                                            </div>
                                        </div>
                                        
                                        <div class="card">
                                            <h5 class="card-header">Sessioni Recenti</h5>
                                            <div class="card-body">
                                                <div class="table-responsive">
                                                    <table class="table table-hover">
                                                        <thead>
                                                            <tr>
                                                                <th>Data/Ora</th>
                                                                <th>IP</th>
                                                                <th>Browser</th>
                                                                <th>Stato</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php
                                                            $stmt = $conn->prepare("SELECT * FROM login_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
                                                            $stmt->bind_param("i", $user_id);
                                                            $stmt->execute();
                                                            $sessions = $stmt->get_result();
                                                            
                                                            while ($session = $sessions->fetch_assoc()):
                                                                $browser = preg_match('/(?:Chrome|Firefox|Safari|Edge|MSIE|Trident|Opera)[\/\s](\d+\.\d+)/', $session['user_agent'], $matches) ? $matches[0] : 'Browser sconosciuto';
                                                            ?>
                                                            <tr>
                                                                <td><?php echo date('d/m/Y H:i', strtotime($session['created_at'])); ?></td>
                                                                <td><?php echo htmlspecialchars($session['ip_address']); ?></td>
                                                                <td><?php echo htmlspecialchars($browser); ?></td>
                                                                <td>
                                                                    <?php if($session['success']): ?>
                                                                        <span class="badge bg-label-success">Successo</span>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-label-danger">Fallito</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                            <?php endwhile; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Tab Attività -->
                                    <div class="tab-pane fade" id="activity-tab">
                                        <div class="card mb-6">
                                            <h5 class="card-header">Statistiche Account</h5>
                                            <div class="card-body">
                                                <div class="row g-4">
                                                    <div class="col-md-4">
                                                        <div class="card shadow-none bg-label-primary">
                                                            <div class="card-body">
                                                                <div class="d-flex align-items-center mb-2">
                                                                    <div class="badge bg-primary p-1 rounded"><i class="ti tabler-login icon-md"></i></div>
                                                                    <h5 class="ms-2 mb-0">Accessi Totali</h5>
                                                                </div>
                                                                <h2 class="mb-0"><?php echo $login_stats['login_count'] ?? 0; ?></h2>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="card shadow-none bg-label-success">
                                                            <div class="card-body">
                                                                <div class="d-flex align-items-center mb-2">
                                                                    <div class="badge bg-success p-1 rounded"><i class="ti tabler-calendar icon-md"></i></div>
                                                                    <h5 class="ms-2 mb-0">Primo Accesso</h5>
                                                                </div>
                                                                <h2 class="mb-0"><?php echo $login_stats['first_login'] ?? 'N/A'; ?></h2>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="card shadow-none bg-label-info">
                                                            <div class="card-body">
                                                                <div class="d-flex align-items-center mb-2">
                                                                    <div class="badge bg-info p-1 rounded"><i class="ti tabler-clock icon-md"></i></div>
                                                                    <h5 class="ms-2 mb-0">Ultimo Accesso</h5>
                                                                </div>
                                                                <h2 class="mb-0"><?php echo date('d/m H:i', strtotime($login_stats['last_login'] ?? 'now')); ?></h2>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="card">
                                            <h5 class="card-header">Cronologia Accessi</h5>
                                            <div class="card-body">
                                                <div class="table-responsive">
                                                    <table class="table table-hover">
                                                        <thead>
                                                            <tr>
                                                                <th>Data/Ora</th>
                                                                <th>IP</th>
                                                                <th>Dispositivo</th>
                                                                <th>Stato</th>
                                                                <th>Note</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php
                                                            $stmt = $conn->prepare("SELECT * FROM login_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
                                                            $stmt->bind_param("i", $user_id);
                                                            $stmt->execute();
                                                            $logs = $stmt->get_result();
                                                            
                                                            while ($log = $logs->fetch_assoc()):
                                                                // Determina il tipo di dispositivo
                                                                $device = 'Desktop';
                                                                if (strpos($log['user_agent'], 'Mobile') !== false) {
                                                                    $device = 'Mobile';
                                                                } else if (strpos($log['user_agent'], 'Tablet') !== false) {
                                                                    $device = 'Tablet';
                                                                }
                                                            ?>
                                                            <tr>
                                                                <td><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                                                                <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                                                <td><?php echo $device; ?></td>
                                                                <td>
                                                                    <?php if($log['success']): ?>
                                                                        <span class="badge bg-label-success">Successo</span>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-label-danger">Fallito</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td><?php echo htmlspecialchars($log['notes'] ?? 'N/A'); ?></td>
                                                            </tr>
                                                            <?php endwhile; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Tab Permessi -->
                                    <div class="tab-pane fade" id="permissions-tab">
                                        <div class="card">
                                            <h5 class="card-header">Permessi Utente</h5>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-12 mb-4">
                                                        <div class="alert alert-info" role="alert">
                                                            <div class="d-flex align-items-center">
                                                                <i class="ti tabler-info-circle me-2"></i>
                                                                <span>Questi sono i permessi associati al tuo ruolo: <strong><?php echo htmlspecialchars($user['role_name'] ?? ''); ?></strong></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if (!empty($user['permissions'])): ?>
                                                        <?php
                                                        // Raggruppa i permessi per categoria
                                                        $categories = [];
                                                        $stmt = $conn->prepare("SELECT name, category FROM permissions WHERE name IN (" . str_repeat('?,', count($user['permissions']) - 1) . "?)");
                                                        $stmt->bind_param(str_repeat('s', count($user['permissions'])), ...$user['permissions']);
                                                        $stmt->execute();
                                                        $result = $stmt->get_result();
                                                        
                                                        while ($row = $result->fetch_assoc()) {
                                                            $categories[$row['category']][] = $row['name'];
                                                        }
                                                        
                                                        foreach ($categories as $category => $perms):
                                                        ?>
                                                        <div class="col-md-6 mb-4">
                                                            <div class="card shadow-none border">
                                                                <div class="card-header bg-transparent">
                                                                    <h6 class="mb-0"><?php echo htmlspecialchars($category); ?></h6>
                                                                </div>
                                                                <div class="card-body pt-3">
                                                                    <ul class="list-group list-group-flush">
                                                                        <?php foreach ($perms as $perm): ?>
                                                                        <li class="list-group-item d-flex align-items-center">
                                                                            <i class="ti tabler-check text-success me-2"></i>
                                                                            <?php echo htmlspecialchars($perm); ?>
                                                                        </li>
                                                                        <?php endforeach; ?>
                                                                    </ul>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                    <div class="col-12">
                                                        <div class="alert alert-warning" role="alert">
                                                            <i class="ti tabler-alert-triangle me-2"></i>
                                                            Non hai permessi associati al tuo ruolo.
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- / Content -->

                    <!-- Footer -->
                    <footer class="content-footer footer bg-footer-theme">
                        <div class="container-xxl">
                            <div class="footer-container d-flex align-items-center justify-content-between py-4 flex-md-row flex-column">
                                <div class="text-body">
                                    © <script>document.write(new Date().getFullYear());</script>, made with ❤️ by 
                                    <a href="https://hydra-dev.xyz" target="_blank" class="footer-link">Hydra Dev</a>
                                </div>
                                <div class="d-none d-lg-inline-block"><a>Version 1.0.0 Alpha</a></div>
                            </div>
                        </div>
                    </footer>
                    <!-- / Footer -->
                </div>
                <!-- Content wrapper -->
            </div>
            <!-- / Layout page -->
        </div>

        <!-- Overlay -->
        <div class="layout-overlay layout-menu-toggle"></div>
        <!-- Drag Target Area To SlideIn Menu On Small Screens -->
        <div class="drag-target"></div>
    </div>
    <!-- / Layout wrapper -->

    <!-- Core JS -->
    <script src="../../../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../../../assets/vendor/libs/popper/popper.js"></script>
    <script src="../../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../../assets/vendor/libs/node-waves/node-waves.js"></script>
    <script src="../../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../../../assets/vendor/js/menu.js"></script>

    <!-- Vendors JS -->
    <script src="../../../assets/vendor/libs/sweetalert2/sweetalert2.js"></script>
    
    <!-- Main JS -->
    <script src="../../../assets/js/main.js"></script>

    <!-- Script personalizzato -->
    <script>
    $(document).ready(function() {
        'use strict';
        
        // Mostra/Nascondi password
        $('.input-group-text').on('click', function() {
            const inputGroup = $(this).closest('.input-group');
            const input = inputGroup.find('input');
            const icon = inputGroup.find('i');
            
            if (input.attr('type') === 'password') {
                input.attr('type', 'text');
                icon.removeClass('tabler-eye-off').addClass('tabler-eye');
            } else {
                input.attr('type', 'password');
                icon.removeClass('tabler-eye').addClass('tabler-eye-off');
            }
        });
        
        // Caricamento immagine avatar
        $('#upload').on('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    $('#uploadedAvatar').attr('src', e.target.result);
                }
                reader.readAsDataURL(file);
                
                // Verifica dimensione file
                const fileSize = Math.round((file.size / 1024));
                let sizeMessage = '';
                
                if (fileSize > 1024) {
                    sizeMessage = `\nDimensione file: ${(fileSize/1024).toFixed(2)} MB. L'immagine sarà ridimensionata.`;
                } else {
                    sizeMessage = `\nDimensione file: ${fileSize} KB. L'immagine sarà ridimensionata.`;
                }
                
                // Chiedi conferma prima di caricare
                Swal.fire({
                    title: 'Conferma caricamento',
                    text: 'Vuoi caricare questa immagine come nuovo avatar?' + sizeMessage,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sì, carica',
                    cancelButtonText: 'Annulla',
                    customClass: {
                        confirmButton: 'btn btn-primary',
                        cancelButton: 'btn btn-outline-danger ms-2'
                    },
                    buttonsStyling: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Invia il form dell'avatar
                        document.getElementById('avatar-form').submit();
                    } else {
                        // Reset dell'immagine caricata
                        resetAvatar();
                    }
                });
            }
        });
        
        // Reset immagine avatar
        function resetAvatar() {
            $('#uploadedAvatar').attr('src', '<?php echo $avatar_url; ?>');
            $('#upload').val('');
        }
        
        // Gestione pulsante reset avatar
        $('#reset-avatar-btn').on('click', function() {
            Swal.fire({
                title: 'Conferma reset',
                text: 'Vuoi davvero eliminare l\'avatar attuale e ripristinare quello predefinito?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sì, ripristina',
                cancelButtonText: 'Annulla',
                customClass: {
                    confirmButton: 'btn btn-danger',
                    cancelButton: 'btn btn-outline-secondary ms-2'
                },
                buttonsStyling: false
            }).then((result) => {
                if (result.isConfirmed) {
                    // Imposta il valore del campo nascosto e invia il form
                    $('#reset_avatar_input').val('1');
                    document.getElementById('avatar-form').submit();
                }
            });
        });
        
        // Gestione del form di cambio password con AJAX
        $('#formChangePassword').on('submit', function(e) {
            e.preventDefault();
            
            const currentPassword = $('#current_password').val();
            const newPassword = $('#new_password').val();
            const confirmPassword = $('#confirm_password').val();
            
            // Verifica client-side
            if (!currentPassword || !newPassword || !confirmPassword) {
                showPasswordAlert('danger', 'Tutti i campi sono obbligatori.');
                return;
            }
            
            if (newPassword !== confirmPassword) {
                showPasswordAlert('danger', 'La nuova password e la conferma non corrispondono.');
                return;
            }
            
            // Verifica requisiti password
            const passwordPattern = /^(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#$%^&*])(?=.{8,})/;
            if (!passwordPattern.test(newPassword)) {
                showPasswordAlert('warning', 'La password non rispetta i requisiti di sicurezza.');
                return;
            }
            
            // Mostra loader
            const submitBtn = $(this).find('button[type="submit"]');
            const originalBtnText = submitBtn.text();
            submitBtn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Aggiornamento...');
            submitBtn.prop('disabled', true);
            
            // Invia dati via AJAX
            $.ajax({
                url: 'update_password_simple.php',
                type: 'POST',
                data: {
                    current_password: currentPassword,
                    new_password: newPassword,
                    confirm_password: confirmPassword
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showPasswordAlert('success', response.message);
                        $('#formChangePassword')[0].reset();
                    } else {
                        showPasswordAlert('danger', response.message);
                    }
                    
                    // Ripristina pulsante
                    submitBtn.html(originalBtnText);
                    submitBtn.prop('disabled', false);
                },
                error: function(xhr, status, error) {
                    console.error("Errore AJAX:", xhr.responseText, status, error);
                    showPasswordAlert('danger', 'Si è verificato un errore durante la richiesta. Riprova più tardi.');
                    
                    // Ripristina pulsante
                    submitBtn.html(originalBtnText);
                    submitBtn.prop('disabled', false);
                }
            });
        });
        
        function showPasswordAlert(type, message) {
            const alert = $('#passwordUpdateAlert');
            alert.removeClass('alert-success alert-danger alert-warning')
                 .addClass('alert-' + type)
                 .html(message)
                 .show();
            
            // Scroll to the alert
            $('html, body').animate({
                scrollTop: alert.offset().top - 100
            }, 200);
            
            // Hide alert after 5 seconds if it's a success message
            if (type === 'success') {
                setTimeout(function() {
                    alert.fadeOut();
                }, 5000);
            }
        }
    });
    </script>
    
    <!-- Menu Accordion Script -->
    <script src="../../../assets/js/menu_accordion.js"></script>
</body>
</html>
<?php
$conn->close();
?>