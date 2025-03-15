<?php
// Include il controllo dell'autenticazione
require_once 'check_auth.php';

// Verifica l'accesso specifico a questa pagina
if (!userHasAccess('Elenco ristoranti')) {
    // Reindirizza alla pagina di accesso negato
    header("Location: access-denied.php");
    exit;
}

// Ottieni i permessi specifici dell'utente per questa funzionalità
$canRead = userHasPermission('Elenco ristoranti', 'read');
$canWrite = userHasPermission('Elenco ristoranti', 'write');
$canCreate = userHasPermission('Elenco ristoranti', 'create');

// Se l'utente non ha nemmeno i permessi di lettura, reindirizza
if (!$canRead) {
    header("Location: access-denied.php");
    exit;
}

// Abilita visualizzazione errori per debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
require_once 'db_connection.php';

// Gestione delle richieste AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET') {
    // Richiesta dettagli ristorante - Richiede permessi di lettura
    if ($_SERVER['REQUEST_METHOD'] === 'GET'&&isset($_GET['action'])&&$_GET['action'] === 'get_details'&&isset($_GET['id'])) {
        if (!$canRead) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Permesso negato']);
            exit;
        }
        
        $restaurantId = intval($_GET['id']);
        
        // Query per ottenere tutti i dettagli del ristorante
        $query = "
            SELECT 
                r.id, 
                r.name, 
                r.alias, 
                r.phone, 
                r.mobile, 
                r.logo, 
                r.cover_image,
                r.description,
                r.notes,
                (
                    SELECT GROUP_CONCAT(ct.name SEPARATOR ', ') 
                    FROM restaurant_cuisine_types rct 
                    JOIN cuisine_types ct ON rct.cuisine_type_id = ct.id 
                    WHERE rct.restaurant_id = r.id
                ) AS cuisine_types,
                (
                    SELECT GROUP_CONCAT(rc.name SEPARATOR ', ') 
                    FROM restaurant_categories_rel rcr 
                    JOIN restaurant_categories rc ON rcr.category_id = rc.id 
                    WHERE rcr.restaurant_id = r.id
                ) AS categories
            FROM restaurants r
            WHERE r.id = ?
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $restaurantId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $restaurant = $result->fetch_assoc();
            header('Content-Type: application/json');
            echo json_encode($restaurant);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Ristorante non trovato']);
        }
        
        $conn->close();
        exit;
    }
    
    // Ottieni dati per modifica ristorante - Richiede permessi di scrittura
    if ($_SERVER['REQUEST_METHOD'] === 'GET'&&isset($_GET['action'])&&$_GET['action'] === 'get_edit_data'&&isset($_GET['id'])) {
        if (!$canWrite) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Permesso negato']);
            exit;
        }
        
        $restaurantId = intval($_GET['id']);
        
        // Query per ottenere i dettagli del ristorante
        $query = "
            SELECT 
                r.*,
                (
                    SELECT GROUP_CONCAT(rct.cuisine_type_id) 
                    FROM restaurant_cuisine_types rct 
                    WHERE rct.restaurant_id = r.id
                ) AS cuisine_type_ids,
                (
                    SELECT GROUP_CONCAT(rcr.category_id) 
                    FROM restaurant_categories_rel rcr 
                    WHERE rcr.restaurant_id = r.id
                ) AS category_ids
            FROM restaurants r
            WHERE r.id = ?
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $restaurantId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $restaurant = $result->fetch_assoc();
            
            // Converti le stringhe di ID in array
            if ($restaurant['cuisine_type_ids']) {
                $restaurant['cuisine_type_ids'] = explode(',', $restaurant['cuisine_type_ids']);
            } else {
                $restaurant['cuisine_type_ids'] = [];
            }
            
            if ($restaurant['category_ids']) {
                $restaurant['category_ids'] = explode(',', $restaurant['category_ids']);
            } else {
                $restaurant['category_ids'] = [];
            }
            
            header('Content-Type: application/json');
            echo json_encode($restaurant);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Ristorante non trovato']);
        }
        
        $conn->close();
        exit;
    }
    
    // Eliminazione ristorante - Richiede permessi di creazione
    if ($_SERVER['REQUEST_METHOD'] === 'POST'&&isset($_POST['action'])&&$_POST['action'] === 'delete'&&isset($_POST['id'])) {
        if (!$canCreate) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Permesso negato']);
            exit;
        }
        
        $restaurantId = intval($_POST['id']);
        $result = deleteRestaurant($conn, $restaurantId);
        
        header('Content-Type: application/json');
        echo json_encode($result);
        
        $conn->close();
        exit;
    }
    
    // Aggiornamento ristorante - Richiede permessi di scrittura
    if ($_SERVER['REQUEST_METHOD'] === 'POST'&&isset($_POST['action'])&&$_POST['action'] === 'update_restaurant'&&isset($_POST['id'])) {
        if (!$canWrite) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Permesso negato']);
            exit;
        }
        
        $restaurantId = intval($_POST['id']);
        $name = $conn->real_escape_string($_POST['name']);
        $alias = $conn->real_escape_string($_POST['alias']);
        $phone = $conn->real_escape_string($_POST['phone']);
        $mobile = $conn->real_escape_string($_POST['mobile']);
        $description = $conn->real_escape_string($_POST['description']);
        $notes = $conn->real_escape_string($_POST['notes']);
        
        // Inizia una transazione
        $conn->begin_transaction();
        
        try {
            // Aggiorna il ristorante
            $updateQuery = "UPDATE restaurants SET 
                            name = ?, 
                            alias = ?, 
                            phone = ?, 
                            mobile = ?, 
                            description = ?, 
                            notes = ? 
                            WHERE id = ?";
            
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("ssssssi", $name, $alias, $phone, $mobile, $description, $notes, $restaurantId);
            
            if ($stmt->execute()) {
                // Gestione del logo
                if (isset($_FILES['logo'])&&$_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                    $logoPath = processImage($_FILES['logo'], 'logo', $restaurantId, 100, 100);
                    if ($logoPath) {
                        $updateLogo = "UPDATE restaurants SET logo = ? WHERE id = ?";
                        $stmtLogo = $conn->prepare($updateLogo);
                        $stmtLogo->bind_param("si", $logoPath, $restaurantId);
                        $stmtLogo->execute();
                    }
                }
                
                // Gestione dell'immagine di copertina
                if (isset($_FILES['cover_image'])&&$_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
                    $coverPath = processImage($_FILES['cover_image'], 'cover', $restaurantId, 100, 100);
                    if ($coverPath) {
                        $updateCover = "UPDATE restaurants SET cover_image = ? WHERE id = ?";
                        $stmtCover = $conn->prepare($updateCover);
                        $stmtCover->bind_param("si", $coverPath, $restaurantId);
                        $stmtCover->execute();
                    }
                }
                
                // Elimina le vecchie relazioni con i tipi di cucina
                $deleteCuisineTypes = "DELETE FROM restaurant_cuisine_types WHERE restaurant_id = ?";
                $stmtDeleteCuisine = $conn->prepare($deleteCuisineTypes);
                $stmtDeleteCuisine->bind_param("i", $restaurantId);
                $stmtDeleteCuisine->execute();
                
                // Gestione dei tipi di cucina
                if (isset($_POST['cuisine_types'])&&is_array($_POST['cuisine_types'])) {
                    $insertCuisine = "INSERT INTO restaurant_cuisine_types (restaurant_id, cuisine_type_id) VALUES (?, ?)";
                    $stmtInsertCuisine = $conn->prepare($insertCuisine);
                    
                    foreach ($_POST['cuisine_types'] as $cuisineId) {
                        $cuisineId = intval($cuisineId);
                        $stmtInsertCuisine->bind_param("ii", $restaurantId, $cuisineId);
                        $stmtInsertCuisine->execute();
                    }
                }
                
                // Elimina le vecchie relazioni con le categorie
                $deleteCategories = "DELETE FROM restaurant_categories_rel WHERE restaurant_id = ?";
                $stmtDeleteCategory = $conn->prepare($deleteCategories);
                $stmtDeleteCategory->bind_param("i", $restaurantId);
                $stmtDeleteCategory->execute();
                
                // Gestione delle categorie
                if (isset($_POST['categories'])&&is_array($_POST['categories'])) {
                    $insertCategory = "INSERT INTO restaurant_categories_rel (restaurant_id, category_id) VALUES (?, ?)";
                    $stmtInsertCategory = $conn->prepare($insertCategory);
                    
                    foreach ($_POST['categories'] as $categoryId) {
                        $categoryId = intval($categoryId);
                        $stmtInsertCategory->bind_param("ii", $restaurantId, $categoryId);
                        $stmtInsertCategory->execute();
                    }
                }
                
                // Commit della transazione
                $conn->commit();
                
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Ristorante aggiornato con successo!']);
            } else {
                throw new Exception("Errore nell'aggiornamento del ristorante: " . $stmt->error);
            }
        } catch (Exception $e) {
            // Rollback in caso di errore
            $conn->rollback();
            
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        
        $conn->close();
        exit;
    }
    
    // Aggiunta di un nuovo ristorante - Richiede permessi di creazione
    if ($_SERVER['REQUEST_METHOD'] === 'POST'&&isset($_POST['action'])&&$_POST['action'] === 'add_restaurant') {
        if (!$canCreate) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Permesso negato']);
            exit;
        }
        
        $name = $conn->real_escape_string($_POST['name']);
        $alias = $conn->real_escape_string($_POST['alias']);
        $phone = $conn->real_escape_string($_POST['phone']);
        $mobile = $conn->real_escape_string($_POST['mobile']);
        $description = $conn->real_escape_string($_POST['description']);
        $notes = $conn->real_escape_string($_POST['notes']);
        
        // Inizia una transazione
        $conn->begin_transaction();
        
        try {
            // Inserisci il ristorante
            $insertQuery = "INSERT INTO restaurants (name, alias, phone, mobile, description, notes) 
                            VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($insertQuery);
            $stmt->bind_param("ssssss", $name, $alias, $phone, $mobile, $description, $notes);
            
            if ($stmt->execute()) {
                $restaurantId = $conn->insert_id;
                
                // Gestione del logo
                if (isset($_FILES['logo'])&&$_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                    $logoPath = processImage($_FILES['logo'], 'logo', $restaurantId, 100, 100);
                    if ($logoPath) {
                        $updateLogo = "UPDATE restaurants SET logo = ? WHERE id = ?";
                        $stmtLogo = $conn->prepare($updateLogo);
                        $stmtLogo->bind_param("si", $logoPath, $restaurantId);
                        $stmtLogo->execute();
                    }
                }
                
                // Gestione dell'immagine di copertina
                if (isset($_FILES['cover_image'])&&$_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
                    $coverPath = processImage($_FILES['cover_image'], 'cover', $restaurantId, 100, 100);
                    if ($coverPath) {
                        $updateCover = "UPDATE restaurants SET cover_image = ? WHERE id = ?";
                        $stmtCover = $conn->prepare($updateCover);
                        $stmtCover->bind_param("si", $coverPath, $restaurantId);
                        $stmtCover->execute();
                    }
                }
                
                // Gestione dei tipi di cucina
                if (isset($_POST['cuisine_types'])&&is_array($_POST['cuisine_types'])) {
                    $insertCuisine = "INSERT INTO restaurant_cuisine_types (restaurant_id, cuisine_type_id) VALUES (?, ?)";
                    $stmtInsertCuisine = $conn->prepare($insertCuisine);
                    
                    foreach ($_POST['cuisine_types'] as $cuisineId) {
                        $cuisineId = intval($cuisineId);
                        $stmtInsertCuisine->bind_param("ii", $restaurantId, $cuisineId);
                        $stmtInsertCuisine->execute();
                    }
                }
                
                // Gestione delle categorie
                if (isset($_POST['categories'])&&is_array($_POST['categories'])) {
                    $insertCategory = "INSERT INTO restaurant_categories_rel (restaurant_id, category_id) VALUES (?, ?)";
                    $stmtInsertCategory = $conn->prepare($insertCategory);
                    
                    foreach ($_POST['categories'] as $categoryId) {
                        $categoryId = intval($categoryId);
                        $stmtInsertCategory->bind_param("ii", $restaurantId, $categoryId);
                        $stmtInsertCategory->execute();
                    }
                }
                
                // Commit della transazione
                $conn->commit();
                
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Ristorante aggiunto con successo!']);
            } else {
                throw new Exception("Errore nell'inserimento del ristorante: " . $stmt->error);
            }
        } catch (Exception $e) {
            // Rollback in caso di errore
            $conn->rollback();
            
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        
        $conn->close();
        exit;
    }
}

