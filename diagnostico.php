<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Incluir el archivo de conexión
require_once 'includes/db_connection.php';

echo "<h1>Diagnóstico de la Base de Datos</h1>";

// Verificar la conexión
if ($conn->connect_error) {
    die("<p style='color:red'>Error de conexión: " . $conn->connect_error . "</p>");
} else {
    echo "<p style='color:green'>Conexión exitosa a la base de datos.</p>";
}

// Mostrar información del servidor
echo "<h2>Información del Servidor</h2>";
echo "<ul>";
echo "<li>Servidor: " . $conn->host_info . "</li>";
echo "<li>Versión del servidor: " . $conn->server_info . "</li>";
echo "</ul>";

// Mostrar todas las tablas
echo "<h2>Tablas en la Base de Datos</h2>";
$tables_query = "SHOW TABLES";
$tables_result = $conn->query($tables_query);

if ($tables_result->num_rows > 0) {
    echo "<ul>";
    while($table = $tables_result->fetch_array()) {
        echo "<li>" . $table[0] . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No se encontraron tablas en la base de datos.</p>";
}

// Verificar cada tabla específica
$tables_to_check = ['categorias', 'materiales', 'productos', 'productoimagenes'];
echo "<h2>Verificación de Tablas Específicas</h2>";
echo "<ul>";

foreach ($tables_to_check as $table) {
    $check_query = "SELECT COUNT(*) as count FROM $table";
    $result = $conn->query($check_query);
    
    if ($result === false) {
        echo "<li style='color:red'>Tabla '$table': Error - " . $conn->error . "</li>";
    } else {
        $row = $result->fetch_assoc();
        echo "<li style='color:green'>Tabla '$table': " . $row['count'] . " registros</li>";
    }
}
echo "</ul>";

// Cerrar conexión
$conn->close();
?>