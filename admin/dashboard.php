
<?php


// Check if price_helpers.php exists
if (file_exists('../includes/price_helpers.php')) {
    require_once '../includes/price_helpers.php';
} else {
    // Función para formatear precios en pesos colombianos
    function format_cop_price($price) {
        return '$' . number_format($price, 0, ',', '.') . ' COP';
    }
}

// Rest of the dashboard code would go here.
// For example:

// echo format_cop_price(1000000);
?>
<?php
session_start();
require_once '../includes/db_connection.php';
require_once '../includes/helpers.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Obtener estadísticas para el dashboard
$query_products = "SELECT COUNT(*) as total FROM productos";
$result_products = $conn->query($query_products);
$total_products = $result_products->fetch_assoc()['total'];

$query_categories = "SELECT COUNT(*) as total FROM categorias";
$result_categories = $conn->query($query_categories);
$total_categories = $result_categories->fetch_assoc()['total'];

$query_materials = "SELECT COUNT(*) as total FROM materiales";
$result_materials = $conn->query($query_materials);
$total_materials = $result_materials->fetch_assoc()['total'];

$query_featured = "SELECT COUNT(*) as total FROM productos WHERE destacado = 1";
$result_featured = $conn->query($query_featured);
$total_featured = $result_featured->fetch_assoc()['total'];

// Obtener últimos productos añadidos
$query_recent = "SELECT p.id_producto, p.nombre, p.precio, c.nombre AS categoria_nombre 
                 FROM productos p 
                 LEFT JOIN categorias c ON p.id_categoria = c.id_categoria 
                 ORDER BY p.id_producto DESC LIMIT 5";
$result_recent = $conn->query($query_recent);

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - Panda Joyeros</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/modern-dashboard.css">
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
                        <li class="nav-item active">
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
                            <a href="products.php" class="nav-link">
                                <i class="fas fa-plus"></i>
                                <span>Nuevo Producto</span>
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
                <h1>Panel de Administración</h1>
                <div class="user-info">
                    Bienvenido, <span class="username"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                </div>
            </header>

            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <!-- Welcome Section -->
                <div class="welcome-section">
                    <div class="welcome-text">
                        <h2>¡Bienvenido de vuelta! 👋</h2>
                        <p>Aquí tienes un resumen de tu tienda de joyería</p>
                    </div>
                    <div class="action-buttons">
                        <a href="products.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Nuevo Producto
                        </a>
                        <a href="../index.php" target="_blank" class="btn btn-outline">
                            <i class="fas fa-eye"></i>
                            Ver Tienda
                        </a>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-info">
                                <p class="stat-label">Productos</p>
                                <p class="stat-value"><?php echo $total_products; ?></p>
                                <div class="stat-change positive">
                                    <i class="fas fa-arrow-up"></i>
                                    <span>+12%</span>
                                </div>
                            </div>
                            <div class="stat-icon blue">
                                <i class="fas fa-gem"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-info">
                                <p class="stat-label">Categorías</p>
                                <p class="stat-value"><?php echo $total_categories; ?></p>
                                <div class="stat-change positive">
                                    <i class="fas fa-arrow-up"></i>
                                    <span>+2</span>
                                </div>
                            </div>
                            <div class="stat-icon green">
                                <i class="fas fa-tags"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-info">
                                <p class="stat-label">Materiales</p>
                                <p class="stat-value"><?php echo $total_materials; ?></p>
                                <div class="stat-change positive">
                                    <i class="fas fa-arrow-up"></i>
                                    <span>+5</span>
                                </div>
                            </div>
                            <div class="stat-icon purple">
                                <i class="fas fa-cubes"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-info">
                                <p class="stat-label">Destacados</p>
                                <p class="stat-value"><?php echo $total_featured; ?></p>
                                <div class="stat-change neutral">
                                    <i class="fas fa-arrow-up"></i>
                                    <span>3 nuevos</span>
                                </div>
                            </div>
                            <div class="stat-icon yellow">
                                <i class="fas fa-star"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Products -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-clock"></i>
                            <h3>Productos Recientes</h3>
                            <p>Los últimos productos añadidos a tu inventario</p>
                        </div>
                        <a href="products.php" class="btn btn-outline btn-sm">Ver Todos</a>
                    </div>
                    <div class="card-content">
                        <?php if ($result_recent && $result_recent->num_rows > 0): ?>
                            <div class="products-list">
                                <?php while ($product = $result_recent->fetch_assoc()): ?>
                                    <div class="product-item">
                                        <div class="product-image">
                                            <i class="fas fa-gem"></i>
                                        </div>
                                        <div class="product-info">
                                            <h4><?php echo htmlspecialchars($product['nombre']); ?></h4>
                                            <div class="product-meta">
                                                <span><?php echo htmlspecialchars($product['categoria_nombre'] ?? 'Sin categoría'); ?></span>
                                                <span class="separator">•</span>
                                                <span class="badge badge-active">Activo</span>
                                            </div>
                                        </div>
                                        <div class="product-actions">
                                            <p class="product-price"><?php echo format_cop_price($product['precio']); ?></p>
                                            <div class="action-buttons">
                                                <button class="btn-icon" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn-icon" title="Ver">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p class="no-data">No hay productos recientes.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <div class="action-card blue">
                        <div class="action-icon">
                            <i class="fas fa-gem"></i>
                        </div>
                        <h3>Gestionar Productos</h3>
                        <p>Añadir, editar o eliminar productos de tu inventario</p>
                    </div>

                    <div class="action-card green">
                        <div class="action-icon">
                            <i class="fas fa-tags"></i>
                        </div>
                        <h3>Organizar Categorías</h3>
                        <p>Crear y gestionar las categorías de tus productos</p>
                    </div>

                    <div class="action-card purple">
                        <div class="action-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3>Ver Estadísticas</h3>
                        <p>Analiza el rendimiento de tu tienda</p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../js/dashboard.js"></script>
</body>
</html>