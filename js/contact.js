document.addEventListener('DOMContentLoaded', function() {
    const contactForm = document.getElementById('contact-form');

    contactForm.addEventListener('submit', function(e) {
        e.preventDefault();

        // Obtener los valores del formulario
        const name = document.getElementById('name').value;
        const email = document.getElementById('email').value;
        const subject = document.getElementById('subject').value;
        const message = document.getElementById('message').value;

        // Aquí normalmente enviarías los datos a un servidor
        // Por ahora, solo mostraremos un mensaje de éxito
        alert(`Gracias ${name}, hemos recibido tu mensaje. Te contactaremos pronto en ${email}.`);

        // Limpiar el formulario
        contactForm.reset();
    });
});

