# Lenny - Role & Permissions Management System

This is a role and permissions management system for the Lenny food delivery platform, allowing admins to create and manage user roles with specific permissions.

## Features

- Role management (Create, Read, Update, Delete)
- Permission management
- User management with role assignments
- Responsive design
- DataTables integration for user listing

## Setup Instructions

### Requirements

- PHP 7.4 or higher
- MariaDB/MySQL
- Web server (Apache, Nginx, etc.)
- Laragon (recommended for local development)

### Installation

1. Clone this repository to your Laragon web directory (usually `C:\laragon\www\lenny`).

2. Create a database named `lennytest` in MariaDB:
   - Open Laragon
   - Click on "Database" to open HeidiSQL
   - Create a new database named `lennytest`

3. Access the application:
   - Open your browser and navigate to `http://localhost/lenny/ruoli-permessi.php`
   - The database tables will be automatically created on the first visit

### Files Description

- `db_connection.php`: Database connection settings
- `db_setup.php`: Creates database tables and inserts initial data
- `roles_api.php`: API for role management
- `permissions_api.php`: API for permission management
- `users_api.php`: API for user management
- `ruoli-permessi.php`: Main page for role and permission management
- `sidebar.php`: Navigation sidebar
- `navbar.php`: Top navigation bar
- `roles_manager.js`: JavaScript for role management

## Usage

### Managing Roles

1. **View Roles**: All existing roles are displayed as cards on the main page.
2. **Create Role**: Click the "Aggiungi nuovo ruolo" button to create a new role.
3. **Edit Role**: Click "Modifica Ruolo" on any role card to edit its permissions.
4. **Delete Role**: Click the trash icon on any role card to delete it.

### Managing Users

1. **View Users**: All users are displayed in the data table at the bottom of the page.
2. **Create User**: Click the "Aggiungi nuovo utente" button above the table.
3. **Edit User**: Click the edit icon in the action column of the user row.
4. **Delete User**: Click the trash icon in the action column of the user row.

## API Documentation

### Roles API

- `GET roles_api.php`: Get all roles
- `GET roles_api.php?id={id}`: Get a specific role with its permissions
- `POST roles_api.php`: Create a new role
- `PUT roles_api.php`: Update an existing role
- `DELETE roles_api.php?id={id}`: Delete a role

### Permissions API

- `GET permissions_api.php`: Get all permissions
- `GET permissions_api.php?category={category}`: Get permissions by category
- `POST permissions_api.php`: Create a new permission
- `PUT permissions_api.php`: Update an existing permission
- `DELETE permissions_api.php?id={id}`: Delete a permission

### Users API

- `GET users_api.php`: Get all users
- `GET users_api.php?id={id}`: Get a specific user
- `POST users_api.php`: Create a new user
- `PUT users_api.php`: Update an existing user
- `DELETE users_api.php?id={id}`: Delete a user

## Customization

### Adding New Permissions

To add new permission categories or items, modify the `$permissions_categories` array in `db_setup.php` and re-run the setup or insert them directly into the database.

### Modifying the UI

The layout uses Bootstrap 5 and can be customized by modifying the HTML in `ruoli-permessi.php` or the CSS in the theme files.
