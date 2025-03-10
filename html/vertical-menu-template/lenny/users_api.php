<?php
// Include the database connection
require_once 'db_connection.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Enable error logging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Create logs directory if it doesn't exist
if (!file_exists('logs')) {
    mkdir('logs', 0777, true);
}

// Log function
function log_action($message, $data = null) {
    $log_file = 'logs/users_api_' . date('Y-m-d') . '.log';
    $log_entry = date('[Y-m-d H:i:s] ') . $message;
    
    if ($data !== null) {
        if (is_array($data) || is_object($data)) {
            $log_entry .= ' ' . json_encode($data);
        } else {
            $log_entry .= ' ' . $data;
        }
    }
    
    file_put_contents($log_file, $log_entry . PHP_EOL, FILE_APPEND);
}

// Helper function for reference binding
function refValues($arr) {
    $refs = [];
    foreach ($arr as $key => $value) {
        $refs[$key] = &$arr[$key];
    }
    return $refs;
}

// Handle DataTables server-side processing for users
if (isset($_GET['draw'])) {
    $draw = intval($_GET['draw']);
    $start = isset($_GET['start']) ? intval($_GET['start']) : 0;
    $length = isset($_GET['length']) ? intval($_GET['length']) : 10;
    $search = isset($_GET['search']['value']) ? $_GET['search']['value'] : '';
    
    log_action('DataTables request', ['draw' => $draw, 'start' => $start, 'length' => $length, 'search' => $search]);
    
    // Count total records
    $totalRecordsQuery = "SELECT COUNT(*) as count FROM users";
    $result = $conn->query($totalRecordsQuery);
    $totalRecords = $result->fetch_assoc()['count'];
    
    // Build search condition
    $searchCondition = "";
    if (!empty($search)) {
        $searchCondition = " AND (
            u.username LIKE '%" . $conn->real_escape_string($search) . "%' OR 
            u.email LIKE '%" . $conn->real_escape_string($search) . "%' OR 
            u.full_name LIKE '%" . $conn->real_escape_string($search) . "%' OR 
            r.name LIKE '%" . $conn->real_escape_string($search) . "%'
        )";
    }
    
    // Count filtered records
    $filteredRecordsQuery = "
        SELECT COUNT(*) as count 
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE 1=1" . $searchCondition;
    
    $result = $conn->query($filteredRecordsQuery);
    $filteredRecords = $result->fetch_assoc()['count'];
    
    // Get ordering
    $orderColumn = 'u.username'; // Default order column
    $orderDirection = 'ASC'; // Default order direction
    
    if (isset($_GET['order']) && isset($_GET['order'][0]['column']) && isset($_GET['order'][0]['dir'])) {
        $columnIndex = intval($_GET['order'][0]['column']);
        $columnName = $_GET['columns'][$columnIndex]['data'] ?? 'username';
        $orderDirection = strtoupper($_GET['order'][0]['dir']) === 'DESC' ? 'DESC' : 'ASC';
        
        // Map column names to database fields
        $columnMap = [
            'username' => 'u.username',
            'email' => 'u.email',
            'full_name' => 'u.full_name',
            'role_name' => 'r.name',
            'status' => 'u.status'
        ];
        
        $orderColumn = isset($columnMap[$columnName]) ? $columnMap[$columnName] : 'u.username';
    }
    
    // Fetch users data
    $query = "
        SELECT 
            u.id, 
            u.username, 
            u.email, 
            u.full_name, 
            u.status, 
            u.avatar,
            r.id as role_id, 
            r.name as role_name
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE 1=1" . $searchCondition . "
        ORDER BY " . $orderColumn . " " . $orderDirection . "
        LIMIT " . $start . ", " . $length;
    
    $result = $conn->query($query);
    
    if (!$result) {
        log_action('Query error', $conn->error);
        echo json_encode([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => [],
            'error' => $conn->error
        ]);
        exit;
    }
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        // Set default avatar if not provided
        if (empty($row['avatar'])) {
            $avatarNum = ($row['id'] % 14) + 1; // Use numbers 1-14 for avatars
            $row['avatar'] = "../../../assets/img/avatars/{$avatarNum}.png";
        }
        
        $data[] = $row;
    }
    
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $totalRecords,
        'recordsFiltered' => $filteredRecords,
        'data' => $data
    ]);
    
    $conn->close();
    exit;
}

