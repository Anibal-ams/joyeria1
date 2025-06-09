<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Conexión directa a la base de datos
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'panda_bd';

// Crear conexión
$conn = new mysqli($host, $username, $password, $database);

// Verificar conexión
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Configurar charset
$conn->set_charset("utf8mb4");

// Verificar y crear tabla productos si no existe
$create_productos_table = "
CREATE TABLE IF NOT EXISTS productos (
    id_producto INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    descripcion TEXT,
    precio DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    stock INT DEFAULT 0,
    id_categoria INT DEFAULT NULL,
    id_material INT DEFAULT NULL,
    destacado TINYINT(1) DEFAULT 0,
    imagen_url VARCHAR(500) DEFAULT NULL,
    fecha_adicion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_categoria (id_categoria),
    INDEX idx_material (id_material),
    INDEX idx_destacado (destacado)
)";
$conn->query($create_productos_table);

// Verificar y crear tabla de imágenes de productos
$create_imagenes_table = "
CREATE TABLE IF NOT EXISTS producto_imagenes (
    id_imagen INT AUTO_INCREMENT PRIMARY KEY,
    id_producto INT NOT NULL,
    ruta_imagen VARCHAR(500) NOT NULL,
    es_principal TINYINT(1) DEFAULT 0,
    orden INT DEFAULT 0,
    fecha_adicion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_producto (id_producto),
    FOREIGN KEY (id_producto) REFERENCES productos(id_producto) ON DELETE CASCADE
)";
$conn->query($create_imagenes_table);

// Verificar y crear tabla categorias si no existe
$create_categorias_table = "
CREATE TABLE IF NOT EXISTS categorias (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($create_categorias_table);

// Verificar y crear tabla materiales si no existe
$create_materiales_table = "
CREATE TABLE IF NOT EXISTS materiales (
    id_material INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($create_materiales_table);

// Insertar datos de ejemplo si las tablas están vacías
$check_categorias = $conn->query("SELECT COUNT(*) as count FROM categorias");
if ($check_categorias->fetch_assoc()['count'] == 0) {
    $conn->query("INSERT INTO categorias (nombre, descripcion) VALUES 
        ('Anillos', 'Anillos de compromiso y matrimonio'),
        ('Collares', 'Collares y cadenas'),
        ('Aretes', 'Aretes y pendientes'),
        ('Pulseras', 'Pulseras y brazaletes'),
        ('Relojes', 'Relojes de lujo')");
}

$check_materiales = $conn->query("SELECT COUNT(*) as count FROM materiales");
if ($check_materiales->fetch_assoc()['count'] == 0) {
    $conn->query("INSERT INTO materiales (nombre, descripcion) VALUES 
        ('Oro 18k', 'Oro de 18 quilates'),
        ('Plata 925', 'Plata esterlina'),
        ('Platino', 'Platino puro'),
        ('Acero Inoxidable', 'Acero quirúrgico'),
        ('Diamante', 'Diamantes naturales'),
        ('Perlas', 'Perlas cultivadas')");
}

// Función para obtener categorías
function getCategorias($conn) {
    $categorias = [];
    $query = "SELECT id_categoria, nombre FROM categorias WHERE activo = 1 ORDER BY nombre";
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $categorias[] = $row;
        }
    }
    return $categorias;
}

// Función para obtener materiales
function getMateriales($conn) {
    $materiales = [];
    $query = "SELECT id_material, nombre FROM materiales ORDER BY nombre";
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $materiales[] = $row;
        }
    }
    return $materiales;
}

// Función para obtener imágenes de un producto
function getImagenesProducto($conn, $id_producto) {
    $imagenes = [];
    $query = "SELECT * FROM producto_imagenes WHERE id_producto = ? ORDER BY es_principal DESC, orden ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id_producto);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $imagenes[] = $row;
        }
    }
    
    return $imagenes;
}

// Función para obtener productos
function getProductos($conn, $search = '', $categoria = '', $material = '') {
    $productos = [];
    $where_conditions = [];
    $params = [];
    $types = "";
    
    $query = "SELECT 
        p.id_producto,
        p.nombre,
        p.descripcion,
        p.precio,
        p.stock,
        p.destacado,
        p.imagen_url,
        p.fecha_adicion,
        c.nombre as categoria_nombre,
        m.nombre as material_nombre,
        (SELECT COUNT(*) FROM producto_imagenes WHERE id_producto = p.id_producto) as total_imagenes,
        (SELECT ruta_imagen FROM producto_imagenes WHERE id_producto = p.id_producto AND es_principal = 1 LIMIT 1) as imagen_principal
        FROM productos p
        LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
        LEFT JOIN materiales m ON p.id_material = m.id_material";
    
    if (!empty($search)) {
        $where_conditions[] = "(p.nombre LIKE ? OR p.descripcion LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "ss";
    }
    
    if (!empty($categoria)) {
        $where_conditions[] = "p.id_categoria = ?";
        $params[] = intval($categoria);
        $types .= "i";
    }
    
    if (!empty($material)) {
        $where_conditions[] = "p.id_material = ?";
        $params[] = intval($material);
        $types .= "i";
    }
    
    if (!empty($where_conditions)) {
        $query .= " WHERE " . implode(" AND ", $where_conditions);
    }
    
    $query .= " ORDER BY p.fecha_adicion DESC, p.id_producto DESC";
    
    if (!empty($params)) {
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = false;
        }
    } else {
        $result = $conn->query($query);
    }
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $productos[] = $row;
        }
    }
    
    return $productos;
}

