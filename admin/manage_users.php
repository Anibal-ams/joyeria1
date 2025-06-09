<?php
session_start();
require_once '../includes/db_connection.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$success_message = $error_message = '';

// Función para validar y limpiar datos
function cleanInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Función para generar hash de contraseña
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Verificar si las columnas existen en la tabla
function checkColumnExists($conn, $table, $column) {
    $query = "SHOW COLUMNS FROM $table LIKE '$column'";
    $result = $conn->query($query);
    return $result && $result->num_rows > 0;
}

// Verificar estructura de la tabla
$has_activo = checkColumnExists($conn, 'administradores', 'activo');
$has_email = checkColumnExists($conn, 'administradores', 'email');
$has_nombre_completo = checkColumnExists($conn, 'administradores', 'nombre_completo');
$has_created_at = checkColumnExists($conn, 'administradores', 'created_at');
$has_last_login = checkColumnExists($conn, 'administradores', 'last_login');

// Handle form submission for adding/editing users
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = isset($_POST['id']) ? intval($_POST['id']) : null;
    $username = cleanInput($_POST['username']);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $email = $has_email ? cleanInput($_POST['email'] ?? '') : '';
    $nombre_completo = $has_nombre_completo ? cleanInput($_POST['nombre_completo'] ?? '') : '';
    $activo = $has_activo ? (isset($_POST['activo']) ? 1 : 0) : 1;

    // Validation
    $errors = [];
    
    if (empty($username)) {
        $errors[] = "El nombre de usuario es obligatorio.";
    } elseif (strlen($username) < 3) {
        $errors[] = "El nombre de usuario debe tener al menos 3 caracteres.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = "El nombre de usuario solo puede contener letras, números y guiones bajos.";
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "El formato del email no es válido.";
    }
    
    // Password validation for new users or when changing password
    if (!$id && empty($password)) {
        $errors[] = "La contraseña es obligatoria para nuevos usuarios.";
    }
    
    if (!empty($password)) {
        if (strlen($password) < 6) {
            $errors[] = "La contraseña debe tener al menos 6 caracteres.";
        }
        if ($password !== $confirm_password) {
            $errors[] = "Las contraseñas no coinciden.";
        }
    }
    
    if (empty($errors)) {
        try {
            // Check for duplicate username (excluding current record if editing)
            $check_query = "SELECT id FROM administradores WHERE username = ?";
            $params = [$username];
            $types = "s";
            
            if ($id) {
                $check_query .= " AND id != ?";
                $params[] = $id;
                $types .= "i";
            }
            
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param($types, ...$params);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error_message = "Ya existe un usuario con ese nombre de usuario.";
            } else {
                if ($id) {
                    // Update existing user
                    $update_fields = ["username = ?"];
                    $update_params = [$username];
                    $update_types = "s";
                    
                    if (!empty($password)) {
                        $hashed_password = hashPassword($password);
                        $update_fields[] = "password = ?";
                        $update_params[] = $hashed_password;
                        $update_types .= "s";
                    }
                    
                    if ($has_email) {
                        $update_fields[] = "email = ?";
                        $update_params[] = $email;
                        $update_types .= "s";
                    }
                    
                    if ($has_nombre_completo) {
                        $update_fields[] = "nombre_completo = ?";
                        $update_params[] = $nombre_completo;
                        $update_types .= "s";
                    }
                    
                    if ($has_activo) {
                        $update_fields[] = "activo = ?";
                        $update_params[] = $activo;
                        $update_types .= "i";
                    }
                    
                    $update_params[] = $id;
                    $update_types .= "i";
                    
                    $query = "UPDATE administradores SET " . implode(", ", $update_fields) . " WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param($update_types, ...$update_params);
                } else {
                    // Add new user
                    $hashed_password = hashPassword($password);
                    
                    $insert_fields = ["username", "password"];
                    $insert_values = ["?", "?"];
                    $insert_params = [$username, $hashed_password];
                    $insert_types = "ss";
                    
                    if ($has_email) {
                        $insert_fields[] = "email";
                        $insert_values[] = "?";
                        $insert_params[] = $email;
                        $insert_types .= "s";
                    }
                    
                    if ($has_nombre_completo) {
                        $insert_fields[] = "nombre_completo";
                        $insert_values[] = "?";
                        $insert_params[] = $nombre_completo;
                        $insert_types .= "s";
                    }
                    
                    if ($has_activo) {
                        $insert_fields[] = "activo";
                        $insert_values[] = "?";
                        $insert_params[] = $activo;
                        $insert_types .= "i";
                    }
                    
                    if ($has_created_at) {
                        $insert_fields[] = "created_at";
                        $insert_values[] = "NOW()";
                    }
                    
                    $query = "INSERT INTO administradores (" . implode(", ", $insert_fields) . ") VALUES (" . implode(", ", $insert_values) . ")";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param($insert_types, ...$insert_params);
                }

                if ($stmt->execute()) {
                    $success_message = $id ? "Usuario actualizado con éxito." : "Usuario administrador creado con éxito.";
                } else {
                    $error_message = "Error al " . ($id ? "actualizar" : "crear") . " el usuario: " . $conn->error;
                }
                $stmt->close();
            }
            $check_stmt->close();
        } catch (Exception $e) {
            $error_message = "Error en la base de datos: " . $e->getMessage();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Handle user deletion
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Don't allow deleting current admin
    if ($id == $_SESSION['admin_id']) {
        $error_message = "No puedes eliminar tu propia cuenta.";
    } else {
        try {
            $query = "DELETE FROM administradores WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $success_message = "Usuario eliminado con éxito.";
            } else {
                $error_message = "Error al eliminar el usuario: " . $conn->error;
            }
            $stmt->close();
        } catch (Exception $e) {
            $error_message = "Error al eliminar el usuario: " . $e->getMessage();
        }
    }
}

