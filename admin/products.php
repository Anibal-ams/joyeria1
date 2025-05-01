<?php
session_start();
require_once '../includes/db_connection.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Handle form submission for adding/editing products
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = isset($_POST['id']) ? intval($_POST['id']) : null;
    $nombre = $_POST['nombre'];
    $id_categoria = intval($_POST['id_categoria']);
    $id_material = intval($_POST['id_material']);
    $descripcion = $_POST['descripcion'];
    $precio = floatval($_POST['precio']);
    $stock = intval($_POST['stock']);
    $peso = floatval($_POST['peso']);
    $dimensiones = $_POST['dimensiones'];
    $destacado = isset($_POST['destacado']) ? 1 : 0;

    if ($id) {
        // Update existing product
        $query = "UPDATE Productos SET nombre = ?, id_categoria = ?, id_material = ?, descripcion = ?, precio = ?, stock = ?, peso = ?, dimensiones = ?, destacado = ? WHERE id_producto = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("siiisddssi", $nombre, $id_categoria, $id_material, $descripcion, $precio, $stock, $peso, $dimensiones, $destacado, $id);
    } else {
        // Add new product
        $query = "INSERT INTO Productos (nombre, id_categoria, id_material, descripcion, precio, stock, peso, dimensiones, destacado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("siiisddsi", $nombre, $id_categoria, $id_material, $descripcion, $precio, $stock, $peso, $dimensiones, $destacado);
    }

    if ($stmt->execute()) {
        $product_id = $id ? $id : $stmt->insert_id;

        // Handle image uploads
        $upload_dir = "../uploads/";
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Delete existing images if updating
        if ($id) {
            $delete_query = "DELETE FROM ProductoImagenes WHERE id_producto = ?";
            $delete_stmt = $conn->prepare($delete_query);
            $delete_stmt->bind_param("i", $id);
            $delete_stmt->execute();
            $delete_stmt->close();
        }

        // Process new image uploads
        for ($i = 1; $i <= 3; $i++) {
            if (isset($_FILES["imagen$i"]) && $_FILES["imagen$i"]['error'] == 0) {
                $temp_name = $_FILES["imagen$i"]["tmp_name"];
                $name = basename($_FILES["imagen$i"]["name"]);
                $extension = pathinfo($name, PATHINFO_EXTENSION);
                $new_name = uniqid() . "." . $extension;
                $destination = $upload_dir . $new_name;

                if (move_uploaded_file($temp_name, $destination)) {
                    $image_query = "INSERT INTO ProductoImagenes (id_producto, imagen_url, orden) VALUES (?, ?, ?)";
                    $image_stmt = $conn->prepare($image_query);
                    $image_url = "uploads/" . $new_name;
                    $image_stmt->bind_param("isi", $product_id, $image_url, $i);
                    $image_stmt->execute();
                    $image_stmt->close();
                }
            }
        }

        $success_message = $id ? "Producto actualizado con éxito." : "Producto añadido con éxito.";
    } else {
        $error_message = "Error al " . ($id ? "actualizar" : "añadir") . " el producto: " . $conn->error;
    }
}

// Fetch all products
$query = "SELECT p.*, c.nombre AS categoria_nombre, m.nombre AS material_nombre 
          FROM Productos p 
          LEFT JOIN Categorias c ON p.id_categoria = c.id_categoria
          LEFT JOIN Materiales m ON p.id_material = m.id_material
          ORDER BY p.id_producto DESC";
$result = $conn->query($query);

// Fetch categories and materials for dropdowns
$categories_query = "SELECT * FROM Categorias";
$categories_result = $conn->query($categories_query);

$materials_query = "SELECT * FROM Materiales";
$materials_result = $conn->query($materials_query);

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Productos - Luxury Jewels</title>
    <link rel="stylesheet" href="../css/admin-styles.css">
