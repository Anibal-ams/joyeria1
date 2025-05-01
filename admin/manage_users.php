<?php
session_start();
require_once '../includes/db_connection.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$success_message = $error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $error_message = "Las contraseñas no coinciden.";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $query = "INSERT INTO Administradores (username, password) VALUES (?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $username, $hashed_password);
        
        if ($stmt->execute()) {
            $success_message = "Usuario administrador creado con éxito.";
        } else {
            $error_message = "Error al crear el usuario: " . $conn->error;
        }
        
        $stmt->close();
    }
}

// Fetch existing admin users
$query = "SELECT id, username FROM Administradores";
$result = $conn->query($query);

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Usuarios Administradores - Luxury Jewels</title>
    <link rel="stylesheet" href="../css/admin-styles.css">
</head>
<body>
    <div class="admin-container">
        <h1>Gestionar Usuarios Administradores</h1>
        <nav>
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="products.php">Gestionar Productos</a></li>
                <li><a href="categories.php">Gestionar Categorías</a></li>
                <li><a href="materials.php">Gestionar Materiales</a></li>
                <li><a href="logout.php">Cerrar Sesión</a></li>
            </ul>
        </nav>

        <?php
        if ($success_message) echo "<p class='success'>$success_message</p>";
        if ($error_message) echo "<p class='error'>$error_message</p>";
        ?>

        <h2>Crear Nuevo Usuario Administrador</h2>
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Nombre de Usuario:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirmar Contraseña:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit">Crear Usuario</button>
        </form>

        <h2>Usuarios Administradores Existentes</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre de Usuario</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

