<?php
require_once '../includes/db_connection.php';
require_once '../includes/helpers.php';

// Inicializar variables de filtro
$category_filter = isset($_GET['categoria']) ? (int)$_GET['categoria'] : 0;
$material_filter = isset($_GET['material']) ? (int)$_GET['material'] : 0;
$price_filter = isset($_GET['precio']) ? (int)$_GET['precio'] : 10000000;
$search_filter = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

// Construir la consulta base con múltiples imágenes
$products_query = "SELECT p.*, c.nombre as categoria_nombre, m.nombre as material_nombre,
                  GROUP_CONCAT(
                    DISTINCT CONCAT(pi.ruta_imagen, '|', COALESCE(pi.orden, 999))
                    ORDER BY COALESCE(pi.orden, 999) ASC
                    SEPARATOR ','
                  ) as imagenes
                  FROM productos p
                  LEFT JOIN producto_imagenes pi ON p.id_producto = pi.id_producto AND pi.es_principal = 1
                  LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
                  LEFT JOIN materiales m ON p.id_material = m.id_material
                  WHERE 1=1";

// Añadir condiciones de filtro si están presentes
$params = [];
$types = "";

if ($category_filter > 0) {
    $products_query .= " AND p.id_categoria = ?";
    $params[] = $category_filter;
    $types .= "i";
}

if ($material_filter > 0) {
    $products_query .= " AND p.id_material = ?";
    $params[] = $material_filter;
    $types .= "i";
}

if ($price_filter > 0) {
    $products_query .= " AND p.precio <= ?";
    $params[] = $price_filter;
    $types .= "i";
}

