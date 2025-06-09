<?php
session_start();
require_once '../includes/db_connection.php';
require_once '../includes/helpers.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$success_message = $error_message = '';

// Handle form submission for adding/editing categories
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = isset($_POST['id']) ? intval($_POST['id']) : null;
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);

    // Validation
    if (empty($nombre)) {
        $error_message = "El nombre de la categoría es obligatorio.";
    } else {
        // Check for duplicate names (excluding current record if editing)
        $check_query = "SELECT id_categoria FROM categorias WHERE nombre = ?";
        if ($id) {
            $check_query .= " AND id_categoria != ?";
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
            $error_message = "Ya existe una categoría con ese nombre.";
        } else {
            if ($id) {
                // Update existing category
                $query = "UPDATE categorias SET nombre = ?, descripcion = ? WHERE id_categoria = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ssi", $nombre, $descripcion, $id);
            } else {
                // Add new category
                $query = "INSERT INTO categorias (nombre, descripcion) VALUES (?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ss", $nombre, $descripcion);
            }

            if ($stmt->execute()) {
                $success_message = $id ? "Categoría actualizada con éxito." : "Categoría añadida con éxito.";
            } else {
                $error_message = "Error al " . ($id ? "actualizar" : "añadir") . " la categoría: " . $conn->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

// Handle category deletion
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Check if category has products
    $check_products = "SELECT COUNT(*) as count FROM productos WHERE id_categoria = ?";
    $check_stmt = $conn->prepare($check_products);
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $product_count = $check_stmt->get_result()->fetch_assoc()['count'];
    $check_stmt->close();
    
    if ($product_count > 0) {
        $error_message = "No se puede eliminar la categoría porque tiene $product_count producto(s) asociado(s).";
    } else {
        $query = "DELETE FROM categorias WHERE id_categoria = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $success_message = "Categoría eliminada con éxito.";
        } else {
            $error_message = "Error al eliminar la categoría: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch all categories with product count
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$where_conditions = [];
$params = [];
$types = "";

if (!empty($search)) {
    $where_conditions[] = "(c.nombre LIKE ? OR c.descripcion LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "ss";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

$query = "SELECT c.*, COUNT(p.id_producto) as product_count 
          FROM categorias c 
          LEFT JOIN productos p ON c.id_categoria = p.id_categoria 
          $where_clause
          GROUP BY c.id_categoria 
          ORDER BY c.nombre ASC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get statistics (simplified without activo column)
$stats_query = "SELECT COUNT(*) as total_categories FROM categorias";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();
$stats['active_categories'] = $stats['total_categories']; // All categories are considered active
$stats['inactive_categories'] = 0;

// Calculate categories with products
$categories_with_products = 0;
$result->data_seek(0);
while ($cat = $result->fetch_assoc()) {
    if ($cat['product_count'] > 0) $categories_with_products++;
}
$result->data_seek(0); // Reset pointer

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Categorías - Panda Joyeros</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/modern-dashboard.css">
    
    <style>
    /* Estilos específicos para categorías */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 24px;
        margin-bottom: 32px;
    }

    .table-container {
        background: var(--card-bg);
        backdrop-filter: var(--backdrop-blur);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        overflow: hidden;
        margin-bottom: 32px;
    }

    .categories-table {
        width: 100%;
        border-collapse: collapse;
    }

    .categories-table thead {
        background: rgba(255, 255, 255, 0.05);
        border-bottom: 1px solid var(--border-color);
    }

    .categories-table th {
        padding: 20px 24px;
        text-align: left;
        font-weight: 600;
        color: var(--text-primary);
        font-size: 14px;
        border-bottom: 1px solid var(--border-color);
    }

    .categories-table th i {
        margin-right: 8px;
        color: var(--accent-yellow);
        width: 16px;
    }

    .categories-table tbody tr {
        border-bottom: 1px solid var(--border-color);
        transition: var(--transition);
    }

    .categories-table tbody tr:hover {
        background: rgba(255, 255, 255, 0.05);
    }

    .categories-table tbody tr.inactive {
        opacity: 0.6;
    }

    .categories-table td {
        padding: 20px 24px;
        vertical-align: middle;
    }

    /* Category Info */
    .category-info {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .category-icon {
        width: 48px;
        height: 48px;
        background: linear-gradient(135deg, var(--accent-yellow), #f59e0b);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #000;
        font-size: 20px;
        flex-shrink: 0;
    }

    .category-details h4 {
        font-size: 16px;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 4px;
    }

    .category-id {
        font-size: 12px;
        color: var(--text-muted);
        font-weight: 500;
    }

    /* Description Cell */
    .description-cell p {
        color: var(--text-secondary);
        font-size: 14px;
        line-height: 1.5;
        margin: 0;
        max-width: 300px;
    }

    .no-description {
        color: var(--text-muted);
        font-style: italic;
        font-size: 14px;
    }

    /* Product Count */
    .product-count {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .count-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 32px;
        height: 32px;
        border-radius: 16px;
        font-size: 14px;
        font-weight: 600;
        padding: 0 12px;
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
        width: 40px;
        height: 40px;
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

    /* No Categories */
    .no-categories {
        text-align: center;
        padding: 80px 40px;
        background: var(--card-bg);
        backdrop-filter: var(--backdrop-blur);
    }

    .no-categories i {
        font-size: 64px;
        color: var(--text-muted);
        margin-bottom: 24px;
        opacity: 0.5;
    }

    .no-categories h3 {
        font-size: 24px;
        font-weight: 600;
        margin-bottom: 12px;
        color: var(--text-primary);
    }

    .no-categories p {
        font-size: 16px;
        color: var(--text-secondary);
        margin-bottom: 32px;
    }

    /* Category Form */
    .category-form {
        padding: 24px;
    }

    .form-group {
        margin-bottom: 24px;
    }

    .form-group label {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 500;
        color: var(--text-primary);
        margin-bottom: 8px;
        font-size: 14px;
    }

    .form-group label i {
        color: var(--accent-yellow);
        width: 16px;
        font-size: 14px;
    }

    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 12px 16px;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        color: var(--text-primary);
        font-size: 14px;
        transition: var(--transition);
        font-family: inherit;
    }

    .form-group input:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: var(--accent-yellow);
        box-shadow: 0 0 0 2px rgba(251, 191, 36, 0.2);
    }

    .form-group input::placeholder,
    .form-group textarea::placeholder {
        color: var(--text-muted);
    }

    .form-group textarea {
        resize: vertical;
        min-height: 100px;
    }

    /* Form Actions */
    .form-actions {
        display: flex;
        gap: 16px;
        justify-content: flex-end;
        padding-top: 24px;
        border-top: 1px solid var(--border-color);
    }

    /* Loading and Animation States */
    .category-row {
        opacity: 0;
        animation: fadeInUp 0.6s ease-out forwards;
    }

    .category-row:nth-child(1) { animation-delay: 0.1s; }
    .category-row:nth-child(2) { animation-delay: 0.2s; }
    .category-row:nth-child(3) { animation-delay: 0.3s; }
    .category-row:nth-child(4) { animation-delay: 0.4s; }
    .category-row:nth-child(5) { animation-delay: 0.5s; }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Hover effects */
    .category-row:hover .category-icon {
        transform: scale(1.05);
    }

    .category-row:hover .category-details h4 {
        color: var(--accent-yellow);
    }

    /* NUEVO MODAL STYLES - IMPORTANTE */
    #categoryModalNew {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100% !important;
        height: 100% !important;
        background: rgba(0, 0, 0, 0.7) !important;
        backdrop-filter: blur(5px) !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        z-index: 99999 !important;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.3s ease, visibility 0.3s ease !important;
    }

    #categoryModalNew.show {
        opacity: 1 !important;
        visibility: visible !important;
    }

    #categoryModalNew .modal-content {
        background: var(--card-bg) !important;
        border-radius: 12px !important;
        width: 90% !important;
        max-width: 600px !important;
        max-height: 90vh !important;
        overflow-y: auto !important;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5) !important;
        transform: translateY(20px) !important;
        transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1) !important;
        border: 1px solid var(--border-color) !important;
    }

    #categoryModalNew.show .modal-content {
        transform: translateY(0) !important;
    }

    #categoryModalNew .modal-header {
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
        padding: 20px 24px !important;
        border-bottom: 1px solid var(--border-color) !important;
    }

    #categoryModalNew .modal-header h3 {
        font-size: 20px !important;
        font-weight: 600 !important;
        color: var(--text-primary) !important;
        margin: 0 !important;
    }

    #categoryModalNew .modal-close {
        background: transparent !important;
        border: none !important;
        color: var(--text-muted) !important;
        font-size: 18px !important;
        cursor: pointer !important;
        width: 36px !important;
        height: 36px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        border-radius: 8px !important;
        transition: all 0.2s ease !important;
    }

    #categoryModalNew .modal-close:hover {
        background: rgba(255, 255, 255, 0.1) !important;
        color: var(--text-primary) !important;
    }

    /* Responsive styles */
    @media (max-width: 1024px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .categories-table {
            font-size: 14px;
        }
        
        .categories-table th,
        .categories-table td {
            padding: 16px 20px;
        }
    }

    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        .categories-table {
            min-width: 800px;
        }
        
        .category-info {
            gap: 12px;
        }
        
        .category-icon {
            width: 40px;
            height: 40px;
            font-size: 16px;
        }
        
        .description-cell p {
            max-width: 200px;
        }
        
        .form-actions {
            flex-direction: column;
        }
        
        #categoryModalNew .modal-content {
            width: 95% !important;
        }
    }

    /* Validation styles */
    .field-error {
        color: var(--accent-red);
        font-size: 12px;
        margin-top: 4px;
        display: flex;
        align-items: center;
        gap: 4px;
        animation: slideDown 0.3s ease-out;
    }

    input.error,
    textarea.error {
        border-color: var(--accent-red) !important;
        box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2) !important;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-5px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
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
                        <li class="nav-item active">
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
                            <a href="#" onclick="showCategoryFormNew(); return false;" class="nav-link">
                                <i class="fas fa-plus"></i>
                                <span>Nueva Categoría</span>
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
                <h1>Gestionar Categorías</h1>
                <div class="user-info">
                    Bienvenido, <span class="username"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                </div>
            </header>

            <!-- Categories Content -->
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
                        <h2><i class="fas fa-tags"></i> Gestión de Categorías</h2>
                        <p>Organiza tus productos por categorías</p>
                    </div>
                    <button class="btn btn-primary" onclick="showCategoryFormNew()">
                        <i class="fas fa-plus"></i>
                        Nueva Categoría
                    </button>
                </div>

                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-info">
                                <div class="stat-label">Total Categorías</div>
                                <div class="stat-value"><?php echo $stats['total_categories']; ?></div>
                            </div>
                            <div class="stat-icon blue">
                                <i class="fas fa-tags"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-info">
                                <div class="stat-label">Categorías con Productos</div>
                                <div class="stat-value"><?php echo $categories_with_products; ?></div>
                            </div>
                            <div class="stat-icon green">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-info">
                                <div class="stat-label">Categorías Vacías</div>
                                <div class="stat-value">
                                    <?php echo $stats['total_categories'] - $categories_with_products; ?>
                                </div>
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
                            <input type="text" name="search" placeholder="Buscar categorías..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <button type="submit" class="btn btn-outline">
                            <i class="fas fa-filter"></i>
                            Buscar
                        </button>
                        
                        <a href="categories.php" class="btn btn-ghost">
                            <i class="fas fa-times"></i>
                            Limpiar
                        </a>
                    </form>
                </div>

                <!-- Categories Table -->
                <div class="table-container">
                    <?php 
                    $result->data_seek(0); // Reset result pointer
                    if ($result && $result->num_rows > 0): 
                    ?>
                        <table class="categories-table">
                            <thead>
                                <tr>
                                    <th>
                                        <i class="fas fa-tag"></i>
                                        Categoría
                                    </th>
                                    <th>
                                        <i class="fas fa-align-left"></i>
                                        Descripción
                                    </th>
                                    <th>
                                        <i class="fas fa-gem"></i>
                                        Productos
                                    </th>
                                    <th>
                                        <i class="fas fa-cogs"></i>
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($category = $result->fetch_assoc()): ?>
                                    <tr class="category-row">
                                        <td>
                                            <div class="category-info">
                                                <div class="category-icon">
                                                    <i class="fas fa-tag"></i>
                                                </div>
                                                <div class="category-details">
                                                    <h4><?php echo htmlspecialchars($category['nombre']); ?></h4>
                                                    <span class="category-id">ID: <?php echo $category['id_categoria']; ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="description-cell">
                                                <?php if (!empty($category['descripcion'])): ?>
                                                    <p><?php echo htmlspecialchars($category['descripcion']); ?></p>
                                                <?php else: ?>
                                                    <span class="no-description">Sin descripción</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="product-count">
                                                <span class="count-badge <?php echo $category['product_count'] > 0 ? 'has-products' : 'no-products'; ?>">
                                                    <?php echo $category['product_count']; ?>
                                                </span>
                                                <span class="count-label">
                                                    <?php echo $category['product_count'] == 1 ? 'producto' : 'productos'; ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn-action btn-edit" 
                                                        onclick="editCategoryNew(<?php echo htmlspecialchars(json_encode($category)); ?>)"
                                                        title="Editar categoría">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn-action btn-view" 
                                                        onclick="viewCategoryProducts(<?php echo $category['id_categoria']; ?>)"
                                                        title="Ver productos de esta categoría">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($category['product_count'] == 0): ?>
                                                    <button class="btn-action btn-delete" 
                                                            onclick="deleteCategoryNew(<?php echo $category['id_categoria']; ?>)"
                                                            title="Eliminar categoría">
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
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-categories">
                            <i class="fas fa-tags"></i>
                            <h3>No hay categorías</h3>
                            <p>No se encontraron categorías que coincidan con los criterios de búsqueda.</p>
                            <button class="btn btn-primary" onclick="showCategoryFormNew()">
                                <i class="fas fa-plus"></i>
                                Crear primera categoría
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- NUEVO Category Form Modal -->
    <div id="categoryModalNew">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitleNew">Nueva Categoría</h3>
                <button class="modal-close" onclick="hideCategoryFormNew()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" class="category-form" id="categoryFormNew">
                <input type="hidden" name="id" id="category_id_new">
                
                <div class="form-group">
                    <label for="nombre_new">
                        <i class="fas fa-tag"></i>
                        Nombre de la categoría
                    </label>
                    <input type="text" id="nombre_new" name="nombre" required 
                           placeholder="Ej: Anillos, Collares, Pulseras...">
                </div>
                
                <div class="form-group">
                    <label for="descripcion_new">
                        <i class="fas fa-align-left"></i>
                        Descripción
                    </label>
                    <textarea id="descripcion_new" name="descripcion" rows="4" 
                              placeholder="Describe el tipo de productos que incluye esta categoría..."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-ghost" onclick="hideCategoryFormNew()">
                        <i class="fas fa-times"></i>
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Guardar Categoría
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/dashboard.js"></script>
    <script>
    // Global variables
    let currentEditingCategory = null;

    // Initialize when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM loaded - initializing categories page');
        initializeCategoriesPage();
    });

    function initializeCategoriesPage() {
        // Initialize form validation
        initializeFormValidation();
        
        // Initialize search functionality
        initializeSearch();
        
        // Auto-hide alerts after 5 seconds
        autoHideAlerts();
        
        // Initialize tooltips
        initializeTooltips();
        
        console.log('Categories page initialized');
    }

    // Show category form modal - NEW VERSION
    function showCategoryFormNew(category = null) {
        console.log('Opening modal with category:', category);
        const modal = document.getElementById('categoryModalNew');
        const modalTitle = document.getElementById('modalTitleNew');
        const form = document.getElementById('categoryFormNew');
        
        if (!modal || !modalTitle || !form) {
            console.error('Modal elements not found!');
            return;
        }
        
        if (category) {
            modalTitle.textContent = 'Editar Categoría';
            populateFormNew(category);
            currentEditingCategory = category;
        } else {
            modalTitle.textContent = 'Nueva Categoría';
            form.reset();
            currentEditingCategory = null;
        }
        
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
        
        // Focus on first input
        setTimeout(() => {
            const firstInput = form.querySelector('input[type="text"]');
            if (firstInput) firstInput.focus();
        }, 300);
        
        console.log('Modal opened successfully');
    }

    // Hide category form modal - NEW VERSION
    function hideCategoryFormNew() {
        console.log('Closing modal');
        const modal = document.getElementById('categoryModalNew');
        if (!modal) {
            console.error('Modal element not found!');
            return;
        }
        
        modal.classList.remove('show');
        document.body.style.overflow = 'auto';
        currentEditingCategory = null;
        
        console.log('Modal closed successfully');
    }

    // Edit category - NEW VERSION
    function editCategoryNew(category) {
        console.log('Editing category:', category);
        showCategoryFormNew(category);
    }

    // View category products
    function viewCategoryProducts(categoryId) {
        console.log('Viewing products for category ID:', categoryId);
        // Redirect to products page with category filter
        window.location.href = `products.php?category=${categoryId}`;
    }

    // Delete category - NEW VERSION
    function deleteCategoryNew(categoryId) {
        console.log('Attempting to delete category ID:', categoryId);
        if (confirm('¿Estás seguro de que quieres eliminar esta categoría? Esta acción no se puede deshacer.')) {
            // Show loading state
            const deleteBtn = event.target.closest('.btn-delete');
            const originalContent = deleteBtn.innerHTML;
            deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            deleteBtn.disabled = true;
            
            // Redirect to delete
            window.location.href = `categories.php?delete=${categoryId}`;
        }
    }

    // Populate form with category data - NEW VERSION
    function populateFormNew(category) {
        console.log('Populating form with data:', category);
        document.getElementById('category_id_new').value = category.id_categoria || '';
        document.getElementById('nombre_new').value = category.nombre || '';
        document.getElementById('descripcion_new').value = category.descripcion || '';
    }

    // Initialize form validation
    function initializeFormValidation() {
        console.log('Initializing form validation');
        const form = document.getElementById('categoryFormNew');
        
        if (!form) {
            console.error('Form element not found!');
            return;
        }
        
        form.addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                return false;
            }
            
            // Show loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalContent = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
            submitBtn.disabled = true;
            
            // Re-enable after 10 seconds as fallback
            setTimeout(() => {
                submitBtn.innerHTML = originalContent;
                submitBtn.disabled = false;
            }, 10000);
        });
        
        // Real-time validation
        const requiredFields = form.querySelectorAll('[required]');
        requiredFields.forEach(field => {
            field.addEventListener('blur', validateField);
            field.addEventListener('input', clearFieldError);
        });
    }

    // Validate individual field
    function validateField(e) {
        const field = e.target;
        const value = field.value.trim();
        
        // Remove existing error
        clearFieldError(e);
        
        if (field.hasAttribute('required') && !value) {
            showFieldError(field, 'Este campo es obligatorio');
            return false;
        }
        
        // Specific validations
        if (field.name === 'nombre') {
            if (value.length < 2) {
                showFieldError(field, 'El nombre debe tener al menos 2 caracteres');
                return false;
            }
            if (value.length > 100) {
                showFieldError(field, 'El nombre no puede exceder 100 caracteres');
                return false;
            }
        }
        
        if (field.name === 'descripcion' && value.length > 500) {
            showFieldError(field, 'La descripción no puede exceder 500 caracteres');
            return false;
        }
        
        return true;
    }

    // Clear field error
    function clearFieldError(e) {
        const field = e.target;
        const errorElement = field.parentNode.querySelector('.field-error');
        if (errorElement) {
            errorElement.remove();
        }
        field.classList.remove('error');
    }

    // Show field error
    function showFieldError(field, message) {
        field.classList.add('error');
        
        const errorElement = document.createElement('div');
        errorElement.className = 'field-error';
        errorElement.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${message}`;
        
        field.parentNode.appendChild(errorElement);
    }

    // Validate entire form
    function validateForm() {
        const form = document.getElementById('categoryFormNew');
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!validateField({ target: field })) {
                isValid = false;
            }
        });
        
        return isValid;
    }

    // Initialize search functionality
    function initializeSearch() {
        const searchInput = document.querySelector('input[name="search"]');
        
        if (searchInput) {
            // Debounced search
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    if (this.value.length >= 2 || this.value.length === 0) {
                        // Auto-submit form for live search
                        // this.form.submit();
                    }
                }, 500);
            });
        }
    }

    // Auto-hide alerts
    function autoHideAlerts() {
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
    }

    // Initialize tooltips
    function initializeTooltips() {
        document.querySelectorAll('[title]').forEach(element => {
            element.addEventListener('mouseenter', function(e) {
                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip';
                tooltip.textContent = this.getAttribute('title');
                document.body.appendChild(tooltip);
                
                const rect = this.getBoundingClientRect();
                tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
                tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';
                
                this.setAttribute('data-title', this.getAttribute('title'));
                this.removeAttribute('title');
            });
            
            element.addEventListener('mouseleave', function() {
                const tooltip = document.querySelector('.tooltip');
                if (tooltip) {
                    tooltip.remove();
                }
                
                if (this.getAttribute('data-title')) {
                    this.setAttribute('title', this.getAttribute('data-title'));
                    this.removeAttribute('data-title');
                }
            });
        });
    }

    // Close modal when clicking outside
    document.addEventListener('click', function(e) {
        const modal = document.getElementById('categoryModalNew');
        if (e.target === modal) {
            hideCategoryFormNew();
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('categoryModalNew');
            if (modal && modal.classList.contains('show')) {
                hideCategoryFormNew();
            }
        }
    });
    </script>
</body>
</html>
