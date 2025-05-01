<?php
session_start();
require_once '../includes/db_connection.php';

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

    if (empty($nombre)) {
        $error_message = "El nombre del material no puede estar vacío.";
    } else {
        if ($id) {
            // Update existing material
            $query = "UPDATE Materiales SET nombre = ?, descripcion = ? WHERE id_material = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssi", $nombre, $descripcion, $id);
        } else {
            // Add new material
            $query = "INSERT INTO Materiales (nombre, descripcion) VALUES (?, ?)";
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
}

// Handle material deletion
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $query = "DELETE FROM Materiales WHERE id_material = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $success_message = "Material eliminado con éxito.";
    } else {
        $error_message = "Error al eliminar el material: " . $conn->error;
    }
    $stmt->close();
}

// Fetch all materials
$query = "SELECT * FROM Materiales ORDER BY nombre";
$result = $conn->query($query);

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Materiales - Luxury Jewels</title>
    <link rel="stylesheet" href="../css/admin-styles.css">
</head>
<body>
    <div class="admin-container">
        <h1>Gestionar Materiales</h1>
        <nav>
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="products.php">Gestionar Productos</a></li>
                <li><a href="categories.php">Gestionar Categorías</a></li>
                <li><a href="logout.php">Cerrar Sesión</a></li>
            </ul>
        </nav>

        <?php
        if ($success_message) echo "<p class='success'>$success_message</p>";
        if ($error_message) echo "<p class='error'>$error_message</p>";
        ?>

        <h2>Añadir/Editar Material</h2>
        <form method="POST" action="">
            <input type="hidden" name="id" id="material_id">
            <div class="form-group">
                <label for="nombre">Nombre:</label>
                <input type="text" id="nombre" name="nombre" required>
            </div>
            <div class="form-group">
                <label for="descripcion">Descripción:</label>
                <textarea id="descripcion" name="descripcion"></textarea>
            </div>
            <button type="submit">Guardar Material</button>
        </form>

        <h2>Lista de Materiales</h2>
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
                        <td><?php echo $row['id_material']; ?></td>
                        <td><?php echo htmlspecialchars($row['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($row['descripcion']); ?></td>
                        <td>
                            <button onclick="editMaterial(<?php echo htmlspecialchars(json_encode($row)); ?>)">Editar</button>
                            <button onclick="deleteMaterial(<?php echo $row['id_material']; ?>)">Eliminar</button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <script>
        function editMaterial(material) {
            document.getElementById('material_id').value = material.id_material;
            document.getElementById('nombre').value = material.nombre;
            document.getElementById('descripcion').value = material.descripcion;
        }

        function deleteMaterial(id) {
            if (confirm('¿Estás seguro de que quieres eliminar este material?')) {
                window.location.href = 'materials.php?delete=' + id;
            }
        }
    </script>
</body>
</html>

