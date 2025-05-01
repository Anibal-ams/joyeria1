document.addEventListener('DOMContentLoaded', function() {
    const mainImage = document.getElementById('main-product-image');
    const thumbnails = document.querySelectorAll('.thumbnail-images img');

    thumbnails.forEach((thumbnail, index) => {
        thumbnail.addEventListener('click', function() {
            // Cambiar la imagen principal
            mainImage.src = this.src;
            mainImage.alt = this.alt;

            // Actualizar la clase activa
            thumbnails.forEach(thumb => thumb.classList.remove('active'));
            this.classList.add('active');
        });
    });

    // Funcionalidad para los botones de "Añadir al Carrito" y "Añadir a Favoritos"
    const addToCartButton = document.querySelector('.add-to-cart');
    const addToWishlistButton = document.querySelector('.add-to-wishlist');
    const quantityInput = document.getElementById('quantity');

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
