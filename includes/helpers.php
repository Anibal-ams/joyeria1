<?php

/**
 * Función para generar una salida segura de texto
 * 
 * @param string $text El texto a procesar
 * @param string $default Valor por defecto si el texto está vacío
 * @return string El texto procesado y seguro para mostrar
 */
function safe_output($text, $default = '') {
    if (empty($text)) {
        return htmlspecialchars($default, ENT_QUOTES, 'UTF-8');
    }
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Función para formatear y mostrar precios de forma segura
 * 
 * @param float $price El precio a formatear
 * @param string $default Valor por defecto si el precio no es válido
 * @return string El precio formateado y seguro para mostrar
 */
function safe_price($price, $default = '0.00') {
    if (!is_numeric($price)) {
        return '$ ' . $default;
    }
    // Formato para pesos colombianos (punto como separador de miles, coma como decimal)
    return '$ ' . number_format($price, 0, ',', '.');
}

// COMENTAR O ELIMINAR ESTA FUNCIÓN PARA EVITAR DUPLICACIÓN
// /**
//  * Función para formatear precio en pesos colombianos
//  * 
//  * @param float $price El precio a formatear
//  * @return string El precio formateado en formato COP
//  */
// function format_cop_price($price) {
//     // Convert to Colombian Pesos format (period as thousands separator, comma as decimal)
//     return '$ ' . number_format($price, 0, ',', '.');
// }

/**
 * Función para normalizar los nombres de las tablas
 * Asegura que todos los nombres de tablas estén en minúsculas
 * 
 * @param string $table Nombre de la tabla
 * @return string Nombre de la tabla normalizado
 */
function get_table_name($table) {
    // Convertir a minúsculas para asegurar compatibilidad
    return strtolower($table);
}

/**
 * Función para ejecutar consultas con nombres de tablas normalizados
 * 
 * @param mysqli $conn Conexión a la base de datos
 * @param string $query Consulta SQL
 * @param array $params Parámetros para la consulta preparada
 * @param string $types Tipos de los parámetros
 * @return mysqli_result|bool Resultado de la consulta
 */
function safe_query($conn, $query, $params = [], $types = "") {
    // Normalizar nombres de tablas comunes
    $tables = ['Categorias', 'Materiales', 'Productos', 'ProductoImagenes'];
    $normalized = ['categorias', 'materiales', 'productos', 'productoimagenes'];
    
    $query = str_replace($tables, $normalized, $query);
    
    if (empty($params)) {
        return $conn->query($query);
    } else {
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result();
    }
}

// Add any other helper functions here

?>