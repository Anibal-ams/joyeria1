<?php
require_once '../includes/db_connection.php';
require_once '../includes/helpers.php';

// Inicializar variables
$product = null;
$images = [];
$related_products = [];

// Obtener el ID del producto de la URL
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($product_id > 0) {
    // Consulta para obtener los detalles del producto
    $product_query = "SELECT p.*, c.nombre AS categoria_nombre, m.nombre AS material_nombre 
                      FROM productos p 
                      LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
                      LEFT JOIN materiales m ON p.id_material = m.id_material
                      WHERE p.id_producto = ?";
    
    $stmt = $conn->prepare($product_query);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $product = $result->fetch_assoc();

        // Consulta para obtener las imágenes del producto (usando la tabla correcta)
        $images_query = "SELECT ruta_imagen, es_principal, orden 
                        FROM producto_imagenes 
                        WHERE id_producto = ? 
                        ORDER BY es_principal DESC, orden ASC";
        $images_stmt = $conn->prepare($images_query);
        $images_stmt->bind_param("i", $product_id);
        $images_stmt->execute();
        $images_result = $images_stmt->get_result();

        while ($image = $images_result->fetch_assoc()) {
            $images[] = $image['ruta_imagen'];
        }

        // Si no hay imágenes en producto_imagenes, usar imagen_url de productos
        if (empty($images) && !empty($product['imagen_url'])) {
            $images[] = $product['imagen_url'];
        }

        // Consulta para obtener productos relacionados
        $related_query = "SELECT p.*, pi.ruta_imagen 
                         FROM productos p
                         LEFT JOIN producto_imagenes pi ON p.id_producto = pi.id_producto AND pi.es_principal = 1
                         WHERE p.id_categoria = ? AND p.id_producto != ?
                         ORDER BY RAND()
                         LIMIT 4";
        $related_stmt = $conn->prepare($related_query);
        $related_stmt->bind_param("ii", $product['id_categoria'], $product_id);
        $related_stmt->execute();
        $related_result = $related_stmt->get_result();

        while ($related_product = $related_result->fetch_assoc()) {
            $related_products[] = $related_product;
        }
    }
}

// Cerrar la conexión
$conn->close();

// Función para obtener la ruta de la imagen
function get_image_path($image_url) {
    if (empty($image_url)) {
        return '../img/no-image.png';
    }
    
    // Si es una URL completa, devolverla tal como está
    if (strpos($image_url, 'http') === 0) {
        return $image_url;
    }
    
    // Si es una ruta relativa, construir la ruta correcta
    // Remover cualquier '../' al inicio y agregar '../' una sola vez
    $clean_path = ltrim($image_url, './');
    return '../' . $clean_path;
}

// Función para formatear precio en pesos colombianos
function format_cop_price($price) {
    return '$ ' . number_format($price, 0, ',', '.');
}

