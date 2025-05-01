<?php
// Incluir archivos necesarios
require_once 'includes/db_connection.php';
require_once 'includes/helpers.php';

// Consulta para obtener productos destacados
$featured_query = "SELECT p.*, GROUP_CONCAT(pi.imagen_url ORDER BY pi.orden ASC SEPARATOR '|') AS imagenes
                 FROM Productos p
                 LEFT JOIN ProductoImagenes pi ON p.id_producto = pi.id_producto
                 WHERE p.destacado = 1
                 GROUP BY p.id_producto
                 LIMIT 3";

// Ejecutar la consulta
$featured_result = $conn->query($featured_query);

// Cerrar la conexión
$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panda Joyeros - Joyería Elegante</title>
    <link rel="icon" href="img/favicon.png" type="image/png">
    <link rel="stylesheet" href="css/styles.css">
    <script src="js/main.js" defer></script>
</head>
<body>
    <header>
        <div class="container">
            <a href="index.php" class="logo">
                <img src="img/logo.png" alt="Panda Joyeros" width="80">
            </a>
            <nav>
                <ul>
                    <li><a href="index.php" class="active">Inicio</a></li>
                    <li><a href="pages/tienda.php">Tienda</a></li>
                    <li><a href="pages/quienes-somos.html">Quienes somos</a></li>
                    <li><a href="pages/contacto.html">Contacto</a></li>
                    <li><a href="admin/login.php">Administración</a></li>
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

    <main>
        <section class="hero">
            <div class="container">
                <h1>Elegancia y calidad</h1>
                <p>Descubre nuestra exclusiva colección de joyas que combina artesanía tradicional con diseños contemporáneos</p>
                <a href="pages/tienda.php" class="cta-button">Explorar Tienda</a>
            </div>
        </section>

        <section class="featured-products">
            <div class="container">
                <h2>Productos Destacados</h2>
                <div class="product-grid">
                    <?php
                    if (isset($featured_result) && $featured_result && $featured_result->num_rows > 0) {
                        while($row = $featured_result->fetch_assoc()) {
                            $imagenes = isset($row['imagenes']) && !empty($row['imagenes']) ? explode('|', $row['imagenes']) : [];
                            $nombre = htmlspecialchars($row['nombre'] ?? 'Producto sin nombre', ENT_QUOTES, 'UTF-8');
                            $descripcion = htmlspecialchars($row['descripcion'] ?? 'Sin descripción', ENT_QUOTES, 'UTF-8');
                            $precio = function_exists('safe_price') ? safe_price($row['precio']) : number_format($row['precio'] ?? 0, 2, ',', '.') . ' €';
                            $id = (int)($row['id_producto'] ?? 0);
                            $stock = isset($row['stock']) ? (int)$row['stock'] : 0;
                    ?>
                    <article class="product-card">
                        <?php if ($stock > 0 && $stock <= 5): ?>
                            <div class="product-badge">¡Últimas unidades!</div>
                        <?php endif; ?>
                        
                        <div class="product-images">
                            <?php if (!empty($imagenes)): ?>
                                <?php foreach ($imagenes as $index => $imagen): ?>
                                    <img src="<?php echo htmlspecialchars($imagen, ENT_QUOTES, 'UTF-8') ?: 'img/no-image.png'; ?>" 
                                         alt="<?php echo $nombre; ?> - Imagen <?php echo $index + 1; ?>"
                                         class="<?php echo $index === 0 ? 'active' : ''; ?>"
                                         loading="lazy">
                                <?php endforeach; ?>
                                
                                <?php if (count($imagenes) > 1): ?>
                                <div class="image-navigation" aria-hidden="true">
                                    <?php for ($i = 0; $i < count($imagenes); $i++): ?>
                                        <span class="image-dot <?php echo $i === 0 ? 'active' : ''; ?>" 
                                              data-index="<?php echo $i; ?>" 
                                              onclick="changeProductImage(this, <?php echo $id; ?>)"></span>
                                    <?php endfor; ?>
                                </div>
                                <?php endif; ?>
                                
                            <?php else: ?>
                                <img src="img/no-image.png" alt="Imagen no disponible para <?php echo $nombre; ?>" class="active">
                            <?php endif; ?>
                            
                            <div class="product-controls">
                                <button class="product-control-btn" aria-label="Añadir a favoritos" title="Añadir a favoritos">
                                    <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none">
                                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                                    </svg>
                                </button>
                                <button class="product-control-btn" aria-label="Vista rápida" title="Vista rápida">
                                    <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <line x1="12" y1="8" x2="12" y2="16"></line>
                                        <line x1="8" y1="12" x2="16" y2="12"></line>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        
                        <div class="product-card-content">
                            <h3><?php echo $nombre; ?></h3>
                            <p class="product-description"><?php echo $descripcion; ?></p>
                            <div class="product-footer">
                                <span class="price"><?php echo $precio; ?></span>
                                <a href="pages/descripcion.php?id=<?php echo $id; ?>" class="buy-button">
                                    Ver Detalles
                                    <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none">
                                        <line x1="5  stroke-width="2" fill="none">
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                        <polyline points="12 5 19 12 12 19"></polyline>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </article>
                    <?php
                        }
                    } else {
                        echo '<div class="no-products"><p>No hay productos destacados disponibles en este momento.</p>
                              <p>Vuelve pronto para descubrir nuestra nueva colección.</p></div>';
                    }
                    ?>
                </div>
            </div>
        </section>

        <section class="about-us">
            <div class="container">
                <div class="about-content">
                    <h2>Especialista en elaboración y Diseño de joyas</h2>
                    <p>Con más de 10 años de experiencia en la creación de joyas excepcionales, combinamos técnicas tradicionales con innovación moderna para crear piezas únicas que perduran en el tiempo.</p>
                    <p>Cada joya es cuidadosamente elaborada por nuestros maestros artesanos, utilizando solo los materiales más finos y piedras preciosas de la más alta calidad.</p>
                    <a href="pages/quienes-somos.html" class="cta-button secondary">Conoce Nuestra Historia</a>
                </div>
                <div class="about-image">
                    <img src="img/artesano.jpg" alt="Artesano trabajando en una joya">
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Sobre Nosotros</h3>
                    <ul>
                        <li><a href="pages/quienes-somos.html">Historia</a></li>
                        <li><a href="pages/quienes-somos.html#our-team">Artesanos</a></li>
                        <li><a href="pages/quienes-somos.html#our-values">Sostenibilidad</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Atención al Cliente</h3>
                    <ul>
                        <li><a href="pages/contacto.html">Contacto</a></li>
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
                <a href="admin/login.php" class="admin-link">Admin</a>
                <p>&copy; <?php echo date('Y'); ?> Panda joyeros. Todos los derechos reservados.</p>
            </div>
        </div>
    </footer>

    <!-- Script para la funcionalidad de las tarjetas de productos -->
    <script>
        // Función para cambiar la imagen activa en las tarjetas de productos
        function changeProductImage(dotElement, productId) {
            const index = parseInt(dotElement.getAttribute('data-index'));
            const productCard = dotElement.closest('.product-card');
            const images = productCard.querySelectorAll('.product-images img');
            const dots = productCard.querySelectorAll('.image-dot');
            
            // Desactivar todas las imágenes y puntos
            images.forEach(img => img.classList.remove('active'));
            dots.forEach(dot => dot.classList.remove('active'));
            
            // Activar la imagen y punto seleccionados
            if (images[index]) images[index].classList.add('active');
            if (dots[index]) dots[index].classList.add('active');
        }
        
        // Inicializar las tarjetas cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            // Añadir funcionalidad para cambiar imágenes automáticamente cada 3 segundos
            const productCards = document.querySelectorAll('.product-card');
            
            productCards.forEach(card => {
                const images = card.querySelectorAll('.product-images img');
                const dots = card.querySelectorAll('.image-dot');
                
                if (images.length > 1) {
                    let currentIndex = 0;
                    
                    // Cambiar imagen cada 3 segundos
                    setInterval(() => {
                        currentIndex = (currentIndex + 1) % images.length;
                        
                        // Desactivar todas las imágenes y puntos
                        images.forEach(img => img.classList.remove('active'));
                        dots.forEach(dot => dot.classList.remove('active'));
                        
                        // Activar la siguiente imagen y punto
                        images[currentIndex].classList.add('active');
                        if (dots[currentIndex]) dots[currentIndex].classList.add('active');
                    }, 3000);
                }
            });
        });
    </script>
</body>
</html>