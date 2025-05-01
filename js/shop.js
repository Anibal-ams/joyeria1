document.addEventListener('DOMContentLoaded', function() {
    const priceRange = document.getElementById('price-range');
    const priceOutput = document.querySelector('.price-range output');
    const addToCartButtons = document.querySelectorAll('.add-to-cart');
    const paginationButtons = document.querySelectorAll('.pagination button');

    // Actualizar el valor mostrado del rango de precios
    if (priceRange && priceOutput) {
        priceRange.addEventListener('input', function() {
            priceOutput.textContent = '€' + this.value;
        });
    }

    // Manejar clics en los botones de "Añadir al Carrito"
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function() {
            const productName = this.parentElement.querySelector('h3').textContent;
            alert(`"${productName}" ha sido añadido al carrito.`);
        });
    });

    // Manejar clics en los botones de paginación
    paginationButtons.forEach(button => {
        button.addEventListener('click', function() {
            if (this.classList.contains('prev')) {
                alert('Cargando página anterior...');
            } else if (this.classList.contains('next')) {
                alert('Cargando página siguiente...');
            }
        });
    });

    // Simular filtrado de productos (para demostración)
    const filterCheckboxes = document.querySelectorAll('.filter-group input[type="checkbox"]');
    filterCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            alert(`Filtro "${this.value}" ${this.checked ? 'aplicado' : 'removido'}. Actualizando productos...`);
        });
    });
});