if (!empty($search_filter)) {
    $products_query .= " AND (p.nombre LIKE ? OR p.descripcion LIKE ?)";
    $search_param = "%{$search_filter}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

// Añadir GROUP BY y ORDER BY
$products_query .= " GROUP BY p.id_producto ORDER BY p.destacado DESC, p.fecha_adicion DESC";

// Preparar y ejecutar la consulta
$stmt = $conn->prepare($products_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products_result = $stmt->get_result();

// Fetch categories
$categories_query = "SELECT * FROM categorias WHERE activo = 1 ORDER BY nombre";
$categories_result = $conn->query($categories_query);

// Fetch materials
$materials_query = "SELECT * FROM materiales ORDER BY nombre";
$materials_result = $conn->query($materials_query);

// Obtener el precio máximo para el rango de precios
$max_price_query = "SELECT MAX(precio) as max_price FROM productos";
$max_price_result = $conn->query($max_price_query);
$max_price_row = $max_price_result->fetch_assoc();
$max_price = $max_price_row['max_price'] ?? 5000000;

// Function to get the correct image path with fallback
function get_image_path($imagenes_string) {
    if (empty($imagenes_string)) {
        return '../img/no-image.png';
    }
    
    $imagenes = explode(',', $imagenes_string);
    $primera_imagen = explode('|', $imagenes[0])[0];
    
    if (empty($primera_imagen)) {
        return '../img/no-image.png';
    }
    
    // Check if the image_url starts with 'http' or 'https'
    if (strpos($primera_imagen, 'http') === 0) {
        return $primera_imagen;
    }
    
    // If it's a relative path, prepend the correct directory
    $image_path = '../' . ltrim($primera_imagen, '/');
    
    // Check if file exists
    if (file_exists($image_path)) {
        return $image_path;
    }
    
    return '../img/no-image.png';
}

// Function to get all product images
function get_all_images($imagenes_string) {
    if (empty($imagenes_string)) {
        return [['url' => '../img/no-image.png', 'orden' => 1]];
    }
    
    $imagenes = explode(',', $imagenes_string);
    $result = [];
    
    foreach ($imagenes as $imagen_data) {
        $parts = explode('|', $imagen_data);
        $url = $parts[0];
        $orden = isset($parts[1]) ? (int)$parts[1] : 999;
        
        if (!empty($url)) {
            if (strpos($url, 'http') === 0) {
                $image_path = $url;
            } else {
                $image_path = '../' . ltrim($url, '/');
            }
            
            $result[] = [
                'url' => $image_path,
                'orden' => $orden
            ];
        }
    }
    
    if (empty($result)) {
        return [['url' => '../img/no-image.png', 'orden' => 1]];
    }
    
    // Sort by orden
    usort($result, function($a, $b) {
        return $a['orden'] - $b['orden'];
    });
    
    return $result;
}

// Function to format price in Colombian Pesos
function format_cop_price($price) {
    return '$ ' . number_format($price, 0, ',', '.');
}

// Function to generate star rating HTML
function generate_stars($rating) {
    $rating = min(5, max(0, $rating));
    $full_stars = floor($rating);
    $half_star = $rating - $full_stars >= 0.5;
    $empty_stars = 5 - $full_stars - ($half_star ? 1 : 0);
    
    $stars_html = '';
    
    // Full stars
    for ($i = 0; $i < $full_stars; $i++) {
        $stars_html .= '★';
    }
    
    // Half star
    if ($half_star) {
        $stars_html .= '★';
    }
    
    // Empty stars
    for ($i = 0; $i < $empty_stars; $i++) {
        $stars_html .= '☆';
    }
    
    return $stars_html;
}

// Function to truncate text
function truncate_text($text, $length = 100) {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . '...';
}

// Close the connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tienda - Panda Joyeros</title>
    
    <link rel="icon" href="../img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/shop.css">
    <style>
        /* Enhanced styles for better product display */
        .product-placeholder {
            width: 100%;
            height: 180px;
            background: linear-gradient(135deg, #f5f5f5 0%, #e8e8e8 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 0.9rem;
            border-radius: 8px;
        }
        
        .product-image-gallery {
            position: relative;
            overflow: hidden;
        }
        
        .product-image-gallery img {
            transition: opacity 0.3s ease;
        }
        
        .image-indicators {
            position: absolute;
            bottom: 8px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 4px;
            z-index: 2;
        }
        
        .image-indicator {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.5);
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        
        .image-indicator.active {
            background-color: var(--primary-color);
        }
        
        .product-stock-info {
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 0.5rem;
        }
        
        .stock-available {
            color: #28a745;
        }
        
        .stock-low {
            color: #ffc107;
        }
        
        .stock-out {
            color: #dc3545;
        }
        
        .search-bar {
            margin-bottom: 1rem;
        }
        
        .search-bar input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        
        .results-info {
            margin-bottom: 1rem;
            color: #666;
            font-size: 0.9rem;
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
                    <li><a href="tienda.php" class="active">Tienda</a></li>
                    <li><a href="quienes-somos.html">Quienes somos</a></li>
                    <li><a href="contacto.html">Contacto</a></li>
                    <li><a href="../admin/login.php">Administración</a></li>
                </ul>
            </nav>
            <div class="user-actions">
                <button aria-label="Favoritos">
                    <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                    </svg>
                </button>
                <button aria-label="Carrito">
                    <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="9" cy="21" r="1"></circle>
                        <circle cx="20" cy="21" r="1"></circle>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                    </svg>
                </button>
            </div>
        </div>
    </header>

    <main class="shop-page">
        <section class="shop-hero">
            <div class="container">
                <h1>Nuestra Colección</h1>
                <p>Descubre la elegancia atemporal en cada pieza</p>
            </div>
        </section>

        <section class="shop-content">
            <div class="container">
                <div class="shop-grid">
                    <aside class="shop-sidebar">
                        <div class="filter-section">
                            <h2>Filtrar por</h2>
                            <form id="filter-form" action="tienda.php" method="get">
                                <!-- Barra de búsqueda -->
                                <div class="search-bar">
                                    <input type="text" name="buscar" placeholder="Buscar productos..." 
                                           value="<?php echo htmlspecialchars($search_filter); ?>">
                                </div>
                                
                                <div class="filter-group">
                                    <h3>Categoría</h3>
                                    <ul>
                                        <li>
                                            <label>
                                                <input type="radio" name="categoria" value="0" <?php echo $category_filter == 0 ? 'checked' : ''; ?>>
                                                Todas las categorías
                                            </label>
                                        </li>
                                        <?php 
                                        if ($categories_result && $categories_result->num_rows > 0) {
                                            $categories_result->data_seek(0);
                                            while ($category = $categories_result->fetch_assoc()): 
                                        ?>
                                            <li>
                                                <label>
                                                    <input type="radio" name="categoria" value="<?php echo $category['id_categoria']; ?>" 
                                                    <?php echo $category_filter == $category['id_categoria'] ? 'checked' : ''; ?>>
                                                    <?php echo safe_output($category['nombre']); ?>
                                                </label>
                                            </li>
                                        <?php endwhile; } ?>
                                    </ul>
                                </div>
                                
                                <div class="filter-group">
                                    <h3>Material</h3>
                                    <ul>
                                        <li>
                                            <label>
                                                <input type="radio" name="material" value="0" <?php echo $material_filter == 0 ? 'checked' : ''; ?>>
                                                Todos los materiales
                                            </label>
                                        </li>
                                        <?php 
                                        if ($materials_result && $materials_result->num_rows > 0) {
                                            $materials_result->data_seek(0);
                                            while ($material = $materials_result->fetch_assoc()): 
                                        ?>
                                            <li>
                                                <label>
                                                    <input type="radio" name="material" value="<?php echo $material['id_material']; ?>"
                                                    <?php echo $material_filter == $material['id_material'] ? 'checked' : ''; ?>>
                                                    <?php echo safe_output($material['nombre']); ?>
                                                </label>
                                            </li>
                                        <?php endwhile; } ?>
                                    </ul>
                                </div>
                                
                                <div class="filter-group">
                                    <h3>Precio</h3>
                                    <div class="price-range">
                                        <input type="range" id="price-range" name="precio" min="0" max="<?php echo $max_price; ?>" step="100000" value="<?php echo $price_filter; ?>">
                                        <output for="price-range"><?php echo format_cop_price($price_filter); ?></output>
                                    </div>
                                </div>
                                
                                <div class="filter-actions">
                                    <button type="submit" class="btn btn-primary">Aplicar filtros</button>
                                    <a href="tienda.php" class="btn btn-secondary">Limpiar filtros</a>
                                </div>
                            </form>
                        </div>
                    </aside>
                    
                    <div class="product-grid">
                        
                        
                        <?php
                        if ($products_result && $products_result->num_rows > 0) {
                            while($product = $products_result->fetch_assoc()) {
                                $imagenes = get_all_images($product['imagenes']);
                                $imagen_principal = $imagenes[0]['url'];
                                $destacado = isset($product['destacado']) && $product['destacado'] == 1;
                                $categoria = isset($product['categoria_nombre']) ? $product['categoria_nombre'] : '';
                                $material = isset($product['material_nombre']) ? $product['material_nombre'] : '';
                                
                                // Generar una valoración aleatoria para demostración
                                $rating = mt_rand(35, 50) / 10;
                                $rating_count = mt_rand(5, 50);
                                
                                // Determinar estado del stock
                                $stock = isset($product['stock']) ? (int)$product['stock'] : 0;
                                $stock_class = '';
                                $stock_text = '';
                                if ($stock > 10) {
                                    $stock_class = 'stock-available';
                                    $stock_text = 'En stock';
                                } elseif ($stock > 0) {
                                    $stock_class = 'stock-low';
                                    $stock_text = "Últimas {$stock} unidades";
                                } else {
                                    $stock_class = 'stock-out';
                                    $stock_text = 'Agotado';
                                }
                        ?>
                        <div class="product-card" 
                             data-category="<?php echo $product['id_categoria']; ?>" 
                             data-material="<?php echo $product['id_material']; ?>" 
                             data-price="<?php echo $product['precio']; ?>"
                             data-product-id="<?php echo $product['id_producto']; ?>">
                            
                            <?php if ($destacado): ?>
                                <div class="product-badge">Destacado</div>
                            <?php endif; ?>
                            
                            <div class="floating-icons">
                                <button class="icon-button favorite-btn" aria-label="Añadir a favoritos" title="Añadir a favoritos" data-product-id="<?php echo $product['id_producto']; ?>">
                                    <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none">
                                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                                    </svg>
                                </button>
                                <button class="icon-button compare-btn" aria-label="Comparar" title="Comparar" data-product-id="<?php echo $product['id_producto']; ?>">
                                    <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none">
                                        <polyline points="17 1 21 5 17 9"></polyline>
                                        <path d="M3 11V9a4 4 0 0 1 4-4h14"></path>
                                        <polyline points="7 23 3 19 7 15"></polyline>
                                        <path d="M21 13v2a4 4 0 0 1-4 4H3"></path>
                                    </svg>
                                </button>
                            </div>
                            
                            <div class="product-image-container product-image-gallery">
                                <?php if (count($imagenes) > 1): ?>
                                    <?php foreach ($imagenes as $index => $imagen): ?>
                                        <img src="<?php echo safe_output($imagen['url']); ?>" 
                                             alt="<?php echo safe_output($product['nombre'], 'Producto'); ?>" 
                                             loading="lazy"
                                             style="<?php echo $index > 0 ? 'display: none;' : ''; ?>"
                                             onerror="this.src='../img/no-image.png'; this.onerror=null;">
                                    <?php endforeach; ?>
                                    
                                    <div class="image-indicators">
                                        <?php foreach ($imagenes as $index => $imagen): ?>
                                            <div class="image-indicator <?php echo $index === 0 ? 'active' : ''; ?>" 
                                                 data-image-index="<?php echo $index; ?>"></div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <img src="<?php echo safe_output($imagen_principal); ?>" 
                                         alt="<?php echo safe_output($product['nombre'], 'Producto'); ?>" 
                                         loading="lazy"
                                         onerror="this.src='../img/no-image.png'; this.onerror=null;">
                                <?php endif; ?>
                            </div>
                            
                            <div class="product-content">
                                <?php if (!empty($categoria)): ?>
                                    <div class="product-category"><?php echo safe_output($categoria); ?></div>
                                <?php endif; ?>
                                
                                <h3><?php echo safe_output($product['nombre'], 'Producto sin nombre'); ?></h3>
                                
                                <?php if (!empty($product['descripcion'])): ?>
                                    <p class="product-description" style="font-size: 0.85rem; color: #666; margin-bottom: 0.5rem;">
                                        <?php echo safe_output(truncate_text($product['descripcion'], 80)); ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="product-rating">
                                    <div class="stars"><?php echo generate_stars($rating); ?></div>
                                    <span class="rating-count">(<?php echo $rating_count; ?>)</span>
                                </div>
                                
                                <?php if (isset($product['stock'])): ?>
                                    <div class="product-stock-info <?php echo $stock_class; ?>">
                                        <?php echo $stock_text; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <p class="price"><?php echo format_cop_price($product['precio']); ?></p>
                                
                                <div class="product-actions">
                                    <a href="descripcion.php?id=<?php echo $product['id_producto']; ?>" class="view-details">Ver detalles</a>
                                    <button class="quick-view" aria-label="Vista rápida" title="Vista rápida" data-product-id="<?php echo $product['id_producto']; ?>">
                                        <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none">
                                            <circle cx="11" cy="11" r="8"></circle>
                                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                            <line x1="11" y1="8" x2="11" y2="14"></line>
                                            <line x1="8" y1="11" x2="14" y2="11"></line>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php
                            }
                        } else {
                            echo "<div class='no-products'>";
                            echo "<p>No hay productos disponibles con los filtros seleccionados.</p>";
                            echo "<p>Intenta con otros criterios de búsqueda.</p>";
                            if (!empty($search_filter)) {
                                echo "<p><a href='tienda.php' class='btn btn-primary'>Ver todos los productos</a></p>";
                            }
                            echo "</div>";
                        }
                        ?>
                    </div>
                </div>
                
                <?php if ($products_result && $products_result->num_rows > 0): ?>
                <div class="pagination">
                    <button class="prev" disabled>Anterior</button>
                    <span class="current-page">Página 1 de 1</span>
                    <button class="next" disabled>Siguiente</button>
                </div>
                <?php endif; ?>
            </div>
        </section>
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
                    <h3>Búsqueda de información</h3>
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
        // Enhanced JavaScript functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Price range slider
            const priceRange = document.getElementById('price-range');
            const priceOutput = document.querySelector('output[for="price-range"]');
            
            if (priceRange && priceOutput) {
                priceRange.addEventListener('input', function() {
                    const value = parseInt(this.value);
                    const formattedValue = '$ ' + value.toLocaleString('es-CO');
                    priceOutput.textContent = formattedValue;
                });
            }
            
            // Image gallery functionality
            document.querySelectorAll('.image-indicator').forEach(indicator => {
                indicator.addEventListener('click', function() {
                    const imageIndex = parseInt(this.dataset.imageIndex);
                    const gallery = this.closest('.product-image-gallery');
                    const images = gallery.querySelectorAll('img');
                    const indicators = gallery.querySelectorAll('.image-indicator');
                    
                    // Hide all images
                    images.forEach(img => img.style.display = 'none');
                    // Show selected image
                    if (images[imageIndex]) {
                        images[imageIndex].style.display = 'block';
                    }
                    
                    // Update indicators
                    indicators.forEach(ind => ind.classList.remove('active'));
                    this.classList.add('active');
                });
            });
            
            // Auto-rotate images on hover
            document.querySelectorAll('.product-image-gallery').forEach(gallery => {
                const images = gallery.querySelectorAll('img');
                const indicators = gallery.querySelectorAll('.image-indicator');
                let currentIndex = 0;
                let rotateInterval;
                
                if (images.length > 1) {
                    gallery.addEventListener('mouseenter', function() {
                        rotateInterval = setInterval(() => {
                            currentIndex = (currentIndex + 1) % images.length;
                            
                            images.forEach(img => img.style.display = 'none');
                            images[currentIndex].style.display = 'block';
                            
                            indicators.forEach(ind => ind.classList.remove('active'));
                            indicators[currentIndex].classList.add('active');
                        }, 1500);
                    });
                    
                    gallery.addEventListener('mouseleave', function() {
                        clearInterval(rotateInterval);
                        currentIndex = 0;
                        
                        images.forEach(img => img.style.display = 'none');
                        images[0].style.display = 'block';
                        
                        indicators.forEach(ind => ind.classList.remove('active'));
                        indicators[0].classList.add('active');
                    });
                }
            });
            
            // Quick view functionality
            document.querySelectorAll('.quick-view').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const productId = this.dataset.productId;
                    const productCard = this.closest('.product-card');
                    const productName = productCard.querySelector('h3').textContent;
                    
                    // Here you would typically open a modal with product details
                    alert(`Vista rápida de: ${productName}\nID: ${productId}\nEsta funcionalidad se implementará próximamente.`);
                });
            });
            
            // Favorite functionality
            document.querySelectorAll('.favorite-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const productId = this.dataset.productId;
                    
                    this.classList.toggle('active');
                    if (this.classList.contains('active')) {
                        this.style.backgroundColor = 'var(--primary-color)';
                        this.style.color = 'white';
                        // Here you would save to favorites
                        console.log(`Added product ${productId} to favorites`);
                    } else {
                        this.style.backgroundColor = 'white';
                        this.style.color = '#555';
                        // Here you would remove from favorites
                        console.log(`Removed product ${productId} from favorites`);
                    }
                });
            });
            
            // Compare functionality
            document.querySelectorAll('.compare-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const productId = this.dataset.productId;
                    
                    this.classList.toggle('active');
                    if (this.classList.contains('active')) {
                        this.style.backgroundColor = 'var(--accent-color)';
                        this.style.color = 'white';
                        console.log(`Added product ${productId} to comparison`);
                    } else {
                        this.style.backgroundColor = 'white';
                        this.style.color = '#555';
                        console.log(`Removed product ${productId} from comparison`);
                    }
                });
            });
            
            // Auto-submit filters on change
            document.querySelectorAll('input[name="categoria"], input[name="material"]').forEach(input => {
                input.addEventListener('change', function() {
                    document.getElementById('filter-form').submit();
                });
            });
            
            // Search functionality
            const searchInput = document.querySelector('input[name="buscar"]');
            if (searchInput) {
                let searchTimeout;
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        if (this.value.length >= 3 || this.value.length === 0) {
                            document.getElementById('filter-form').submit();
                        }
                    }, 500);
                });
            }
        });
    </script>
</body>
</html>
