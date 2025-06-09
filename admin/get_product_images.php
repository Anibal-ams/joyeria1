<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

// Verificar que se proporcione el ID del producto
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de producto inválido']);
    exit();
}

$id_producto = intval($_GET['id']);

// Conexión a la base de datos
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'panda_bd';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de conexión a la base de datos']);
    exit();
}

$conn->set_charset("utf8mb4");

// Obtener imágenes del producto
$query = "SELECT id_imagen, ruta_imagen, es_principal, orden FROM producto_imagenes WHERE id_producto = ? ORDER BY es_principal DESC, orden ASC";
$stmt = $conn->prepare($query);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Error en la preparación de la consulta']);
    exit();
}

$stmt->bind_param("i", $id_producto);
$stmt->execute();
$result = $stmt->get_result();

$imagenes = [];
while ($row = $result->fetch_assoc()) {
    $imagenes[] = $row;
}

$stmt->close();
$conn->close();

// Devolver las imágenes como JSON
header('Content-Type: application/json');
echo json_encode($imagenes);
?>