// Handle user status toggle (only if activo column exists)
if (isset($_GET['toggle_status']) && $has_activo) {
    $id = intval($_GET['toggle_status']);
    
    if ($id == $_SESSION['admin_id']) {
        $error_message = "No puedes desactivar tu propia cuenta.";
    } else {
        try {
            $query = "UPDATE administradores SET activo = NOT activo WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $success_message = "Estado del usuario actualizado con éxito.";
            } else {
                $error_message = "Error al actualizar el estado: " . $conn->error;
            }
            $stmt->close();
        } catch (Exception $e) {
            $error_message = "Error al actualizar el estado: " . $e->getMessage();
        }
    }
}

// Fetch users with filters
$search = isset($_GET['search']) ? cleanInput($_GET['search']) : '';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

$where_conditions = [];
$params = [];
$types = "";

if (!empty($search)) {
    $search_conditions = ["username LIKE ?"];
    $search_param = "%$search%";
    $params[] = $search_param;
    $types .= "s";
    
    if ($has_email) {
        $search_conditions[] = "email LIKE ?";
        $params[] = $search_param;
        $types .= "s";
    }
    
    if ($has_nombre_completo) {
        $search_conditions[] = "nombre_completo LIKE ?";
        $params[] = $search_param;
        $types .= "s";
    }
    
    $where_conditions[] = "(" . implode(" OR ", $search_conditions) . ")";
}