// Handle CRUD operations for users
$method = $_SERVER['REQUEST_METHOD'];
log_action('API Request', $method);

switch ($method) {
    case 'GET':
        // Get user(s)
        if (isset($_GET['id'])) {
            // Get a specific user
            $id = intval($_GET['id']);
            log_action('GET user', $id);
            
            $stmt = $conn->prepare("
                SELECT u.*, r.name as role_name
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.id = ?
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            if ($user) {
                // Remove password from response
                unset($user['password']);
                
                // Set default avatar if not provided
                if (empty($user['avatar'])) {
                    $avatarNum = ($user['id'] % 14) + 1;
                    $user['avatar'] = "../../../assets/img/avatars/{$avatarNum}.png";
                }
                
                echo json_encode(['success' => true, 'data' => $user]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Utente non trovato']);
            }
        } else {
            // Get all users
            log_action('GET all users');
            
            $query = "
                SELECT u.id, u.username, u.email, u.full_name, u.status, u.avatar,
                       r.id as role_id, r.name as role_name
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                ORDER BY u.username
            ";
            $result = $conn->query($query);
            
            if (!$result) {
                log_action('Query error', $conn->error);
                echo json_encode(['success' => false, 'message' => 'Errore di query: ' . $conn->error]);
                break;
            }
            
            $users = [];
            while ($row = $result->fetch_assoc()) {
                // Set default avatar if not provided
                if (empty($row['avatar'])) {
                    $avatarNum = ($row['id'] % 14) + 1;
                    $row['avatar'] = "../../../assets/img/avatars/{$avatarNum}.png";
                }
                
                $users[] = $row;
            }
            
            echo json_encode(['success' => true, 'data' => $users]);
        }
        break;
        
    case 'POST':
        // Create a new user
        $requestData = json_decode(file_get_contents('php://input'), true);
        log_action('POST create user', $requestData);
        
        if (!isset($requestData['username']) || !isset($requestData['email']) || !isset($requestData['password'])) {
            echo json_encode(['success' => false, 'message' => 'Username, email e password sono richiesti']);
            break;
        }
        
        // Check if username already exists
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE username = ?");
        $stmt->bind_param("s", $requestData['username']);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Username già in uso']);
            break;
        }
        
        // Check if email already exists
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE email = ?");
        $stmt->bind_param("s", $requestData['email']);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Email già in uso']);
            break;
        }
        
        // Hash the password
        $hashedPassword = password_hash($requestData['password'], PASSWORD_DEFAULT);
        
        // Set default values for optional fields
        $fullName = $requestData['full_name'] ?? '';
        $roleId = !empty($requestData['role_id']) ? $requestData['role_id'] : null;
        $status = $requestData['status'] ?? 'active';
        
        // Insert new user
        $stmt = $conn->prepare("
            INSERT INTO users (username, email, password, full_name, role_id, status)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssssis", 
            $requestData['username'],
            $requestData['email'],
            $hashedPassword,
            $fullName,
            $roleId,
            $status
        );
        
        if ($stmt->execute()) {
            $userId = $conn->insert_id;
            log_action('User created', ['id' => $userId, 'username' => $requestData['username']]);
            
            echo json_encode([
                'success' => true,
                'id' => $userId,
                'message' => 'Utente creato con successo'
            ]);
        } else {
            log_action('Error creating user', $conn->error);
            echo json_encode(['success' => false, 'message' => 'Errore nella creazione dell\'utente: ' . $conn->error]);
        }
        break;
        
    case 'PUT':
        // Update an existing user
        $requestData = json_decode(file_get_contents('php://input'), true);
        log_action('PUT update user', $requestData);
        
        if (!isset($requestData['id'])) {
            echo json_encode(['success' => false, 'message' => 'ID utente richiesto']);
            break;
        }
        
        $userId = intval($requestData['id']);
        
        // Check if user exists
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] == 0) {
            echo json_encode(['success' => false, 'message' => 'Utente non trovato']);
            break;
        }
        
        // Check if username is already taken by another user
        if (isset($requestData['username'])) {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE username = ? AND id != ?");
            $stmt->bind_param("si", $requestData['username'], $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['count'] > 0) {
                echo json_encode(['success' => false, 'message' => 'Username già in uso da un altro utente']);
                break;
            }
        }
        
        // Check if email is already taken by another user
        if (isset($requestData['email'])) {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE email = ? AND id != ?");
            $stmt->bind_param("si", $requestData['email'], $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['count'] > 0) {
                echo json_encode(['success' => false, 'message' => 'Email già in uso da un altro utente']);
                break;
            }
        }
        
        // Build UPDATE query
        $sql = "UPDATE users SET ";
        $types = "";
        $params = [];
        
        if (isset($requestData['username'])) {
            $sql .= "username = ?, ";
            $types .= "s";
            $params[] = $requestData['username'];
        }
        
        if (isset($requestData['email'])) {
            $sql .= "email = ?, ";
            $types .= "s";
            $params[] = $requestData['email'];
        }
        
        if (isset($requestData['full_name'])) {
            $sql .= "full_name = ?, ";
            $types .= "s";
            $params[] = $requestData['full_name'];
        }
        
        if (isset($requestData['role_id'])) {
            $sql .= "role_id = ?, ";
            $types .= "i";
            $params[] = $requestData['role_id'];
        }
        
        if (isset($requestData['status'])) {
            $sql .= "status = ?, ";
            $types .= "s";
            $params[] = $requestData['status'];
        }
        
        if (isset($requestData['password']) && !empty($requestData['password'])) {
            $hashedPassword = password_hash($requestData['password'], PASSWORD_DEFAULT);
            $sql .= "password = ?, ";
            $types .= "s";
            $params[] = $hashedPassword;
        }
        
        // Remove trailing comma and space
        $sql = rtrim($sql, ", ");
        
        // Add WHERE clause
        $sql .= " WHERE id = ?";
        $types .= "i";
        $params[] = $userId;
        
        if (count($params) > 1) { // At least one field to update plus the ID
            $stmt = $conn->prepare($sql);
            
            // Bind parameters dynamically
            $bindParams = array($types);
            foreach ($params as $key => $value) {
                $bindParams[] = $value;
            }
            
            call_user_func_array(array($stmt, 'bind_param'), refValues($bindParams));
            
            if ($stmt->execute()) {
                log_action('User updated', ['id' => $userId]);
                echo json_encode(['success' => true, 'message' => 'Utente aggiornato con successo']);
            } else {
                log_action('Error updating user', $conn->error);
                echo json_encode(['success' => false, 'message' => 'Errore nell\'aggiornamento dell\'utente: ' . $conn->error]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Nessun campo da aggiornare']);
        }
        break;
        
    case 'DELETE':
        // Delete a user
        if (!isset($_GET['id'])) {
            echo json_encode(['success' => false, 'message' => 'ID utente richiesto']);
            break;
        }
        
        $userId = intval($_GET['id']);
        log_action('DELETE user', $userId);
        
        // Check if user exists
        $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'Utente non trovato']);
            break;
        }
        
        // Delete the user
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        
        if ($stmt->execute()) {
            log_action('User deleted', ['id' => $userId, 'username' => $user['username']]);
            echo json_encode(['success' => true, 'message' => 'Utente eliminato con successo']);
        } else {
            log_action('Error deleting user', $conn->error);
            echo json_encode(['success' => false, 'message' => 'Errore nell\'eliminazione dell\'utente: ' . $conn->error]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Metodo non supportato']);
        break;
}

$conn->close();
