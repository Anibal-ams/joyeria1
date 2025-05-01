<?php
require_once '../includes/db_connection.php';
require_once '../includes/helpers.php';

// Fetch all products with their main image
$products_query = "SELECT p.*, c.nombre as categoria_nombre, pi.imagen_url 
                  FROM Productos p
                  LEFT JOIN ProductoImagenes pi ON p.id_producto = pi.id_producto AND pi.orden = 1
                  LEFT JOIN Categorias c ON p.id_categoria = c.id_categoria";
$products_result = $conn->query($products_query);

// Fetch categories
$categories_query = "SELECT * FROM Categorias";
$categories_result = $conn->query($categories_query);

// Fetch materials
$materials_query = "SELECT * FROM Materiales";
$materials_result = $conn->query($materials_query);

// Close the connection
$conn->close();

// Function to get the correct image path
function get_image_path($image_url) {
   if (empty($image_url)) {
       return '../img/no-image.png';
   }
   // Check if the image_url starts with 'http' or 'https'
   if (strpos($image_url, 'http') === 0) {
       return $image_url;
   }
   // If it's a relative path, prepend the correct directory
   return '../' . ltrim($image_url, '/');
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
                   <li><a href="quienes-somos.html">Sobre Nosotros</a></li>
                   <li><a href="contacto.html">Contacto</a></li>
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
                           <div class="filter-group">
                               <h3>Categoría</h3>
                               <ul>
                                   <?php while ($category = $categories_result->fetch_assoc()): ?>
                                       <li>
                                           <label>
                                               <input type="checkbox" name="category" value="<?php echo $category['id_categoria']; ?>">
                                               <?php echo safe_output($category['nombre']); ?>
                                           </label>
                                       </li>
                                   <?php endwhile; ?>
                               </ul>
                           </div>
                           <div class="filter-group">
                               <h3>Material</h3>
                               <ul>
                                   <?php while ($material = $materials_result->fetch_assoc()): ?>
                                       <li>
                                           <label>
                                               <input type="checkbox" name="material" value="<?php echo $material['id_material']; ?>">
                                               <?php echo safe_output($material['nombre']); ?>
                                           </label>
                                       </li>
                                   <?php endwhile; ?>
                               </ul>
                           </div>
                           <div class="filter-group">
                               <h3>Precio</h3>
                               <div class="price-range">
                                   <input type="range" id="price-range" min="0" max="5000" step="100" value="2500">
                                   <output for="price-range">€2500</output>
                               </div>
                           </div>
                       </div>
                   </aside>
                   <div class="product-grid">
                       <?php
                       if ($products_result && $products_result->num_rows > 0) {
                           while($product = $products_result->fetch_assoc()) {
                               $image_path = get_image_path($product['imagen_url']);
                               $destacado = isset($product['destacado']) && $product['destacado'] == 1;
                               $categoria = isset($product['categoria_nombre']) ? $product['categoria_nombre'] : '';
                               // Generar una valoración aleatoria para demostración
                               $rating = mt_rand(35, 50) / 10;
                               $rating_count = mt_rand(5, 50);
                       ?>
                       <div class="product-card">
                           <?php if ($destacado): ?>
                               <div class="product-badge">Destacado</div>
                           <?php endif; ?>
                           
                           <div class="floating-icons">
                               <button class="icon-button" aria-label="Añadir a favoritos" title="Añadir a favoritos">
                                   <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none">
                                       <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                                   </svg>
                               </button>
                               <button class="icon-button" aria-label="Comparar" title="Comparar">
                                   <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none">
                                       <polyline points="17 1 21 5 17 9"></polyline>
                                       <path d="M3 11V9a4 4 0 0 1 4-4h14"></path>
                                       <polyline points="7 23 3 19 7 15"></polyline>
                                       <path d="M21 13v2a4 4 0 0 1-4 4H3"></path>
                                   </svg>
                               </button>
                           </div>
                           
                           <div class="product-image-container">
                               <img src="<?php echo safe_output($image_path); ?>" alt="<?php echo safe_output($product['nombre'], 'Producto'); ?>" loading="lazy">
                           </div>
                           
                           <div class="product-content">
                               <?php if (!empty($categoria)): ?>
                                   <div class="product-category"><?php echo safe_output($categoria); ?></div>
                               <?php endif; ?>
                               
                               <h3><?php echo safe_output($product['nombre'], 'Producto sin nombre'); ?></h3>
                               
                               <div class="product-rating">
                                   <div class="stars"><?php echo generate_stars($rating); ?></div>
                                   <span class="rating-count">(<?php echo $rating_count; ?>)</span>
                               </div>
                               
                               <p class="price"><?php echo safe_price($product['precio']); ?></p>
                               
                               <div class="product-actions">
                                   <a href="descripcion.php?id=<?php echo $product['id_producto']; ?>" class="view-details">Ver detalles</a>
                                   <button class="quick-view" aria-label="Vista rápida" title="Vista rápida">
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
                           echo "<div class='no-products'><p>No hay productos disponibles en este momento.</p><p>Vuelve pronto para descubrir nuestra nueva colección.</p></div>";
                       }
                       ?>
                   </div>
               </div>
               <div class="pagination">
                   <button class="prev" disabled>Anterior</button>
                   <span class="current-page">Página 1 de 3</span>
                   <button class="next">Siguiente</button>
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

   <script src="../js/main.js"></script>
   <script>
       // Script para actualizar el valor del rango de precio
       document.getElementById('price-range').addEventListener('input', function() {
           document.querySelector('output[for="price-range"]').textContent = '€' + this.value;
       });
       
       // Script para la vista rápida (simulado)
       document.querySelectorAll('.quick-view').forEach(button => {
           button.addEventListener('click', function(e) {
               e.preventDefault();
               const productCard = this.closest('.product-card');
               const productName = productCard.querySelector('h3').textContent;
               alert('Vista rápida de: ' + productName + '\nEsta funcionalidad se implementará próximamente.');
           });
       });
       
       // Script para los botones de favoritos
       document.querySelectorAll('.icon-button').forEach(button => {
           button.addEventListener('click', function(e) {
               e.preventDefault();
               if (this.getAttribute('aria-label') === 'Añadir a favoritos') {
                   this.classList.toggle('active');
                   if (this.classList.contains('active')) {
                       this.style.backgroundColor = 'var(--primary-color)';
                       this.style.color = 'white';
                   } else {
                       this.style.backgroundColor = 'white';
                       this.style.color = '#555';
                   }
               }
           });
       });
   </script>
</body>
</html>