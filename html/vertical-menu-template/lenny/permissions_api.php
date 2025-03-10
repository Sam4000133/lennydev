<?php
// Include the database connection
require_once 'db_connection.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Get the HTTP method
$method = $_SERVER['REQUEST_METHOD'];

// Handle different HTTP methods
switch ($method) {
    case 'GET':
        // Get all permissions or permissions by category
        if (isset($_GET['category'])) {
            $category = $_GET['category'];
            $stmt = $conn->prepare("SELECT * FROM permissions WHERE category = ? ORDER BY name");
            $stmt->bind_param("s", $category);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            // Get all permissions
            $sql = "SELECT * FROM permissions ORDER BY category, name";
            $result = $conn->query($sql);
        }
        
        $permissions = [];
        while ($row = $result->fetch_assoc()) {
            $permissions[] = $row;
        }
        
        echo json_encode(['success' => true, 'data' => $permissions]);
        break;
        
    case 'POST':
        // Create a new permission
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['name']) || !isset($data['category'])) {
            echo json_encode(['success' => false, 'message' => 'Permission name and category are required']);
            exit;
        }
        
        $stmt = $conn->prepare("INSERT INTO permissions (name, category, description) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $data['name'], $data['category'], $data['description']);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'id' => $conn->insert_id, 
                'message' => 'Permission created successfully'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error creating permission: ' . $conn->error]);
        }
        break;
        
    case 'PUT':
        // Update an existing permission
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['id']) || !isset($data['name']) || !isset($data['category'])) {
            echo json_encode(['success' => false, 'message' => 'Permission ID, name, and category are required']);
            exit;
        }
        
        $stmt = $conn->prepare("UPDATE permissions SET name = ?, category = ?, description = ? WHERE id = ?");
        $stmt->bind_param("sssi", $data['name'], $data['category'], $data['description'], $data['id']);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Permission updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating permission: ' . $conn->error]);
        }
        break;
        
    case 'DELETE':
        // Delete a permission
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Permission ID is required']);
            exit;
        }
        
        // Check if permission is being used
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM role_permissions WHERE permission_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] > 0) {
            echo json_encode([
                'success' => false, 
                'message' => 'Cannot delete permission: it is assigned to ' . $row['count'] . ' roles'
            ]);
            exit;
        }
        
        $stmt = $conn->prepare("DELETE FROM permissions WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Permission deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error deleting permission: ' . $conn->error]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Method not supported']);
        break;
}

$conn->close();
?>