// Crear directorio de uploads si no existe
$upload_dir = '../uploads/productos/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Variables para mensajes
$message = '';
$message_type = '';

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $nombre = trim($_POST['nombre']);
                $descripcion = trim($_POST['descripcion']);
                $precio = floatval($_POST['precio']);
                $stock = intval($_POST['stock']);
                $id_categoria = intval($_POST['id_categoria']);
                $id_material = intval($_POST['id_material']);
                $destacado = isset($_POST['destacado']) ? 1 : 0;
                
                // Validaciones
                if (empty($nombre)) {
                    $message = "El nombre del producto es obligatorio.";
                    $message_type = "error";
                } elseif (empty($descripcion)) {
                    $message = "La descripción del producto es obligatoria.";
                    $message_type = "error";
                } elseif ($precio <= 0) {
                    $message = "El precio debe ser mayor a 0.";
                    $message_type = "error";
                } else {
                    // Convertir 0 a NULL para las claves foráneas
                    $id_categoria = $id_categoria == 0 ? NULL : $id_categoria;
                    $id_material = $id_material == 0 ? NULL : $id_material;
                    
                    // Iniciar transacción
                    $conn->begin_transaction();
                    
                    try {
                        // Insertar producto
                        $query = "INSERT INTO productos (nombre, descripcion, precio, stock, id_categoria, id_material, destacado) VALUES (?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($query);
                        
                        if (!$stmt) {
                            throw new Exception("Error en la preparación de la consulta: " . $conn->error);
                        }
                        
                        $stmt->bind_param("ssdiiii", $nombre, $descripcion, $precio, $stock, $id_categoria, $id_material, $destacado);
                        
                        if (!$stmt->execute()) {
                            throw new Exception("Error al crear el producto: " . $stmt->error);
                        }
                        
                        $id_producto = $conn->insert_id;
                        $stmt->close();
                        
                        // Procesar imágenes
                        $uploaded_images = 0;
                        $has_main_image = false;
                        
                        // Crear directorio específico para este producto
                        $product_dir = $upload_dir . $id_producto . '/';
                        if (!file_exists($product_dir)) {
                            mkdir($product_dir, 0777, true);
                        }
                        
                        // Procesar hasta 10 imágenes
                        for ($i = 0; $i < 10; $i++) {
                            if (isset($_FILES['producto_imagen_' . $i]) && $_FILES['producto_imagen_' . $i]['error'] == 0) {
                                $file = $_FILES['producto_imagen_' . $i];
                                $filename = $file['name'];
                                $tmp_name = $file['tmp_name'];
                                
                                // Generar nombre único para evitar colisiones
                                $unique_filename = uniqid() . '_' . $filename;
                                $filepath = $product_dir . $unique_filename;
                                
                                // Mover archivo
                                if (move_uploaded_file($tmp_name, $filepath)) {
                                    // Determinar si es la imagen principal (la primera imagen o la marcada como principal)
                                    $es_principal = 0;
                                    if (isset($_POST['imagen_principal']) && $_POST['imagen_principal'] == $i) {
                                        $es_principal = 1;
                                        $has_main_image = true;
                                    } elseif ($uploaded_images == 0 && !$has_main_image) {
                                        $es_principal = 1;
                                        $has_main_image = true;
                                    }
                                    
                                    // Guardar referencia en la base de datos
                                    $ruta_relativa = 'uploads/productos/' . $id_producto . '/' . $unique_filename;
                                    $query = "INSERT INTO producto_imagenes (id_producto, ruta_imagen, es_principal, orden) VALUES (?, ?, ?, ?)";
                                    $stmt = $conn->prepare($query);
                                    
                                    if (!$stmt) {
                                        throw new Exception("Error en la preparación de la consulta de imágenes: " . $conn->error);
                                    }
                                    
                                    $orden = $uploaded_images;
                                    $stmt->bind_param("isii", $id_producto, $ruta_relativa, $es_principal, $orden);
                                    
                                    if (!$stmt->execute()) {
                                        throw new Exception("Error al guardar la imagen: " . $stmt->error);
                                    }
                                    
                                    $stmt->close();
                                    $uploaded_images++;
                                } else {
                                    throw new Exception("Error al mover el archivo subido.");
                                }
                            }
                        }
                        
                        // Confirmar transacción
                        $conn->commit();
                        
                        $message = "Producto '$nombre' creado exitosamente con $uploaded_images imágenes.";
                        $message_type = "success";
                    } catch (Exception $e) {
                        // Revertir transacción en caso de error
                        $conn->rollback();
                        $message = $e->getMessage();
                        $message_type = "error";
                    }
                }
                break;
                
            case 'update':
                $id_producto = intval($_POST['id_producto']);
                $nombre = trim($_POST['nombre']);
                $descripcion = trim($_POST['descripcion']);
                $precio = floatval($_POST['precio']);
                $stock = intval($_POST['stock']);
                $id_categoria = intval($_POST['id_categoria']);
                $id_material = intval($_POST['id_material']);
                $destacado = isset($_POST['destacado']) ? 1 : 0;
                
                if (empty($nombre) || empty($descripcion) || $precio <= 0) {
                    $message = "Por favor, completa todos los campos obligatorios.";
                    $message_type = "error";
                } else {
                    // Convertir 0 a NULL para las claves foráneas
                    $id_categoria = $id_categoria == 0 ? NULL : $id_categoria;
                    $id_material = $id_material == 0 ? NULL : $id_material;
                    
                    // Iniciar transacción
                    $conn->begin_transaction();
                    
                    try {
                        // Actualizar producto
                        $query = "UPDATE productos SET nombre = ?, descripcion = ?, precio = ?, stock = ?, id_categoria = ?, id_material = ?, destacado = ? WHERE id_producto = ?";
                        $stmt = $conn->prepare($query);
                        
                        if (!$stmt) {
                            throw new Exception("Error en la preparación de la consulta: " . $conn->error);
                        }
                        
                        $stmt->bind_param("ssdiiiis", $nombre, $descripcion, $precio, $stock, $id_categoria, $id_material, $destacado, $id_producto);
                        
                        if (!$stmt->execute()) {
                            throw new Exception("Error al actualizar el producto: " . $stmt->error);
                        }
                        
                        $stmt->close();
                        
                        // Crear directorio específico para este producto si no existe
                        $product_dir = $upload_dir . $id_producto . '/';
                        if (!file_exists($product_dir)) {
                            mkdir($product_dir, 0777, true);
                        }
                        
                        // Procesar imágenes nuevas
                        $uploaded_images = 0;
                        $new_main_image = isset($_POST['imagen_principal']) ? intval($_POST['imagen_principal']) : -1;
                        
                        // Si se seleccionó una nueva imagen principal entre las existentes
                        if ($new_main_image >= 0 && $new_main_image < 100) { // Asumimos que las nuevas imágenes tienen índices >= 100
                            // Resetear todas las imágenes a no principales
                            $reset_query = "UPDATE producto_imagenes SET es_principal = 0 WHERE id_producto = ?";
                            $reset_stmt = $conn->prepare($reset_query);
                            $reset_stmt->bind_param("i", $id_producto);
                            $reset_stmt->execute();
                            $reset_stmt->close();
                            
                            // Establecer la nueva imagen principal
                            $set_main_query = "UPDATE producto_imagenes SET es_principal = 1 WHERE id_producto = ? AND id_imagen = ?";
                            $set_main_stmt = $conn->prepare($set_main_query);
                            $set_main_stmt->bind_param("ii", $id_producto, $new_main_image);
                            $set_main_stmt->execute();
                            $set_main_stmt->close();
                        }
                        
                        // Procesar hasta 10 imágenes nuevas
                        for ($i = 0; $i < 10; $i++) {
                            if (isset($_FILES['producto_imagen_' . $i]) && $_FILES['producto_imagen_' . $i]['error'] == 0) {
                                $file = $_FILES['producto_imagen_' . $i];
                                $filename = $file['name'];
                                $tmp_name = $file['tmp_name'];
                                
                                // Generar nombre único para evitar colisiones
                                $unique_filename = uniqid() . '_' . $filename;
                                $filepath = $product_dir . $unique_filename;
                                
                                // Mover archivo
                                if (move_uploaded_file($tmp_name, $filepath)) {
                                    // Determinar si es la imagen principal
                                    $es_principal = 0;
                                    if ($new_main_image == (100 + $i)) { // Índices para nuevas imágenes
                                        // Resetear todas las imágenes a no principales
                                        $reset_query = "UPDATE producto_imagenes SET es_principal = 0 WHERE id_producto = ?";
                                        $reset_stmt = $conn->prepare($reset_query);
                                        $reset_stmt->bind_param("i", $id_producto);
                                        $reset_stmt->execute();
                                        $reset_stmt->close();
                                        
                                        $es_principal = 1;
                                    }
                                    
                                    // Obtener el orden máximo actual
                                    $max_order_query = "SELECT MAX(orden) as max_orden FROM producto_imagenes WHERE id_producto = ?";
                                    $max_order_stmt = $conn->prepare($max_order_query);
                                    $max_order_stmt->bind_param("i", $id_producto);
                                    $max_order_stmt->execute();
                                    $max_order_result = $max_order_stmt->get_result();
                                    $max_order = $max_order_result->fetch_assoc()['max_orden'] ?? -1;
                                    $max_order_stmt->close();
                                    
                                    // Guardar referencia en la base de datos
                                    $ruta_relativa = 'uploads/productos/' . $id_producto . '/' . $unique_filename;
                                    $query = "INSERT INTO producto_imagenes (id_producto, ruta_imagen, es_principal, orden) VALUES (?, ?, ?, ?)";
                                    $stmt = $conn->prepare($query);
                                    
                                    if (!$stmt) {
                                        throw new Exception("Error en la preparación de la consulta de imágenes: " . $conn->error);
                                    }
                                    
                                    $orden = $max_order + 1 + $uploaded_images;
                                    $stmt->bind_param("isii", $id_producto, $ruta_relativa, $es_principal, $orden);
                                    
                                    if (!$stmt->execute()) {
                                        throw new Exception("Error al guardar la imagen: " . $stmt->error);
                                    }
                                    
                                    $stmt->close();
                                    $uploaded_images++;
                                } else {
                                    throw new Exception("Error al mover el archivo subido.");
                                }
                            }
                        }
                        
                        // Eliminar imágenes marcadas para eliminación
                        if (isset($_POST['eliminar_imagenes']) && !empty($_POST['eliminar_imagenes'])) {
                            $imagenes_a_eliminar = explode(',', $_POST['eliminar_imagenes']);
                            
                            foreach ($imagenes_a_eliminar as $id_imagen) {
                                $id_imagen = intval($id_imagen);
                                
                                // Obtener ruta de la imagen
                                $get_path_query = "SELECT ruta_imagen FROM producto_imagenes WHERE id_imagen = ? AND id_producto = ?";
                                $get_path_stmt = $conn->prepare($get_path_query);
                                $get_path_stmt->bind_param("ii", $id_imagen, $id_producto);
                                $get_path_stmt->execute();
                                $path_result = $get_path_stmt->get_result();
                                
                                if ($path_row = $path_result->fetch_assoc()) {
                                    $ruta_imagen = '../' . $path_row['ruta_imagen'];
                                    
                                    // Eliminar archivo físico
                                    if (file_exists($ruta_imagen)) {
                                        unlink($ruta_imagen);
                                    }
                                    
                                    // Eliminar registro de la base de datos
                                    $delete_query = "DELETE FROM producto_imagenes WHERE id_imagen = ? AND id_producto = ?";
                                    $delete_stmt = $conn->prepare($delete_query);
                                    $delete_stmt->bind_param("ii", $id_imagen, $id_producto);
                                    $delete_stmt->execute();
                                    $delete_stmt->close();
                                }
                                
                                $get_path_stmt->close();
                            }
                            
                            // Verificar si hay al menos una imagen principal
                            $check_main_query = "SELECT COUNT(*) as count FROM producto_imagenes WHERE id_producto = ? AND es_principal = 1";
                            $check_main_stmt = $conn->prepare($check_main_query);
                            $check_main_stmt->bind_param("i", $id_producto);
                            $check_main_stmt->execute();
                            $check_main_result = $check_main_stmt->get_result();
                            $has_main = $check_main_result->fetch_assoc()['count'] > 0;
                            $check_main_stmt->close();
                            
                            // Si no hay imagen principal pero hay imágenes, establecer la primera como principal
                            if (!$has_main) {
                                $set_first_main_query = "UPDATE producto_imagenes SET es_principal = 1 WHERE id_producto = ? ORDER BY orden ASC LIMIT 1";
                                $set_first_main_stmt = $conn->prepare($set_first_main_query);
                                $set_first_main_stmt->bind_param("i", $id_producto);
                                $set_first_main_stmt->execute();
                                $set_first_main_stmt->close();
                            }
                        }
                        
                        // Confirmar transacción
                        $conn->commit();
                        
                        $message = "Producto actualizado exitosamente" . ($uploaded_images > 0 ? " con $uploaded_images nuevas imágenes" : "") . ".";
                        $message_type = "success";
                    } catch (Exception $e) {
                        // Revertir transacción en caso de error
                        $conn->rollback();
                        $message = $e->getMessage();
                        $message_type = "error";
                    }
                }
                break;
                
            case 'delete':
                $id_producto = intval($_POST['id_producto']);
                
                // Iniciar transacción
                $conn->begin_transaction();
                
                try {
                    // Obtener todas las imágenes del producto
                    $get_images_query = "SELECT ruta_imagen FROM producto_imagenes WHERE id_producto = ?";
                    $get_images_stmt = $conn->prepare($get_images_query);
                    $get_images_stmt->bind_param("i", $id_producto);
                    $get_images_stmt->execute();
                    $images_result = $get_images_stmt->get_result();
                    
                    // Eliminar archivos físicos
                    while ($image = $images_result->fetch_assoc()) {
                        $ruta_imagen = '../' . $image['ruta_imagen'];
                        if (file_exists($ruta_imagen)) {
                            unlink($ruta_imagen);
                        }
                    }
                    
                    $get_images_stmt->close();
                    
                    // Eliminar directorio del producto
                    $product_dir = $upload_dir . $id_producto . '/';
                    if (file_exists($product_dir)) {
                        // Eliminar archivos restantes en el directorio
                        $files = glob($product_dir . '*');
                        foreach ($files as $file) {
                            if (is_file($file)) {
                                unlink($file);
                            }
                        }
                        
                        // Eliminar directorio
                        rmdir($product_dir);
                    }
                    
                    // Las imágenes se eliminarán automáticamente por la restricción ON DELETE CASCADE
                    
                    // Eliminar producto
                    $query = "DELETE FROM productos WHERE id_producto = ?";
                    $stmt = $conn->prepare($query);
                    
                    if (!$stmt) {
                        throw new Exception("Error en la preparación de la consulta: " . $conn->error);
                    }
                    
                    $stmt->bind_param("i", $id_producto);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Error al eliminar el producto: " . $stmt->error);
                    }
                    
                    $stmt->close();
                    
                    // Confirmar transacción
                    $conn->commit();
                    
                    $message = "Producto eliminado exitosamente.";
                    $message_type = "success";
                } catch (Exception $e) {
                    // Revertir transacción en caso de error
                    $conn->rollback();
                    $message = $e->getMessage();
                    $message_type = "error";
                }
                break;
                
            case 'toggle_destacado':
                $id_producto = intval($_POST['id_producto']);
                $destacado = intval($_POST['destacado']);
                
                $query = "UPDATE productos SET destacado = ? WHERE id_producto = ?";
                $stmt = $conn->prepare($query);
                
                if ($stmt) {
                    $stmt->bind_param("ii", $destacado, $id_producto);
                    
                    if ($stmt->execute()) {
                        $message = "Estado de destacado actualizado.";
                        $message_type = "success";
                    } else {
                        $message = "Error al actualizar el estado: " . $stmt->error;
                        $message_type = "error";
                    }
                    $stmt->close();
                } else {
                    $message = "Error en la preparación de la consulta: " . $conn->error;
                    $message_type = "error";
                }
                break;
                
            case 'delete_image':
                $id_imagen = intval($_POST['id_imagen']);
                $id_producto = intval($_POST['id_producto']);
                
                // Iniciar transacción
                $conn->begin_transaction();
                
                try {
                    // Obtener ruta de la imagen
                    $get_path_query = "SELECT ruta_imagen, es_principal FROM producto_imagenes WHERE id_imagen = ? AND id_producto = ?";
                    $get_path_stmt = $conn->prepare($get_path_query);
                    $get_path_stmt->bind_param("ii", $id_imagen, $id_producto);
                    $get_path_stmt->execute();
                    $path_result = $get_path_stmt->get_result();
                    
                    if ($path_row = $path_result->fetch_assoc()) {
                        $ruta_imagen = '../' . $path_row['ruta_imagen'];
                        $es_principal = $path_row['es_principal'];
                        
                        // Eliminar archivo físico
                        if (file_exists($ruta_imagen)) {
                            unlink($ruta_imagen);
                        }
                        
                        // Eliminar registro de la base de datos
                        $delete_query = "DELETE FROM producto_imagenes WHERE id_imagen = ? AND id_producto = ?";
                        $delete_stmt = $conn->prepare($delete_query);
                        $delete_stmt->bind_param("ii", $id_imagen, $id_producto);
                        
                        if (!$delete_stmt->execute()) {
                            throw new Exception("Error al eliminar la imagen: " . $delete_stmt->error);
                        }
                        
                        $delete_stmt->close();
                        
                        // Si era la imagen principal, establecer otra como principal
                        if ($es_principal) {
                            $set_main_query = "UPDATE producto_imagenes SET es_principal = 1 WHERE id_producto = ? ORDER BY orden ASC LIMIT 1";
                            $set_main_stmt = $conn->prepare($set_main_query);
                            $set_main_stmt->bind_param("i", $id_producto);
                            $set_main_stmt->execute();
                            $set_main_stmt->close();
                        }
                    }
                    
                    $get_path_stmt->close();
                    
                    // Confirmar transacción
                    $conn->commit();
                    
                    $message = "Imagen eliminada exitosamente.";
                    $message_type = "success";
                } catch (Exception $e) {
                    // Revertir transacción en caso de error
                    $conn->rollback();
                    $message = $e->getMessage();
                    $message_type = "error";
                }
                break;
                
            case 'set_main_image':
                $id_imagen = intval($_POST['id_imagen']);
                $id_producto = intval($_POST['id_producto']);
                
                // Iniciar transacción
                $conn->begin_transaction();
                
                try {
                    // Resetear todas las imágenes a no principales
                    $reset_query = "UPDATE producto_imagenes SET es_principal = 0 WHERE id_producto = ?";
                    $reset_stmt = $conn->prepare($reset_query);
                    $reset_stmt->bind_param("i", $id_producto);
                    
                    if (!$reset_stmt->execute()) {
                        throw new Exception("Error al resetear imágenes principales: " . $reset_stmt->error);
                    }
                    
                    $reset_stmt->close();
                    
                    // Establecer la nueva imagen principal
                    $set_main_query = "UPDATE producto_imagenes SET es_principal = 1 WHERE id_imagen = ? AND id_producto = ?";
                    $set_main_stmt = $conn->prepare($set_main_query);
                    $set_main_stmt->bind_param("ii", $id_imagen, $id_producto);
                    
                    if (!$set_main_stmt->execute()) {
                        throw new Exception("Error al establecer imagen principal: " . $set_main_stmt->error);
                    }
                    
                    $set_main_stmt->close();
                    
                    // Confirmar transacción
                    $conn->commit();
                    
                    $message = "Imagen principal actualizada.";
                    $message_type = "success";
                } catch (Exception $e) {
                    // Revertir transacción en caso de error
                    $conn->rollback();
                    $message = $e->getMessage();
                    $message_type = "error";
                }
                break;
        }
    }
}

