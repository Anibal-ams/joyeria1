<?php
session_start();
require_once '../includes/db_connection.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    $query = "DELETE FROM Productos WHERE id_producto = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $success_message = "Producto eliminado con éxito.";
    } else {
        $error_message = "Error al eliminar el producto: " . $conn->error;
    }
    
    $stmt->close();
}

$conn->close();

header("Location: products.php" . (isset($success_message) ? "?success=" . urlencode($success_message) : (isset($error_message) ? "?error=" . urlencode($error_message) : "")));
exit();

