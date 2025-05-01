<?php
require_once '../includes/db_connection.php';

// Crear la tabla Administradores si no existe
$create_table_query = "
CREATE TABLE IF NOT EXISTS Administradores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($create_table_query) === TRUE) {
    echo "";
} else {
    die("Error al crear la tabla Administradores: " . $conn->error);
}

// Verificar el número de administradores existentes
$check_query = "SELECT COUNT(*) as count FROM Administradores";
$result = $conn->query($check_query);
$admin_count = $result->fetch_assoc()['count'];

if ($admin_count >= 3) {
    die("Ya existen 3 administradores. No se pueden crear más por razones de seguridad.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $errors = [];

    if (strlen($username) < 3) {
        $errors[] = "El nombre de usuario debe tener al menos 3 caracteres.";
    }

    if (strlen($password) < 8 || !preg_match("/[A-Z]/", $password) || !preg_match("/[a-z]/", $password) || !preg_match("/[0-9]/", $password)) {
        $errors[] = "La contraseña debe tener al menos 8 caracteres, incluir mayúsculas, minúsculas y números.";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Las contraseñas no coinciden.";
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $query = "INSERT INTO Administradores (username, password) VALUES (?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $username, $hashed_password);

        if ($stmt->execute()) {
            $success_message = "Usuario administrador creado con éxito.";
        } else {
            $errors[] = "Error al crear el usuario administrador: " . $conn->error;
        }

        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Administrador</title>
    <style>

        *{

           margin:0;
           padding:0;
           box-sizing: border-box;
           font-family: "Poppins", sans-serif;
        }

        body {
     display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    background-image:url(../uploads/fondo.jpg);
    background-size: cover;
    background-position: center;
        }
    .container {
    width: 400px;
    background: transparent;
    border:  2px solid rgba(222, 233, 19, 0.853);
    backdrop-filter: blur(20px);
    box-shadow:  0 0 10px rgba(0, 0, 0, .2);
    color: #fff;
    border-radius: 10px;
    padding: 30px 40px;

     }

      h1 {
    font-size: 36px;
    text-align: center;
    color: rgb(223, 241, 28)
        }
        form {
            display: flex;
            flex-direction: column;
            
        }
        label {
            margin-top: 10px;
            margin-bottom: 5px;
        }
        input {
             
           width: 100%;
           height: 100%;
           background:while;
           border:none;
           outline:none;
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;  
            border: 2px solid rgba(171, 11, 11, 0.2);
            

        }
        button {
            width: 100%;
            height: 45px;     
            background:#60d7bf;
            border: none;
            outline: none;
            border-radius: 100px;
            box-shadow: 0 0 10px rgba(0, 0, 0, .1); 
            cursor: pointer;
            font-size: 16px;
            color: #333;
        }
        button:hover {
            background-color: #b08d5a;
        }
        .error {
            color: #ff0000;
            margin-bottom: 15px;
        }
        .success {
            color: #008000;
            margin-bottom: 15px;
        }
        .button-container {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        .login-button {
            width: 100%;
            height:45px;
            background:#60d7bf ;
            padding: 8px;
            border: none;
            border-radius: 100px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            text-align: center;
        }
        .login-button:hover {
            background-color: #555;
        }
        button[type="submit"], .login-button {
            flex: 1;
            margin: 0 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Crear Nuevo Administrador (<?php echo $admin_count + 1; ?> de 3)</h1>
        <?php
        if (isset($errors)) {
            foreach ($errors as $error) {
                echo "<p class='error'>$error</p>";
            }
        }
        if (isset($success_message)) {
            echo "<p class='success'>$success_message</p>";
        }
        ?>
        <form method="POST" id="create-admin-form">
            <label for="username">Nombre de usuario (mínimo 3 caracteres):</label>
            <input type="text" id="username" name="username" required>
            
            <label for="password">Contraseña (mínimo 8 caracteres, incluyendo mayúsculas, minúsculas y números):</label>
            <input type="password" id="password" name="password" required>
            
            <label for="confirm_password">Confirmar contraseña:</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
            
        </form>
        <div class="button-container">
            <button type="submit" form="create-admin-form">Crear Administrador</button>
            <a href="login.php" class="login-button">Iniciar Sesión</a>
        </div>
    </div>
</body>
</html>