// Funzione per processare le immagini caricate
function processImage($file, $type, $restaurantId, $width, $height) {
    $targetDir = "../../../uploads/restaurants/";
    
    // Crea la directory se non esiste
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    $fileName = $type . '_' . $restaurantId . '_' . time() . '.jpg';
    $targetFile = $targetDir . $fileName;
    
    // Controlla il tipo di file
    $imageFileType = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    if($imageFileType != "jpg"&&$imageFileType != "png"&&$imageFileType != "jpeg") {
        return false;
    }
    
    // Carica l'immagine originale
    $sourceImage = null;
    if ($imageFileType == "jpg" || $imageFileType == "jpeg") {
        $sourceImage = imagecreatefromjpeg($file["tmp_name"]);
    } else if ($imageFileType == "png") {
        $sourceImage = imagecreatefrompng($file["tmp_name"]);
    }
    
    if (!$sourceImage) {
        return false;
    }
    
    // Crea un'immagine di dimensioni specificate
    $destImage = imagecreatetruecolor($width, $height);
    
    // Ridimensiona l'immagine
    imagecopyresampled($destImage, $sourceImage, 0, 0, 0, 0, $width, $height, imagesx($sourceImage), imagesy($sourceImage));
    
    // Salva l'immagine ridimensionata
    imagejpeg($destImage, $targetFile, 90);
    
    // Pulisci la memoria
    imagedestroy($sourceImage);
    imagedestroy($destImage);
    
    return 'uploads/restaurants/' . $fileName;
}

