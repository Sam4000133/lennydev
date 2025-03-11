<?php
// Include the database connection
require_once 'db_connection.php';

// Create roles table
$sql_roles = "CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql_roles) === FALSE) {
    echo "Error creating roles table: " . $conn->error;
}

// Create permissions table
$sql_permissions = "CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    category VARCHAR(50) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql_permissions) === FALSE) {
    echo "Error creating permissions table: " . $conn->error;
}

// Create role_permissions table (many-to-many relationship)
$sql_role_permissions = "CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    can_read BOOLEAN DEFAULT FALSE,
    can_write BOOLEAN DEFAULT FALSE,
    can_create BOOLEAN DEFAULT FALSE,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
)";

if ($conn->query($sql_role_permissions) === FALSE) {
    echo "Error creating role_permissions table: " . $conn->error;
}

// Create users table
$sql_users = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    full_name VARCHAR(100),
    role_id INT,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    avatar VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id)
)";

if ($conn->query($sql_users) === FALSE) {
    echo "Error creating users table: " . $conn->error;
}

// Insert default roles if they don't exist
$default_roles = [
    ['Administrator', 'Full access to all features'],
    ['Ristoratore', 'Restaurant owner with access to restaurant management features'],
    ['Users', 'Regular users with limited access'],
    ['Driver', 'Delivery drivers with access to delivery features'],
    ['Staff Ordini', 'Order management staff with access to order-related features']
];

$stmt = $conn->prepare("INSERT IGNORE INTO roles (name, description) VALUES (?, ?)");
$stmt->bind_param("ss", $role_name, $role_description);

foreach ($default_roles as $role) {
    $role_name = $role[0];
    $role_description = $role[1];
    $stmt->execute();
}
$stmt->close();

// Insert default permissions based on menu categories
$permissions_categories = [
    'Panoramica',
    'Gestione Ordini',
    'Ordini in corso',
    'Cronologia ordini',
    'Resi e rimborsi',
    'Gestione Ristorante',
    'Informazioni base',
    'Indirizzo e consegna',
    'Orari di apertura',
    'Menu',
    'Dati operativi',
    'Pagamenti',
    'Commissioni',
    'Notifiche',
    'Integrazione IA',
    'Documenti',
    'Promozioni',
    'Gestione Driver',
    'Registrazione driver',
    'Assegnazione ordini',
    'Tracking GPS',
    'Pagamenti driver',
    'Analytics',
    'Report vendite',
    'Performance',
    'Statistiche prodotti',
    'CRM',
    'Clienti',
    'Recensioni',
    'Reclami',
    'Marketplace',
    'Elenco ristoranti',
    'Filtri e ricerca',
    'Categorie',
    'Comunicazioni',
    'Email automatiche',
    'SMS',
    'Chat supporto',
    'Abbonamenti',
    'Piani membership',
    'Fatturazione',
    'Sistema',
    'Impostazioni',
    'Ruoli & permessi',
    'Integrazioni',
    'Sicurezza',
    'Privacy',
    'Backup'
];

$stmt = $conn->prepare("INSERT IGNORE INTO permissions (name, category, description) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $perm_name, $perm_category, $perm_description);

foreach ($permissions_categories as $category) {
    $perm_name = $category;
    $perm_category = $category;
    $perm_description = "Permission for " . $category;
    $stmt->execute();
}
$stmt->close();

// Give Administrator role all permissions
$sql = "INSERT IGNORE INTO role_permissions (role_id, permission_id, can_read, can_write, can_create)
        SELECT 1, id, TRUE, TRUE, TRUE FROM permissions";
$conn->query($sql);

echo "Database setup completed successfully!";

$conn->close();
?>
