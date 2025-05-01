<?php
session_start();
require_once '../includes/db_connection.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Obtener estadísticas para el dashboard
$query_products = "SELECT COUNT(*) as total FROM Productos";
$result_products = $conn->query($query_products);
$total_products = $result_products->fetch_assoc()['total'];

// Obtener total de categorías
$query_categories = "SELECT COUNT(*) as total FROM Categorias";
$result_categories = $conn->query($query_categories);
$total_categories = $result_categories->fetch_assoc()['total'];

// Obtener total de materiales
$query_materials = "SELECT COUNT(*) as total FROM Materiales";
$result_materials = $conn->query($query_materials);
$total_materials = $result_materials->fetch_assoc()['total'];

// Obtener productos destacados
$query_featured = "SELECT COUNT(*) as total FROM Productos WHERE destacado = 1";
$result_featured = $conn->query($query_featured);
$total_featured = $result_featured->fetch_assoc()['total'];

// Obtener últimos productos añadidos (sin usar fecha_creacion)
$query_recent = "SELECT id_producto, nombre, precio FROM Productos ORDER BY id_producto DESC LIMIT 5";
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/admin-styles.css">
</head>
<body>
    <div class="admin-container">
        <h1>Bienvenido, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></h1>
        
        <nav>
            <ul>
                <li><a href="products.php"><i class="fas fa-gem"></i> Productos</a></li>
                <li><a href="categories.php"><i class="fas fa-tags"></i> Categorías</a></li>
                <li><a href="materials.php"><i class="fas fa-cubes"></i> Materiales</a></li>
                <li><a href="manage_users.php"><i class="fas fa-users"></i> Usuarios</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a></li>
            </ul>
        </nav>
        
        <h2>Panel de Control</h2>
        
        <div class="dashboard-widgets">
            <div class="widget">
                <div class="widget-icon">
                    <i class="fas fa-gem"></i>
                </div>
                <div class="widget-value"><?php echo $total_products; ?></div>
                <div class="widget-label">Productos</div>
            </div>
            
            <div class="widget">
                <div class="widget-icon">
                    <i class="fas fa-tags"></i>
                </div>
                <div class="widget-value"><?php echo $total_categories; ?></div>
                <div class="widget-label">Categorías</div>
            </div>
            
            <div class="widget">
                <div class="widget-icon">
                    <i class="fas fa-cubes"></i>
                </div>
                <div class="widget-value"><?php echo $total_materials; ?></div>
                <div class="widget-label">Materiales</div>
            </div>
            
            <div class="widget">
                <div class="widget-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="widget-value"><?php echo $total_featured; ?></div>
                <div class="widget-label">Productos Destacados</div>
            </div>
        </div>
        
        <div class="dashboard-summary">
            <h3><i class="fas fa-clock"></i> Productos Recientes</h3>
            
            <?php if ($result_recent && $result_recent->num_rows > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Precio</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($product = $result_recent->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $product['id_producto']; ?></td>
                                    <td><?php echo htmlspecialchars($product['nombre']); ?></td>
                                    <td><?php echo number_format($product['precio'], 2, ',', '.') . ' €'; ?></td>
                                    <td>
                                        <a href="edit_product.php?id=<?php echo $product['id_producto']; ?>" class="btn-secondary" style="padding: 5px 10px; font-size: 0.8rem;">
                                            <i class="fas fa-edit"></i> Editar
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-center">No hay productos recientes.</p>
            <?php endif; ?>
        </div>
        
        <div class="flex flex-between mt-4">
            <a href="products.php" class="btn">
                <i class="fas fa-plus"></i> Añadir Nuevo Producto
            </a>
            <a href="../index.php" target="_blank" class="btn btn-secondary">
                <i class="fas fa-eye"></i> Ver Tienda
            </a>
        </div>
    </div>
    
    <script>
        // Animación para los widgets
        document.addEventListener('DOMContentLoaded', function() {
            const widgets = document.querySelectorAll('.widget');
            widgets.forEach((widget, index) => {
                setTimeout(() => {
                    widget.style.opacity = '1';
                    widget.style.transform = 'translateY(0)';
                }, 100 * index);
            });
        });
    </script>
</body>
</html>