// Funzione per eliminare un ristorante
function deleteRestaurant($conn, $restaurantId) {
    // Inizia una transazione
    $conn->begin_transaction();
    
    try {
        // Prima otteniamo i percorsi delle immagini per poterle eliminare
        $imageQuery = "SELECT logo, cover_image FROM restaurants WHERE id = $restaurantId";
        $imageResult = $conn->query($imageQuery);
        
        if ($imageResult->num_rows > 0) {
            $imageRow = $imageResult->fetch_assoc();
            $logoPath = $imageRow['logo'];
            $coverPath = $imageRow['cover_image'];
            
            // Elimina le relazioni con i tipi di cucina
            $deleteCuisineTypes = "DELETE FROM restaurant_cuisine_types WHERE restaurant_id = $restaurantId";
            $conn->query($deleteCuisineTypes);
            
            // Elimina le relazioni con le categorie
            $deleteCategories = "DELETE FROM restaurant_categories_rel WHERE restaurant_id = $restaurantId";
            $conn->query($deleteCategories);
            
            // Elimina il ristorante
            $deleteRestaurant = "DELETE FROM restaurants WHERE id = $restaurantId";
            $result = $conn->query($deleteRestaurant);
            
            if ($result) {
                // Commit della transazione
                $conn->commit();
                
                // Elimina fisicamente le immagini
                if ($logoPath&&file_exists('../../../' . $logoPath)) {
                    unlink('../../../' . $logoPath);
                }
                
                if ($coverPath&&file_exists('../../../' . $coverPath)) {
                    unlink('../../../' . $coverPath);
                }
                
                return ['success' => true, 'message' => 'Ristorante eliminato con successo!'];
            } else {
                throw new Exception("Errore nell'eliminazione del ristorante: " . $conn->error);
            }
        } else {
            throw new Exception("Ristorante non trovato");
        }
    } catch (Exception $e) {
        // Rollback in caso di errore
        $conn->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Funzione per verificare i permessi specifici dell'utente
function userHasPermission($permissionName, $action = 'read') {
    global $conn;
    
    // Ottieni l'ID del ruolo dalla sessione
    $roleId = $_SESSION['role_id'];
    
    // Traduci l'azione in colonna del database
    $column = 'can_read';
    if ($action === 'write') {
        $column = 'can_write';
    } elseif ($action === 'create') {
        $column = 'can_create';
    }
    
    // Query per verificare il permesso
    $query = "
        SELECT rp.$column
        FROM role_permissions rp
        JOIN permissions p ON rp.permission_id = p.id
        WHERE rp.role_id = ? AND p.name = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $roleId, $permissionName);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return (bool)$row[$column];
    }
    
    return false;
}

// Carica i tipi di cucina
$cuisineTypes = [];
$cuisineQuery = "SELECT id, name FROM cuisine_types ORDER BY name";
$cuisineResult = $conn->query($cuisineQuery);
if ($cuisineResult&&$cuisineResult->num_rows > 0) {
    while ($row = $cuisineResult->fetch_assoc()) {
        $cuisineTypes[] = $row;
    }
}