</head>
<body>
    <div class="admin-container">
        <h1>Gestionar Productos</h1>
        <nav>
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="categories.php">Gestionar Categorías</a></li>
                <li><a href="materials.php">Gestionar Materiales</a></li>
                <li><a href="logout.php">Cerrar Sesión</a></li>
            </ul>
        </nav>

        <?php
        if (isset($success_message)) echo "<p class='success'>$success_message</p>";
        if (isset($error_message)) echo "<p class='error'>$error_message</p>";
        ?>

        <h2>Añadir/Editar Producto</h2>
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="id" id="product_id">
            <div class="form-group">
                <label for="nombre">Nombre:</label>
                <input type="text" id="nombre" name="nombre" required>
            </div>
            <div class="form-group">
                <label for="id_categoria">Categoría:</label>
                <select id="id_categoria" name="id_categoria" required>
                    <?php while ($category = $categories_result->fetch_assoc()): ?>
                        <option value="<?php echo $category['id_categoria']; ?>"><?php echo htmlspecialchars($category['nombre']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="id_material">Material:</label>
                <select id="id_material" name="id_material" required>
                    <?php while ($material = $materials_result->fetch_assoc()): ?>
                        <option value="<?php echo $material['id_material']; ?>"><?php echo htmlspecialchars($material['nombre']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="descripcion">Descripción:</label>
                <textarea id="descripcion" name="descripcion" required></textarea>
            </div>
            <div class="form-group">
                <label for="precio">Precio:</label>
                <input type="number" id="precio" name="precio" step="0.01" required>
            </div>
            <div class="form-group">
                <label for="stock">Stock:</label>
                <input type="number" id="stock" name="stock" required>
            </div>
            <div class="form-group">
                <label for="peso">Peso (g):</label>
                <input type="number" id="peso" name="peso" step="0.01">
            </div>
            <div class="form-group">
                <label for="dimensiones">Dimensiones:</label>
                <input type="text" id="dimensiones" name="dimensiones">
            </div>
            <div class="form-group">
                <label for="destacado">Destacado:</label>
                <input type="checkbox" id="destacado" name="destacado">
            </div>
            <div class="form-group">
                <label for="imagen1">Imagen 1:</label>
                <input type="file" id="imagen1" name="imagen1" accept="image/*">
            </div>
            <div class="form-group">
                <label for="imagen2">Imagen 2:</label>
                <input type="file" id="imagen2" name="imagen2" accept="image/*">
            </div>
            <div class="form-group">
                <label for="imagen3">Imagen 3:</label>
                <input type="file" id="imagen3" name="imagen3" accept="image/*">
            </div>
            <button type="submit">Guardar Producto</button>
        </form>

        <h2>Lista de Productos</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Categoría</th>
                    <th>Material</th>
                    <th>Precio</th>
                    <th>Stock</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['id_producto']; ?></td>
                        <td><?php echo htmlspecialchars($row['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($row['categoria_nombre']); ?></td>
                        <td><?php echo htmlspecialchars($row['material_nombre']); ?></td>
                        <td>€<?php echo number_format($row['precio'], 2); ?></td>
                        <td><?php echo $row['stock']; ?></td>
                        <td>
                            <button onclick="editProduct(<?php echo htmlspecialchars(json_encode($row)); ?>)">Editar</button>
                            <button onclick="deleteProduct(<?php echo $row['id_producto']; ?>)">Eliminar</button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <script>
        function editProduct(product) {
            document.getElementById('product_id').value = product.id_producto;
            document.getElementById('nombre').value = product.nombre;
            document.getElementById('id_categoria').value = product.id_categoria;
            document.getElementById('id_material').value = product.id_material;
            document.getElementById('descripcion').value = product.descripcion;
            document.getElementById('precio').value = product.precio;
            document.getElementById('stock').value = product.stock;
            document.getElementById('peso').value = product.peso;
            document.getElementById('dimensiones').value = product.dimensiones;
            document.getElementById('destacado').checked = product.destacado == 1;
            // Note: We can't pre-fill file inputs for security reasons
        }

        function deleteProduct(id) {
            if (confirm('¿Estás seguro de que quieres eliminar este producto?')) {
                window.location.href = 'delete_product.php?id=' + id;
            }
        }
    </script>
</body>
</html>