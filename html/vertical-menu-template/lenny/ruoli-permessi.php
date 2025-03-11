<?php
// Include il controllo dell'autenticazione
require_once 'check_auth.php';

// Verifica l'accesso specifico a questa pagina
if (!userHasAccess('Ruoli&permessi')) {
    // Reindirizza alla pagina di accesso negato
    header("Location: access-denied.php");
    exit;
}

// Resto del codice della pagina...
// Abilita visualizzazione errori per debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
require_once 'db_connection.php';

// Check if db_setup.php has been run
$check_tables = $conn->query("SHOW TABLES LIKE 'roles'");
if ($check_tables->num_rows == 0) {
    require_once 'db_setup.php';
    echo "<script>console.log('Database setup completed successfully!');</script>";
}

// Carica i ruoli esistenti
$rolesQuery = "SELECT r.id, r.name, r.description, 
               (SELECT COUNT(*) FROM users WHERE role_id = r.id) as user_count,
               r.created_at
               FROM roles r 
               ORDER BY r.id ASC";
$roles = $conn->query($rolesQuery);
$rolesList = [];

if ($roles) {
    while ($role = $roles->fetch_assoc()) {
        $rolesList[] = $role;
    }
}

// NON chiudere la connessione qui!
?>
<!doctype html>
<html lang="en" class="layout-navbar-fixed layout-menu-fixed layout-compact" dir="ltr"
  data-skin="default" data-assets-path="../../../assets/" data-template="vertical-menu-template" data-bs-theme="light">
  
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Ruoli&permessi</title>
    <meta name="description" content="" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../../../assets/img/favicon/favicon.ico" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&ampdisplay=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../../../assets/vendor/fonts/iconify-icons.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="../../../assets/vendor/libs/node-waves/node-waves.css" />
    <link rel="stylesheet" href="../../../assets/vendor/libs/pickr/pickr-themes.css" />
    <link rel="stylesheet" href="../../../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../../../assets/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="../../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="../../../assets/vendor/libs/@form-validation/form-validation.css" />
    <!-- Aggiunti CSS per DataTables -->
    <link rel="stylesheet" href="../../../assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css" />
    <link rel="stylesheet" href="../../../assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css" />
    <link rel="stylesheet" href="../../../assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css" />
    <link rel="stylesheet" href="../../../assets/vendor/libs/sweetalert2/sweetalert2.css" />
    <link rel="stylesheet" href="../../../assets/vendor/libs/dropzone/dropzone.css" />

    <!-- Helpers -->
    <script src="../../../assets/vendor/js/helpers.js"></script>
    <script src="../../../assets/vendor/js/template-customizer.js"></script>
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
                        <h4 class="mb-1">Lista dei ruoli</h4>
                        <p class="mb-6">
                            Un ruolo definisce quali menu e funzioni specifiche sono disponibili a un utente. <br />
                            In base al ruolo assegnato, ogni utente potrà accedere solo alle parti del sistema di cui ha effettivamente bisogno.
                        </p>
                        
                        <!-- Dynamic Role Cards Container -->
                        <div class="row g-6">
                            <!-- Add new role card (Template for JavaScript) -->
                            <div class="col-xl-4 col-lg-6 col-md-6">
                                <div class="card h-100">
                                    <div class="row h-100">
                                        <div class="col-sm-5">
                                            <div class="d-flex align-items-end h-100 justify-content-center mt-sm-0 mt-4">
                                                <img src="../../../assets/img/illustrations/add-new-roles.png" 
                                                     class="img-fluid" alt="Image" width="83" />
                                            </div>
                                        </div>
                                        <div class="col-sm-7">
                                            <div class="card-body text-sm-end text-center ps-sm-0">
                                                <button
                                                    data-bs-target="#addRoleModal"
                                                    data-bs-toggle="modal"
                                                    class="btn btn-sm btn-primary mb-4 text-nowrap add-new-role w-100">
                                                    Aggiungi nuovo ruolo
                                                </button>
                                                <button
                                                    class="btn btn-sm btn-primary text-nowrap w-100"
                                                    id="toggleUsersListBtn">
                                                    Gestione Utenti
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Existing role cards - Mantenuto il layout originale ma con dati dinamici -->
                            <?php foreach($rolesList as $role): ?>
                            <div class="col-xl-4 col-lg-6 col-md-6">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between mb-2">
                                            <h5 class="card-title mb-0"><?php echo htmlspecialchars($role['name']); ?></h5>
                                            <div class="role-actions">
                                                <?php if ($role['id'] > 1): // Non permettere modifica per admin ?>
                                                <div class="btn-group">
                                                    <button class="btn btn-sm btn-icon btn-text-secondary rounded-pill edit-role" 
                                                        data-id="<?php echo $role['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($role['name']); ?>"
                                                        data-description="<?php echo htmlspecialchars($role['description'] ?? ''); ?>"
                                                        data-bs-toggle="tooltip" data-bs-placement="top" title="Modifica">
                                                        <i class="icon-base ti tabler-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-icon btn-text-secondary rounded-pill delete-role" 
                                                        data-id="<?php echo $role['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($role['name']); ?>"
                                                        data-bs-toggle="tooltip" data-bs-placement="top" title="Elimina"
                                                        <?php echo ($role['user_count'] > 0) ? 'disabled' : ''; ?>>
                                                        <i class="icon-base ti tabler-trash"></i>
                                                    </button>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($role['description'])): ?>
                                        <p class="card-text text-muted small mb-3"><?php echo htmlspecialchars($role['description']); ?></p>
                                        <?php endif; ?>
                                        
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted small">Utenti:</span>
                                            <span class="badge bg-label-primary"><?php echo $role['user_count']; ?></span>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-muted small">Creato:</span>
                                            <span class="text-muted small"><?php echo date('d/m/Y', strtotime($role['created_at'])); ?></span>
                                        </div>
                                        
                                        <div class="mt-3 pt-2 border-top">
                                            <button type="button" class="btn btn-outline-primary btn-sm w-100 view-permissions" 
                                                data-id="<?php echo $role['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($role['name']); ?>">
                                                <i class="ti tabler-key me-1"></i> Visualizza permessi
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <!--/ Role cards -->
                        
                        <!-- Sezione Utenti (inizialmente nascosta) -->
                        <div id="usersListSection" class="mt-5" style="display: none;">
                            <hr class="my-4">
                            <h4 class="mb-4">Gestione Utenti</h4>
                            
                            <!-- DataTable -->
                            <div class="card">
                                <div class="card-datatable table-responsive">
                                  <table class="datatables-users table border-top" id="users-table">
                                    <thead>
                                      <tr>
                                        <th></th>
                                        <th>Avatar</th>
                                        <th>Utente</th>
                                        <th>Email</th>
                                        <th>Ruolo</th>
                                        <th>Status</th>
                                        <th>Azioni</th>
                                      </tr>
                                    </thead>
                                    <tbody>
                                      <!-- I dati verranno caricati via AJAX -->
                                    </tbody>
                                  </table>
                                </div>
                            </div>
                            <!--/ DataTable -->
                        </div>
                        <!-- / Sezione Utenti -->

                        <!-- Add Role Modal -->
                        <div class="modal fade" id="addRoleModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-simple modal-dialog-centered modal-add-new-role">
                                <div class="modal-content">
                                    <div class="modal-body">
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        <div class="text-center mb-6">
                                            <h4 class="role-title">Aggiungi nuovo ruolo</h4>
                                            <p class="text-body-secondary">Imposta i permessi per il ruolo</p>
                                        </div>
                                        
                                        <!-- Add role form -->
                                        <form id="addRoleForm" class="row g-3">
                                            <input type="hidden" id="modalRoleId" name="modalRoleId" value="">
                                            <div class="col-12 form-control-validation mb-3">
                                                <label class="form-label" for="modalRoleName">Nome Ruolo</label>
                                                <input type="text" id="modalRoleName" name="modalRoleName"
                                                    class="form-control" placeholder="Inserisci un nome per il ruolo" required />
                                            </div>
                                            <div class="col-12 form-control-validation mb-3">
                                                <label class="form-label" for="modalRoleDescription">Descrizione (opzionale)</label>
                                                <textarea id="modalRoleDescription" name="modalRoleDescription"
                                                    class="form-control" placeholder="Descrizione del ruolo" rows="2"></textarea>
                                            </div>
                                            <div class="col-12">
                                                <h5 class="mb-6">Permessi del Ruolo</h5>
                                                
                                                <!-- Permission table -->
                                                <div class="table-responsive">
                                                    <table class="table table-flush-spacing">
                                                        <tbody>
                                                            <tr>
                                                                <td class="text-nowrap fw-medium">
                                                                    Accesso Amministratore
                                                                    <i class="icon-base ti tabler-info-circle icon-xs"
                                                                       data-bs-toggle="tooltip" data-bs-placement="top"
                                                                       title="Abilita l'accesso all'intero sistema"></i>
                                                                </td>
                                                                <td>
                                                                    <div class="d-flex justify-content-end">
                                                                        <div class="form-check mb-0">
                                                                            <input class="form-check-input" type="checkbox" id="selectAll" />
                                                                            <label class="form-check-label" for="selectAll"> Seleziona Tutti </label>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                            <?php
                                                            $permissionsQuery = "SELECT DISTINCT category FROM permissions ORDER BY category";
                                                            $categories = $conn->query($permissionsQuery);
                                                            if ($categories) {
                                                                while ($category = $categories->fetch_assoc()) {
                                                                    $categoryName = $category['category'];
                                                                    $categoryId = preg_replace('/[^a-z0-9]/', '', strtolower($categoryName));
                                                                    echo '
                                                                    <tr>
                                                                        <td class="text-nowrap fw-medium text-heading">' . htmlspecialchars($categoryName) . '</td>
                                                                        <td>
                                                                            <div class="d-flex justify-content-end">
                                                                                <div class="form-check mb-0 me-4 me-lg-12">
                                                                                    <input class="form-check-input permission-checkbox" type="checkbox" 
                                                                                        data-category="' . htmlspecialchars($categoryName) . '"
                                                                                        data-type="read"
                                                                                        id="' . $categoryId . 'Read" />
                                                                                    <label class="form-check-label" for="' . $categoryId . 'Read"> Lettura </label>
                                                                                </div>
                                                                                <div class="form-check mb-0 me-4 me-lg-12">
                                                                                    <input class="form-check-input permission-checkbox" type="checkbox"
                                                                                        data-category="' . htmlspecialchars($categoryName) . '"
                                                                                        data-type="write"
                                                                                        id="' . $categoryId . 'Write" />
                                                                                    <label class="form-check-label" for="' . $categoryId . 'Write"> Scrittura </label>
                                                                                </div>
                                                                                <div class="form-check mb-0">
                                                                                    <input class="form-check-input permission-checkbox" type="checkbox"
                                                                                        data-category="' . htmlspecialchars($categoryName) . '"
                                                                                        data-type="create"
                                                                                        id="' . $categoryId . 'Create" />
                                                                                    <label class="form-check-label" for="' . $categoryId . 'Create"> Creazione </label>
                                                                                </div>
                                                                            </div>
                                                                        </td>
                                                                    </tr>';
                                                                }
                                                            }
                                                            ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <!--/ Permission table -->
                                            </div>
                                            <div class="col-12 text-center">
                                                <button type="submit" class="btn btn-primary me-sm-4 me-1">Salva</button>
                                                <button type="reset" class="btn btn-label-secondary"
                                                    data-bs-dismiss="modal" aria-label="Close">
                                                    Annulla
                                                </button>
                                            </div>
                                        </form>
                                        <!--/ Add role form -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!--/ Add Role Modal -->
                        
                        <!-- Modal per visualizzare i permessi -->
                        <div class="modal fade" id="viewPermissionsModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="permissionRoleName">Permessi del ruolo</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="table-responsive">
                                            <table class="table table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th>Permesso</th>
                                                        <th class="text-center">Lettura</th>
                                                        <th class="text-center">Scrittura</th>
                                                        <th class="text-center">Creazione</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="permissionsTableBody">
                                                    <!-- I permessi verranno caricati via AJAX -->
                                                </tbody>
                                            </table>
                                        </div>
                                        <div id="noPermissionsMessage" class="text-center p-4" style="display: none;">
                                            <i class="ti tabler-alert-circle text-warning" style="font-size: 3rem;"></i>
                                            <p class="mt-3">Nessun permesso assegnato a questo ruolo.</p>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Chiudi</button>
                                        <button type="button" class="btn btn-primary edit-permissions-btn">Modifica</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!--/ Modal per visualizzare i permessi -->
                        
                        <!-- Modal per Aggiungere/Modificare Utente -->
                        <div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
                          <div class="modal-dialog modal-lg modal-simple modal-dialog-centered">
                            <div class="modal-content">
                              <div class="modal-header bg-body px-3 border-bottom">
                                <h5 class="modal-title" id="userModalTitle">Aggiungi nuovo utente</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                              </div>
                              <div class="modal-body px-sm-4 px-3">
                                <div class="text-center mb-4">
                                  <p class="text-body-secondary" id="userModalSubtitle">Inserisci i dati per il nuovo utente</p>
                                </div>
                                <!-- Form utente -->
                                <form id="userForm" class="row g-3">
                                  <input type="hidden" id="userId" name="userId" value="">
                                  
                                  <div class="col-12 mb-4">
                                    <label class="form-label" for="avatar">Avatar utente</label>
                                    <div class="d-flex align-items-center">
                                      <div class="me-3">
                                        <div class="avatar avatar-xl">
                                          <img id="avatarPreview" src="../../../assets/img/avatars/1.png" alt="Avatar" class="rounded-circle">
                                        </div>
                                      </div>
                                      <div class="d-flex flex-column">
                                        <div class="input-group">
                                          <input type="file" id="avatarUpload" class="form-control" accept="image/*" />
                                          <input type="hidden" id="avatarValue" name="avatar" />
                                        </div>
                                        <div class="form-text mt-2">
                                          <button type="button" id="removeAvatar" class="btn btn-sm btn-label-danger">
                                            <i class="icon-base ti tabler-trash me-1"></i>Rimuovi avatar
                                          </button>
                                        </div>
                                        <small class="text-muted">Immagine consigliata: JPG, PNG. Dimensione massima: 2MB</small>
                                      </div>
                                    </div>
                                  </div>
                                  
                                  <div class="col-12 col-md-6">
                                    <label class="form-label" for="username">Username <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                      <span class="input-group-text"><i class="icon-base ti tabler-at"></i></span>
                                      <input
                                        type="text"
                                        id="username"
                                        name="username"
                                        class="form-control"
                                        placeholder="johndoe"
                                        required />
                                    </div>
                                  </div>
                                  
                                  <div class="col-12 col-md-6">
                                    <label class="form-label" for="email">Email <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                      <span class="input-group-text"><i class="icon-base ti tabler-mail"></i></span>
                                      <input
                                        type="email"
                                        id="email"
                                        name="email"
                                        class="form-control"
                                        placeholder="example@domain.com"
                                        required />
                                    </div>
                                  </div>
                                  
                                  <div class="col-12 col-md-6">
                                    <label class="form-label" for="fullName">Nome Completo</label>
                                    <div class="input-group">
                                      <span class="input-group-text"><i class="icon-base ti tabler-user"></i></span>
                                      <input
                                        type="text"
                                        id="fullName"
                                        name="fullName"
                                        class="form-control"
                                        placeholder="John Doe" />
                                    </div>
                                  </div>
                                  
                                  <div class="col-12 col-md-6">
                                    <label class="form-label" for="roleId">Ruolo</label>
                                    <div class="input-group">
                                      <span class="input-group-text"><i class="icon-base ti tabler-shield"></i></span>
                                      <select id="roleId" name="roleId" class="form-select">
                                        <option value="">Seleziona un ruolo</option>
                                        <?php
                                        // Add role options for select
                                        $rolesQuery = "SELECT id, name FROM roles ORDER BY name";
                                        $roles = $conn->query($rolesQuery);
                                        
                                        if ($roles) {
                                            while ($role = $roles->fetch_assoc()) {
                                                echo '<option value="' . $role['id'] . '">' . htmlspecialchars($role['name']) . '</option>';
                                            }
                                        }
                                        ?>
                                      </select>
                                    </div>
                                  </div>
                                  
                                  <div class="col-12 col-md-6">
                                    <label class="form-label" for="password">Password <span class="text-danger password-required">*</span></label>
                                    <div class="input-group input-group-merge">
                                      <span class="input-group-text"><i class="icon-base ti tabler-lock"></i></span>
                                      <input
                                        type="password"
                                        id="password"
                                        name="password"
                                        class="form-control"
                                        placeholder="········" />
                                      <span class="input-group-text cursor-pointer toggle-password">
                                        <i class="icon-base ti tabler-eye-off"></i>
                                      </span>
                                    </div>
                                    <small class="form-text text-muted password-hint" style="display: none;">Lascia vuoto per mantenere la password attuale (solo per modifiche).</small>
                                  </div>
                                  
                                  <div class="col-12 col-md-6">
                                    <label class="form-label" for="status">Stato</label>
                                    <div class="input-group">
                                      <span class="input-group-text"><i class="icon-base ti tabler-circle-check"></i></span>
                                      <select id="status" name="status" class="form-select">
                                        <option value="active">Attivo</option>
                                        <option value="inactive">Inattivo</option>
                                        <option value="suspended">Sospeso</option>
                                      </select>
                                    </div>
                                  </div>
                                  
                                  <div class="col-12 text-center mt-4">
                                    <button type="submit" class="btn btn-primary me-sm-3 me-1">
                                      <i class="icon-base ti tabler-device-floppy me-1"></i>
                                      <span>Salva</span>
                                    </button>
                                    <button
                                      type="reset"
                                      class="btn btn-label-secondary"
                                      data-bs-dismiss="modal"
                                      aria-label="Close">
                                      <i class="icon-base ti tabler-x me-1"></i>
                                      <span>Annulla</span>
                                    </button>
                                  </div>
                                </form>
                                <!--/ Form utente -->
                              </div>
                            </div>
                          </div>
                        </div>
                        <!--/ Modal per Aggiungere/Modificare Utente -->
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
    <script src="../../../assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js"></script>
    <script src="../../../assets/vendor/libs/select2/select2.js"></script>
    <script src="../../../assets/vendor/libs/@form-validation/popular.js"></script>
    <script src="../../../assets/vendor/libs/@form-validation/bootstrap5.js"></script>
    <script src="../../../assets/vendor/libs/@form-validation/auto-focus.js"></script>
    <script src="../../../assets/vendor/libs/sweetalert2/sweetalert2.js"></script>
    <script src="../../../assets/vendor/libs/dropzone/dropzone.js"></script>

    <!-- Main JS -->
    <script src="../../../assets/js/main.js"></script>

    <!-- Roles Manager JS -->
    <script src="roles_manager.js"></script>

    <!-- Script per la gestione della DataTable e utenti -->
    <script>
    $(document).ready(function() {
      'use strict';
      
      // Verifica permessi
      const canRead = <?php echo hasPermission('Ruoli&permessi', 'read') ? 'true' : 'false'; ?>;
      const canWrite = <?php echo hasPermission('Ruoli&permessi', 'write') ? 'true' : 'false'; ?>;
      const canCreate = <?php echo hasPermission('Ruoli&permessi', 'create') ? 'true' : 'false'; ?>;
      
      // Toggle per mostrare/nascondere la sezione utenti
      $('#toggleUsersListBtn').on('click', function() {
        const usersSection = $('#usersListSection');
        
        if (usersSection.is(':visible')) {
          usersSection.slideUp();
          $(this).text('Gestione Utenti');
        } else {
          usersSection.slideDown();
          $(this).text('Nascondi Utenti');
          
          // Inizializza la tabella solo alla prima apertura
          if (!$.fn.DataTable.isDataTable('#users-table')) {
            initializeUsersTable();
          }
        }
      });
      
      // Gestione dei ruoli
      $(document).ready(function() {
        // Gestione "Seleziona tutti" per i permessi
        $('#selectAll').on('change', function() {
          $('.permission-checkbox').prop('checked', $(this).prop('checked'));
        });
        
        // Aggiorna "Seleziona tutti" quando cambiano i checkbox individuali
        $(document).on('change', '.permission-checkbox', function() {
          const allChecked = $('.permission-checkbox:not(:checked)').length === 0;
          $('#selectAll').prop('checked', allChecked);
        });
        
        // Visualizza permessi di un ruolo
        $('.view-permissions').on('click', function() {
          const roleId = $(this).data('id');
          const roleName = $(this).data('name');
          
          // Imposta il titolo del modal
          $('#permissionRoleName').text(`Permessi del ruolo: ${roleName}`);
          $('.edit-permissions-btn').data('id', roleId);
          
          // Carica i permessi via AJAX
          $.ajax({
            url: 'roles_api.php',
            type: 'GET',
            data: {action: 'getPermissions', roleId: roleId},
            dataType: 'json',
            success: function(response) {
              if (response.success) {
                const permissions = response.data;
                
                if (permissions.length === 0) {
                  $('#permissionsTableBody').hide();
                  $('#noPermissionsMessage').show();
                } else {
                  $('#permissionsTableBody').empty().show();
                  $('#noPermissionsMessage').hide();
                  
                  permissions.forEach(function(perm) {
                    const row = `
                    <tr>
                      <td>${perm.name}</td>
                      <td class="text-center">${perm.can_read ? '<i class="ti tabler-check text-success"></i>' : '<i class="ti tabler-x text-danger"></i>'}</td>
                      <td class="text-center">${perm.can_write ? '<i class="ti tabler-check text-success"></i>' : '<i class="ti tabler-x text-danger"></i>'}</td>
                      <td class="text-center">${perm.can_create ? '<i class="ti tabler-check text-success"></i>' : '<i class="ti tabler-x text-danger"></i>'}</td>
                    </tr>`;
                    $('#permissionsTableBody').append(row);
                  });
                }
                
                const modal = new bootstrap.Modal($('#viewPermissionsModal'));
                modal.show();
              } else {
                Swal.fire({
                  title: 'Errore',
                  text: 'Impossibile caricare i permessi',
                  icon: 'error'
                });
              }
            },
            error: function() {
              Swal.fire({
                title: 'Errore',
                text: 'Errore di comunicazione con il server',
                icon: 'error'
              });
            }
          });
        });
        
        // Gestione form di aggiunta/modifica ruolo
        $('#addRoleForm').on('submit', function(e) {
          e.preventDefault();
          
          if (!canWrite&&!canCreate) {
            Swal.fire({
              title: 'Permesso negato',
              text: 'Non hai i permessi per modificare o creare ruoli',
              icon: 'warning'
            });
            return;
          }
          
          const roleId = $('#modalRoleId').val();
          const roleName = $('#modalRoleName').val().trim();
          const roleDescription = $('#modalRoleDescription').val().trim();
          
          if (!roleName) {
            Swal.fire({
              title: 'Errore',
              text: 'Il nome del ruolo è obbligatorio',
              icon: 'error'
            });
            return;
          }
          
          // Raccogli i permessi selezionati
          const permissions = [];
          $('.permission-checkbox:checked').each(function() {
            const category = $(this).data('category');
            const type = $(this).data('type');
            
            let permission = permissions.find(p => p.category === category);
            if (!permission) {
              permission = {category: category, can_read: 0, can_write: 0, can_create: 0};
              permissions.push(permission);
            }
            
            permission[`can_${type}`] = 1;
          });
          
          // Prepara i dati da inviare
          const formData = {
            action: roleId ? 'updateRole' : 'addRole',
            role: {
              id: roleId,
              name: roleName,
              description: roleDescription
            },
            permissions: permissions
          };
          
          // Mostra spinner durante il salvataggio
          const submitBtn = $(this).find('button[type="submit"]');
          const originalBtnText = submitBtn.html();
          submitBtn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Salvataggio...');
          submitBtn.prop('disabled', true);
          
          // Invia i dati
          $.ajax({
            url: 'roles_api.php',
            type: 'POST',
            data: JSON.stringify(formData),
            contentType: 'application/json',
            dataType: 'json',
            success: function(response) {
              // Ripristina il pulsante
              submitBtn.html(originalBtnText);
              submitBtn.prop('disabled', false);
              
              if (response.success) {
                // Chiudi il modal
                const modal = bootstrap.Modal.getInstance($('#addRoleModal'));
                modal.hide();
                
                // Mostra messaggio di successo
                Swal.fire({
                  title: 'Operazione completata',
                  text: roleId ? 'Ruolo aggiornato con successo' : 'Nuovo ruolo creato con successo',
                  icon: 'success'
                }).then(() => {
                  location.reload();
                });
              } else {
                Swal.fire({
                  title: 'Errore',
                  text: response.message || 'Si è verificato un errore durante l\'operazione',
                  icon: 'error'
                });
              }
            },
            error: function() {
              // Ripristina il pulsante
              submitBtn.html(originalBtnText);
              submitBtn.prop('disabled', false);
              
              Swal.fire({
                title: 'Errore',
                text: 'Errore di comunicazione con il server',
                icon: 'error'
              });
            }
          });
        });
        
        // Visualizza modal di modifica ruolo
        $('.edit-role').on('click', function() {
          if (!canWrite) {
            Swal.fire({
              title: 'Permesso negato',
              text: 'Non hai i permessi per modificare ruoli',
              icon: 'warning'
            });
            return;
          }
          
          const roleId = $(this).data('id');
          const roleName = $(this).data('name');
          const roleDescription = $(this).data('description') || '';
          
          // Popola il form
          $('#modalRoleId').val(roleId);
          $('#modalRoleName').val(roleName);
          $('#modalRoleDescription').val(roleDescription);
          $('#addRoleModal .role-title').text('Modifica ruolo');
          
          // Carica i permessi del ruolo
          $.ajax({
            url: 'roles_api.php',
            type: 'GET',
            data: {action: 'getPermissions', roleId: roleId},
            dataType: 'json',
            success: function(response) {
              if (response.success) {
                // Reset tutti i checkbox
                $('.permission-checkbox').prop('checked', false);
                
                // Imposta i checkbox in base ai permessi
                const permissions = response.data;
                permissions.forEach(function(perm) {
                  const category = perm.category;
                  const categoryId = category.toLowerCase().replace(/[^a-z0-9]/g, '');
                  
                  if (perm.can_read) {
                    $(`#${categoryId}Read`).prop('checked', true);
                  }
                  if (perm.can_write) {
                    $(`#${categoryId}Write`).prop('checked', true);
                  }
                  if (perm.can_create) {
                    $(`#${categoryId}Create`).prop('checked', true);
                  }
                });
                
                // Verifica se tutti i checkbox sono selezionati
                const allChecked = $('.permission-checkbox:not(:checked)').length === 0;
                $('#selectAll').prop('checked', allChecked);
                
                // Apri il modal
                const modal = new bootstrap.Modal($('#addRoleModal'));
                modal.show();
              } else {
                Swal.fire({
                  title: 'Errore',
                  text: 'Impossibile caricare i permessi del ruolo',
                  icon: 'error'
                });
              }
            }
          });
        });
        
        // Gestione eliminazione ruolo
        $('.delete-role').on('click', function() {
          if (!canWrite) {
            Swal.fire({
              title: 'Permesso negato',
              text: 'Non hai i permessi per eliminare ruoli',
              icon: 'warning'
            });
            return;
          }
          
          const roleId = $(this).data('id');
          const roleName = $(this).data('name');
          
          Swal.fire({
            title: 'Sei sicuro?',
            text: `Stai per eliminare il ruolo "${roleName}". Questa azione non può essere annullata!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sì, elimina!',
            cancelButtonText: 'Annulla'
          }).then((result) => {
            if (result.isConfirmed) {
              $.ajax({
                url: 'roles_api.php',
                type: 'POST',
                data: JSON.stringify({
                  action: 'deleteRole',
                  roleId: roleId
                }),
                contentType: 'application/json',
                dataType: 'json',
                success: function(response) {
                  if (response.success) {
                    Swal.fire({
                      title: 'Eliminato!',
                      text: 'Il ruolo è stato eliminato con successo',
                      icon: 'success'
                    }).then(() => {
                      location.reload();
                    });
                  } else {
                    Swal.fire({
                      title: 'Errore',
                      text: response.message || 'Impossibile eliminare il ruolo',
                      icon: 'error'
                    });
                  }
                },
                error: function() {
                  Swal.fire({
                    title: 'Errore',
                    text: 'Errore di comunicazione con il server',
                    icon: 'error'
                  });
                }
              });
            }
          });
        });
        
        // Click sul pulsante di modifica permessi
        $('.edit-permissions-btn').on('click', function() {
          if (!canWrite) {
            Swal.fire({
              title: 'Permesso negato',
              text: 'Non hai i permessi per modificare i permessi',
              icon: 'warning'
            });
            return;
          }
          
          const roleId = $(this).data('id');
          const viewModal = bootstrap.Modal.getInstance($('#viewPermissionsModal'));
          viewModal.hide();
          
          // Clicca sul pulsante di modifica ruolo corrispondente
          $(`.edit-role[data-id="${roleId}"]`).click();
        });
      });
      
      // Funzione per inizializzare la DataTable degli utenti
      function initializeUsersTable() {
        // Inizializzazione variabili
        let usersTable;
        let userForm = document.getElementById('userForm');
        let userModal = document.getElementById('userModal');
        let passwordHint = document.querySelector('.password-hint');
        let passwordRequired = document.querySelector('.password-required');
        let isNewUser = true;
        
        // Inizializzazione DataTable
        usersTable = $('#users-table').DataTable({
          ajax: {
            url: 'users_api.php',
            dataSrc: 'data'
          },
          columns: [
            { data: '', defaultContent: '', className: 'control' },
            { data: 'avatar', 
              render: function(data, type, row) {
                const avatarId = (row.id % 14) + 1;
                return `<div class="avatar avatar-sm">
                          <img src="${data || '../../../assets/img/avatars/' + avatarId + '.png'}" alt="Avatar" class="rounded-circle">
                        </div>`;
              } 
            },
            { data: 'username', 
              render: function(data, type, row) {
                return `<div class="d-flex justify-content-start align-items-center user-name">
                          <div class="d-flex flex-column">
                            <span class="fw-medium">${data}</span>
                          </div>
                        </div>`;
              } 
            },
            { data: 'email' },
            { data: 'role_name', defaultContent: '<span class="text-muted">Non assegnato</span>' },
            { data: 'status',
              render: function(data) {
                const statusClass = {
                  'active': 'bg-label-success',
                  'inactive': 'bg-label-secondary',
                  'suspended': 'bg-label-warning'
                };
                
                return `<span class="badge ${statusClass[data] || 'bg-label-primary'}">${data}</span>`;
              }
            },
            { 
              data: null,
              render: function (data, type, row) {
                let buttons = `<div class="d-flex">`;
                
                if (canWrite) {
                  buttons += `
                    <button class="btn btn-sm btn-icon btn-text-secondary rounded-pill edit-user me-1" 
                       data-id="${row.id}" data-bs-toggle="tooltip" data-bs-placement="top" title="Modifica">
                      <i class="icon-base ti tabler-edit"></i>
                    </button>`;
                }
                
                buttons += `
                  <button class="btn btn-sm btn-icon btn-text-secondary rounded-pill view-user me-1" 
                     data-id="${row.id}" data-bs-toggle="tooltip" data-bs-placement="top" title="Visualizza">
                    <i class="icon-base ti tabler-eye"></i>
                  </button>`;
                
                if (canWrite) {
                  buttons += `
                    <button class="btn btn-sm btn-icon btn-text-secondary rounded-pill delete-user" 
                       data-id="${row.id}" data-bs-toggle="tooltip" data-bs-placement="top" title="Elimina">
                      <i class="icon-base ti tabler-trash"></i>
                    </button>`;
                }
                
                buttons += `</div>`;
                return buttons;
              }
            }
          ],
          dom: '<"card-header d-flex flex-wrap py-3"<"d-flex align-items-center me-3"l><"me-5"f><"dt-action-buttons text-end ms-auto"B>><"table-responsive"t><"card-footer d-flex align-items-center"<"m-0"i><"pagination justify-content-end"p>>',
          lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Tutti"]],
          buttons: [
            {
              text: '<i class="icon-base ti tabler-plus me-1"></i><span>Aggiungi nuovo utente</span>',
              className: 'btn btn-primary',
              attr: {
                'data-bs-toggle': 'modal',
                'data-bs-target': '#userModal'
              },
              action: function() {
                if (!canCreate) {
                  Swal.fire({
                    title: 'Permesso negato',
                    text: 'Non hai i permessi per creare nuovi utenti',
                    icon: 'warning'
                  });
                  return;
                }
                resetUserForm();
              }
            }
          ],
          responsive: {
            details: {
              display: $.fn.dataTable.Responsive.display.modal({
                header: function(row) {
                  const data = row.data();
                  return 'Dettagli per ' + (data.full_name || data.username);
                }
              }),
              type: 'column',
              renderer: function(api, rowIdx, columns) {
                const data = $.map(columns, function(col, i) {
                  return col.title !== ''&&col.hidden
                    ? '<tr data-dt-row="' + col.rowIndex + '" data-dt-column="' + col.columnIndex + '">' +
                        '<td>' + col.title + ':' + '</td> ' +
                        '<td>' + col.data + '</td>' +
                      '</tr>'
                    : '';
                }).join('');

                return data ? $('<table class="table"/>').append(data) : false;
              }
            }
          },
          language: {
            search: 'Cerca:',
            searchPlaceholder: 'Cerca utente...',
            lengthMenu: 'Mostra _MENU_ elementi',
            info: 'Visualizzati _START_ - _END_ di _TOTAL_ elementi',
            infoEmpty: 'Nessun elemento disponibile',
            infoFiltered: '(filtrati da _MAX_ elementi totali)',
            paginate: {
              first: 'Primo',
              previous: 'Precedente',
              next: 'Successivo',
              last: 'Ultimo'
            },
            emptyTable: 'Nessun dato disponibile nella tabella'
          }
        });

        // Inizializza i tooltip
        function initializeTooltips() {
          $('[data-bs-toggle="tooltip"]').tooltip('dispose').tooltip();
        }

        // Reinizializza i tooltip dopo ogni ridisegno della tabella
        usersTable.on('draw', function () {
          initializeTooltips();
        });

        // Gestione dell'avatar
        function setupAvatarHandling() {
          const avatarUpload = document.getElementById('avatarUpload');
          const avatarPreview = document.getElementById('avatarPreview');
          const removeAvatarBtn = document.getElementById('removeAvatar');
          const avatarValue = document.getElementById('avatarValue');
          
          // Quando viene selezionato un file
          if (avatarUpload) {
            avatarUpload.addEventListener('change', function(e) {
              if (this.files&&this.files[0]) {
                const file = this.files[0];
                
                // Controllo dimensione file (max 2MB)
                if (file.size > 2 * 1024 * 1024) {
                  Swal.fire({
                    title: 'File troppo grande',
                    text: 'La dimensione massima consentita è 2MB',
                    icon: 'error',
                    confirmButtonText: 'OK',
                    customClass: {
                      confirmButton: 'btn btn-primary'
                    },
                    buttonsStyling: false
                  });
                  this.value = '';
                  return;
                }
                
                // Preview dell'immagine
                const reader = new FileReader();
                reader.onload = function(e) {
                  avatarPreview.src = e.target.result;
                  avatarValue.value = e.target.result;
                };
                reader.readAsDataURL(file);
              }
            });
          }
          
          // Pulsante per rimuovere l'avatar
          if (removeAvatarBtn) {
            removeAvatarBtn.addEventListener('click', function() {
              // Usa un avatar predefinito basato su un numero casuale
              const avatarId = Math.floor(Math.random() * 14) + 1;
              const defaultAvatar = `../../../assets/img/avatars/${avatarId}.png`;
              avatarPreview.src = defaultAvatar;
              avatarValue.value = 'removed'; // Valore speciale per indicare la rimozione
              
              if (avatarUpload) {
                avatarUpload.value = '';
              }
            });
          }
        }
        
        // Chiamata alla funzione di setup per l'avatar
        setupAvatarHandling();

        // Toggle per la visibilità della password
        $(document).on('click', '.toggle-password', function() {
          const passwordInput = $(this).siblings('input');
          const icon = $(this).find('i');
          
          if (passwordInput.attr('type') === 'password') {
            passwordInput.attr('type', 'text');
            icon.removeClass('tabler-eye-off').addClass('tabler-eye');
          } else {
            passwordInput.attr('type', 'password');
            icon.removeClass('tabler-eye').addClass('tabler-eye-off');
          }
        });

        // Reset del form
        function resetUserForm() {
          if (userForm) {
            userForm.reset();
            document.getElementById('userId').value = '';
            document.getElementById('userModalTitle').textContent = 'Aggiungi nuovo utente';
            document.getElementById('userModalSubtitle').textContent = 'Inserisci i dati per il nuovo utente';
            
            // Reset avatar
            const avatarId = Math.floor(Math.random() * 14) + 1;
            document.getElementById('avatarPreview').src = `../../../assets/img/avatars/${avatarId}.png`;
            document.getElementById('avatarValue').value = '';
            document.getElementById('avatarUpload').value = '';
            
            // Gestione elementi per password
            if (passwordHint) passwordHint.style.display = 'none';
            if (passwordRequired) passwordRequired.style.display = 'inline';
            
            // Imposta flag per nuovo utente
            isNewUser = true;
          }
        }

        // Gestione del form per aggiungere/modificare un utente
        if (userForm) {
          userForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if ((!isNewUser&&!canWrite) || (isNewUser&&!canCreate)) {
              Swal.fire({
                title: 'Permesso negato',
                text: 'Non hai i permessi per questa operazione',
                icon: 'warning'
              });
              return;
            }
            
            // Recupera i dati dal form
            const userId = document.getElementById('userId').value;
            const username = document.getElementById('username').value.trim();
            const email = document.getElementById('email').value.trim();
            const fullName = document.getElementById('fullName').value.trim();
            const roleId = document.getElementById('roleId').value;
            const password = document.getElementById('password').value;
            const status = document.getElementById('status').value;
            const avatarValue = document.getElementById('avatarValue').value;
            
            // Validazione base
            if (!username || !email) {
              Swal.fire({
                title: 'Errore',
                text: 'Username e email sono campi obbligatori',
                icon: 'error',
                confirmButtonText: 'OK',
                customClass: {
                  confirmButton: 'btn btn-primary'
                },
                buttonsStyling: false
              });
              return;
            }
            
            // Validazione email
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
              Swal.fire({
                title: 'Errore',
                text: 'Inserisci un indirizzo email valido',
                icon: 'error',
                confirmButtonText: 'OK',
                customClass: {
                  confirmButton: 'btn btn-primary'
                },
                buttonsStyling: false
              });
              return;
            }
            
            // Password obbligatoria per i nuovi utenti
            if (isNewUser&&!password) {
              Swal.fire({
                title: 'Errore',
                text: 'La password è obbligatoria per i nuovi utenti',
                icon: 'error',
                confirmButtonText: 'OK',
                customClass: {
                  confirmButton: 'btn btn-primary'
                },
                buttonsStyling: false
              });
              return;
            }
            
            // Prepara i dati da inviare
            const userData = {
              username: username,
              email: email,
              full_name: fullName,
              role_id: roleId || null,
              status: status
            };
            
            // Gestione avatar
            if (avatarValue) {
              if (avatarValue === 'removed') {
                userData.avatar = null;  // Rimuovi avatar esistente
              } else if (avatarValue.startsWith('data:image')) {
                userData.avatar = avatarValue;  // Imposta il nuovo avatar base64
              }
            }
            
            // Aggiungi la password solo se fornita
            if (password) {
              userData.password = password;
            }
            
            // Aggiungi l'ID se è un aggiornamento
            if (userId) {
              userData.id = userId;
            }
            
            // Mostra spinner durante il salvataggio
            const submitBtn = userForm.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Salvataggio...';
            submitBtn.disabled = true;
            
            // Invia i dati all'API
            fetch('users_api.php', {
              method: userId ? 'PUT' : 'POST',
              headers: {
                'Content-Type': 'application/json'
              },
              body: JSON.stringify(userData)
            })
              .then(response => response.json())
              .then(data => {
                // Ripristina il pulsante
                submitBtn.innerHTML = originalBtnText;
                submitBtn.disabled = false;
                
                if (data.success) {
                  // Chiudi il modal
                  const modalInstance = bootstrap.Modal.getInstance(userModal);
                  if (modalInstance) {
                    modalInstance.hide();
                  }
                  
                  // Ricarica la tabella
                  usersTable.ajax.reload();
                  
                  // Messaggio di successo
                  Swal.fire({
                    title: 'Operazione completata',
                    text: userId ? 'Utente aggiornato con successo!' : 'Utente creato con successo!',
                    icon: 'success',
                    confirmButtonText: 'OK',
                    customClass: {
                      confirmButton: 'btn btn-success'
                    },
                    buttonsStyling: false
                  });
                } else {
                  // Messaggio di errore
                  Swal.fire({
                    title: 'Errore',
                    text: data.message || 'Si è verificato un errore',
                    icon: 'error',
                    confirmButtonText: 'OK',
                    customClass: {
                      confirmButton: 'btn btn-primary'
                    },
                    buttonsStyling: false
                  });
                }
              })
              .catch(error => {
                // Ripristina il pulsante
                submitBtn.innerHTML = originalBtnText;
                submitBtn.disabled = false;
                
                console.error('Errore:', error);
                Swal.fire({
                  title: 'Errore',
                  text: 'Errore di connessione al server. Riprova più tardi.',
                  icon: 'error',
                  confirmButtonText: 'OK',
                  customClass: {
                    confirmButton: 'btn btn-primary'
                  },
                  buttonsStyling: false
                });
              });
          });
        }

        // Gestione del click sul pulsante di modifica
        $(document).on('click', '.edit-user', function() {
          if (!canWrite) {
            Swal.fire({
              title: 'Permesso negato',
              text: 'Non hai i permessi per modificare utenti',
              icon: 'warning'
            });
            return;
          }
          
          const userId = $(this).data('id');
          
          // Aggiorna il titolo del modal
          document.getElementById('userModalTitle').textContent = 'Modifica utente';
          document.getElementById('userModalSubtitle').textContent = 'Modifica i dati dell\'utente';
          
          // Mostra/nascondi elementi relativi alla password
          if (passwordHint) passwordHint.style.display = 'block';
          if (passwordRequired) passwordRequired.style.display = 'none';
          
          // Imposta flag per utente esistente
          isNewUser = false;
          
          // Mostra spinner sul pulsante
          const editBtn = $(this);
          const originalContent = editBtn.html();
          editBtn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');
          editBtn.prop('disabled', true);
          
          // Recupera i dati dell'utente
          fetch(`users_api.php?id=${userId}`)
            .then(response => response.json())
            .then(data => {
              // Ripristina il pulsante
              editBtn.html(originalContent);
              editBtn.prop('disabled', false);
              
              if (data.success) {
                const user = data.data;
                
                // Popola il form con i dati utente
                document.getElementById('userId').value = user.id;
                document.getElementById('username').value = user.username;
                document.getElementById('email').value = user.email;
                document.getElementById('fullName').value = user.full_name || '';
                document.getElementById('roleId').value = user.role_id || '';
                document.getElementById('status').value = user.status || 'active';
                document.getElementById('password').value = ''; // Password sempre vuota
                
                // Imposta l'avatar
                const avatarPreview = document.getElementById('avatarPreview');
                const avatarValue = document.getElementById('avatarValue');
                
                if (user.avatar) {
                  avatarPreview.src = user.avatar;
                  avatarValue.value = ''; // Reset per evitare invio non necessario
                } else {
                  // Avatar predefinito basato sull'ID utente
                  const avatarId = (user.id % 14) + 1;
                  avatarPreview.src = `../../../assets/img/avatars/${avatarId}.png`;
                  avatarValue.value = '';
                }
                
                // Apri il modal
                const modal = new bootstrap.Modal(userModal);
                modal.show();
              } else {
                Swal.fire({
                  title: 'Errore',
                  text: 'Errore nel caricamento dei dati utente: ' + data.message,
                  icon: 'error',
                  confirmButtonText: 'OK',
                  customClass: {
                    confirmButton: 'btn btn-primary'
                  },
                  buttonsStyling: false
                });
              }
            })
            .catch(error => {
              // Ripristina il pulsante
              editBtn.html(originalContent);
              editBtn.prop('disabled', false);
              
              console.error('Errore:', error);
              Swal.fire({
                title: 'Errore',
                text: 'Errore di connessione al server. Riprova più tardi.',
                icon: 'error',
                confirmButtonText: 'OK',
                customClass: {
                  confirmButton: 'btn btn-primary'
                },
                buttonsStyling: false
              });
            });
        });

        // Gestione del click sul pulsante di visualizzazione
        $(document).on('click', '.view-user', function() {
          const userId = $(this).data('id');
          
          // Mostra spinner sul pulsante
          const viewBtn = $(this);
          const originalContent = viewBtn.html();
          viewBtn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');
          viewBtn.prop('disabled', true);
          
          // Recupera i dati dell'utente
          fetch(`users_api.php?id=${userId}`)
            .then(response => response.json())
            .then(data => {
              // Ripristina il pulsante
              viewBtn.html(originalContent);
              viewBtn.prop('disabled', false);
              
              if (data.success) {
                const user = data.data;
                
                // Determina il colore dello stato
                let statusColorClass = 'success';
                if (user.status === 'inactive') statusColorClass = 'secondary';
                if (user.status === 'suspended') statusColorClass = 'warning';
                
                // Prepara il contenuto per il modal
                let content = `
                  <div class="text-center mb-4">
                    <div class="avatar avatar-xl mb-2">
                      <img src="${user.avatar || '../../../assets/img/avatars/' + (user.id % 14 + 1) + '.png'}" alt="Avatar" class="rounded-circle">
                    </div>
                    <h4 class="mb-1">${user.username}</h4>
                    <span class="d-block text-body-secondary">${user.email}</span>
                    <span class="badge bg-${statusColorClass} mt-2">${user.status}</span>
                  </div>
                  <div class="row">
                    <div class="col-12 mb-3">
                      <h6 class="fw-semibold">Nome completo:</h6>
                      <p>${user.full_name || '-'}</p>
                    </div>
                    <div class="col-12 mb-3">
                      <h6 class="fw-semibold">Ruolo:</h6>
                      <p>${user.role_name || 'Non assegnato'}</p>
                    </div>
                    <div class="col-12 mb-3">
                      <h6 class="fw-semibold">Data registrazione:</h6>
                      <p>${user.created_at || '-'}</p>
                    </div>
                    <div class="col-12 mb-3">
                      <h6 class="fw-semibold">Ultimo accesso:</h6>
                      <p>${user.last_login || 'Mai'}</p>
                    </div>
                  </div>`;
                
                // Aggiungi pulsanti solo se l'utente ha permessi di modifica
                if (canWrite) {
                  content += `
                  <div class="row mt-3">
                    <div class="col-12 text-center">
                      <button type="button" class="btn btn-primary edit-from-view me-2" data-id="${user.id}">
                        <i class="icon-base ti tabler-edit me-1"></i>Modifica
                      </button>
                      <button type="button" class="btn btn-danger delete-from-view" data-id="${user.id}">
                        <i class="icon-base ti tabler-trash me-1"></i>Elimina
                      </button>
                    </div>
                  </div>`;
                }
                
                // Mostra i dettagli dell'utente
                Swal.fire({
                  title: 'Dettagli utente',
                  html: content,
                  showConfirmButton: false,
                  showCloseButton: true,
                  customClass: {
                    popup: 'swal2-user-details'
                  },
                  width: '32rem',
                  didOpen: () => {
                    // Aggiungi gestori eventi per i pulsanti
                    document.querySelector('.edit-from-view')?.addEventListener('click', function() {
                      Swal.close();
                      $(`.edit-user[data-id="${this.getAttribute('data-id')}"]`).click();
                    });
                    
                    document.querySelector('.delete-from-view')?.addEventListener('click', function() {
                      Swal.close();
                      $(`.delete-user[data-id="${this.getAttribute('data-id')}"]`).click();
                    });
                  }
                });
              } else {
                Swal.fire({
                  title: 'Errore',
                  text: 'Errore nel caricamento dei dati utente: ' + data.message,
                  icon: 'error',
                  confirmButtonText: 'OK',
                  customClass: {
                    confirmButton: 'btn btn-primary'
                  },
                  buttonsStyling: false
                });
              }
            })
            .catch(error => {
              // Ripristina il pulsante
              viewBtn.html(originalContent);
              viewBtn.prop('disabled', false);
              
              console.error('Errore:', error);
              Swal.fire({
                title: 'Errore',
                text: 'Errore di connessione al server. Riprova più tardi.',
                icon: 'error',
                confirmButtonText: 'OK',
                customClass: {
                  confirmButton: 'btn btn-primary'
                },
                buttonsStyling: false
              });
            });
        });

        // Gestione del click sul pulsante di eliminazione
        $(document).on('click', '.delete-user', function() {
          if (!canWrite) {
            Swal.fire({
              title: 'Permesso negato',
              text: 'Non hai i permessi per eliminare utenti',
              icon: 'warning'
            });
            return;
          }
          
          const userId = $(this).data('id');
          
          // Ottieni nome utente dalla riga della tabella
          const row = usersTable.row($(this).closest('tr')).data();
          const userName = row ? (row.username || 'questo utente') : 'questo utente';
          
          // Chiedi conferma
          Swal.fire({
            title: 'Sei sicuro?',
            text: `Stai per eliminare l'utente "${userName}". Questa azione non può essere annullata!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sì, elimina!',
            cancelButtonText: 'Annulla',
            customClass: {
              confirmButton: 'btn btn-danger me-3',
              cancelButton: 'btn btn-secondary'
            },
            buttonsStyling: false
          }).then((result) => {
            if (result.isConfirmed) {
              // Mostra spinner sul pulsante
              const deleteBtn = $(this);
              const originalContent = deleteBtn.html();
              deleteBtn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');
              deleteBtn.prop('disabled', true);
              
              // Invia la richiesta di eliminazione
              fetch(`users_api.php?id=${userId}`, {
                method: 'DELETE'
              })
                .then(response => response.json())
                .then(data => {
                  // Ripristina il pulsante
                  deleteBtn.html(originalContent);
                  deleteBtn.prop('disabled', false);
                  
                  if (data.success) {
                    // Ricarica la tabella
                    usersTable.ajax.reload();
                    
                    // Messaggio di successo
                    Swal.fire({
                      title: 'Eliminato!',
                      text: 'L\'utente è stato eliminato con successo.',
                      icon: 'success',
                      customClass: {
                        confirmButton: 'btn btn-success'
                      },
                      buttonsStyling: false
                    });
                  } else {
                    Swal.fire({
                      title: 'Errore',
                      text: data.message || 'Si è verificato un errore durante l\'eliminazione',
                      icon: 'error',
                      customClass: {
                        confirmButton: 'btn btn-primary'
                      },
                      buttonsStyling: false
                    });
                  }
                })
                .catch(error => {
                  // Ripristina il pulsante
                  deleteBtn.html(originalContent);
                  deleteBtn.prop('disabled', false);
                  
                  console.error('Errore:', error);
                  Swal.fire({
                    title: 'Errore',
                    text: 'Errore di connessione al server. Riprova più tardi.',
                    icon: 'error',
                    customClass: {
                      confirmButton: 'btn btn-primary'
                    },
                    buttonsStyling: false
                  });
                });
            }
          });
        });

        // Inizializzazione dei tooltip
        initializeTooltips();
        
        // Aggiungi stile custom per SweetAlert
        const style = document.createElement('style');
        style.textContent = `
          .swal2-user-details .swal2-html-container {
            overflow-x: hidden;
          }
        `;
        document.head.appendChild(style);
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