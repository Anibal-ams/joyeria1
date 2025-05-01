document.addEventListener('DOMContentLoaded', function() {
    // Manejar el envío del formulario de newsletter
    const newsletterForm = document.querySelector('.newsletter-form');
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const email = this.querySelector('input[type="email"]').value;
            alert(`Gracias por suscribirte con el email: ${email}`);
            this.reset();
        });
    }

    // Manejar clics en los botones de compra
    const buyButtons = document.querySelectorAll('.buy-button');
    buyButtons.forEach(button => {
        button.addEventListener('click', function() {
            const productName = this.closest('.product-card').querySelector('h3').textContent;
            alert(`Has añadido ${productName} a tu carrito`);
        });
    });

    // Cambiar la opacidad del header al hacer scroll
    const header = document.querySelector('header');
    window.addEventListener('scroll', () => {
        if (window.scrollY > 100) {
            header.style.backgroundColor = 'rgba(255, 255, 255, 0.9)';
        } else {
            header.style.backgroundColor = 'var(--background-color)';
        }
    });
});