if ($status_filter !== '' && $has_activo) {
    $where_conditions[] = "activo = ?";
    $params[] = intval($status_filter);
    $types .= "i";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

try {
    // Build select fields based on available columns
    $select_fields = ["id", "username", "password"];
    
    if ($has_email) $select_fields[] = "email";
    if ($has_nombre_completo) $select_fields[] = "nombre_completo";
    if ($has_activo) $select_fields[] = "activo";
    if ($has_created_at) $select_fields[] = "created_at";
    if ($has_last_login) $select_fields[] = "last_login";
    
    $query = "SELECT " . implode(", ", $select_fields) . " FROM administradores $where_clause ORDER BY " . 
             ($has_created_at ? "created_at DESC" : "id DESC");
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();

    // Get statistics
    $stats_fields = ["COUNT(*) as total_users"];
    
    if ($has_activo) {
        $stats_fields[] = "SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as active_users";
        $stats_fields[] = "SUM(CASE WHEN activo = 0 THEN 1 ELSE 0 END) as inactive_users";
    } else {
        $stats_fields[] = "COUNT(*) as active_users";
        $stats_fields[] = "0 as inactive_users";
    }
    
    if ($has_created_at) {
        $stats_fields[] = "SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as new_today";
    } else {
        $stats_fields[] = "0 as new_today";
    }
    
    $stats_query = "SELECT " . implode(", ", $stats_fields) . " FROM administradores";
    $stats_result = $conn->query($stats_query);
    $stats = $stats_result->fetch_assoc();

} catch (Exception $e) {
    $error_message = "Error al cargar los usuarios: " . $e->getMessage();
    $result = null;
    $stats = [
        'total_users' => 0,
        'active_users' => 0,
        'inactive_users' => 0,
        'new_today' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Usuarios Administradores - Panda Joyeros</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #f59e0b;
            --primary-dark: #d97706;
            --secondary-color: #0f172a;
            --text-color: #f8fafc;
            --bg-color: #0f172a;
            --card-bg: rgba(255, 255, 255, 0.05);
            --border-color: rgba(255, 255, 255, 0.1);
            --success-color: #10b981;
            --error-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --backdrop-blur: blur(10px);
            --border-radius: 12px;
            --transition: all 0.3s ease;
            --shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f172a, #1e293b, #334155);
            color: var(--text-color);
            min-height: 100vh;
            line-height: 1.6;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: var(--backdrop-blur);
            border-right: 1px solid var(--border-color);
            padding: 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 24px;
            border-bottom: 1px solid var(--border-color);
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #000;
            font-size: 24px;
            font-weight: bold;
        }

        .logo-text h2 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .logo-text p {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
        }

        .sidebar-nav {
            padding: 24px 0;
        }

        .nav-section {
            margin-bottom: 32px;
        }

        .nav-title {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: rgba(255, 255, 255, 0.5);
            margin-bottom: 16px;
            padding: 0 24px;
        }

        .nav-menu {
            list-style: none;
        }

        .nav-item {
            margin-bottom: 4px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 24px;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: var(--transition);
            border-left: 3px solid transparent;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.05);
            color: var(--primary-color);
            border-left-color: var(--primary-color);
        }

        .nav-item.active .nav-link {
            background: rgba(245, 158, 11, 0.1);
            color: var(--primary-color);
            border-left-color: var(--primary-color);
        }

        .nav-link i {
            width: 20px;
            text-align: center;
        }

        .sidebar-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 24px;
            border-top: 1px solid var(--border-color);
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            text-decoration: none;
            border-radius: 8px;
            transition: var(--transition);
        }

        .logout-btn:hover {
            background: rgba(239, 68, 68, 0.2);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            display: flex;
            flex-direction: column;
        }

        .main-header {
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: var(--backdrop-blur);
            border-bottom: 1px solid var(--border-color);
            padding: 16px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .main-header h1 {
            font-size: 24px;
            font-weight: 600;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
        }

        .username {
            color: var(--primary-color);
            font-weight: 500;
        }

        .dashboard-content {
            flex: 1;
            padding: 32px;
            overflow-y: auto;
        }

        /* Alerts */
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            animation: slideDown 0.3s ease-out;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error-color);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 32px;
        }

        .page-title h2 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-title p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 16px;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: #000;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(245, 158, 11, 0.3);
        }

        .btn-outline {
            background: transparent;
            color: var(--text-color);
            border: 1px solid var(--border-color);
        }

        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: var(--primary-color);
        }

        .btn-ghost {
            background: transparent;
            color: rgba(255, 255, 255, 0.7);
        }

        .btn-ghost:hover {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-color);
        }

        /* Statistics Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--card-bg);
            backdrop-filter: var(--backdrop-blur);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 24px;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .stat-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stat-info {
            flex: 1;
        }

        .stat-label {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-color);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-icon.blue {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
        }

        .stat-icon.green {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }

        .stat-icon.yellow {
            background: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
        }

        .stat-icon.red {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        /* Database Warning */
        .db-warning {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }

        .db-warning h4 {
            color: var(--warning-color);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .db-warning p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
            margin-bottom: 12px;
        }

        .db-warning ul {
            list-style: none;
            padding-left: 0;
        }

        .db-warning li {
            color: rgba(255, 255, 255, 0.7);
            font-size: 13px;
            margin-bottom: 4px;
            padding-left: 20px;
            position: relative;
        }

        .db-warning li::before {
            content: "•";
            color: var(--warning-color);
            position: absolute;
            left: 0;
        }

        /* Filters */
        .filters-section {
            background: var(--card-bg);
            backdrop-filter: var(--backdrop-blur);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 24px;
            margin-bottom: 32px;
        }

        .filters-form {
            display: flex;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-box {
            position: relative;
            flex: 1;
            min-width: 250px;
        }

        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.5);
        }

        .search-box input {
            width: 100%;
            padding: 12px 12px 12px 40px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-color);
            font-size: 14px;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.2);
        }

        .filter-select {
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-color);
            font-size: 14px;
            min-width: 150px;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        /* Users Grid */
        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .user-card {
            background: var(--card-bg);
            backdrop-filter: var(--backdrop-blur);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            overflow: hidden;
            transition: var(--transition);
            position: relative;
        }

        .user-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .user-card.inactive {
            opacity: 0.7;
        }

        .user-header {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 24px;
            border-bottom: 1px solid var(--border-color);
            background: rgba(255, 255, 255, 0.02);
        }

        .user-avatar {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #000;
            font-size: 24px;
            font-weight: bold;
            flex-shrink: 0;
            position: relative;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .admin-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            width: 24px;
            height: 24px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 12px;
            border: 2px solid var(--bg-color);
        }

        .user-info h3 {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 4px;
        }

        .username {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 500;
            margin-bottom: 12px;
        }

        .user-status {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge.active {
            background: rgba(16, 185, 129, 0.2);
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .status-badge.inactive {
            background: rgba(156, 163, 175, 0.2);
            color: rgba(156, 163, 175, 0.8);
            border: 1px solid rgba(156, 163, 175, 0.3);
        }

        .user-details {
            padding: 24px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            font-size: 14px;
        }

        .detail-item:last-child {
            margin-bottom: 0;
        }

        .detail-item i {
            width: 16px;
            color: var(--primary-color);
            font-size: 14px;
            flex-shrink: 0;
        }

        .detail-item span {
            color: rgba(255, 255, 255, 0.8);
            line-height: 1.4;
        }

        .user-actions {
            display: flex;
            justify-content: center;
            gap: 8px;
            padding: 20px 24px;
            border-top: 1px solid var(--border-color);
            background: rgba(255, 255, 255, 0.02);
        }

        .btn-action {
            width: 40px;
            height: 40px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .btn-action:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-edit {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }

        .btn-edit:hover:not(:disabled) {
            background: rgba(59, 130, 246, 0.2);
            transform: scale(1.05);
        }

        .btn-toggle {
            background: rgba(251, 191, 36, 0.1);
            color: #fbbf24;
            border: 1px solid rgba(251, 191, 36, 0.2);
        }

        .btn-toggle:hover:not(:disabled) {
            background: rgba(251, 191, 36, 0.2);
            transform: scale(1.05);
        }

        .btn-delete {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .btn-delete:hover:not(:disabled) {
            background: rgba(239, 68, 68, 0.2);
            transform: scale(1.05);
        }

        .btn-self {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(4px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: var(--card-bg);
            backdrop-filter: var(--backdrop-blur);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease-out;
        }

        .modal-content.large {
            max-width: 800px;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 24px;
            border-bottom: 1px solid var(--border-color);
        }

        .modal-header h3 {
            font-size: 20px;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.7);
            font-size: 20px;
            cursor: pointer;
            padding: 8px;
            border-radius: 4px;
            transition: var(--transition);
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-color);
        }

        /* Form */
        .user-form {
            padding: 24px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            color: var(--text-color);
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group label i {
            color: var(--primary-color);
            width: 16px;
            font-size: 14px;
            flex-shrink: 0;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-color);
            font-size: 14px;
            transition: var(--transition);
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.2);
        }

        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .checkbox-label {
            display: flex !important;
            align-items: center !important;
            gap: 12px !important;
            cursor: pointer;
            margin-bottom: 0 !important;
        }

        .checkbox-label input[type="checkbox"] {
            display: none;
        }

        .checkmark {
            width: 20px;
            height: 20px;
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid var(--border-color);
            border-radius: 4px;
            position: relative;
            transition: var(--transition);
        }

        .checkbox-label input[type="checkbox"]:checked + .checkmark {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }

        .checkbox-label input[type="checkbox"]:checked + .checkmark::after {
            content: "\f00c";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #000;
            font-size: 12px;
        }

        .form-help {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
            margin-top: 4px;
            font-style: italic;
        }

        .form-actions {
            display: flex;
            gap: 16px;
            justify-content: flex-end;
            padding-top: 24px;
            border-top: 1px solid var(--border-color);
        }

        /* No users */
        .no-users {
            grid-column: 1 / -1;
            text-align: center;
            padding: 80px 40px;
            background: var(--card-bg);
            backdrop-filter: var(--backdrop-blur);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
        }

        .no-users i {
            font-size: 64px;
            color: rgba(255, 255, 255, 0.3);
            margin-bottom: 24px;
        }

        .no-users h3 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .no-users p {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 32px;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .users-grid {
                grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .users-grid {
                grid-template-columns: 1fr;
            }

            .filters-form {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                min-width: auto;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .modal-content {
                margin: 10px;
                width: calc(100% - 20px);
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo-container">
                    <div class="logo-icon">
                        <i class="fas fa-gem"></i>
                    </div>
                    <div class="logo-text">
                        <h2>Panda Joyeros</h2>
                        <p>Panel Admin</p>
                    </div>
                </div>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-section">
                    <h3 class="nav-title">Navegación Principal</h3>
                    <ul class="nav-menu">
                        <li class="nav-item">
                            <a href="dashboard.php" class="nav-link">
                                <i class="fas fa-chart-bar"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="products.php" class="nav-link">
                                <i class="fas fa-gem"></i>
                                <span>Productos</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="categories.php" class="nav-link">
                                <i class="fas fa-tags"></i>
                                <span>Categorías</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="materials.php" class="nav-link">
                                <i class="fas fa-cubes"></i>
                                <span>Materiales</span>
                            </a>
                        </li>
                        <li class="nav-item active">
                            <a href="manage_users.php" class="nav-link">
                                <i class="fas fa-users-cog"></i>
                                <span>Administradores</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="nav-section">
                    <h3 class="nav-title">Acciones Rápidas</h3>
                    <ul class="nav-menu">
                        <li class="nav-item">
                            <a href="#" onclick="showUserForm()" class="nav-link">
                                <i class="fas fa-user-plus"></i>
                                <span>Nuevo Admin</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="../index.php" target="_blank" class="nav-link">
                                <i class="fas fa-eye"></i>
                                <span>Ver Tienda</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="sidebar-footer">
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Cerrar Sesión</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="main-header">
                <h1>Gestionar Administradores</h1>
                <div class="user-info">
                    Bienvenido, <span class="username"><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></span>
                </div>
            </header>

            <!-- Content -->
            <div class="dashboard-content">
                <!-- Database Structure Warning -->
                <!-- <?php if (!$has_activo || !$has_email || !$has_nombre_completo): ?>
                    <div class="db-warning">
                        <h4><i class="fas fa-exclamation-triangle"></i> Estructura de Base de Datos Incompleta</h4>
                        <p>Tu tabla 'administradores' no tiene todas las columnas necesarias. Ejecuta el script SQL para actualizar la estructura:</p>
                        <ul>
                            <?php if (!$has_activo): ?><li>Falta columna 'activo' - No se podrán activar/desactivar usuarios</li><?php endif; ?>
                            <?php if (!$has_email): ?><li>Falta columna 'email' - No se podrán guardar emails</li><?php endif; ?>
                            <?php if (!$has_nombre_completo): ?><li>Falta columna 'nombre_completo' - No se podrán guardar nombres completos</li><?php endif; ?>
                            <?php if (!$has_created_at): ?><li>Falta columna 'created_at' - No se mostrarán fechas de creación</li><?php endif; ?>
                            <?php if (!$has_last_login): ?><li>Falta columna 'last_login' - No se mostrarán últimos accesos</li><?php endif; ?>
                        </ul>
                    </div>
                <?php endif; ?> -->

                <!-- Messages -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <!-- Page Header -->
                <div class="page-header">
                    <div class="page-title">
                        <h2><i class="fas fa-users-cog"></i> Gestión de Administradores</h2>
                        <p>Administra los usuarios administradores del sistema</p>
                    </div>
                    <button class="btn btn-primary" onclick="showUserForm()">
                        <i class="fas fa-user-plus"></i>
                        Nuevo Administrador
                    </button>
                </div>

                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-info">
                                <div class="stat-label">Total Administradores</div>
                                <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                            </div>
                            <div class="stat-icon blue">
                                <i class="fas fa-users-cog"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-info">
                                <div class="stat-label">Administradores Activos</div>
                                <div class="stat-value"><?php echo $stats['active_users']; ?></div>
                            </div>
                            <div class="stat-icon green">
                                <i class="fas fa-user-check"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-info">
                                <div class="stat-label">Administradores Inactivos</div>
                                <div class="stat-value"><?php echo $stats['inactive_users']; ?></div>
                            </div>
                            <div class="stat-icon red">
                                <i class="fas fa-user-times"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-info">
                                <div class="stat-label">Nuevos Hoy</div>
                                <div class="stat-value"><?php echo $stats['new_today']; ?></div>
                            </div>
                            <div class="stat-icon yellow">
                                <i class="fas fa-user-plus"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters and Search -->
                <div class="filters-section">
                    <form method="GET" class="filters-form">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" placeholder="Buscar administradores..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <?php if ($has_activo): ?>
                        <select name="status_filter" class="filter-select">
                            <option value="">Todos los estados</option>
                            <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Activos</option>
                            <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Inactivos</option>
                        </select>
                        <?php endif; ?>
                        
                        <button type="submit" class="btn btn-outline">
                            <i class="fas fa-search"></i>
                            Buscar
                        </button>
                        
                        <a href="manage_users_compatible.php" class="btn btn-ghost">
                            <i class="fas fa-times"></i>
                            Limpiar
                        </a>
                    </form>
                </div>

                <!-- Users Grid -->
                <div class="users-grid">
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($user = $result->fetch_assoc()): ?>
                            <?php 
                            $user_activo = $has_activo ? $user['activo'] : 1;
                            $user_email = $has_email ? $user['email'] : '';
                            $user_nombre_completo = $has_nombre_completo ? $user['nombre_completo'] : '';
                            $user_created_at = $has_created_at ? $user['created_at'] : '';
                            $user_last_login = $has_last_login ? $user['last_login'] : '';
                            ?>
                            <div class="user-card <?php echo $user_activo ? 'active' : 'inactive'; ?>">
                                <div class="user-header">
                                    <div class="user-avatar">
                                        <i class="fas fa-user-shield"></i>
                                        <div class="admin-badge">
                                            <i class="fas fa-crown"></i>
                                        </div>
                                    </div>
                                    <div class="user-info">
                                        <h3><?php echo htmlspecialchars($user_nombre_completo ?: $user['username']); ?></h3>
                                        <p class="username">@<?php echo htmlspecialchars($user['username']); ?></p>
                                        <div class="user-status">
                                            <span class="status-badge <?php echo $user_activo ? 'active' : 'inactive'; ?>">
                                                <i class="fas fa-circle"></i>
                                                <?php echo $user_activo ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="user-details">
                                    <?php if (!empty($user_email)): ?>
                                        <div class="detail-item">
                                            <i class="fas fa-envelope"></i>
                                            <span><?php echo htmlspecialchars($user_email); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($user_created_at)): ?>
                                        <div class="detail-item">
                                            <i class="fas fa-calendar"></i>
                                            <span>Creado: <?php echo date('d/m/Y', strtotime($user_created_at)); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($user_last_login)): ?>
                                        <div class="detail-item">
                                            <i class="fas fa-clock"></i>
                                            <span>Último acceso: <?php echo date('d/m/Y H:i', strtotime($user_last_login)); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="user-actions">
                                    <button class="btn-action btn-edit" 
                                            onclick="editUser(<?php echo htmlspecialchars(json_encode([
                                                'id' => $user['id'],
                                                'username' => $user['username'],
                                                'email' => $user_email,
                                                'nombre_completo' => $user_nombre_completo,
                                                'activo' => $user_activo
                                            ])); ?>)"
                                            title="Editar administrador">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <?php if ($user['id'] != $_SESSION['admin_id']): ?>
                                        <?php if ($has_activo): ?>
                                        <button class="btn-action btn-toggle" 
                                                onclick="toggleUserStatus(<?php echo $user['id']; ?>, <?php echo $user_activo; ?>)"
                                                title="<?php echo $user_activo ? 'Desactivar' : 'Activar'; ?> administrador">
                                            <i class="fas <?php echo $user_activo ? 'fa-user-slash' : 'fa-user-check'; ?>"></i>
                                        </button>
                                        <?php endif; ?>
                                        
                                        <button class="btn-action btn-delete" 
                                                onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')"
                                                title="Eliminar administrador">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php else: ?>
                                        <button class="btn-action btn-self" 
                                                title="Tu cuenta actual"
                                                disabled>
                                            <i class="fas fa-user-circle"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="no-users">
                            <i class="fas fa-users-cog"></i>
                            <h3>No hay administradores</h3>
                            <p>No se encontraron administradores que coincidan con los criterios de búsqueda.</p>
                            <button class="btn btn-primary" onclick="showUserForm()">
                                <i class="fas fa-user-plus"></i>
                                Crear primer administrador
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- User Form Modal -->
    <div class="modal" id="userModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Nuevo Administrador</h3>
                <button class="modal-close" onclick="hideUserForm()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" class="user-form">
                <input type="hidden" name="id" id="user_id">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="username">
                            <i class="fas fa-user"></i>
                            Nombre de usuario
                        </label>
                        <input type="text" id="username" name="username" required 
                               placeholder="Ej: admin_juan" maxlength="50">
                    </div>
                    
                    <?php if ($has_email): ?>
                    <div class="form-group">
                        <label for="email">
                            <i class="fas fa-envelope"></i>
                            Email (opcional)
                        </label>
                        <input type="email" id="email" name="email" 
                               placeholder="admin@pandajoyeros.com" maxlength="100">
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($has_nombre_completo): ?>
                    <div class="form-group full-width">
                        <label for="nombre_completo">
                            <i class="fas fa-id-card"></i>
                            Nombre completo (opcional)
                        </label>
                        <input type="text" id="nombre_completo" name="nombre_completo" 
                               placeholder="Juan Pérez García" maxlength="100">
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="password">
                            <i class="fas fa-key"></i>
                            Nueva contraseña
                        </label>
                        <input type="password" id="password" name="password" 
                               placeholder="Mínimo 6 caracteres" minlength="6">
                        <small class="form-help" id="password-help" style="display: none;">
                            Deja en blanco para mantener la contraseña actual
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">
                            <i class="fas fa-key"></i>
                            Confirmar contraseña
                        </label>
                        <input type="password" id="confirm_password" name="confirm_password" 
                               placeholder="Repite la contraseña">
                    </div>
                    
                    <?php if ($has_activo): ?>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="activo" name="activo" checked>
                            <span class="checkmark"></span>
                            Administrador activo
                        </label>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-ghost" onclick="hideUserForm()">
                        <i class="fas fa-times"></i>
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Guardar Administrador
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Global variables
        let currentEditingUser = null;
        const hasActivo = <?php echo $has_activo ? 'true' : 'false'; ?>;
        const hasEmail = <?php echo $has_email ? 'true' : 'false'; ?>;
        const hasNombreCompleto = <?php echo $has_nombre_completo ? 'true' : 'false'; ?>;

        // Show user form modal
        function showUserForm(user = null) {
            const modal = document.getElementById('userModal');
            const modalTitle = document.getElementById('modalTitle');
            const form = modal.querySelector('.user-form');
            const passwordHelp = document.getElementById('password-help');
            const passwordField = document.getElementById('password');

            if (user) {
                modalTitle.textContent = 'Editar Administrador';
                populateForm(user);
                currentEditingUser = user;
                passwordHelp.style.display = 'block';
                passwordField.removeAttribute('required');
            } else {
                modalTitle.textContent = 'Nuevo Administrador';
                form.reset();
                if (hasActivo) {
                    document.getElementById('activo').checked = true;
                }
                currentEditingUser = null;
                passwordHelp.style.display = 'none';
                passwordField.setAttribute('required', 'required');
            }

            modal.classList.add('show');
            document.body.style.overflow = 'hidden';

            // Focus on first input
            setTimeout(() => {
                const firstInput = form.querySelector('input[type="text"]');
                if (firstInput) firstInput.focus();
            }, 300);
        }

        // Hide user form modal
        function hideUserForm() {
            const modal = document.getElementById('userModal');
            modal.classList.remove('show');
            document.body.style.overflow = 'auto';
            currentEditingUser = null;
        }

        // Edit user
        function editUser(user) {
            showUserForm(user);
        }

        // Toggle user status
        function toggleUserStatus(userId, currentStatus) {
            if (!hasActivo) {
                alert('La funcionalidad de activar/desactivar no está disponible. Actualiza la estructura de la base de datos.');
                return;
            }
            
            const action = currentStatus ? 'desactivar' : 'activar';
            if (confirm(`¿Estás seguro de que quieres ${action} este administrador?`)) {
                window.location.href = `manage_users_compatible.php?toggle_status=${userId}`;
            }
        }

        // Delete user
        function deleteUser(userId, username) {
            if (confirm(`¿Estás seguro de que quieres eliminar al administrador "${username}"? Esta acción no se puede deshacer.`)) {
                window.location.href = `manage_users_compatible.php?delete=${userId}`;
            }
        }

        // Populate form with user data
        function populateForm(user) {
            document.getElementById('user_id').value = user.id || '';
            document.getElementById('username').value = user.username || '';
            
            if (hasEmail) {
                document.getElementById('email').value = user.email || '';
            }
            
            if (hasNombreCompleto) {
                document.getElementById('nombre_completo').value = user.nombre_completo || '';
            }
            
            if (hasActivo) {
                document.getElementById('activo').checked = user.activo == 1;
            }

            // Clear password fields when editing
            document.getElementById('password').value = '';
            document.getElementById('confirm_password').value = '';
        }

        // Close modal when clicking outside
        document.addEventListener('click', (e) => {
            const modal = document.getElementById('userModal');
            if (e.target === modal) {
                hideUserForm();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const modal = document.getElementById('userModal');
                if (modal.classList.contains('show')) {
                    hideUserForm();
                }
            }
        });

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', () => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach((alert) => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => {
                        alert.remove();
                    }, 300);
                }, 5000);
            });
        });
    </script>
</body>
</html>

<?php
// Close database connection
if (isset($conn)) {
    $conn->close();
}
?>
