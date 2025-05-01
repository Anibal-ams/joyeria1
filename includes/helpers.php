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
        return '€' . $default;
    }
    return '€' . number_format($price, 2, '.', ',');
}

// Add any other helper functions here

?>