// Si no se encontró el producto, redirigir a la página de tienda
if (!$product) {
    header("Location: tienda.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo safe_output($product['nombre']); ?> - Panda Joyeros</title>
    <link rel="icon" href="../img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/description.css">
    <style>
        /* Estilos adicionales para mejorar la carga de imágenes */
        .prod-main-image-container {
            position: relative;
            background: #f8f8f8;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .prod-main-image-container img {
            width: 100%;
            height: 400px;
            object-fit: contain;
            transition: opacity 0.3s ease;
        }
        
        .prod-thumbnail-images {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .prod-thumbnail {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border: 2px solid transparent;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .prod-thumbnail:hover {
            border-color: #d4af37;
            transform: scale(1.05);
        }
        
        .prod-thumbnail.active {
            border-color: #d4af37;
            box-shadow: 0 0 10px rgba(212, 175, 55, 0.3);
        }
        
        .image-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f0f0f0;
            color: #999;
            font-size: 14px;
            height: 100%;
            width: 100%;
        }
        
        .prod-related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .prod-related-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .prod-related-card:hover {
            transform: translateY(-5px);
        }
        
        .prod-related-card img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 6px;
            margin-bottom: 10px;
        }
        
        .stock-info {
            margin: 15px 0;
            padding: 10px;
            border-radius: 6px;
            font-weight: 500;
        }
        
        .stock-available {
            background: #e8f5e8;
            color: #2d5a2d;
            border: 1px solid #c3e6c3;
        }
        
        .stock-low {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .stock-out {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <a href="../index.php" class="logo">
                <img src="../img/logo.png" alt="Panda Joyeros" width="80">
            </a>
            <nav>
                <ul>
                    <li><a href="../index.php">Inicio</a></li>
                    <li><a href="tienda.php">Tienda</a></li>
                    <li><a href="descripcion.php" class="active">Descripción</a></li>
                    <li><a href="quienes-somos.html">Sobre Nosotros</a></li>
                    <li><a href="contacto.html">Contacto</a></li>
                </ul>
            </nav>
            <div class="user-actions">
                <button aria-label="Favoritos">
                    <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                    </svg>
                </button>
                <button aria-label="Carrito">
                    <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none">
                        <circle cx="9" cy="21" r="1"></circle>
                        <circle cx="20" cy="21" r="1"></circle>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                    </svg>
                </button>
            </div>
        </div>
    </header>

    <main class="prod-description">
        <div class="container">
            <div class="prod-grid">
                <div class="prod-images">
                    <div class="prod-main-image-container">
                        <?php if (!empty($images)): ?>
                            <img src="<?php echo get_image_path($images[0]); ?>" 
                                 alt="<?php echo safe_output($product['nombre']); ?>" 
                                 id="prod-main-image"
                                 onerror="this.onerror=null; this.src='../img/no-image.png';">
                        <?php else: ?>
                            <div class="image-placeholder">
                                <span>Sin imagen disponible</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (count($images) > 1): ?>
                    <div class="prod-thumbnail-images">
                        <?php foreach ($images as $index => $image): 
                            $thumb_path = get_image_path($image);
                        ?>
                            <img src="<?php echo safe_output($thumb_path); ?>" 
                                 alt="<?php echo safe_output($product['nombre']) . ' - Imagen ' . ($index + 1); ?>" 
                                 class="prod-thumbnail <?php echo $index === 0 ? 'active' : ''; ?>"
                                 onerror="this.style.display='none';">
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="prod-info">
                    <h1 class="prod-title"><?php echo safe_output($product['nombre']); ?></h1>
                    <p class="prod-price"><?php echo format_cop_price($product['precio']); ?></p>
                    
                    <div class="prod-rating">
                        <span class="prod-stars">★★★★★</span>
                        <span class="prod-reviews">(25 reseñas)</span>
                    </div>
                    
                    <?php if (!empty($product['descripcion'])): ?>
                    <p class="prod-description"><?php echo safe_output($product['descripcion']); ?></p>
                    <?php endif; ?>
                    
                    <!-- Información de stock -->
                    <?php 
                    $stock = (int)$product['stock'];
                    if ($stock > 10): 
                    ?>
                        <div class="stock-info stock-available">
                            ✓ En stock (<?php echo $stock; ?> unidades disponibles)
                        </div>
                    <?php elseif ($stock > 0): ?>
                        <div class="stock-info stock-low">
                            ⚠ Pocas unidades (<?php echo $stock; ?> disponibles)
                        </div>
                    <?php else: ?>
                        <div class="stock-info stock-out">
                            ✗ Producto agotado
                        </div>
                    <?php endif; ?>
                    
                    <div class="prod-options">
                        <div class="prod-option">
                            <label for="prod-quantity">Cantidad:</label>
                            <input type="number" id="prod-quantity" name="quantity" min="1" max="<?php echo max(1, $stock); ?>" value="1" <?php echo $stock <= 0 ? 'disabled' : ''; ?>>
                        </div>
                    </div>
                    
                    <button class="prod-add-to-cart" <?php echo $stock <= 0 ? 'disabled' : ''; ?>>
                        <?php echo $stock > 0 ? 'Añadir al Carrito' : 'Producto Agotado'; ?>
                    </button>
                    
                    <button class="prod-add-to-wishlist">
                        <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                        </svg>
                        Añadir a Favoritos
                    </button>
                </div>
            </div>
            
            <div class="prod-details">
                <h2>Detalles del Producto</h2>
                <ul>
                    <?php if (!empty($product['categoria_nombre'])): ?>
                    <li><strong>Categoría:</strong> <?php echo safe_output($product['categoria_nombre']); ?></li>
                    <?php endif; ?>
                    
                    <?php if (!empty($product['material_nombre'])): ?>
                    <li><strong>Material:</strong> <?php echo safe_output($product['material_nombre']); ?></li>
                    <?php endif; ?>
                    
                    <?php if (!empty($product['peso'])): ?>
                    <li><strong>Peso:</strong> <?php echo safe_output($product['peso']); ?> g</li>
                    <?php endif; ?>
                    
                    <?php if (!empty($product['dimensiones'])): ?>
                    <li><strong>Dimensiones:</strong> <?php echo safe_output($product['dimensiones']); ?></li>
                    <?php endif; ?>
                    
                    <li><strong>Stock:</strong> <?php echo $stock; ?> unidades</li>
                    
                    <?php if (!empty($product['fecha_adicion'])): ?>
                    <li><strong>Fecha de adición:</strong> <?php echo date('d/m/Y', strtotime($product['fecha_adicion'])); ?></li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <?php if (!empty($related_products)): ?>
            <div class="prod-related">
                <h2>Productos Relacionados</h2>
                <div class="prod-related-grid">
                    <?php foreach ($related_products as $related_product): 
                        $related_image_path = get_image_path($related_product['ruta_imagen'] ?? $related_product['imagen_url'] ?? '');
                    ?>
                    <div class="prod-related-card">
                        <img src="<?php echo safe_output($related_image_path); ?>" 
                             alt="<?php echo safe_output($related_product['nombre']); ?>"
                             onerror="this.onerror=null; this.src='../img/no-image.png';">
                        <h3><?php echo safe_output($related_product['nombre']); ?></h3>
                        <p class="prod-related-price"><?php echo format_cop_price($related_product['precio']); ?></p>
                        <a href="descripcion.php?id=<?php echo $related_product['id_producto']; ?>" class="prod-view-product">Ver Producto</a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Sobre Nosotros</h3>
                    <ul>
                        <li><a href="quienes-somos.html">Historia</a></li>
                        <li><a href="quienes-somos.html#our-team">Artesanos</a></li>
                        <li><a href="quienes-somos.html#our-values">Sostenibilidad</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Atención al Cliente</h3>
                    <ul>
                        <li><a href="contacto.html">Contacto</a></li>
                        <li><a href="#">Envíos</a></li>
                        <li><a href="#">Devoluciones</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Legal</h3>
                    <ul>
                        <li><a href="#">Privacidad</a></li>
                        <li><a href="#">Términos</a></li>
                        <li><a href="#">Cookies</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Newsletter</h3>
                    <p>Suscríbete para recibir las últimas novedades y ofertas exclusivas.</p>
                    <form class="newsletter-form">
                        <input type="email" placeholder="Tu email" required>
                        <button type="submit">Suscribir</button>
                    </form>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Panda Joyeros. Todos los derechos reservados.</p>
            </div>
        </div>
    </footer>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const mainImage = document.getElementById('prod-main-image');
        const thumbnails = document.querySelectorAll('.prod-thumbnail');

        // Funcionalidad de galería de imágenes
        thumbnails.forEach((thumbnail, index) => {
            thumbnail.addEventListener('click', function() {
                if (mainImage) {
                    mainImage.src = this.src;
                    mainImage.alt = this.alt;
                    
                    thumbnails.forEach(thumb => thumb.classList.remove('active'));
                    this.classList.add('active');
                }
            });
        });

        // Funcionalidad del carrito
        const addToCartButton = document.querySelector('.prod-add-to-cart');
        const addToWishlistButton = document.querySelector('.prod-add-to-wishlist');
        const quantityInput = document.getElementById('prod-quantity');

        if (addToCartButton && !addToCartButton.disabled) {
            addToCartButton.addEventListener('click', function() {
                const quantity = quantityInput ? quantityInput.value : 1;
                const productName = document.querySelector('.prod-title').textContent;
                
                // Aquí puedes agregar la lógica real del carrito
                alert(`Se han añadido ${quantity} unidad(es) de "${productName}" al carrito.`);
                
                // Efecto visual
                this.style.background = '#28a745';
                this.textContent = '¡Añadido!';
                setTimeout(() => {
                    this.style.background = '';
                    this.textContent = 'Añadir al Carrito';
                }, 2000);
            });
        }

        if (addToWishlistButton) {
            addToWishlistButton.addEventListener('click', function() {
                const productName = document.querySelector('.prod-title').textContent;
                
                // Toggle estado de favorito
                this.classList.toggle('active');
                if (this.classList.contains('active')) {
                    this.style.background = '#d4af37';
                    this.style.color = 'white';
                    alert(`"${productName}" ha sido añadido a tu lista de favoritos.`);
                } else {
                    this.style.background = '';
                    this.style.color = '';
                    alert(`"${productName}" ha sido removido de tu lista de favoritos.`);
                }
            });
        }

        // Validación de cantidad
        if (quantityInput) {
            quantityInput.addEventListener('change', function() {
                const max = parseInt(this.getAttribute('max'));
                const min = parseInt(this.getAttribute('min'));
                let value = parseInt(this.value);
                
                if (value > max) {
                    this.value = max;
                    alert(`Solo hay ${max} unidades disponibles.`);
                } else if (value < min) {
                    this.value = min;
                }
            });
        }
    });
    </script>
</body>
</html>