// Carica le categorie di ristoranti
$categories = [];
$categoriesQuery = "SELECT id, name FROM restaurant_categories ORDER BY name";
$categoriesResult = $conn->query($categoriesQuery);
if ($categoriesResult&&$categoriesResult->num_rows > 0) {
    while ($row = $categoriesResult->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Carica i dati dei ristoranti per la tabella
$restaurants = [];
$query = "
    SELECT 
        r.id, 
        r.name, 
        r.alias, 
        r.phone, 
        r.mobile, 
        r.logo, 
        r.cover_image,
        r.description,
        r.notes,
        (
            SELECT GROUP_CONCAT(ct.name SEPARATOR ', ') 
            FROM restaurant_cuisine_types rct 
            JOIN cuisine_types ct ON rct.cuisine_type_id = ct.id 
            WHERE rct.restaurant_id = r.id
        ) AS cuisine_types,
        (
            SELECT GROUP_CONCAT(rc.name SEPARATOR ', ') 
            FROM restaurant_categories_rel rcr 
            JOIN restaurant_categories rc ON rcr.category_id = rc.id 
            WHERE rcr.restaurant_id = r.id
        ) AS categories
    FROM restaurants r
    ORDER BY r.id DESC
";

$result = $conn->query($query);
if ($result&&$result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Pulizia e sanificazione dei dati
        $row['name'] = htmlspecialchars($row['name']);
        $row['alias'] = htmlspecialchars($row['alias'] ?? '');
        $row['cuisine_types'] = htmlspecialchars($row['cuisine_types'] ?? '');
        $row['categories'] = htmlspecialchars($row['categories'] ?? '');
        $row['description'] = htmlspecialchars($row['description'] ?? '');
        $row['notes'] = htmlspecialchars($row['notes'] ?? '');
        
        $restaurants[] = $row;
    }
}

// Ottieni il conteggio dei ristoranti aggiunti negli ultimi 30 giorni
$last30DaysQuery = "
    SELECT COUNT(*) as count 
    FROM restaurants 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
";
$last30DaysResult = $conn->query($last30DaysQuery);
$newRestaurantsCount = 0;
if ($last30DaysResult&&$last30DaysResult->num_rows > 0) {
    $row = $last30DaysResult->fetch_assoc();
    $newRestaurantsCount = $row['count'];
}
?>
<!doctype html>
<html lang="en" class="layout-navbar-fixed layout-menu-fixed layout-compact" dir="ltr"
  data-skin="default" data-assets-path="../../../assets/" data-template="vertical-menu-template" data-bs-theme="light">
  
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Elenco Ristoranti</title>
    <meta name="description" content="Gestione elenco ristoranti" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../../../assets/img/favicon/favicon.ico" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../../../assets/vendor/fonts/iconify-icons.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="../../../assets/vendor/libs/node-waves/node-waves.css" />
    <link rel="stylesheet" href="../../../assets/vendor/libs/pickr/pickr-themes.css" />
    <link rel="stylesheet" href="../../../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../../../assets/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="../../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="../../../assets/vendor/libs/@form-validation/umd/styles/index.min.css" />
    <!-- DataTable e Select2 CSS -->
    <link rel="stylesheet" href="../../../assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css" />
    <link rel="stylesheet" href="../../../assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css" />
    <link rel="stylesheet" href="../../../assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css" />
    <link rel="stylesheet" href="../../../assets/vendor/libs/select2/select2.css" />
    <link rel="stylesheet" href="../../../assets/vendor/libs/sweetalert2/sweetalert2.css" />

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
                        <h4 class="py-3 mb-4"><span class="text-muted fw-light">Marketplace /</span> Elenco Ristoranti</h4>
                        
                        <!-- Flash Messages per operazioni CRUD -->
                        <div id="alert-container"></div>
                        
                        <!-- Stats Cards -->
                        <div class="row g-6 mb-6">
                            <div class="col-sm-6 col-xl-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex align-items-start justify-content-between">
                                            <div class="content-left">
                                                <span class="text-heading">Totale</span>
                                                <div class="d-flex align-items-center my-1">
                                                    <h4 class="mb-0 me-2"><?php echo count($restaurants); ?></h4>
                                                </div>
                                                <small class="mb-0">Ristoranti registrati</small>
                                            </div>
                                            <div class="avatar">
                                                <span class="avatar-initial rounded bg-label-primary">
                                                    <i class="icon-base ti tabler-building-store icon-26px"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex align-items-start justify-content-between">
                                            <div class="content-left">
                                                <span class="text-heading">Tipi Cucina</span>
                                                <div class="d-flex align-items-center my-1">
                                                    <h4 class="mb-0 me-2"><?php echo count($cuisineTypes); ?></h4>
                                                </div>
                                                <small class="mb-0">Categorie di cucina</small>
                                            </div>
                                            <div class="avatar">
                                                <span class="avatar-initial rounded bg-label-danger">
                                                    <i class="icon-base ti tabler-tools-kitchen-2 icon-26px"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex align-items-start justify-content-between">
                                            <div class="content-left">
                                                <span class="text-heading">Categorie</span>
                                                <div class="d-flex align-items-center my-1">
                                                    <h4 class="mb-0 me-2"><?php echo count($categories); ?></h4>
                                                </div>
                                                <small class="mb-0">Tipologie di ristoranti</small>
                                            </div>
                                            <div class="avatar">
                                                <span class="avatar-initial rounded bg-label-success">
                                                    <i class="icon-base ti tabler-category-2 icon-26px"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex align-items-start justify-content-between">
                                            <div class="content-left">
                                                <span class="text-heading">Ultimi 30 giorni</span>
                                                <div class="d-flex align-items-center my-1">
                                                    <h4 class="mb-0 me-2"><?php echo $newRestaurantsCount; ?></h4>
                                                </div>
                                                <small class="mb-0">Nuovi ristoranti aggiunti</small>
                                            </div>
                                            <div class="avatar">
                                                <span class="avatar-initial rounded bg-label-warning">
                                                    <i class="icon-base ti tabler-calendar-plus icon-26px"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- / Stats Cards -->
                        
                        <!-- Datatable Card -->
                        <div class="card">
                            <div class="card-header border-bottom">
                                <h5 class="card-title mb-0">Filtri</h5>
                                <div class="d-flex justify-content-between align-items-center row pt-4 gap-4 gap-md-0">
                                    <div class="col-md-4 cuisine_type_filter"></div>
                                    <div class="col-md-4 category_filter"></div>
                                    <div class="col-md-4">
                                        <!-- Eventuali filtri aggiuntivi -->
                                    </div>
                                </div>
                            </div>
                            <div class="card-datatable table-responsive">
                                <table class="datatables-restaurants table">
                                    <thead class="border-top">
                                        <tr>
                                            <th></th>
                                            <th>Logo</th>
                                            <th>Nome</th>
                                            <th>Alias</th>
                                            <th>Tipi di Cucina</th>
                                            <th>Categorie</th>
                                            <th>Contatti</th>
                                            <th>Azioni</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($restaurants as $restaurant): ?>
                                        <tr>
                                            <td><?php echo $restaurant['id']; ?></td>
                                            <td>
                                                <?php if($restaurant['logo']): ?>
                                                <img src="../../../<?php echo $restaurant['logo']; ?>" class="rounded" width="40" height="40" alt="Logo">
                                                <?php else: ?>
                                                <img src="../../../assets/img/avatars/1.png" class="rounded" width="40" height="40" alt="Default logo">
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $restaurant['name']; ?></td>
                                            <td><?php echo $restaurant['alias']; ?></td>
                                            <td><?php echo $restaurant['cuisine_types']; ?></td>
                                            <td><?php echo $restaurant['categories']; ?></td>
                                            <td>
                                                <?php if($restaurant['phone']): ?>
                                                <span class="badge bg-label-info me-1"><span style="margin-right:4px;font-weight:bold;">☎</span><?php echo $restaurant['phone']; ?></span>
                                                <?php endif; ?>
                                                <?php if($restaurant['mobile']): ?>
                                                <span class="badge bg-label-primary"><span style="margin-right:4px;font-weight:bold;">📱</span><?php echo $restaurant['mobile']; ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if ($canRead): ?>
                                                    <a href="javascript:;" class="btn btn-text-secondary rounded-pill waves-effect btn-icon view-details" data-id="<?php echo $restaurant['id']; ?>" title="Visualizza">
                                                        <i class="icon-base ti tabler-eye icon-22px"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($canWrite): ?>
                                                    <a href="javascript:;" class="btn btn-text-secondary rounded-pill waves-effect btn-icon edit-restaurant" data-id="<?php echo $restaurant['id']; ?>" title="Modifica">
                                                        <i class="icon-base ti tabler-pencil icon-22px"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($canCreate): ?>
                                                    <a href="javascript:;" class="btn btn-text-secondary rounded-pill waves-effect btn-icon delete-record" data-id="<?php echo $restaurant['id']; ?>" title="Elimina">
                                                        <i class="icon-base ti tabler-trash icon-22px"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <!--/ Datatable Card -->
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

        <!-- Offcanvas per visualizzare i dettagli di un ristorante -->
        <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasViewRestaurant" aria-labelledby="offcanvasViewRestaurantLabel">
            <div class="offcanvas-header border-bottom">
                <h5 id="offcanvasViewRestaurantLabel" class="offcanvas-title">Dettagli Ristorante</h5>
                <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body mx-0 flex-grow-1 p-6 h-100">
                <div class="row mb-4">
                    <div class="col-md-6 text-center mb-3">
                        <h6 class="text-muted mb-2">Logo</h6>
                        <img id="detail-logo" src="" alt="Logo Ristorante" class="rounded mb-2" style="max-width: 100px; max-height: 100px;">
                    </div>
                    <div class="col-md-6 text-center mb-3">
                        <h6 class="text-muted mb-2">Immagine di Copertina</h6>
                        <img id="detail-cover" src="" alt="Copertina Ristorante" class="rounded mb-2" style="max-width: 100px; max-height: 100px;">
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6 class="text-muted">Nome</h6>
                        <p id="detail-name" class="mb-0"></p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted">Alias</h6>
                        <p id="detail-alias" class="mb-0"></p>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6 class="text-muted">Tipi di Cucina</h6>
                        <p id="detail-cuisine" class="mb-0"></p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted">Categorie</h6>
                        <p id="detail-categories" class="mb-0"></p>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6 class="text-muted">Telefono</h6>
                        <p id="detail-phone" class="mb-0"></p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted">Cellulare</h6>
                        <p id="detail-mobile" class="mb-0"></p>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-12">
                        <h6 class="text-muted">Descrizione</h6>
                        <p id="detail-description" class="mb-0"></p>
                    </div>
                </div>
                
                <div class="row mb-5">
                    <div class="col-12">
                        <h6 class="text-muted">Note</h6>
                        <p id="detail-notes" class="mb-0"></p>
                    </div>
                </div>
                
                <div class="d-flex mt-4">
                    <button type="button" class="btn btn-outline-secondary w-100 me-3" data-bs-dismiss="offcanvas">Chiudi</button>
                    <?php if ($canWrite): ?>
                    <button type="button" class="btn btn-primary w-100 edit-from-view">Modifica</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Offcanvas per aggiungere un nuovo ristorante -->
        <?php if ($canCreate): ?>
        <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasAddRestaurant" aria-labelledby="offcanvasAddRestaurantLabel">
            <div class="offcanvas-header border-bottom">
                <h5 id="offcanvasAddRestaurantLabel" class="offcanvas-title">Aggiungi Ristorante</h5>
                <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body mx-0 flex-grow-1 p-6 h-100">
                <form class="add-restaurant pt-0" id="addRestaurantForm" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_restaurant">
                    
                    <div class="mb-4">
                        <label class="form-label" for="name">Nome Ristorante *</label>
                        <input type="text" class="form-control" id="name" name="name" placeholder="Nome del ristorante" required />
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label" for="alias">Alias</label>
                        <input type="text" class="form-control" id="alias" name="alias" placeholder="Nome abbreviato o alias" />
                        <small class="text-muted">Nome breve o abbreviazione</small>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label" for="cuisine_types">Tipi di Cucina</label>
                        <select class="select2 form-select" id="cuisine_types" name="cuisine_types[]" multiple>
                            <?php foreach ($cuisineTypes as $cuisine): ?>
                                <option value="<?php echo $cuisine['id']; ?>"><?php echo htmlspecialchars($cuisine['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label" for="categories">Categorie</label>
                        <select class="select2 form-select" id="categories" name="categories[]" multiple>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label" for="phone">Telefono</label>
                        <input type="text" class="form-control" id="phone" name="phone" placeholder="Numero di telefono fisso" />
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label" for="mobile">Cellulare</label>
                        <input type="text" class="form-control" id="mobile" name="mobile" placeholder="Numero di cellulare" />
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label" for="logo">Logo (100x100)</label>
                        <input type="file" id="logo" name="logo" class="form-control" />
                        <small class="text-muted">Formato: JPG, JPEG, PNG. Max 2MB.</small>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label" for="cover_image">Immagine di Copertina (100x100)</label>
                        <input type="file" id="cover_image" name="cover_image" class="form-control" />
                        <small class="text-muted">Formato: JPG, JPEG, PNG. Max 2MB.</small>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label" for="description">Descrizione</label>
                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="Breve descrizione del ristorante"></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label" for="notes">Note</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Note aggiuntive"></textarea>
                    </div>
                    
                    <div class="d-flex mt-4">
                        <button type="button" class="btn btn-outline-secondary w-100 me-3" data-bs-dismiss="offcanvas">Annulla</button>
                        <button type="submit" class="btn btn-primary w-100 data-submit">Salva</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Offcanvas per modificare un ristorante -->
        <?php if ($canWrite): ?>
        <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasEditRestaurant" aria-labelledby="offcanvasEditRestaurantLabel">
            <div class="offcanvas-header border-bottom">
                <h5 id="offcanvasEditRestaurantLabel" class="offcanvas-title">Modifica Ristorante</h5>
                <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body mx-0 flex-grow-1 p-6 h-100">
                <form class="edit-restaurant pt-0" id="editRestaurantForm" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_restaurant">
                    <input type="hidden" id="edit-id" name="id">
                    
                    <div class="mb-4">
                        <label class="form-label" for="edit-name">Nome Ristorante *</label>
                        <input type="text" class="form-control" id="edit-name" name="name" placeholder="Nome del ristorante" required />
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label" for="edit-alias">Alias</label>
                        <input type="text" class="form-control" id="edit-alias" name="alias" placeholder="Nome abbreviato o alias" />
                        <small class="text-muted">Nome breve o abbreviazione</small>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label" for="edit-cuisine-types">Tipi di Cucina</label>
                        <select class="edit-select2 form-select" id="edit-cuisine-types" name="cuisine_types[]" multiple>
                            <?php foreach ($cuisineTypes as $cuisine): ?>
                                <option value="<?php echo $cuisine['id']; ?>"><?php echo htmlspecialchars($cuisine['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label" for="edit-categories">Categorie</label>
                        <select class="edit-select2 form-select" id="edit-categories" name="categories[]" multiple>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label" for="edit-phone">Telefono</label>
                        <input type="text" class="form-control" id="edit-phone" name="phone" placeholder="Numero di telefono fisso" />
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label" for="edit-mobile">Cellulare</label>
                        <input type="text" class="form-control" id="edit-mobile" name="mobile" placeholder="Numero di cellulare" />
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Logo attuale</label>
                        <div class="text-center mb-3">
                            <img id="edit-logo-preview" src="" alt="Logo Ristorante" class="rounded" style="max-width: 100px; max-height: 100px;">
                        </div>
                        <input type="file" id="edit-logo" name="logo" class="form-control" />
                        <small class="text-muted">Formato: JPG, JPEG, PNG. Max 2MB.</small>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Immagine di Copertina attuale</label>
                        <div class="text-center mb-3">
                            <img id="edit-cover-preview" src="" alt="Copertina Ristorante" class="rounded" style="max-width: 100px; max-height: 100px;">
                        </div>
                        <input type="file" id="edit-cover-image" name="cover_image" class="form-control" />
                        <small class="text-muted">Formato: JPG, JPEG, PNG. Max 2MB.</small>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label" for="edit-description">Descrizione</label>
                        <textarea class="form-control" id="edit-description" name="description" rows="3" placeholder="Breve descrizione del ristorante"></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label" for="edit-notes">Note</label>
                        <textarea class="form-control" id="edit-notes" name="notes" rows="3" placeholder="Note aggiuntive"></textarea>
                    </div>
                    
                    <div class="d-flex mt-4">
                        <button type="button" class="btn btn-outline-secondary w-100 me-3" data-bs-dismiss="offcanvas">Annulla</button>
                        <button type="submit" class="btn btn-primary w-100">Aggiorna</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

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
    <script src="../../../assets/vendor/libs/moment/moment.js"></script>
    <script src="../../../assets/vendor/libs/jquery-datatables/jquery.dataTables.js"></script>
    <script src="../../../assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js"></script>
    <script src="../../../assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.js"></script>
    <script src="../../../assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.js"></script>
    <script src="../../../assets/vendor/libs/select2/select2.js"></script>
    <script src="../../../assets/vendor/libs/sweetalert2/sweetalert2.js"></script>

    <!-- Main JS -->
    <script src="../../../assets/js/main.js"></script>
    
    <!-- Passaggio dei permessi al JavaScript -->
    <script>
        var userPermissions = {
            canRead: <?php echo $canRead ? 'true' : 'false'; ?>,
            canWrite: <?php echo $canWrite ? 'true' : 'false'; ?>,
            canCreate: <?php echo $canCreate ? 'true' : 'false'; ?>
        };
    </script>
    
    <!-- Script personalizzato per la DataTable avanzata -->
    <script>
    $(document).ready(function() {
        'use strict';
        
        // Configurazione colori e tema
        let borderColor, bodyBg, headingColor;
        borderColor = config.colors.borderColor;
        bodyBg = config.colors.bodyBg;
        headingColor = config.colors.headingColor;
        
        // Inizializza Select2 per i form
        $('.select2').select2({
            dropdownParent: $('#offcanvasAddRestaurant')
        });
        
        $('.edit-select2').select2({
            dropdownParent: $('#offcanvasEditRestaurant')
        });
        
        // Funzione per mostrare messaggi di alert
        function showAlert(message, type = 'success') {
            const alertHTML = `
                <div class="alert alert-${type} alert-dismissible mb-4" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            $('#alert-container').html(alertHTML);
            
            // Auto-nascondi dopo 5 secondi
            setTimeout(function() {
                $('.alert').fadeOut('slow', function() {
                    $(this).remove();
                });
            }, 5000);
        }
        
        // Inizializza la DataTable avanzata
        const dt_restaurants_table = $('.datatables-restaurants');
        
        if (dt_restaurants_table.length) {
            const dt_restaurants = dt_restaurants_table.DataTable({
                columnDefs: [
                    {
                        // Per Responsive
                        className: 'control',
                        searchable: false,
                        orderable: false,
                        responsivePriority: 2,
                        targets: 0
                    },
                    {
                        // Logo
                        targets: 1,
                        responsivePriority: 4,
                        orderable: false,
                        searchable: false
                    },
                    {
                        // Nome
                        targets: 2,
                        responsivePriority: 1
                    },
                    {
                        // Alias
                        targets: 3,
                        responsivePriority: 3
                    },
                    {
                        // Tipi di Cucina
                        targets: 4,
                        responsivePriority: 5
                    },
                    {
                        // Categorie
                        targets: 5,
                        responsivePriority: 6
                    },
                    {
                        // Contatti
                        targets: 6,
                        responsivePriority: 7,
                        orderable: false
                    },
                    {
                        // Azioni
                        targets: -1,
                        title: 'Azioni',
                        searchable: false,
                        orderable: false,
                        responsivePriority: 2
                    }
                ],
                order: [[0, 'desc']], // Ordina per ID in modo decrescente
                dom: '<"card-header border-bottom p-2"<"head-label"><"dt-action-buttons text-end"B>><"d-flex justify-content-between align-items-center row mx-2"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>t<"d-flex justify-content-between row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
                displayLength: 10,
                lengthMenu: [10, 25, 50, 75, 100],
                language: {
                    lengthMenu: '_MENU_',
                    search: '',
                    searchPlaceholder: 'Cerca ristoranti',
                    info: "Visualizzati da _START_ a _END_ di _TOTAL_ ristoranti",
                    paginate: {
                        next: '<i class="icon-base ti tabler-chevron-right icon-18px"></i>',
                        previous: '<i class="icon-base ti tabler-chevron-left icon-18px"></i>',
                        first: '<i class="icon-base ti tabler-chevrons-left icon-18px"></i>',
                        last: '<i class="icon-base ti tabler-chevrons-right icon-18px"></i>'
                    }
                },
                buttons: [
                    {
                        extend: 'collection',
                        className: 'btn btn-label-secondary dropdown-toggle me-3',
                        text: '<span class="d-flex align-items-center gap-2"><i class="icon-base ti tabler-upload icon-xs"></i> <span class="d-none d-sm-inline-block">Esporta</span></span>',
                        buttons: [
                            {
                                extend: 'print',
                                text: `<span class="d-flex align-items-center"><i class="icon-base ti tabler-printer me-1"></i>Stampa</span>`,
                                className: 'dropdown-item',
                                exportOptions: {
                                    columns: [2, 3, 4, 5, 6]
                                }
                            },
                            {
                                extend: 'csv',
                                text: `<span class="d-flex align-items-center"><i class="icon-base ti tabler-file-text me-1"></i>CSV</span>`,
                                className: 'dropdown-item',
                                exportOptions: {
                                    columns: [2, 3, 4, 5, 6]
                                }
                            },
                            {
                                extend: 'excel',
                                text: `<span class="d-flex align-items-center"><i class="icon-base ti tabler-file-spreadsheet me-1"></i>Excel</span>`,
                                className: 'dropdown-item',
                                exportOptions: {
                                    columns: [2, 3, 4, 5, 6]
                                }
                            },
                            {
                                extend: 'pdf',
                                text: `<span class="d-flex align-items-center"><i class="icon-base ti tabler-file-description me-1"></i>PDF</span>`,
                                className: 'dropdown-item',
                                exportOptions: {
                                    columns: [2, 3, 4, 5, 6]
                                }
                            }
                        ]
                    },
                    <?php if ($canCreate): ?>
                    {
                        text: '<span class="d-flex align-items-center gap-2"><i class="icon-base ti tabler-plus icon-xs"></i> <span class="d-none d-sm-inline-block">Nuovo Ristorante</span></span>',
                        className: 'add-new btn btn-primary',
                        attr: {
                            'data-bs-toggle': 'offcanvas',
                            'data-bs-target': '#offcanvasAddRestaurant'
                        }
                    }
                    <?php endif; ?>
                ],
                responsive: {
                    details: {
                        display: $.fn.dataTable.Responsive.display.modal({
                            header: function (row) {
                                const data = row.data();
                                return 'Dettagli di ' + data[2]; // Nome del ristorante
                            }
                        }),
                        type: 'column',
                        renderer: function (api, rowIdx, columns) {
                            const data = columns
                              .map(function (col) {
                                return col.title !== ''&&col.title !== undefined&&col.title !== 'Azioni'&&col.title !== 'ID'
                                  ? `<tr data-dt-row="${col.rowIndex}" data-dt-column="${col.columnIndex}">
                                      <td>${col.title}:</td>
                                      <td>${col.data}</td>
                                    </tr>`
                                  : '';
                              })
                              .join('');

                            return data
                              ? $('<table class="table table-responsive"/><tbody />').append(data)
                              : false;
                        }
                    }
                },
                initComplete: function () {
                    // Crea filtri per la tabella
                    this.api().columns([4, 5]).every(function (index) {
                        const column = this;
                        let label = '';
                        let containerClass = '';
    
                        if (index === 4) {
                            label = 'Tipo Cucina';
                            containerClass = '.cuisine_type_filter';
                        } else if (index === 5) {
                            label = 'Categoria';
                            containerClass = '.category_filter';
                        }
    
                        // Crea il filtro select
                        const select = document.createElement('select');
                        select.className = 'form-select text-capitalize filter-select';
                        select.innerHTML = `<option value="">${label}: Tutti</option>`;
                        
                        $(containerClass).append(select);
                        
                        // Popola il select con valori unici
                        const uniqueValues = new Set();
                        column.data().each(function (d) {
                            if (d&&d.includes(', ')) {
                                d.split(', ').forEach(item => {
                                    if (item.trim()) uniqueValues.add(item.trim());
                                });
                            } else if (d) {
                                uniqueValues.add(d);
                            }
                        });
                        
                        // Aggiungi le opzioni al select
                        [...uniqueValues].sort().forEach(val => {
                            select.innerHTML += `<option value="${val}">${val}</option>`;
                        });
                        
                        // Gestisci l'evento change
                        $(select).on('change', function () {
                            const val = $.fn.dataTable.util.escapeRegex(this.value);
                            column.search(val ? val : '', false, false).draw();
                        });
                    });
                }
            });
            
            // Personalizza l'aspetto del DataTable
            $('.datatables-restaurants').css('width', '100%');
            $('.card-header .dt-action-buttons').addClass('pt-3 pt-md-0');
            $('.card-header .dt-action-buttons .btn-group').addClass('d-flex gap-2');
            $('.card-header .head-label').append('<h5 class="card-title mb-0">Elenco Ristoranti</h5>');
        }
        
        // Visualizzazione dettagli ristorante
        if (userPermissions.canRead) {
            $(document).on('click', '.view-details', function() {
                const restaurantId = $(this).data('id');
                
                $.ajax({
                    url: '?action=get_details&id=' + restaurantId,
                    type: 'GET',
                    dataType: 'json',
                    success: function(data) {
                        if (!data.error) {
                            // Popola i campi del modal
                            $('#detail-name').text(data.name || '');
                            $('#detail-alias').text(data.alias || '');
                            $('#detail-cuisine').text(data.cuisine_types || '');
                            $('#detail-categories').text(data.categories || '');
                            $('#detail-phone').text(data.phone || 'Non specificato');
                            $('#detail-mobile').text(data.mobile || 'Non specificato');
                            $('#detail-description').text(data.description || '');
                            $('#detail-notes').text(data.notes || '');
                            
                            // Imposta le immagini
                            if (data.logo) {
                                $('#detail-logo').attr('src', '../../../' + data.logo);
                            } else {
                                $('#detail-logo').attr('src', '../../../assets/img/avatars/1.png');
                            }
                            
                            if (data.cover_image) {
                                $('#detail-cover').attr('src', '../../../' + data.cover_image);
                            } else {
                                $('#detail-cover').attr('src', '../../../assets/img/avatars/1.png');
                            }
                            
                            // Memorizza l'ID per il pulsante di modifica
                            $('.edit-from-view').data('id', data.id);
                            
                            // Apri l'offcanvas di visualizzazione
                            const viewOffcanvas = new bootstrap.Offcanvas(document.getElementById('offcanvasViewRestaurant'));
                            viewOffcanvas.show();
                        } else {
                            showAlert(data.error || 'Impossibile ottenere i dettagli del ristorante', 'danger');
                        }
                    },
                    error: function() {
                        showAlert('Si è verificato un errore di rete', 'danger');
                    }
                });
            });
        }
        
        // Passa dalla visualizzazione alla modifica
        if (userPermissions.canWrite) {
            $(document).on('click', '.edit-from-view', function() {
                const restaurantId = $(this).data('id');
                
                // Chiudi l'offcanvas di visualizzazione
                const viewOffcanvas = bootstrap.Offcanvas.getInstance(document.getElementById('offcanvasViewRestaurant'));
                viewOffcanvas.hide();
                
                // Apri l'offcanvas di modifica con un leggero ritardo
                setTimeout(function() {
                    $('.edit-restaurant[data-id="' + restaurantId + '"]').trigger('click');
                }, 500);
            });
            
            // Apertura form di modifica
            $(document).on('click', '.edit-restaurant', function() {
                const restaurantId = $(this).data('id');
                
                $.ajax({
                    url: '?action=get_edit_data&id=' + restaurantId,
                    type: 'GET',
                    dataType: 'json',
                    success: function(data) {
                        if (!data.error) {
                            // Popola i campi del form
                            $('#edit-id').val(data.id);
                            $('#edit-name').val(data.name || '');
                            $('#edit-alias').val(data.alias || '');
                            $('#edit-phone').val(data.phone || '');
                            $('#edit-mobile').val(data.mobile || '');
                            $('#edit-description').val(data.description || '');
                            $('#edit-notes').val(data.notes || '');
                            
                            // Imposta le immagini
                            if (data.logo) {
                                $('#edit-logo-preview').attr('src', '../../../' + data.logo);
                                $('#edit-logo-preview').show();
                            } else {
                                $('#edit-logo-preview').attr('src', '../../../assets/img/avatars/1.png');
                            }
                            
                            if (data.cover_image) {
                                $('#edit-cover-preview').attr('src', '../../../' + data.cover_image);
                                $('#edit-cover-preview').show();
                            } else {
                                $('#edit-cover-preview').attr('src', '../../../assets/img/avatars/1.png');
                            }
                            
                            // Seleziona le categorie e i tipi di cucina
                            if (data.cuisine_type_ids&&data.cuisine_type_ids.length > 0) {
                                $('#edit-cuisine-types').val(data.cuisine_type_ids).trigger('change');
                            } else {
                                $('#edit-cuisine-types').val(null).trigger('change');
                            }
                            
                            if (data.category_ids&&data.category_ids.length > 0) {
                                $('#edit-categories').val(data.category_ids).trigger('change');
                            } else {
                                $('#edit-categories').val(null).trigger('change');
                            }
                            
                            // Apri l'offcanvas di modifica
                            const editOffcanvas = new bootstrap.Offcanvas(document.getElementById('offcanvasEditRestaurant'));
                            editOffcanvas.show();
                        } else {
                            showAlert(data.error || 'Impossibile ottenere i dati del ristorante', 'danger');
                        }
                    },
                    error: function() {
                        showAlert('Si è verificato un errore di rete', 'danger');
                    }
                });
            });
            
            // Gestione invio del form di modifica
            $('#editRestaurantForm').on('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Chiudi l'offcanvas
                            const editOffcanvas = bootstrap.Offcanvas.getInstance(document.getElementById('offcanvasEditRestaurant'));
                            editOffcanvas.hide();
                            
                            // Mostra messaggio di successo
                            showAlert('Ristorante aggiornato con successo!');
                            
                            // Ricarica la pagina
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            showAlert(response.message || 'Si è verificato un errore durante l\'aggiornamento', 'danger');
                        }
                    },
                    error: function() {
                        showAlert('Si è verificato un errore di rete durante l\'aggiornamento', 'danger');
                    }
                });
            });
        }
        
        // Gestione aggiunta nuovo ristorante
        if (userPermissions.canCreate) {
            $('#addRestaurantForm').on('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Chiudi l'offcanvas
                            const addOffcanvas = bootstrap.Offcanvas.getInstance(document.getElementById('offcanvasAddRestaurant'));
                            addOffcanvas.hide();
                            
                            // Mostra messaggio di successo
                            showAlert('Ristorante aggiunto con successo!');
                            
                            // Resetta il form
                            $('#addRestaurantForm')[0].reset();
                            $('#cuisine_types').val(null).trigger('change');
                            $('#categories').val(null).trigger('change');
                            
                            // Ricarica la pagina
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            showAlert(response.message || 'Si è verificato un errore durante l\'aggiunta', 'danger');
                        }
                    },
                    error: function() {
                        showAlert('Si è verificato un errore di rete durante l\'aggiunta', 'danger');
                    }
                });
            });
            
            // Eliminazione ristorante
            $(document).on('click', '.delete-record', function() {
                const restaurantId = $(this).data('id');
                
                Swal.fire({
                    title: 'Sei sicuro?',
                    text: "Questa azione non può essere annullata!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sì, elimina!',
                    cancelButtonText: 'Annulla',
                    customClass: {
                        confirmButton: 'btn btn-primary me-3',
                        cancelButton: 'btn btn-label-secondary'
                    },
                    buttonsStyling: false
                }).then(function(result) {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: window.location.href,
                            type: 'POST',
                            data: {
                                action: 'delete',
                                id: restaurantId
                            },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    // Rimuovi la riga dalla tabella
                                    dt_restaurants_table.DataTable().row(function(idx, data) {
                                        return data[0] == restaurantId;
                                    }).remove().draw();
                                    
                                    // Mostra messaggio di successo
                                    showAlert('Ristorante eliminato con successo!');
                                } else {
                                    showAlert(response.message || 'Si è verificato un errore durante l\'eliminazione', 'danger');
                                }
                            },
                            error: function() {
                                showAlert('Si è verificato un errore di rete durante l\'eliminazione', 'danger');
                            }
                        });
                    }
                });
            });
        }
        
        // Disabilita elementi UI se non hai i permessi necessari
        if (!userPermissions.canRead) {
            $(document).off('click', '.view-details');
            $('.view-details').hide();
        }
        
        if (!userPermissions.canWrite) {
            $(document).off('click', '.edit-restaurant');
            $(document).off('click', '.edit-from-view');
            $('.edit-restaurant').hide();
            $('.edit-from-view').hide();
        }
        
        if (!userPermissions.canCreate) {
            $(document).off('click', '.delete-record');
            $('.delete-record').hide();
            $('.add-new').hide();
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