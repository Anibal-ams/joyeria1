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
                     FROM Productos p 
                     LEFT JOIN Categorias c ON p.id_categoria = c.id_categoria
                     LEFT JOIN Materiales m ON p.id_material = m.id_material
                     WHERE p.id_producto = ?";
   
   $stmt = $conn->prepare($product_query);
   $stmt->bind_param("i", $product_id);
   $stmt->execute();
   $result = $stmt->get_result();

   if ($result && $result->num_rows > 0) {
       $product = $result->fetch_assoc();

       // Consulta para obtener las imágenes del producto
       $images_query = "SELECT imagen_url FROM ProductoImagenes WHERE id_producto = ? ORDER BY orden";
       $images_stmt = $conn->prepare($images_query);
       $images_stmt->bind_param("i", $product_id);
       $images_stmt->execute();
       $images_result = $images_stmt->get_result();

       while ($image = $images_result->fetch_assoc()) {
           $images[] = $image['imagen_url'];
       }

       // Consulta para obtener productos relacionados
       $related_query = "SELECT p.*, pi.imagen_url 
                         FROM Productos p
                         LEFT JOIN ProductoImagenes pi ON p.id_producto = pi.id_producto AND pi.orden = 1
                         WHERE p.id_categoria = ? AND p.id_producto != ?
                         LIMIT 3";
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
       return '../img/placeholder.jpg';
   }
   return strpos($image_url, 'http') === 0 ? $image_url : '../' . ltrim($image_url, '/');
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
   <link rel="icon" href="https://scontent.fclo8-1.fna.fbcdn.net/v/t1.6435-9/51272927_388836028592340_5069720473542066176_n.jpg?_nc_cat=106&ccb=1-7&_nc_sid=6ee11a&_nc_eui2=AeFH0pLOXQi70Ej6pbSwQ1IDXZpjvWkc0kFdmmO9aRzSQdjb1mnpqEOlpICRpre2SnSIQ3gVdSR8Wf03e0w5ZX3D&_nc_ohc=n1SMTuspGckQ7kNvgEos2J1&_nc_oc=AdhDqxi_xaSd-SE5DqLpRXC4Pz7QldgW_C7Lk8RRjRdfmvL2yASC5cqDy09RdiTkM_k&_nc_zt=23&_nc_ht=scontent.fclo8-1.fna&_nc_gid=AkthrGTijLTJBM8dShHudrp&oh=00_AYCdHBv4OfonBGvWPSEjac7jNziKnuFN_En4S62BtGWcWw&oe=67CCF0A5" type="image/png">
   <link rel="stylesheet" href="../css/styles.css">
   <link rel="stylesheet" href="../css/description.css">
