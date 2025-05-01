document.addEventListener('DOMContentLoaded', function() {
    // Existing code...

    // Image slider for product cards
    const productCards = document.querySelectorAll('.product-card');
    productCards.forEach(card => {
        const images = card.querySelectorAll('.product-images img');
        let currentImageIndex = 0;

        if (images.length > 1) {
            setInterval(() => {
                images[currentImageIndex].classList.remove('active');
                currentImageIndex = (currentImageIndex + 1) % images.length;
                images[currentImageIndex].classList.add('active');
            }, 3000); // Change image every 3 seconds
        }
    });

    // Existing code...
});

