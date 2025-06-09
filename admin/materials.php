<?php
session_start();
require_once '../includes/db_connection.php';
require_once '../includes/helpers.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$success_message = $error_message = '';

// Handle form submission for adding/editing materials
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = isset($_POST['id']) ? intval($_POST['id']) : null;
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);

    // Validation
    if (empty($nombre)) {
        $error_message = "El nombre del material es obligatorio.";
    } else {
        // Check for duplicate names (excluding current record if editing)
        $check_query = "SELECT id_material FROM materiales WHERE nombre = ?";
        if ($id) {
            $check_query .= " AND id_material != ?";
        }
        
        $check_stmt = $conn->prepare($check_query);
        if ($id) {
            $check_stmt->bind_param("si", $nombre, $id);
        } else {
            $check_stmt->bind_param("s", $nombre);
        }
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error_message = "Ya existe un material con ese nombre.";
        } else {
            if ($id) {
                // Update existing material
                $query = "UPDATE materiales SET nombre = ?, descripcion = ? WHERE id_material = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ssi", $nombre, $descripcion, $id);
            } else {
                // Add new material
                $query = "INSERT INTO materiales (nombre, descripcion) VALUES (?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ss", $nombre, $descripcion);
            }

            if ($stmt->execute()) {
                $success_message = $id ? "Material actualizado con éxito." : "Material añadido con éxito.";
            } else {
                $error_message = "Error al " . ($id ? "actualizar" : "añadir") . " el material: " . $conn->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

// Handle material deletion
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Check if material has products
    $check_products = "SELECT COUNT(*) as count FROM productos WHERE id_material = ?";
    $check_stmt = $conn->prepare($check_products);
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $product_count = $check_stmt->get_result()->fetch_assoc()['count'];
    $check_stmt->close();
    
    if ($product_count > 0) {
        $error_message = "No se puede eliminar el material porque tiene $product_count producto(s) asociado(s).";
    } else {
        $query = "DELETE FROM materiales WHERE id_material = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $success_message = "Material eliminado con éxito.";
        } else {
            $error_message = "Error al eliminar el material: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch all materials with product count
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$where_conditions = [];
$params = [];
$types = "";

if (!empty($search)) {
    $where_conditions[] = "(m.nombre LIKE ? OR m.descripcion LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "ss";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

$query = "SELECT m.*, COUNT(p.id_producto) as product_count 
          FROM materiales m 
          LEFT JOIN productos p ON m.id_material = p.id_material 
          $where_clause
          GROUP BY m.id_material 
          ORDER BY m.nombre ASC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get statistics (simplified)
$stats_query = "SELECT COUNT(*) as total_materials FROM materiales";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Calculate materials with and without products
$materials_with_products = 0;
$result->data_seek(0);
while ($mat = $result->fetch_assoc()) {
    if ($mat['product_count'] > 0) $materials_with_products++;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Materiales - Panda Joyeros</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/modern-dashboard.css">
    
    <style>
        /* Materials Grid Styles */
        .materials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .material-card {
            background: var(--card-bg);
            backdrop-filter: var(--backdrop-blur);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            overflow: hidden;
            transition: var(--transition);
            position: relative;
        }

        .material-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow);
            border-color: rgba(255, 255, 255, 0.2);
        }

        /* Material Header */
        .material-header {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 24px;
            border-bottom: 1px solid var(--border-color);
            background: rgba(255, 255, 255, 0.02);
        }

        .material-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, var(--accent-yellow), #f59e0b);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #000;
            font-size: 24px;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            transition: var(--transition);
        }

        .material-card:hover .material-icon {
            transform: scale(1.05);
        }

        .material-title h3 {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
            line-height: 1.3;
        }

        .material-id {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* Material Info */
        .material-info {
            padding: 24px;
        }

        .material-description {
            color: var(--text-secondary);
            font-size: 14px;
            line-height: 1.5;
            font-style: italic;
        }

        .no-description {
            color: var(--text-muted);
            font-size: 14px;
            font-style: italic;
        }

        /* Material Footer */
        .material-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-top: 1px solid var(--border-color);
            background: rgba(255, 255, 255, 0.02);
        }

        .product-count {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .count-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 28px;
            height: 28px;
            border-radius: 14px;
            font-size: 12px;
            font-weight: 600;
            padding: 0 8px;
        }

        .count-badge.has-products {
            background: rgba(16, 185, 129, 0.2);
            color: var(--accent-green);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .count-badge.no-products {
            background: rgba(156, 163, 175, 0.2);
            color: var(--text-muted);
            border: 1px solid rgba(156, 163, 175, 0.3);
        }

        .count-label {
            font-size: 12px;
            color: var(--text-muted);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-action {
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .btn-action:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-edit {
            background: rgba(59, 130, 246, 0.1);
            color: var(--accent-blue);
            border: 1px solid rgba(59, 130, 246, 0.2);
        }

        .btn-edit:hover:not(:disabled) {
            background: rgba(59, 130, 246, 0.2);
            transform: scale(1.05);
        }

        .btn-view {
            background: rgba(16, 185, 129, 0.1);
            color: var(--accent-green);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .btn-view:hover:not(:disabled) {
            background: rgba(16, 185, 129, 0.2);
            transform: scale(1.05);
        }

        .btn-delete {
            background: rgba(239, 68, 68, 0.1);
            color: var(--accent-red);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .btn-delete:hover:not(:disabled) {
            background: rgba(239, 68, 68, 0.2);
            transform: scale(1.05);
        }

        .btn-delete.disabled {
            background: rgba(156, 163, 175, 0.1);
            color: var(--text-muted);
            border: 1px solid rgba(156, 163, 175, 0.2);
        }

        /* No Materials */
        .no-materials {
            grid-column: 1 / -1;
            text-align: center;
            padding: 80px 40px;
            background: var(--card-bg);
            backdrop-filter: var(--backdrop-blur);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
        }

        .no-materials i {
            font-size: 64px;
            color: var(--text-muted);
            margin-bottom: 24px;
            opacity: 0.5;
        }

        .no-materials h3 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--text-primary);
        }

        .no-materials p {
            font-size: 16px;
            color: var(--text-secondary);
            margin-bottom: 32px;
        }

        /* Statistics Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        /* Animation keyframes */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .material-card {
            opacity: 0;
            animation: fadeInUp 0.6s ease-out forwards;
        }

        .material-card:nth-child(1) { animation-delay: 0.1s; }
        .material-card:nth-child(2) { animation-delay: 0.2s; }
        .material-card:nth-child(3) { animation-delay: 0.3s; }
        .material-card:nth-child(4) { animation-delay: 0.4s; }
        .material-card:nth-child(5) { animation-delay: 0.5s; }
        .material-card:nth-child(6) { animation-delay: 0.6s; }

        /* Modal Styles */
        #materialModalNew {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            background-color: rgba(0, 0, 0, 0.8) !important;
            backdrop-filter: blur(8px) !important;
            z-index: 99999 !important;
            display: none !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 20px !important;
            box-sizing: border-box !important;
        }

        #materialModalNew.show {
            display: flex !important;
        }

        .modal-content-new {
            width: 100% !important;
            max-width: 600px !important;
            background: linear-gradient(135deg, rgba(30, 30, 30, 0.95), rgba(20, 20, 20, 0.98)) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            border-radius: 16px !important;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.8), 0 0 0 1px rgba(255, 255, 255, 0.05) !important;
            overflow: hidden !important;
            max-height: 90vh !important;
            display: flex !important;
            flex-direction: column !important;
            animation: modalFadeIn 0.3s ease-out forwards !important;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-header-new {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            padding: 24px 32px !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
            background: rgba(255, 255, 255, 0.02) !important;
        }

        .modal-header-new h3 {
            font-size: 20px !important;
            font-weight: 600 !important;
            color: #ffffff !important;
            margin: 0 !important;
            display: flex !important;
            align-items: center !important;
            gap: 12px !important;
        }

        .modal-header-new h3::before {
            content: "✨" !important;
            font-size: 18px !important;
        }

        .modal-close-new {
            background: rgba(255, 255, 255, 0.1) !important;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
            color: #ffffff !important;
            font-size: 16px !important;
            cursor: pointer !important;
            width: 40px !important;
            height: 40px !important;
            border-radius: 50% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            transition: all 0.2s ease !important;
        }

        .modal-close-new:hover {
            background: rgba(239, 68, 68, 0.2) !important;
            border-color: rgba(239, 68, 68, 0.4) !important;
            color: #ef4444 !important;
            transform: scale(1.1) !important;
        }

        .modal-body-new {
            padding: 32px !important;
            overflow-y: auto !important;
            flex: 1 !important;
        }

        .form-group-new {
            margin-bottom: 24px !important;
        }

        .form-group-new label {
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            font-weight: 500 !important;
            color: #ffffff !important;
            margin-bottom: 8px !important;
            font-size: 14px !important;
        }

        .form-group-new label i {
            color: #fbbf24 !important;
            width: 16px !important;
            font-size: 14px !important;
        }

        .form-group-new input,
        .form-group-new textarea {
            width: 100% !important;
            padding: 16px 20px !important;
            background: rgba(255, 255, 255, 0.1) !important;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
            border-radius: 12px !important;
            color: #ffffff !important;
            font-size: 14px !important;
            transition: all 0.3s ease !important;
            font-family: "Inter", sans-serif !important;
            box-sizing: border-box !important;
        }

        .form-group-new input:focus,
        .form-group-new textarea:focus {
            outline: none !important;
            border-color: #fbbf24 !important;
            box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.2) !important;
            background: rgba(255, 255, 255, 0.15) !important;
        }

        .form-group-new input::placeholder,
        .form-group-new textarea::placeholder {
            color: rgba(255, 255, 255, 0.5) !important;
        }

        .form-group-new textarea {
            resize: vertical !important;
            min-height: 120px !important;
        }

        .modal-footer-new {
            display: flex !important;
            justify-content: flex-end !important;
            gap: 16px !important;
            padding: 24px 32px !important;
            border-top: 1px solid rgba(255, 255, 255, 0.1) !important;
            background: rgba(255, 255, 255, 0.02) !important;
        }

        .btn-cancel-new {
            background: rgba(255, 255, 255, 0.1) !important;
            color: #ffffff !important;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
            padding: 12px 24px !important;
            border-radius: 8px !important;
            font-weight: 500 !important;
            font-size: 14px !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
        }

        .btn-cancel-new:hover {
            background: rgba(255, 255, 255, 0.2) !important;
            transform: translateY(-1px) !important;
        }

        .btn-save-new {
            background: linear-gradient(135deg, #fbbf24, #f59e0b) !important;
            color: #000000 !important;
            border: none !important;
            padding: 12px 24px !important;
            border-radius: 8px !important;
            font-weight: 600 !important;
            font-size: 14px !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
        }

        .btn-save-new:hover {
            background: linear-gradient(135deg, #f59e0b, #d97706) !important;
            transform: translateY(-1px) !important;
            box-shadow: 0 8px 25px rgba(251, 191, 36, 0.3) !important;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .materials-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .materials-grid {
                grid-template-columns: 1fr;
            }
            
            .material-footer {
                flex-direction: column;
                gap: 16px;
                align-items: stretch;
            }
            
            .action-buttons {
                justify-content: center;
            }
            
            .modal-footer-new {
                flex-direction: column;
            }
            
            .btn-cancel-new, .btn-save-new {
                width: 100%;
                justify-content: center;
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
                        <li class="nav-item active">
                            <a href="materials.php" class="nav-link">
                                <i class="fas fa-cubes"></i>
                                <span>Materiales</span>
                            </a>
                        </li>
                        <li class="nav-item">
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
                            <a href="#" onclick="showMaterialFormNew(); return false;" class="nav-link">
                                <i class="fas fa-plus"></i>
                                <span>Nuevo Material</span>
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
                <h1>Gestionar Materiales</h1>
                <div class="user-info">
                    Bienvenido, <span class="username"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                </div>
            </header>

            <!-- Materials Content -->
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
                        <h2><i class="fas fa-cubes"></i> Gestión de Materiales</h2>
                        <p>Administra los materiales utilizados en tus productos</p>
                    </div>
                    <button class="btn btn-primary" onclick="showMaterialFormNew(); return false;">
                        <i class="fas fa-plus"></i>
                        Nuevo Material
                    </button>
                </div>

                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-info">
                                <div class="stat-label">Total Materiales</div>
                                <div class="stat-value"><?php echo $stats['total_materials']; ?></div>
                            </div>
                            <div class="stat-icon blue">
                                <i class="fas fa-cubes"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-info">
                                <div class="stat-label">Con Productos</div>
                                <div class="stat-value"><?php echo $materials_with_products; ?></div>
                            </div>
                            <div class="stat-icon green">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-info">
                                <div class="stat-label">Sin Productos</div>
                                <div class="stat-value"><?php echo $stats['total_materials'] - $materials_with_products; ?></div>
                            </div>
                            <div class="stat-icon yellow">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters and Search -->
                <div class="filters-section">
                    <form method="GET" class="filters-form">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" placeholder="Buscar materiales..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <button type="submit" class="btn btn-outline">
                            <i class="fas fa-search"></i>
                            Buscar
                        </button>
                        
                        <a href="materials.php" class="btn btn-ghost">
                            <i class="fas fa-times"></i>
                            Limpiar
                        </a>
                    </form>
                </div>

                <!-- Materials Grid -->
                <div class="materials-grid">
                    <?php 
                    $result->data_seek(0); // Reset result pointer
                    if ($result && $result->num_rows > 0): 
                    ?>
                        <?php while ($material = $result->fetch_assoc()): ?>
                            <div class="material-card">
                                <div class="material-header">
                                    <div class="material-icon">
                                        <i class="fas fa-cube"></i>
                                    </div>
                                    <div class="material-title">
                                        <h3><?php echo htmlspecialchars($material['nombre']); ?></h3>
                                        <span class="material-id">ID: <?php echo $material['id_material']; ?></span>
                                    </div>
                                </div>
                                
                                <div class="material-info">
                                    <?php if (!empty($material['descripcion'])): ?>
                                        <p class="material-description">
                                            <?php echo htmlspecialchars($material['descripcion']); ?>
                                        </p>
                                    <?php else: ?>
                                        <p class="no-description">Sin descripción</p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="material-footer">
                                    <div class="product-count">
                                        <span class="count-badge <?php echo $material['product_count'] > 0 ? 'has-products' : 'no-products'; ?>">
                                            <?php echo $material['product_count']; ?>
                                        </span>
                                        <span class="count-label">
                                            <?php echo $material['product_count'] == 1 ? 'producto' : 'productos'; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="action-buttons">
                                        <button class="btn-action btn-edit" 
                                                onclick="editMaterialNew(<?php echo htmlspecialchars(json_encode($material)); ?>)"
                                                title="Editar material">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-action btn-view" 
                                                onclick="viewMaterialProducts(<?php echo $material['id_material']; ?>)"
                                                title="Ver productos con este material">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($material['product_count'] == 0): ?>
                                            <button class="btn-action btn-delete" 
                                                    onclick="deleteMaterial(<?php echo $material['id_material']; ?>)"
                                                    title="Eliminar material">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php else: ?>
                                            <button class="btn-action btn-delete disabled" 
                                                    title="No se puede eliminar: tiene productos asociados"
                                                    disabled>
                                                <i class="fas fa-lock"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="no-materials">
                            <i class="fas fa-cubes"></i>
                            <h3>No hay materiales</h3>
                            <p>No se encontraron materiales que coincidan con los criterios de búsqueda.</p>
                            <button class="btn btn-primary" onclick="showMaterialFormNew(); return false;">
                                <i class="fas fa-plus"></i>
                                Crear primer material
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- NEW Material Form Modal -->
    <div id="materialModalNew">
        <div class="modal-content-new">
            <div class="modal-header-new">
                <h3 id="modalTitleNew">Nuevo Material</h3>
                <button type="button" class="modal-close-new" onclick="hideMaterialFormNew()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" id="materialFormNew">
                <div class="modal-body-new">
                    <input type="hidden" name="id" id="material_id_new">
                    
                    <div class="form-group-new">
                        <label for="nombre_new">
                            <i class="fas fa-cube"></i>
                            Nombre del material
                        </label>
                        <input type="text" id="nombre_new" name="nombre" required 
                               placeholder="Ej: Oro 18k, Plata 925, Platino...">
                    </div>
                    
                    <div class="form-group-new">
                        <label for="descripcion_new">
                            <i class="fas fa-align-left"></i>
                            Descripción
                        </label>
                        <textarea id="descripcion_new" name="descripcion" rows="4" 
                                  placeholder="Describe las características y propiedades del material..."></textarea>
                    </div>
                </div>
                
                <div class="modal-footer-new">
                    <button type="button" class="btn-cancel-new" onclick="hideMaterialFormNew()">
                        <i class="fas fa-times"></i>
                        Cancelar
                    </button>
                    <button type="submit" class="btn-save-new">
                        <i class="fas fa-save"></i>
                        Guardar Material
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/dashboard.js"></script>
    <script>
        // Material Modal Functions
        function showMaterialFormNew(material = null) {
            console.log("Showing material form", material);
            const modal = document.getElementById('materialModalNew');
            const modalTitle = document.getElementById('modalTitleNew');
            const form = document.getElementById('materialFormNew');
            
            if (material) {
                modalTitle.textContent = 'Editar Material';
                document.getElementById('material_id_new').value = material.id_material || '';
                document.getElementById('nombre_new').value = material.nombre || '';
                document.getElementById('descripcion_new').value = material.descripcion || '';
            } else {
                modalTitle.textContent = 'Nuevo Material';
                form.reset();
            }
            
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
            
            // Focus on first input
            setTimeout(() => {
                const firstInput = document.getElementById('nombre_new');
                if (firstInput) firstInput.focus();
            }, 300);
        }

        function hideMaterialFormNew() {
            console.log("Hiding material form");
            const modal = document.getElementById('materialModalNew');
            modal.classList.remove('show');
            document.body.style.overflow = 'auto';
        }

        function editMaterialNew(material) {
            console.log("Editing material", material);
            showMaterialFormNew(material);
        }

        function viewMaterialProducts(materialId) {
            console.log("Viewing products for material", materialId);
            window.location.href = `products.php?material=${materialId}`;
        }

        function deleteMaterial(materialId) {
            if (confirm('¿Estás seguro de que quieres eliminar este material? Esta acción no se puede deshacer.')) {
                console.log("Deleting material", materialId);
                window.location.href = `materials.php?delete=${materialId}`;
            }
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            const modal = document.getElementById('materialModalNew');
            if (e.target === modal) {
                hideMaterialFormNew();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('materialModalNew');
                if (modal.classList.contains('show')) {
                    hideMaterialFormNew();
                }
            }
        });

        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            console.log("DOM loaded, initializing materials page");
            
            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
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
