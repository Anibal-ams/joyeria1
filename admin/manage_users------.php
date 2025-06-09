<?php
session_start();
require_once '../includes/db_connection.php';
require_once '../includes/helpers.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$success_message = $error_message = '';

// Handle form submission for adding/editing users
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = isset($_POST['id']) ? intval($_POST['id']) : null;
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $nombre_completo = trim($_POST['nombre_completo']);
    $telefono = trim($_POST['telefono']);
    $direccion = trim($_POST['direccion']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $role = $_POST['role'];
    $activo = isset($_POST['activo']) ? 1 : 0;

    // Validation
    $errors = [];
    
    if (empty($username)) {
        $errors[] = "El nombre de usuario es obligatorio.";
    } elseif (strlen($username) < 3) {
        $errors[] = "El nombre de usuario debe tener al menos 3 caracteres.";
    }
    
    if (empty($email)) {
        $errors[] = "El email es obligatorio.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "El formato del email no es válido.";
    }
    
    if (empty($nombre_completo)) {
        $errors[] = "El nombre completo es obligatorio.";
    }
    
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
        // Check for duplicate username/email (excluding current record if editing)
        $check_query = "SELECT id_usuario FROM usuarios WHERE (username = ? OR email = ?)";
        if ($id) {
            $check_query .= " AND id_usuario != ?";
        }
        
        $check_stmt = $conn->prepare($check_query);
        if ($id) {
            $check_stmt->bind_param("ssi", $username, $email, $id);
        } else {
            $check_stmt->bind_param("ss", $username, $email);
        }
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error_message = "Ya existe un usuario con ese nombre de usuario o email.";
        } else {
            if ($id) {
                // Update existing user
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $query = "UPDATE usuarios SET username = ?, email = ?, nombre_completo = ?, telefono = ?, direccion = ?, password = ?, role = ?, activo = ?, fecha_actualizacion = NOW() WHERE id_usuario = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("sssssssii", $username, $email, $nombre_completo, $telefono, $direccion, $hashed_password, $role, $activo, $id);
                } else {
                    $query = "UPDATE usuarios SET username = ?, email = ?, nombre_completo = ?, telefono = ?, direccion = ?, role = ?, activo = ?, fecha_actualizacion = NOW() WHERE id_usuario = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ssssssii", $username, $email, $nombre_completo, $telefono, $direccion, $role, $activo, $id);
                }
            } else {
                // Add new user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $query = "INSERT INTO usuarios (username, email, nombre_completo, telefono, direccion, password, role, activo, fecha_registro) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sssssssi", $username, $email, $nombre_completo, $telefono, $direccion, $hashed_password, $role, $activo);
            }

            if ($stmt->execute()) {
                $success_message = $id ? "Usuario actualizado con éxito." : "Usuario añadido con éxito.";
            } else {
                $error_message = "Error al " . ($id ? "actualizar" : "añadir") . " el usuario: " . $conn->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
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
        $query = "DELETE FROM usuarios WHERE id_usuario = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $success_message = "Usuario eliminado con éxito.";
        } else {
            $error_message = "Error al eliminar el usuario: " . $conn->error;
        }
        $stmt->close();
    }
}

