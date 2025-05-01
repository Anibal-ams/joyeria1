<?php
session_start();
require_once __DIR__ . '/../includes/db_connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $query = "SELECT * FROM Administradores WHERE username = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];
            header("Location: dashboard.php");
            exit();
        }
    }
    
    $error = "Usuario o contraseña incorrectos";
}

// Verificar si ya existe un usuario administrador
$check_query = "SELECT COUNT(*) as count FROM Administradores";
$result = $conn->query($check_query);
$admin_count = $result->fetch_assoc()['count'];

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> Login de administracion - Pnada joyeros</title>
    <link rel="stylesheet" href="../css/admin-styles.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>


        .init-admin-button {
            margin-top: 20px;
            padding: 10px 15px;
            background-color: #f0ad4e;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .init-admin-button:hover {
            background-color: #ec971f;
        }
        .warning {
            color: #8a6d3b;
            background-color:rgb(227, 227, 252);
            border: 1px solidrgb(204, 204, 250);
            padding: 15px;
            margin-top: 20px;
            border-radius: 4px;
        }
        .admin-actions {
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            text-align: center;
        }

        .admin-link {
            padding: 10px 15px;
            background-color: #60d7bf;
            color: black;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s ease;
            display: inline-block;
        }

        .admin-link:hover {
            background-color: #4cae4c;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <h1>Login de administracion</h1>
        <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Usuario:</label>
                <input type="text" id="username"    name="username" required >
                <i class='bx bxs-user'></i>
            </div>
            <div class="form-group">
                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" required>
                <i class='bx bxs-lock-alt' ></i>
            </div>
            <button type="submit">Iniciar Sesión</button>
        </form>
        <div class="admin-actions">
            <a href="init_admin.php" class="admin-link">Crear Nuevo Usuario</a>
            
            <a href="index.php" class="active">Inicio</a>
        </div>
        <?php if ($admin_count == 0): ?>
            <div class="warning">
                <p><strong>Atención:</strong> No se ha detectado ningún usuario administrador. Si es la primera vez que configura el sistema, utilice el botón de abajo para crear el primer usuario administrador.</p>
            </div>
            <a href="init_admin.php" class="init-admin-button">Crear Primer Usuario Administrador</a>
        <?php endif; ?>
    </div>
</body>
</html>