// Obtener datos para mostrar
$categorias = getCategorias($conn);
$materiales = getMateriales($conn);

// Filtros
$search = $_GET['search'] ?? '';
$filter_categoria = $_GET['categoria'] ?? '';
$filter_material = $_GET['material'] ?? '';

$productos = getProductos($conn, $search, $filter_categoria, $filter_material);

// Obtener estadísticas
$stats_query = "SELECT 
    COUNT(*) as total_productos,
    COUNT(CASE WHEN destacado = 1 THEN 1 END) as productos_destacados,
    SUM(stock) as total_stock,
    AVG(precio) as precio_promedio
    FROM productos";
$stats_result = $conn->query($stats_query);
$stats = $stats_result ? $stats_result->fetch_assoc() : ['total_productos' => 0, 'productos_destacados' => 0, 'total_stock' => 0, 'precio_promedio' => 0];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos - Panda Joyeros</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #f59e0b;
            --primary-dark: #d97706;
            --secondary-color: #0f172a;
            --text-color: #f8fafc;
            --bg-color: #0f172a;
            --card-bg: rgba(255, 255, 255, 0.05);
            --border-color: rgba(255, 255, 255, 0.1);
            --success-color: #10b981;
            --error-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --backdrop-blur: blur(10px);
            --border-radius: 12px;
            --transition: all 0.3s ease;
            --shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f172a, #1e293b, #334155);
            color: var(--text-color);
            min-height: 100vh;
            line-height: 1.6;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: var(--backdrop-blur);
            border-right: 1px solid var(--border-color);
            padding: 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 24px;
            border-bottom: 1px solid var(--border-color);
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #000;
            font-size: 24px;
            font-weight: bold;
        }

        .logo-text h2 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .logo-text p {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
        }

        .sidebar-nav {
            padding: 24px 0;
        }

        .nav-section {
            margin-bottom: 32px;
        }

        .nav-title {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: rgba(255, 255, 255, 0.5);
            margin-bottom: 16px;
            padding: 0 24px;
        }

        .nav-menu {
            list-style: none;
        }

        .nav-item {
            margin-bottom: 4px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 24px;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: var(--transition);
            border-left: 3px solid transparent;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.05);
            color: var(--primary-color);
            border-left-color: var(--primary-color);
        }

        .nav-item.active .nav-link {
            background: rgba(245, 158, 11, 0.1);
            color: var(--primary-color);
            border-left-color: var(--primary-color);
        }

        .nav-link i {
            width: 20px;
            text-align: center;
        }

        .sidebar-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 24px;
            border-top: 1px solid var(--border-color);
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            text-decoration: none;
            border-radius: 8px;
            transition: var(--transition);
        }

        .logout-btn:hover {
            background: rgba(239, 68, 68, 0.2);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            display: flex;
            flex-direction: column;
        }

        .main-header {
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: var(--backdrop-blur);
            border-bottom: 1px solid var(--border-color);
            padding: 16px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .main-header h1 {
            font-size: 24px;
            font-weight: 600;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
        }

        .username {
            color: var(--primary-color);
            font-weight: 500;
        }

        .dashboard-content {
            flex: 1;
            padding: 32px;
            overflow-y: auto;
        }

        /* Alerts */
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            animation: slideDown 0.3s ease-out;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error-color);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 32px;
        }

        .page-title h2 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-title p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 16px;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: #000;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(245, 158, 11, 0.3);
        }

        .btn-outline {
            background: transparent;
            color: var(--text-color);
            border: 1px solid var(--border-color);
        }

        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: var(--primary-color);
        }

        .btn-ghost {
            background: transparent;
            color: rgba(255, 255, 255, 0.7);
        }

        .btn-ghost:hover {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-color);
        }

        /* Statistics Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--card-bg);
            backdrop-filter: var(--backdrop-blur);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 24px;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .stat-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stat-info {
            flex: 1;
        }

        .stat-label {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-color);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-icon.blue {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
        }

        .stat-icon.green {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }

        .stat-icon.yellow {
            background: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
        }

        .stat-icon.purple {
            background: rgba(139, 92, 246, 0.2);
            color: #8b5cf6;
        }

        /* Filters */
        .filters-section {
            background: var(--card-bg);
            backdrop-filter: var(--backdrop-blur);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 24px;
            margin-bottom: 32px;
        }

        .filters-form {
            display: flex;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-box {
            position: relative;
            flex: 1;
            min-width: 250px;
        }

        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.5);
        }

        .search-box input {
            width: 100%;
            padding: 12px 12px 12px 40px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-color);
            font-size: 14px;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.2);
        }

        .filter-select {
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-color);
            font-size: 14px;
            min-width: 150px;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        /* Products List - Cambio de grid a lista vertical */
        .products-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-bottom: 32px;
        }

        .product-card {
            background: var(--card-bg);
            backdrop-filter: var(--backdrop-blur);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            overflow: hidden;
            transition: var(--transition);
            position: relative;
            display: flex; /* Cambio a flex horizontal */
            align-items: center;
            padding: 20px;
            min-height: 120px;
        }

        .product-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
            border-color: rgba(255, 255, 255, 0.2);
        }

        /* Contenedor de imagen a la izquierda */
        .product-image-container {
            position: relative;
            width: 80px; /* Ancho fijo para la imagen */
            height: 80px; /* Altura fija para la imagen */
            min-width: 80px; /* Evitar que se encoja */
            margin-right: 20px;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 8px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .product-image {
            max-width: 100%;
            max-height: 100%;
            width: auto;
            height: auto;
            object-fit: contain;
            object-position: center;
            transition: var(--transition);
            display: block;
            border-radius: 6px;
        }

        .product-card:hover .product-image {
            transform: scale(1.05);
        }

        .product-placeholder {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.05);
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255, 255, 255, 0.3);
            flex-direction: column;
            gap: 4px;
        }

        .product-placeholder i {
            font-size: 24px;
        }

        .product-placeholder span {
            font-size: 10px;
            color: rgba(255, 255, 255, 0.5);
            text-align: center;
        }

        /* Contenido del producto - ocupa el resto del espacio */
        .product-content {
            flex: 1;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto; /* Título/desc, precio/stock, meta, acciones */
            gap: 20px;
            align-items: center;
        }

        /* Columna 1: Información principal */
        .product-main-info {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .product-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-color);
            margin: 0;
            line-height: 1.3;
        }

        .product-description {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Columna 2: Precio y stock */
        .product-price-info {
            display: flex;
            flex-direction: column;
            gap: 8px;
            align-items: flex-start;
        }

        .product-price {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary-color);
            margin: 0;
        }

        .product-stock {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Columna 3: Metadatos */
        .product-meta {
            display: flex;
            flex-direction: column;
            gap: 6px;
            align-items: flex-start;
        }

        .product-meta span {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Columna 4: Acciones */
        .product-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .btn-action {
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: var(--transition);
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-width: 40px;
        }

        .btn-edit {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }

        .btn-edit:hover {
            background: rgba(59, 130, 246, 0.2);
        }

        .btn-toggle {
            background: rgba(245, 158, 11, 0.1);
            color: var(--primary-color);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .btn-toggle:hover {
            background: rgba(245, 158, 11, 0.2);
        }

        .btn-delete {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .btn-delete:hover {
            background: rgba(239, 68, 68, 0.2);
        }

        /* Badges */
        .badge-destacado {
            position: absolute;
            top: 12px;
            right: 12px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: #000;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            z-index: 1;
        }

        .image-count-badge {
            position: absolute;
            top: 4px;
            left: 4px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 9px;
            font-weight: 600;
            z-index: 3;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(4px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: var(--card-bg);
            backdrop-filter: var(--backdrop-blur);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            max-width: 800px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 24px;
            border-bottom: 1px solid var(--border-color);
        }

        .modal-header h3 {
            font-size: 20px;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.7);
            font-size: 20px;
            cursor: pointer;
            padding: 8px;
            border-radius: 4px;
            transition: var(--transition);
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-color);
        }

        /* Form */
        .product-form {
            padding: 24px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            color: var(--text-color);
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group label i {
            color: var(--primary-color);
            width: 16px;
            font-size: 14px;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-color);
            font-size: 14px;
            transition: var(--transition);
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.2);
        }

        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 8px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary-color);
        }

        .form-actions {
            display: flex;
            gap: 16px;
            justify-content: flex-end;
            padding-top: 24px;
            border-top: 1px solid var(--border-color);
        }

        /* No products */
        .no-products {
            text-align: center;
            padding: 80px 40px;
            background: var(--card-bg);
            backdrop-filter: var(--backdrop-blur);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
        }

        .no-products i {
            font-size: 64px;
            color: rgba(255, 255, 255, 0.3);
            margin-bottom: 24px;
        }

        .no-products h3 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .no-products p {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 32px;
        }

        /* Image Upload */
        .images-section {
            grid-column: 1 / -1;
            margin-bottom: 24px;
        }

        .images-section h4 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .images-section h4 i {
            color: var(--primary-color);
            width: 16px;
            font-size: 14px;
        }

        .images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .image-upload {
            position: relative;
            height: 150px;
            background: rgba(255, 255, 255, 0.05);
            border: 2px dashed var(--border-color);
            border-radius: 8px;
            overflow: hidden;
            transition: var(--transition);
        }

        .image-upload:hover {
            border-color: var(--primary-color);
            background: rgba(255, 255, 255, 0.1);
        }

        .image-upload input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
            z-index: 2;
        }

        .image-upload-content {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 16px;
            text-align: center;
            z-index: 1;
        }

        .image-upload-content i {
            font-size: 24px;
            color: rgba(255, 255, 255, 0.5);
            margin-bottom: 8px;
        }

        .image-upload-content span {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.7);
        }

        /* Image Preview en Modal */
        .image-preview {
            position: relative;
            height: 150px;
            min-height: 150px;
            max-height: 150px;
            border-radius: 8px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .image-preview img {
            max-width: 60%;
            max-height: 60%;
            width: auto;
            height: auto;
            object-fit: contain;
            object-position: center;
            display: block;
            margin: 0 auto;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .image-preview-placeholder {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255, 255, 255, 0.5);
            flex-direction: column;
            gap: 8px;
        }

        .image-preview-placeholder i {
            font-size: 32px;
        }

        .image-preview-placeholder span {
            font-size: 12px;
            text-align: center;
        }

        .image-preview-actions {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            opacity: 0;
            transition: var(--transition);
        }

        .image-preview:hover .image-preview-actions {
            opacity: 1;
        }

        .image-action {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
        }

        .image-action:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        .image-action.delete {
            background: rgba(239, 68, 68, 0.5);
        }

        .image-action.delete:hover {
            background: rgba(239, 68, 68, 0.7);
        }

        .image-action.main {
            background: rgba(245, 158, 11, 0.5);
        }

        .image-action.main:hover {
            background: rgba(245, 158, 11, 0.7);
        }

        .main-image-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: #000;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            z-index: 3;
        }

        .existing-images {
            margin-bottom: 16px;
        }

        .existing-images h5 {
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 12px;
            color: rgba(255, 255, 255, 0.8);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .product-content {
                grid-template-columns: 1fr auto;
                gap: 12px;
            }

            .product-price-info,
            .product-meta {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filters-form {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                min-width: auto;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .modal-content {
                margin: 10px;
                width: calc(100% - 20px);
            }

            .images-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            }

            .product-card {
                flex-direction: column;
                align-items: flex-start;
                padding: 16px;
            }

            .product-image-container {
                width: 60px;
                height: 60px;
                min-width: 60px;
                margin-right: 0;
                margin-bottom: 12px;
                align-self: center;
            }

            .product-content {
                grid-template-columns: 1fr;
                gap: 12px;
                width: 100%;
            }

            .product-actions {
                justify-content: center;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo-container">
                    <div class="logo-icon">
                        <i class="fas fa-gem"></i>
                    </div>
                    <div class="logo-text">
                        <h2>Panda Joyeros</h2>
                        <p>Panel Admin</p>
                    </div>
                </div>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-section">
                    <h3 class="nav-title">Navegación Principal</h3>
                    <ul class="nav-menu">
                        <li class="nav-item">
                            <a href="dashboard.php" class="nav-link">
                                <i class="fas fa-chart-bar"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        <li class="nav-item active">
                            <a href="products.php" class="nav-link">
                                <i class="fas fa-gem"></i>
                                <span>Productos</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="categories.php" class="nav-link">
                                <i class="fas fa-tags"></i>
                                <span>Categorías</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="materials.php" class="nav-link">
                                <i class="fas fa-cubes"></i>
                                <span>Materiales</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="manage_users.php" class="nav-link">
                                <i class="fas fa-users"></i>
                                <span>Usuarios</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="nav-section">
                    <h3 class="nav-title">Acciones Rápidas</h3>
                    <ul class="nav-menu">
                        <li class="nav-item">
                            <a href="#" onclick="showProductForm()" class="nav-link">
                                <i class="fas fa-plus"></i>
                                <span>Nuevo Producto</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="../index.php" target="_blank" class="nav-link">
                                <i class="fas fa-eye"></i>
                                <span>Ver Tienda</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="sidebar-footer">
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Cerrar Sesión</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="main-header">
                <h1>Gestión de Productos</h1>
                <div class="user-info">
                    Bienvenido, <span class="username"><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></span>
                </div>
            </header>

            <!-- Content -->
            <div class="dashboard-content">
                <!-- Messages -->
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'error'; ?>">
                        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <!-- Page Header -->
                <div class="page-header">
                    <div class="page-title">
                        <h2><i class="fas fa-gem"></i> Gestión de Productos</h2>
                        <p>Administra el catálogo de productos de la joyería</p>
                    </div>
                    <button class="btn btn-primary" onclick="showProductForm()">
                        <i class="fas fa-plus"></i>
                        Nuevo Producto
                    </button>
                </div>

                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-info">
                                <div class="stat-label">Total Productos</div>
                                <div class="stat-value"><?php echo $stats['total_productos']; ?></div>
                            </div>
                            <div class="stat-icon blue">
                                <i class="fas fa-box"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-info">
                                <div class="stat-label">Productos Destacados</div>
                                <div class="stat-value"><?php echo $stats['productos_destacados']; ?></div>
                            </div>
                            <div class="stat-icon yellow">
                                <i class="fas fa-star"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-info">
                                <div class="stat-label">Stock Total</div>
                                <div class="stat-value"><?php echo $stats['total_stock']; ?></div>
                            </div>
                            <div class="stat-icon green">
                                <i class="fas fa-boxes"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-info">
                                <div class="stat-label">Precio Promedio</div>
                                <div class="stat-value">$<?php echo number_format($producto['precio'] ?? 0, 2); ?></div>
                            </div>
                            <div class="stat-icon purple">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters and Search -->
                <div class="filters-section">
                    <form method="GET" class="filters-form">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" placeholder="Buscar productos..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <select name="categoria" class="filter-select">
                            <option value="">Todas las categorías</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?php echo $categoria['id_categoria']; ?>" <?php echo $filter_categoria == $categoria['id_categoria'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($categoria['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="material" class="filter-select">
                            <option value="">Todos los materiales</option>
                            <?php foreach ($materiales as $material): ?>
                                <option value="<?php echo $material['id_material']; ?>" <?php echo $filter_material == $material['id_material'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($material['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <button type="submit" class="btn btn-outline">
                            <i class="fas fa-search"></i>
                            Buscar
                        </button>
                        
                        <a href="products.php" class="btn btn-ghost">
                            <i class="fas fa-times"></i>
                            Limpiar
                        </a>
                    </form>
                </div>

                <!-- Products List -->
                <div class="products-list">
                    <?php if (empty($productos)): ?>
                        <div class="no-products">
                            <i class="fas fa-box-open"></i>
                            <h3>No hay productos</h3>
                            <p>Comienza agregando tu primer producto al catálogo</p>
                            <button class="btn btn-primary" onclick="showProductForm()">
                                <i class="fas fa-plus"></i>
                                Crear primer producto
                            </button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($productos as $producto): ?>
                            <div class="product-card">
                                <?php if ($producto['destacado']): ?>
                                    <div class="badge-destacado">
                                        <i class="fas fa-star"></i> Destacado
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Imagen del producto -->
                                <div class="product-image-container">
                                    <?php if ($producto['total_imagenes'] > 0): ?>
                                        <div class="image-count-badge">
                                            <i class="fas fa-images"></i> <?php echo $producto['total_imagenes']; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($producto['imagen_principal'])): ?>
                                        <img src="../<?php echo htmlspecialchars($producto['imagen_principal']); ?>" 
                                             class="product-image" 
                                             alt="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                             onerror="this.style.display='none'; this.parentElement.querySelector('.product-placeholder').style.display='flex';">
                                        <div class="product-placeholder" style="display: none;">
                                            <i class="fas fa-image"></i>
                                            <span>Sin imagen</span>
                                        </div>
                                    <?php elseif (!empty($producto['imagen_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($producto['imagen_url']); ?>" 
                                             class="product-image" 
                                             alt="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                             onerror="this.style.display='none'; this.parentElement.querySelector('.product-placeholder').style.display='flex';">
                                        <div class="product-placeholder" style="display: none;">
                                            <i class="fas fa-image"></i>
                                            <span>Sin imagen</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="product-placeholder">
                                            <i class="fas fa-image"></i>
                                            <span>Sin imagen</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Contenido del producto -->
                                <div class="product-content">
                                    <!-- Información principal -->
                                    <div class="product-main-info">
                                        <h3 class="product-title"><?php echo htmlspecialchars($producto['nombre']); ?></h3>
                                        <p class="product-description">
                                            <?php echo htmlspecialchars($producto['descripcion']); ?>
                                        </p>
                                    </div>
                                    
                                    <!-- Precio y stock -->
                                    <div class="product-price-info">
                                        <div class="product-price">$<?php echo number_format($producto['precio'], 2); ?></div>
                                        <div class="product-stock">
                                            <i class="fas fa-boxes"></i>
                                            Stock: <?php echo $producto['stock']; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Metadatos -->
                                    <div class="product-meta">
                                        <span>
                                            <i class="fas fa-tag"></i>
                                            <?php echo htmlspecialchars($producto['categoria_nombre'] ?? 'Sin categoría'); ?>
                                        </span>
                                        <span>
                                            <i class="fas fa-gem"></i>
                                            <?php echo htmlspecialchars($producto['material_nombre'] ?? 'Sin material'); ?>
                                        </span>
                                    </div>
                                    
                                    <!-- Acciones -->
                                    <div class="product-actions">
                                        <button class="btn-action btn-edit" 
                                                onclick="editProduct(<?php echo htmlspecialchars(json_encode($producto)); ?>)"
                                                title="Editar producto">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        
                                        <button class="btn-action btn-toggle" 
                                                onclick="toggleDestacado(<?php echo $producto['id_producto']; ?>, <?php echo $producto['destacado'] ? 0 : 1; ?>)"
                                                title="<?php echo $producto['destacado'] ? 'Quitar destacado' : 'Marcar como destacado'; ?>">
                                            <i class="fas fa-star"></i>
                                        </button>
                                        
                                        <button class="btn-action btn-delete" 
                                                onclick="deleteProduct(<?php echo $producto['id_producto']; ?>, '<?php echo htmlspecialchars($producto['nombre']); ?>')"
                                                title="Eliminar producto">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Product Form Modal -->
    <div class="modal" id="productModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Nuevo Producto</h3>
                <button class="modal-close" onclick="hideProductForm()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" class="product-form" enctype="multipart/form-data">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id_producto" id="productId">
                <input type="hidden" name="eliminar_imagenes" id="eliminarImagenes">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nombre">
                            <i class="fas fa-tag"></i>
                            Nombre del producto
                        </label>
                        <input type="text" id="nombre" name="nombre" required 
                               placeholder="Ej: Anillo de compromiso">
                    </div>
                    
                    <div class="form-group">
                        <label for="precio">
                            <i class="fas fa-dollar-sign"></i>
                            Precio
                        </label>
                        <input type="number" id="precio" name="precio" step="0.01" min="0" required 
                               placeholder="0.00">
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="descripcion">
                            <i class="fas fa-align-left"></i>
                            Descripción
                        </label>
                        <textarea id="descripcion" name="descripcion" rows="3" required 
                                  placeholder="Describe las características del producto..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="stock">
                            <i class="fas fa-boxes"></i>
                            Stock
                        </label>
                        <input type="number" id="stock" name="stock" min="0" value="1">
                    </div>
                    
                    <div class="form-group">
                        <label for="id_categoria">
                            <i class="fas fa-folder"></i>
                            Categoría
                        </label>
                        <select id="id_categoria" name="id_categoria">
                            <option value="0">Sin categoría</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?php echo $categoria['id_categoria']; ?>">
                                    <?php echo htmlspecialchars($categoria['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="id_material">
                            <i class="fas fa-gem"></i>
                            Material
                        </label>
                        <select id="id_material" name="id_material">
                            <option value="0">Sin material</option>
                            <?php foreach ($materiales as $material): ?>
                                <option value="<?php echo $material['id_material']; ?>">
                                    <?php echo htmlspecialchars($material['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <i class="fas fa-star"></i>
                            Opciones
                        </label>
                        <div class="checkbox-group">
                            <input type="checkbox" id="destacado" name="destacado">
                            <label for="destacado">Producto destacado</label>
                        </div>
                    </div>
                    
                    <!-- Imágenes existentes (solo en modo edición) -->
                    <div class="images-section" id="existingImagesSection" style="display: none;">
                        <h4><i class="fas fa-images"></i> Imágenes actuales</h4>
                        <div class="existing-images">
                            <h5>Haz clic en una imagen para marcarla como principal</h5>
                            <div class="images-grid" id="existingImagesGrid">
                                <!-- Las imágenes existentes se cargarán aquí via JavaScript -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- Subir nuevas imágenes -->
                    <div class="images-section">
                        <h4><i class="fas fa-upload"></i> Subir imágenes (máximo 10)</h4>
                        <div class="images-grid" id="imageUploadsGrid">
                            <!-- Los campos de subida se generarán aquí via JavaScript -->
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-ghost" onclick="hideProductForm()">
                        <i class="fas fa-times"></i>
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i>
                        Guardar Producto
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Hidden forms for actions -->
    <form method="POST" id="toggleForm" style="display: none;">
        <input type="hidden" name="action" value="toggle_destacado">
        <input type="hidden" name="id_producto" id="toggleProductId">
        <input type="hidden" name="destacado" id="toggleDestacado">
    </form>

    <form method="POST" id="deleteForm" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id_producto" id="deleteProductId">
    </form>

    <form method="POST" id="deleteImageForm" style="display: none;">
        <input type="hidden" name="action" value="delete_image">
        <input type="hidden" name="id_imagen" id="deleteImageId">
        <input type="hidden" name="id_producto" id="deleteImageProductId">
    </form>

    <form method="POST" id="setMainImageForm" style="display: none;">
        <input type="hidden" name="action" value="set_main_image">
        <input type="hidden" name="id_imagen" id="setMainImageId">
        <input type="hidden" name="id_producto" id="setMainImageProductId">
    </form>

    <script>
        // Variables globales
        let currentProduct = null;
        let imagesToDelete = [];
        let selectedMainImage = null;

        // Generar campos de subida de imágenes
        function generateImageUploads() {
            const grid = document.getElementById('imageUploadsGrid');
            grid.innerHTML = '';
            
            for (let i = 0; i < 10; i++) {
                const uploadDiv = document.createElement('div');
                uploadDiv.className = 'image-upload';
                uploadDiv.innerHTML = `
                    <input type="file" name="producto_imagen_${i}" accept="image/*" onchange="previewImage(this, ${i})">
                    <div class="image-upload-content" id="upload-content-${i}">
                        <i class="fas fa-plus"></i>
                        <span>Subir imagen ${i + 1}</span>
                    </div>
                `;
                grid.appendChild(uploadDiv);
            }
        }

        // Previsualizar imagen
        function previewImage(input, index) {
            const file = input.files[0];
            const content = document.getElementById(`upload-content-${index}`);
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    content.innerHTML = `
                        <img src="${e.target.result}" style="width: 100%; height: 100%; object-fit: cover;">
                        <div class="image-preview-actions">
                            <button type="button" class="image-action main" onclick="setNewImageAsMain(${100 + index})" title="Marcar como principal">
                                <i class="fas fa-star"></i>
                            </button>
                            <button type="button" class="image-action delete" onclick="clearImageUpload(${index})" title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    `;
                };
                reader.readAsDataURL(file);
            } else {
                content.innerHTML = `
                    <i class="fas fa-plus"></i>
                    <span>Subir imagen ${index + 1}</span>
                `;
            }
        }

        // Limpiar campo de subida
        function clearImageUpload(index) {
            const input = document.querySelector(`input[name="producto_imagen_${index}"]`);
            const content = document.getElementById(`upload-content-${index}`);
            
            input.value = '';
            content.innerHTML = `
                <i class="fas fa-plus"></i>
                <span>Subir imagen ${index + 1}</span>
            `;
            
            // Si era la imagen principal seleccionada, resetear
            if (selectedMainImage === (100 + index)) {
                selectedMainImage = null;
            }
        }

        // Marcar nueva imagen como principal
        function setNewImageAsMain(imageIndex) {
            selectedMainImage = imageIndex;
            
            // Crear campo hidden para enviar la selección
            let mainImageInput = document.getElementById('imagenPrincipalInput');
            if (!mainImageInput) {
                mainImageInput = document.createElement('input');
                mainImageInput.type = 'hidden';
                mainImageInput.name = 'imagen_principal';
                mainImageInput.id = 'imagenPrincipalInput';
                document.querySelector('.product-form').appendChild(mainImageInput);
            }
            mainImageInput.value = imageIndex;
            
            // Actualizar visualización
            updateMainImageDisplay();
        }

        // Actualizar visualización de imagen principal
        function updateMainImageDisplay() {
            // Remover badges existentes
            document.querySelectorAll('.main-image-badge').forEach(badge => badge.remove());
            
            // Agregar badge a la imagen principal seleccionada
            if (selectedMainImage !== null) {
                let targetElement = null;
                
                if (selectedMainImage < 100) {
                    // Imagen existente
                    targetElement = document.querySelector(`[data-image-id="${selectedMainImage}"]`);
                } else {
                    // Nueva imagen
                    const index = selectedMainImage - 100;
                    targetElement = document.getElementById(`upload-content-${index}`);
                }
                
                if (targetElement) {
                    const badge = document.createElement('div');
                    badge.className = 'main-image-badge';
                    badge.innerHTML = '<i class="fas fa-star"></i> Principal';
                    targetElement.style.position = 'relative';
                    targetElement.appendChild(badge);
                }
            }
        }

        // Cargar imágenes existentes
        function loadExistingImages(productId) {
            if (!productId) return;
            
            // Hacer petición AJAX para obtener las imágenes
            fetch(`get_product_images.php?id=${productId}`)
                .then(response => response.json())
                .then(images => {
                    const grid = document.getElementById('existingImagesGrid');
                    const section = document.getElementById('existingImagesSection');
                    
                    if (images.length > 0) {
                        section.style.display = 'block';
                        grid.innerHTML = '';
                        
                        images.forEach(image => {
                            const imageDiv = document.createElement('div');
                            imageDiv.className = 'image-preview';
                            imageDiv.setAttribute('data-image-id', image.id_imagen);
                            imageDiv.innerHTML = `
                                <img src="../${image.ruta_imagen}" alt="Imagen del producto" 
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="image-preview-placeholder" style="display: none;">
                                    <i class="fas fa-image fa-2x"></i>
                                </div>
                                ${image.es_principal ? '<div class="main-image-badge"><i class="fas fa-star"></i> Principal</div>' : ''}
                                <div class="image-preview-actions">
                                    <button type="button" class="image-action main" onclick="setExistingImageAsMain(${image.id_imagen})" title="Marcar como principal">
                                        <i class="fas fa-star"></i>
                                    </button>
                                    <button type="button" class="image-action delete" onclick="markImageForDeletion(${image.id_imagen})" title="Eliminar">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            `;
                            grid.appendChild(imageDiv);
                            
                            if (image.es_principal) {
                                selectedMainImage = parseInt(image.id_imagen);
                            }
                        });
                    } else {
                        section.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error al cargar imágenes:', error);
                });
        }

        // Marcar imagen existente como principal
        function setExistingImageAsMain(imageId) {
            selectedMainImage = imageId;
            
            // Crear campo hidden para enviar la selección
            let mainImageInput = document.getElementById('imagenPrincipalInput');
            if (!mainImageInput) {
                mainImageInput = document.createElement('input');
                mainImageInput.type = 'hidden';
                mainImageInput.name = 'imagen_principal';
                mainImageInput.id = 'imagenPrincipalInput';
                document.querySelector('.product-form').appendChild(mainImageInput);
            }
            mainImageInput.value = imageId;
            
            // Actualizar visualización
            updateMainImageDisplay();
        }

        // Marcar imagen para eliminación
        function markImageForDeletion(imageId) {
            if (confirm('¿Estás seguro de que quieres eliminar esta imagen?')) {
                imagesToDelete.push(imageId);
                
                // Ocultar la imagen
                const imageElement = document.querySelector(`[data-image-id="${imageId}"]`);
                if (imageElement) {
                    imageElement.style.opacity = '0.5';
                    imageElement.style.pointerEvents = 'none';
                    
                    // Agregar indicador de eliminación
                    const deleteIndicator = document.createElement('div');
                    deleteIndicator.className = 'main-image-badge';
                    deleteIndicator.style.background = '#ef4444';
                    deleteIndicator.innerHTML = '<i class="fas fa-trash"></i> Eliminar';
                    imageElement.appendChild(deleteIndicator);
                }
                
                // Actualizar campo hidden
                document.getElementById('eliminarImagenes').value = imagesToDelete.join(',');
                
                // Si era la imagen principal, resetear
                if (selectedMainImage === imageId) {
                    selectedMainImage = null;
                }
            }
        }

        // Show product form modal
        function showProductForm(product = null) {
            const modal = document.getElementById('productModal');
            const modalTitle = document.getElementById('modalTitle');
            const form = modal.querySelector('.product-form');

            // Resetear variables
            currentProduct = product;
            imagesToDelete = [];
            selectedMainImage = null;
            
            // Limpiar campo de imágenes a eliminar
            document.getElementById('eliminarImagenes').value = '';
            
            // Remover campo de imagen principal si existe
            const existingMainInput = document.getElementById('imagenPrincipalInput');
            if (existingMainInput) {
                existingMainInput.remove();
            }

            if (product) {
                modalTitle.textContent = 'Editar Producto';
                populateForm(product);
                document.getElementById('formAction').value = 'update';
                document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> Actualizar Producto';
                
                // Cargar imágenes existentes
                loadExistingImages(product.id_producto);
            } else {
                modalTitle.textContent = 'Nuevo Producto';
                form.reset();
                document.getElementById('formAction').value = 'create';
                document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> Guardar Producto';
                document.getElementById('stock').value = '1';
                
                // Ocultar sección de imágenes existentes
                document.getElementById('existingImagesSection').style.display = 'none';
            }

            // Generar campos de subida
            generateImageUploads();

            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        // Hide product form modal
        function hideProductForm() {
            const modal = document.getElementById('productModal');
            modal.classList.remove('show');
            document.body.style.overflow = 'auto';
            currentProduct = null;
            imagesToDelete = [];
            selectedMainImage = null;
        }

        // Edit product
        function editProduct(product) {
            showProductForm(product);
        }

        // Toggle destacado status
        function toggleDestacado(productId, destacado) {
            document.getElementById('toggleProductId').value = productId;
            document.getElementById('toggleDestacado').value = destacado;
            document.getElementById('toggleForm').submit();
        }

        // Delete product
        function deleteProduct(productId, productName) {
            if (confirm(`¿Estás seguro de que quieres eliminar el producto "${productName}"?\n\nEsta acción eliminará también todas sus imágenes y no se puede deshacer.`)) {
                document.getElementById('deleteProductId').value = productId;
                document.getElementById('deleteForm').submit();
            }
        }

        // Populate form with product data
        function populateForm(product) {
            document.getElementById('productId').value = product.id_producto || '';
            document.getElementById('nombre').value = product.nombre || '';
            document.getElementById('descripcion').value = product.descripcion || '';
            document.getElementById('precio').value = product.precio || '';
            document.getElementById('stock').value = product.stock || '';
            document.getElementById('id_categoria').value = product.id_categoria || 0;
            document.getElementById('id_material').value = product.id_material || 0;
            document.getElementById('destacado').checked = product.destacado == 1;
        }

        // Close modal when clicking outside
        document.addEventListener('click', (e) => {
            const modal = document.getElementById('productModal');
            if (e.target === modal) {
                hideProductForm();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const modal = document.getElementById('productModal');
                if (modal.classList.contains('show')) {
                    hideProductForm();
                }
            }
        });

        // Mejorar manejo de errores de imagen
        document.addEventListener('DOMContentLoaded', () => {
            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach((alert) => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => {
                        alert.remove();
                    }, 300);
                }, 5000);
            });
            
            // Asegurar que los placeholders funcionen correctamente
            document.querySelectorAll('.product-image').forEach(img => {
                img.addEventListener('error', function() {
                    this.style.display = 'none';
                    const placeholder = this.parentElement.querySelector('.product-placeholder');
                    if (placeholder) {
                        placeholder.style.display = 'flex';
                    }
                });
            });
        });
    </script>
</body>
</html>