// Handle user status toggle
if (isset($_GET['toggle_status'])) {
    $id = intval($_GET['toggle_status']);
    
    if ($id == $_SESSION['admin_id']) {
        $error_message = "No puedes desactivar tu propia cuenta.";
    } else {
        $query = "UPDATE usuarios SET activo = NOT activo WHERE id_usuario = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $success_message = "Estado del usuario actualizado con éxito.";
        } else {
            $error_message = "Error al actualizar el estado: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch users with filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role_filter']) ? $_GET['role_filter'] : '';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

$where_conditions = [];
$params = [];
$types = "";

if (!empty($search)) {
    $where_conditions[] = "(username LIKE ? OR email LIKE ? OR nombre_completo LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "sss";
}

if (!empty($role_filter)) {
    $where_conditions[] = "role = ?";
    $params[] = $role_filter;
    $types .= "s";
}

if ($status_filter !== '') {
    $where_conditions[] = "activo = ?";
    $params[] = intval($status_filter);
    $types .= "i";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

$query = "SELECT * FROM administradores $where_clause ORDER BY fecha_registro DESC";
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as active_users,
    SUM(CASE WHEN activo = 0 THEN 1 ELSE 0 END) as inactive_users,
    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_users,
    SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as regular_users,
    SUM(CASE WHEN DATE(fecha_registro) = CURDATE() THEN 1 ELSE 0 END) as new_today
    FROM usuarios";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Usuarios - Panda Joyeros</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/modern-dashboard.css">
    <link rel="stylesheet" href="../css/users.css">
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
                                <i class="fas fa-users"></i>
                                <span>Usuarios</span>
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
                                <span>Nuevo Usuario</span>
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
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1>Gestionar Usuarios</h1>
                <div class="user-info">
                    Bienvenido, <span class="username"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                </div>
            </header>

            <!-- Users Content -->
            <div class="dashboard-content">
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
                        <h2><i class="fas fa-users"></i> Gestión de Usuarios</h2>
                        <p>Administra los usuarios del sistema y sus permisos</p>
                    </div>
                    <button class="btn btn-primary" onclick="showUserForm()">
                        <i class="fas fa-user-plus"></i>
                        Nuevo Usuario
                    </button>
                </div>

                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-info">
                                <div class="stat-label">Total Usuarios</div>
                                <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                            </div>
                            <div class="stat-icon blue">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-info">
                                <div class="stat-label">Usuarios Activos</div>
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
                                <div class="stat-label">Administradores</div>
                                <div class="stat-value"><?php echo $stats['admin_users']; ?></div>
                            </div>
                            <div class="stat-icon purple">
                                <i class="fas fa-user-shield"></i>
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
                            <input type="text" name="search" placeholder="Buscar usuarios..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <select name="role_filter" class="filter-select">
                            <option value="">Todos los roles</option>
                            <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Administradores</option>
                            <option value="user" <?php echo $role_filter === 'user' ? 'selected' : ''; ?>>Usuarios</option>
                        </select>
                        
                        <select name="status_filter" class="filter-select">
                            <option value="">Todos los estados</option>
                            <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Activos</option>
                            <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Inactivos</option>
                        </select>
                        
                        <button type="submit" class="btn btn-outline">
                            <i class="fas fa-search"></i>
                            Buscar
                        </button>
                        
                        <a href="manage_users.php" class="btn btn-ghost">
                            <i class="fas fa-times"></i>
                            Limpiar
                        </a>
                    </form>
                </div>

                <!-- Users Grid -->
                <div class="users-grid">
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($user = $result->fetch_assoc()): ?>
                            <div class="user-card <?php echo $user['activo'] ? 'active' : 'inactive'; ?>">
                                <div class="user-header">
                                    <div class="user-avatar">
                                        <i class="fas fa-user"></i>
                                        <?php if ($user['role'] === 'admin'): ?>
                                            <div class="admin-badge">
                                                <i class="fas fa-crown"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="user-info">
                                        <h3><?php echo htmlspecialchars($user['nombre_completo']); ?></h3>
                                        <p class="username">@<?php echo htmlspecialchars($user['username']); ?></p>
                                        <div class="user-status">
                                            <span class="status-badge <?php echo $user['activo'] ? 'active' : 'inactive'; ?>">
                                                <i class="fas fa-circle"></i>
                                                <?php echo $user['activo'] ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                            <span class="role-badge <?php echo $user['role']; ?>">
                                                <i class="fas <?php echo $user['role'] === 'admin' ? 'fa-shield-alt' : 'fa-user'; ?>"></i>
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="user-details">
                                    <div class="detail-item">
                                        <i class="fas fa-envelope"></i>
                                        <span><?php echo htmlspecialchars($user['email']); ?></span>
                                    </div>
                                    <?php if (!empty($user['telefono'])): ?>
                                        <div class="detail-item">
                                            <i class="fas fa-phone"></i>
                                            <span><?php echo htmlspecialchars($user['telefono']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($user['direccion'])): ?>
                                        <div class="detail-item">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <span><?php echo htmlspecialchars($user['direccion']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="detail-item">
                                        <i class="fas fa-calendar"></i>
                                        <span>Registrado: <?php echo date('d/m/Y', strtotime($user['fecha_registro'])); ?></span>
                                    </div>
                                </div>
                                
                                <div class="user-actions">
                                    <button class="btn-action btn-edit" 
                                            onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)"
                                            title="Editar usuario">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <?php if ($user['id_usuario'] != $_SESSION['admin_id']): ?>
                                        <button class="btn-action btn-toggle" 
                                                onclick="toggleUserStatus(<?php echo $user['id_usuario']; ?>, <?php echo $user['activo']; ?>)"
                                                title="<?php echo $user['activo'] ? 'Desactivar' : 'Activar'; ?> usuario">
                                            <i class="fas <?php echo $user['activo'] ? 'fa-user-slash' : 'fa-user-check'; ?>"></i>
                                        </button>
                                        
                                        <button class="btn-action btn-delete" 
                                                onclick="deleteUser(<?php echo $user['id_usuario']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')"
                                                title="Eliminar usuario">
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
                            <i class="fas fa-users"></i>
                            <h3>No hay usuarios</h3>
                            <p>No se encontraron usuarios que coincidan con los criterios de búsqueda.</p>
                            <button class="btn btn-primary" onclick="showUserForm()">
                                <i class="fas fa-user-plus"></i>
                                Crear primer usuario
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- User Form Modal -->
    <div class="modal" id="userModal">
        <div class="modal-content large">
            <div class="modal-header">
                <h3 id="modalTitle">Nuevo Usuario</h3>
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
                               placeholder="Ej: juan_perez">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">
                            <i class="fas fa-envelope"></i>
                            Email
                        </label>
                        <input type="email" id="email" name="email" required 
                               placeholder="usuario@ejemplo.com">
                    </div>
                    
                    <div class="form-group">
                        <label for="nombre_completo">
                            <i class="fas fa-id-card"></i>
                            Nombre completo
                        </label>
                        <input type="text" id="nombre_completo" name="nombre_completo" required 
                               placeholder="Juan Pérez García">
                    </div>
                    
                    <div class="form-group">
                        <label for="telefono">
                            <i class="fas fa-phone"></i>
                            Teléfono
                        </label>
                        <input type="tel" id="telefono" name="telefono" 
                               placeholder="+57 300 123 4567">
                    </div>
                    
                    <div class="form-group">
                        <label for="role">
                            <i class="fas fa-shield-alt"></i>
                            Rol
                        </label>
                        <select id="role" name="role" required>
                            <option value="user">Usuario</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="activo" name="activo" checked>
                            <span class="checkmark"></span>
                            Usuario activo
                        </label>
                    </div>
                </div>
                
                <div class="form-group full-width">
                    <label for="direccion">
                        <i class="fas fa-map-marker-alt"></i>
                        Dirección
                    </label>
                    <textarea id="direccion" name="direccion" rows="3" 
                              placeholder="Dirección completa del usuario..."></textarea>
                </div>
                
                <div class="password-section">
                    <h4><i class="fas fa-lock"></i> Contraseña</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="password">
                                <i class="fas fa-key"></i>
                                Nueva contraseña
                            </label>
                            <input type="password" id="password" name="password" 
                                   placeholder="Mínimo 6 caracteres">
                            <small class="form-help" id="password-help">
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
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-ghost" onclick="hideUserForm()">
                        <i class="fas fa-times"></i>
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Guardar Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/dashboard.js"></script>
    <script src="../js/users.js"></script>
</body>
</html>
