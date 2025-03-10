<?php
// debug_form.php - Versione completa e ottimizzata
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ottieni e registra l'input
$method = $_SERVER['REQUEST_METHOD'];
$input = file_get_contents('php://input');
$headers = getallheaders();

// Crea una directory logs se non esiste
if (!file_exists('logs')) {
    mkdir('logs', 0777, true);
}

// Scrivi in un file accessibile
$log_file = 'logs/debug_log_' . date('Y-m-d') . '.txt';
file_put_contents($log_file, date('[Y-m-d H:i:s] ') . "Method: $method\nInput: $input\n\n", FILE_APPEND);

// Elabora la richiesta
if ($method === 'POST' || $method === 'PUT') {
    require_once 'db_connection.php';
    
    $data = json_decode($input, true);
    file_put_contents($log_file, "Dati decodificati: " . print_r($data, true) . "\n", FILE_APPEND);
    
    if (!isset($data['name']) || empty($data['name'])) {
        echo json_encode(['success' => false, 'message' => 'Nome ruolo richiesto']);
        exit;
    }
    
    if ($method === 'POST') {
        // Crea un nuovo ruolo
        $stmt = $conn->prepare("INSERT INTO roles (name, description) VALUES (?, ?)");
        $description = isset($data['description']) ? $data['description'] : '';
        $stmt->bind_param("ss", $data['name'], $description);
        
        if ($stmt->execute()) {
            $roleId = $conn->insert_id;
            file_put_contents($log_file, "Ruolo creato con ID: $roleId\n", FILE_APPEND);
            
            // Gestisci i permessi, se presenti
            if (isset($data['permissions']) && is_array($data['permissions'])) {
                $permissionsAdded = 0;
                
                foreach ($data['permissions'] as $permission) {
                    // Verifica se abbiamo la categoria o l'ID
                    if (isset($permission['category'])) {
                        // Ottieni tutti i permessi per questa categoria
                        $category = $permission['category'];
                        $canRead = isset($permission['can_read']) ? (int)$permission['can_read'] : 0;
                        $canWrite = isset($permission['can_write']) ? (int)$permission['can_write'] : 0;
                        $canCreate = isset($permission['can_create']) ? (int)$permission['can_create'] : 0;
                        
                        file_put_contents($log_file, "Elaborazione permesso per categoria: $category\n", FILE_APPEND);
                        
                        $permQuery = $conn->prepare("SELECT id FROM permissions WHERE category = ?");
                        $permQuery->bind_param("s", $category);
                        $permQuery->execute();
                        $result = $permQuery->get_result();
                        
                        while ($row = $result->fetch_assoc()) {
                            $permId = $row['id'];
                            
                            // Inserisci i permessi per questo ruolo
                            $insertStmt = $conn->prepare("
                                INSERT INTO role_permissions (role_id, permission_id, can_read, can_write, can_create) 
                                VALUES (?, ?, ?, ?, ?)
                            ");
                            $insertStmt->bind_param("iiiii", $roleId, $permId, $canRead, $canWrite, $canCreate);
                            
                            if ($insertStmt->execute()) {
                                $permissionsAdded++;
                                file_put_contents($log_file, "Aggiunto permesso ID $permId per $category\n", FILE_APPEND);
                            } else {
                                file_put_contents($log_file, "Errore inserimento permesso: " . $conn->error . "\n", FILE_APPEND);
                            }
                        }
                    } 
                    elseif (isset($permission['id'])) {
                        // Usa direttamente l'ID del permesso
                        $permId = $permission['id'];
                        $canRead = isset($permission['can_read']) ? (int)$permission['can_read'] : 0;
                        $canWrite = isset($permission['can_write']) ? (int)$permission['can_write'] : 0;
                        $canCreate = isset($permission['can_create']) ? (int)$permission['can_create'] : 0;
                        
                        file_put_contents($log_file, "Elaborazione permesso ID: $permId\n", FILE_APPEND);
                        
                        $insertStmt = $conn->prepare("
                            INSERT INTO role_permissions (role_id, permission_id, can_read, can_write, can_create) 
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $insertStmt->bind_param("iiiii", $roleId, $permId, $canRead, $canWrite, $canCreate);
                        
                        if ($insertStmt->execute()) {
                            $permissionsAdded++;
                            file_put_contents($log_file, "Aggiunto permesso ID $permId\n", FILE_APPEND);
                        } else {
                            file_put_contents($log_file, "Errore inserimento permesso: " . $conn->error . "\n", FILE_APPEND);
                        }
                    }
                }
                
                file_put_contents($log_file, "Permessi aggiunti: $permissionsAdded\n", FILE_APPEND);
            }
            
            echo json_encode(['success' => true, 'id' => $roleId, 'message' => 'Ruolo creato con successo']);
        } else {
            file_put_contents($log_file, "Errore creazione ruolo: " . $conn->error . "\n", FILE_APPEND);
            echo json_encode(['success' => false, 'message' => 'Errore creazione ruolo: ' . $conn->error]);
        }
    } else { // PUT - aggiorna un ruolo esistente
        if (!isset($data['id'])) {
            echo json_encode(['success' => false, 'message' => 'ID ruolo richiesto per aggiornamento']);
            exit;
        }
        
        $stmt = $conn->prepare("UPDATE roles SET name = ?, description = ? WHERE id = ?");
        $description = isset($data['description']) ? $data['description'] : '';
        $stmt->bind_param("ssi", $data['name'], $description, $data['id']);
        
        if ($stmt->execute()) {
            $roleId = $data['id'];
            file_put_contents($log_file, "Ruolo aggiornato con ID: $roleId\n", FILE_APPEND);
            
            // Elimina i permessi esistenti
            $deleteStmt = $conn->prepare("DELETE FROM role_permissions WHERE role_id = ?");
            $deleteStmt->bind_param("i", $roleId);
            $deleteStmt->execute();
            file_put_contents($log_file, "Permessi esistenti eliminati per ruolo ID: $roleId\n", FILE_APPEND);
            
            // Aggiungi i nuovi permessi
            if (isset($data['permissions']) && is_array($data['permissions'])) {
                $permissionsAdded = 0;
                
                foreach ($data['permissions'] as $permission) {
                    // Verifica se abbiamo la categoria o l'ID
                    if (isset($permission['category'])) {
                        // Ottieni tutti i permessi per questa categoria
                        $category = $permission['category'];
                        $canRead = isset($permission['can_read']) ? (int)$permission['can_read'] : 0;
                        $canWrite = isset($permission['can_write']) ? (int)$permission['can_write'] : 0;
                        $canCreate = isset($permission['can_create']) ? (int)$permission['can_create'] : 0;
                        
                        file_put_contents($log_file, "Elaborazione permesso per categoria: $category\n", FILE_APPEND);
                        
                        $permQuery = $conn->prepare("SELECT id FROM permissions WHERE category = ?");
                        $permQuery->bind_param("s", $category);
                        $permQuery->execute();
                        $result = $permQuery->get_result();
                        
                        while ($row = $result->fetch_assoc()) {
                            $permId = $row['id'];
                            
                            // Inserisci i permessi per questo ruolo
                            $insertStmt = $conn->prepare("
                                INSERT INTO role_permissions (role_id, permission_id, can_read, can_write, can_create) 
                                VALUES (?, ?, ?, ?, ?)
                            ");
                            $insertStmt->bind_param("iiiii", $roleId, $permId, $canRead, $canWrite, $canCreate);
                            
                            if ($insertStmt->execute()) {
                                $permissionsAdded++;
                                file_put_contents($log_file, "Aggiunto permesso ID $permId per $category\n", FILE_APPEND);
                            } else {
                                file_put_contents($log_file, "Errore inserimento permesso: " . $conn->error . "\n", FILE_APPEND);
                            }
                        }
                    } 
                    elseif (isset($permission['id'])) {
                        // Usa direttamente l'ID del permesso
                        $permId = $permission['id'];
                        $canRead = isset($permission['can_read']) ? (int)$permission['can_read'] : 0;
                        $canWrite = isset($permission['can_write']) ? (int)$permission['can_write'] : 0;
                        $canCreate = isset($permission['can_create']) ? (int)$permission['can_create'] : 0;
                        
                        file_put_contents($log_file, "Elaborazione permesso ID: $permId\n", FILE_APPEND);
                        
                        $insertStmt = $conn->prepare("
                            INSERT INTO role_permissions (role_id, permission_id, can_read, can_write, can_create) 
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $insertStmt->bind_param("iiiii", $roleId, $permId, $canRead, $canWrite, $canCreate);
                        
                        if ($insertStmt->execute()) {
                            $permissionsAdded++;
                            file_put_contents($log_file, "Aggiunto permesso ID $permId\n", FILE_APPEND);
                        } else {
                            file_put_contents($log_file, "Errore inserimento permesso: " . $conn->error . "\n", FILE_APPEND);
                        }
                    }
                }
                
                file_put_contents($log_file, "Permessi aggiunti: $permissionsAdded\n", FILE_APPEND);
            }
            
            echo json_encode(['success' => true, 'message' => 'Ruolo aggiornato con successo']);
        } else {
            file_put_contents($log_file, "Errore aggiornamento ruolo: " . $conn->error . "\n", FILE_APPEND);
            echo json_encode(['success' => false, 'message' => 'Errore aggiornamento ruolo: ' . $conn->error]);
        }
    }
    
    $conn->close();
} else {
    // Metodo non supportato
    echo json_encode(['success' => false, 'message' => 'Metodo non supportato: ' . $method]);
}
?>