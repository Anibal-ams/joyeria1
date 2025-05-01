<?php
session_start();
require_once '../includes/db_connection.php';

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

    if (empty($nombre)) {
        $error_message = "El nombre de la categoría no puede estar vacío.";
    } else {
        if ($id) {
            // Update existing category
            $query = "UPDATE Categorias SET nombre = ?, descripcion = ? WHERE id_categoria = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssi", $nombre, $descripcion, $id);
        } else {
            // Add new category
            $query = "INSERT INTO Categorias (nombre, descripcion) VALUES (?, ?)";
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
}

// Handle category deletion
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $query = "DELETE FROM Categorias WHERE id_categoria = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $success_message = "Categoría eliminada con éxito.";
    } else {
        $error_message = "Error al eliminar la categoría: " . $conn->error;
    }
    $stmt->close();
}

// Fetch all categories
$query = "SELECT * FROM Categorias ORDER BY nombre";
$result = $conn->query($query);

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Categorías - Luxury Jewels</title>
    <link rel="stylesheet" href="../css/admin-styles.css">
</head>
<body>
    <div class="admin-container">
        <h1>Gestionar Categorías</h1>
        <nav>
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="products.php">Gestionar Productos</a></li>
                <li><a href="materials.php">Gestionar Materiales</a></li>
                <li><a href="logout.php">Cerrar Sesión</a></li>
            </ul>
        </nav>

        <?php
        if ($success_message) echo "<p class='success'>$success_message</p>";
        if ($error_message) echo "<p class='error'>$error_message</p>";
        ?>

        <h2>Añadir/Editar Categoría</h2>
        <form method="POST" action="">
            <input type="hidden" name="id" id="category_id">
            <div class="form-group">
                <label for="nombre">Nombre:</label>
                <input type="text" id="nombre" name="nombre" required>
            </div>
            <div class="form-group">
                <label for="descripcion">Descripción:</label>
                <textarea id="descripcion" name="descripcion"></textarea>
            </div>
            <button type="submit">Guardar Categoría</button>
        </form>

        <h2>Lista de Categorías</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Descripción</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['id_categoria']; ?></td>
                        <td><?php echo htmlspecialchars($row['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($row['descripcion']); ?></td>
                        <td>
                            <button onclick="editCategory(<?php echo htmlspecialchars(json_encode($row)); ?>)">Editar</button>
                            <button onclick="deleteCategory(<?php echo $row['id_categoria']; ?>)">Eliminar</button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <script>
        function editCategory(category) {
            document.getElementById('category_id').value = category.id_categoria;
            document.getElementById('nombre').value = category.nombre;
            document.getElementById('descripcion').value = category.descripcion;
        }

        function deleteCategory(id) {
            if (confirm('¿Estás seguro de que quieres eliminar esta categoría?')) {
                window.location.href = 'categories.php?delete=' + id;
            }
        }
    </script>
</body>
</html>