</head>
<body>
   <header>
       <div class="container">
           <a href="../index.php" class="logo">
              
           <img src="https://scontent.fclo8-1.fna.fbcdn.net/v/t1.6435-9/51272927_388836028592340_5069720473542066176_n.jpg?_nc_cat=106&ccb=1-7&_nc_sid=6ee11a&_nc_eui2=AeFH0pLOXQi70Ej6pbSwQ1IDXZpjvWkc0kFdmmO9aRzSQdjb1mnpqEOlpICRpre2SnSIQ3gVdSR8Wf03e0w5ZX3D&_nc_ohc=n1SMTuspGckQ7kNvgHIjfiw&_nc_oc=AdhLq4haH9Wx8HyNq-nRopYW_l-bien1G1kqam_RWJ8OSDFYSE2TYOpbXsW7MhUhBNQ&_nc_zt=23&_nc_ht=scontent.fclo8-1.fna&_nc_gid=A9MC3JRY02YXJaPT21BEtV3&oh=00_AYA6OArn_m1UADcNhw-rBQsBtMGYpjDyoc_lImmcbRrRDQ&oe=67CF2325" alt="" width="80px">
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
                       <img src="<?php echo get_image_path($images[0]); ?>" 
                            alt="<?php echo safe_output($product['nombre']); ?>" 
                            id="prod-main-image">
                   </div>
                   <div class="prod-thumbnail-images">
                       <?php foreach ($images as $index => $image): 
                           $thumb_path = get_image_path($image);
                       ?>
                           <img src="<?php echo safe_output($thumb_path); ?>" 
                                alt="<?php echo safe_output($product['nombre']) . ' - Imagen ' . ($index + 1); ?>" 
                                class="prod-thumbnail <?php echo $index === 0 ? 'active' : ''; ?>">
                       <?php endforeach; ?>
                   </div>
               </div>
               <div class="prod-info">
                   <h1 class="prod-title"><?php echo safe_output($product['nombre']); ?></h1>
                   <p class="prod-price">€<?php echo number_format($product['precio'], 2); ?></p>
                   <div class="prod-rating">
                       <span class="prod-stars">★★★★★</span>
                       <span class="prod-reviews">(25 reseñas)</span>
                   </div>
                   <p class="prod-description"><?php echo safe_output($product['descripcion']); ?></p>
                   <div class="prod-options">
                       <div class="prod-option">
                           <label for="prod-quantity">Cantidad:</label>
                           <input type="number" id="prod-quantity" name="quantity" min="1" value="1">
                       </div>
                   </div>
                   <button class="prod-add-to-cart">Añadir al Carrito</button>
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
                   <li><strong>Categoría:</strong> <?php echo safe_output($product['categoria_nombre']); ?></li>
                   <li><strong>Material:</strong> <?php echo safe_output($product['material_nombre']); ?></li>
                   <li><strong>Peso:</strong> <?php echo safe_output($product['peso']); ?> g</li>
                   <li><strong>Dimensiones:</strong> <?php echo safe_output($product['dimensiones']); ?></li>
               </ul>
           </div>
           <?php if (!empty($related_products)): ?>
           <div class="prod-related">
               <h2>Productos Relacionados</h2>
               <div class="prod-related-grid">
                   <?php foreach ($related_products as $related_product): 
                       $related_image_path = get_image_path($related_product['imagen_url']);
                   ?>
                   <div class="prod-related-card">
                       <img src="<?php echo safe_output($related_image_path); ?>" 
                            alt="<?php echo safe_output($related_product['nombre']); ?>"
                            onerror="this.onerror=null; this.src='../img/placeholder.jpg';">
                       <h3><?php echo safe_output($related_product['nombre']); ?></h3>
                       <p class="prod-related-price">€<?php echo number_format($related_product['precio'], 2); ?></p>
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
                       <li><a href="quienes-somos.php">Historia</a></li>
                       <li><a href="quienes-somos.php#our-team">Artesanos</a></li>
                       <li><a href="quienes-somos.php#our-values">Sostenibilidad</a></li>
                   </ul>
               </div>
               <div class="footer-section">
                   <h3>Atención al Cliente</h3>
                   <ul>
                       <li><a href="contacto.php">Contacto</a></li>
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
                   <h3>Busqueda de informacion</h3>
                   <p>Suscríbete para recibir las últimas novedades y ofertas exclusivas.</p>
                   <form class="newsletter-form">
                       <input type="email" placeholder="Tu email" required>
                       <button type="submit">Suscribir</button>
                   </form>
               </div>
           </div>
           <div class="footer-bottom">
               <p>&copy; 2024 Luxury Jewels. Todos los derechos reservados.</p>
           </div>
       </div>
   </footer>

   <script src="../js/main.js"></script>
   <script>
   document.addEventListener('DOMContentLoaded', function() {
       const mainImage = document.getElementById('prod-main-image');
       const thumbnails = document.querySelectorAll('.prod-thumbnail');

       thumbnails.forEach((thumbnail, index) => {
           thumbnail.addEventListener('click', function() {
               mainImage.src = this.src;
               mainImage.alt = this.alt;
               
               thumbnails.forEach(thumb => thumb.classList.remove('active'));
               this.classList.add('active');
           });
       });

       const addToCartButton = document.querySelector('.prod-add-to-cart');
       const addToWishlistButton = document.querySelector('.prod-add-to-wishlist');
       const quantityInput = document.getElementById('prod-quantity');

       if (addToCartButton) {
           addToCartButton.addEventListener('click', function() {
               const quantity = quantityInput ? quantityInput.value : 1;
               alert(`Se han añadido ${quantity} unidad(es) al carrito.`);
           });
       }

       if (addToWishlistButton) {
           addToWishlistButton.addEventListener('click', function() {
               alert('El producto ha sido añadido a tu lista de favoritos.');
           });
       }
   });
   </script>
</body>
</html>

