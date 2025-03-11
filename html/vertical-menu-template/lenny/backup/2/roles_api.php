<?php
// Include the database connection
require_once 'db_connection.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Get the HTTP method
$method = $_SERVER['REQUEST_METHOD'];

// Enable debug logging
$debug = true;

// Debug logging function
function debug_log($message, $data = null) {
    global $debug;
    if ($debug) {
        $log = date('[Y-m-d H:i:s] ') . $message;
        if ($data !== null) {
            $log .= ': ' . (is_string($data) ? $data : json_encode($data));
        }
        $log .= "\n";
        file_put_contents('roles_debug.log', $log, FILE_APPEND);
    }
}

// Log the request
debug_log("Request method", $method);
if ($method == 'POST' || $method == 'PUT') {
    $input = file_get_contents('php://input');
    debug_log("Request body", $input);
}

// Helper function for reference binding
function refValues($arr) {
    $refs = array();
    foreach($arr as $key => $value)
        $refs[$key] = &$arr[$key];
    return $refs;
}

// Handle different HTTP methods
switch ($method) {
    case 'GET':
        // Get all roles or a specific role
        if (isset($_GET['id'])) {
            // Get a specific role
            $id = $_GET['id'];
            debug_log("GET role by ID", $id);
            
            $stmt = $conn->prepare("SELECT * FROM roles WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $role = $result->fetch_assoc();
            
            if ($role) {
                // Get the permissions for this role
                $stmt = $conn->prepare("
                    SELECT p.id, p.name, p.category, rp.can_read, rp.can_write, rp.can_create 
                    FROM permissions p
                    LEFT JOIN role_permissions rp ON p.id = rp.permission_id AND rp.role_id = ?
                    ORDER BY p.category, p.name
                ");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $permissions_result = $stmt->get_result();
                
                $permissions = [];
                while ($permission = $permissions_result->fetch_assoc()) {
                    $permissions[] = $permission;
                }
                
                $role['permissions'] = $permissions;
                
                // Get users with this role
                $stmt = $conn->prepare("
                    SELECT id, username, email, full_name, status, avatar
                    FROM users
                    WHERE role_id = ?
                ");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $users_result = $stmt->get_result();
                
                $users = [];
                while ($user = $users_result->fetch_assoc()) {
                    $users[] = $user;
                }
                
                $role['users'] = $users;
                
                debug_log("Role data retrieved", count($permissions) . " permissions");
                echo json_encode(['success' => true, 'data' => $role]);
            } else {
                debug_log("Role not found", $id);
                echo json_encode(['success' => false, 'message' => 'Role not found']);
            }
        } else {
            // Get all roles with user count
            debug_log("GET all roles");
            
            $sql = "
                SELECT r.*, COUNT(u.id) as user_count
                FROM roles r
                LEFT JOIN users u ON r.id = u.role_id
                GROUP BY r.id
                ORDER BY r.name
            ";
            $result = $conn->query($sql);
            
            $roles = [];
            while ($row = $result->fetch_assoc()) {
                // Get a few users for this role (for the avatar display)
                $stmt = $conn->prepare("
                    SELECT id, username, full_name, avatar
                    FROM users
                    WHERE role_id = ?
                    LIMIT 4
                ");
                $stmt->bind_param("i", $row['id']);
                $stmt->execute();
                $users_result = $stmt->get_result();
                
                $sample_users = [];
                while ($user = $users_result->fetch_assoc()) {
                    $sample_users[] = $user;
                }
                
                $row['sample_users'] = $sample_users;
                $roles[] = $row;
            }
            
            debug_log("Retrieved roles", count($roles));
            echo json_encode(['success' => true, 'data' => $roles]);
        }
        break;
        
    case 'POST':
        // Create a new role
        $data = json_decode(file_get_contents('php://input'), true);
        debug_log("POST create role", $data);
        
        if (!isset($data['name']) || empty($data['name'])) {
            debug_log("Error: Role name is required");
            echo json_encode(['success' => false, 'message' => 'Role name is required']);
            exit;
        }
        
        // Insert the new role
        $stmt = $conn->prepare("INSERT INTO roles (name, description) VALUES (?, ?)");
        $description = isset($data['description']) ? $data['description'] : '';
        $stmt->bind_param("ss", $data['name'], $description);
        
        if ($stmt->execute()) {
            $role_id = $conn->insert_id;
            debug_log("Role created", $role_id);
            
            // Insert permissions if provided
            if (isset($data['permissions']) && is_array($data['permissions']) && count($data['permissions']) > 0) {
                debug_log("Inserting permissions", count($data['permissions']));
                
                $insertSuccess = 0;
                $insertErrors = 0;
                
                foreach ($data['permissions'] as $permission) {
                    if (!isset($permission['id'])) {
                        debug_log("Skipping permission without ID");
                        continue;
                    }
                    
                    $perm_id = $permission['id'];
                    $can_read = isset($permission['can_read']) ? (int)$permission['can_read'] : 0;
                    $can_write = isset($permission['can_write']) ? (int)$permission['can_write'] : 0;
                    $can_create = isset($permission['can_create']) ? (int)$permission['can_create'] : 0;
                    
                    try {
                        $perm_stmt = $conn->prepare("
                            INSERT INTO role_permissions (role_id, permission_id, can_read, can_write, can_create) 
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $perm_stmt->bind_param("iiiii", $role_id, $perm_id, $can_read, $can_write, $can_create);
                        
                        if ($perm_stmt->execute()) {
                            $insertSuccess++;
                        } else {
                            debug_log("Error inserting permission", "ID: $perm_id, Error: " . $conn->error);
                            $insertErrors++;
                        }
                    } catch (Exception $e) {
                        debug_log("Exception when inserting permission", $e->getMessage());
                        $insertErrors++;
                    }
                }
                
                debug_log("Permission inserts completed", "Success: $insertSuccess, Errors: $insertErrors");
            } else {
                debug_log("No permissions to insert");
            }
            
            echo json_encode(['success' => true, 'id' => $role_id, 'message' => 'Role created successfully']);
        } else {
            debug_log("Error creating role", $conn->error);
            echo json_encode(['success' => false, 'message' => 'Error creating role: ' . $conn->error]);
        }
        break;
        
    case 'PUT':
        // Update an existing role
        $data = json_decode(file_get_contents('php://input'), true);
        debug_log("PUT update role", $data);
        
        if (!isset($data['id']) || !isset($data['name']) || empty($data['name'])) {
            debug_log("Error: Role ID and name are required");
            echo json_encode(['success' => false, 'message' => 'Role ID and name are required']);
            exit;
        }
        
        // Update the role
        $stmt = $conn->prepare("UPDATE roles SET name = ?, description = ? WHERE id = ?");
        $description = isset($data['description']) ? $data['description'] : '';
        $stmt->bind_param("ssi", $data['name'], $description, $data['id']);
        
        if ($stmt->execute()) {
            debug_log("Role updated", $data['id']);
            
            // Delete existing permissions for this role
            $delete_stmt = $conn->prepare("DELETE FROM role_permissions WHERE role_id = ?");
            $delete_stmt->bind_param("i", $data['id']);
            $delete_result = $delete_stmt->execute();
            
            debug_log("Deleted old permissions", $delete_result ? "Success" : "Failed: " . $conn->error);
            
            // Insert new permissions
            if (isset($data['permissions']) && is_array($data['permissions']) && count($data['permissions']) > 0) {
                debug_log("Inserting new permissions", count($data['permissions']));
                
                $insertSuccess = 0;
                $insertErrors = 0;
                
                foreach ($data['permissions'] as $permission) {
                    if (!isset($permission['id'])) {
                        debug_log("Skipping permission without ID");
                        continue;
                    }
                    
                    $perm_id = $permission['id'];
                    $can_read = isset($permission['can_read']) ? (int)$permission['can_read'] : 0;
                    $can_write = isset($permission['can_write']) ? (int)$permission['can_write'] : 0;
                    $can_create = isset($permission['can_create']) ? (int)$permission['can_create'] : 0;
                    
                    try {
                        $perm_stmt = $conn->prepare("
                            INSERT INTO role_permissions (role_id, permission_id, can_read, can_write, can_create) 
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $perm_stmt->bind_param("iiiii", $data['id'], $perm_id, $can_read, $can_write, $can_create);
                        
                        if ($perm_stmt->execute()) {
                            $insertSuccess++;
                        } else {
                            debug_log("Error inserting permission", "ID: $perm_id, Error: " . $conn->error);
                            $insertErrors++;
                        }
                    } catch (Exception $e) {
                        debug_log("Exception when inserting permission", $e->getMessage());
                        $insertErrors++;
                    }
                }
                
                debug_log("Permission inserts completed", "Success: $insertSuccess, Errors: $insertErrors");
            } else {
                debug_log("No permissions to insert");
            }
            
            echo json_encode(['success' => true, 'message' => 'Role updated successfully']);
        } else {
            debug_log("Error updating role", $conn->error);
            echo json_encode(['success' => false, 'message' => 'Error updating role: ' . $conn->error]);
        }
        break;
        
    case 'DELETE':
        // Delete a role
        $id = $_GET['id'] ?? null;
        debug_log("DELETE role", $id);
        
        if (!$id) {
            debug_log("Error: Role ID is required");
            echo json_encode(['success' => false, 'message' => 'Role ID is required']);
            exit;
        }
        
        // Check if there are users with this role
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] > 0) {
            debug_log("Cannot delete role - has users", $row['count']);
            echo json_encode(['success' => false, 'message' => 'Cannot delete role: there are ' . $row['count'] . ' users assigned to this role']);
            exit;
        }
        
        // Delete the role permissions first
        $stmt = $conn->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        debug_log("Deleted role permissions");
        
        // Delete the role
        $stmt = $conn->prepare("DELETE FROM roles WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            debug_log("Role deleted successfully");
            echo json_encode(['success' => true, 'message' => 'Role deleted successfully']);
        } else {
            debug_log("Error deleting role", $conn->error);
            echo json_encode(['success' => false, 'message' => 'Error deleting role: ' . $conn->error]);
        }
        break;
        
    default:
        debug_log("Method not supported", $method);
        echo json_encode(['success' => false, 'message' => 'Method not supported']);
        break;
}

$conn->close();
?>