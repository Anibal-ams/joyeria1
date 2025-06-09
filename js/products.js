// Products JavaScript functionality

// Global variables
let currentEditingProduct = null;

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeProductsPage();
});

function initializeProductsPage() {
    // Initialize file upload previews
    initializeFileUploads();
    
    // Initialize form validation
    initializeFormValidation();
    
    // Initialize search functionality
    initializeSearch();
    
    // Auto-hide alerts after 5 seconds
    autoHideAlerts();
}

// Show product form modal
function showProductForm(product = null) {
    const modal = document.getElementById('productModal');
    const modalTitle = document.getElementById('modalTitle');
    const form = modal.querySelector('.product-form');
    
    if (product) {
        modalTitle.textContent = 'Editar Producto';
        populateForm(product);
        currentEditingProduct = product;
    } else {
        modalTitle.textContent = 'Nuevo Producto';
        form.reset();
        currentEditingProduct = null;
    }
    
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    
    // Focus on first input
    setTimeout(() => {
        const firstInput = form.querySelector('input[type="text"]');
        if (firstInput) firstInput.focus();
    }, 300);
}

// Hide product form modal
function hideProductForm() {
    const modal = document.getElementById('productModal');
    modal.classList.remove('show');
    document.body.style.overflow = 'auto';
    currentEditingProduct = null;
}

// Edit product
function editProduct(product) {
    showProductForm(product);
}

// View product details
function viewProduct(productId) {
    // Create a detailed view modal or redirect to a detailed page
    alert(`Ver detalles del producto ID: ${productId}`);
    // TODO: Implement product detail view
}

// Delete product
function deleteProduct(productId) {
    if (confirm('¿Estás seguro de que quieres eliminar este producto? Esta acción no se puede deshacer.')) {
        // Show loading state
        const deleteBtn = event.target.closest('.btn-delete');
        const originalContent = deleteBtn.innerHTML;
        deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        deleteBtn.disabled = true;
        
        // Redirect to delete
        window.location.href = `products.php?delete=${productId}`;
    }
}

// Populate form with product data
function populateForm(product) {
    document.getElementById('product_id').value = product.id_producto || '';
    document.getElementById('nombre').value = product.nombre || '';
    document.getElementById('id_categoria').value = product.id_categoria || '';
    document.getElementById('id_material').value = product.id_material || '';
    document.getElementById('descripcion').value = product.descripcion || '';
    document.getElementById('precio').value = product.precio || '';
    document.getElementById('stock').value = product.stock || '';
    document.getElementById('peso').value = product.peso || '';
    document.getElementById('dimensiones').value = product.dimensiones || '';
    document.getElementById('destacado').checked = product.destacado == 1;
}

// Initialize file upload functionality
function initializeFileUploads() {
    const fileInputs = document.querySelectorAll('input[type="file"]');
    
    fileInputs.forEach(input => {
        input.addEventListener('change', function(e) {
            const file = e.target.files[0];
            const label = e.target.closest('.upload-label');
            
            if (file) {
                // Validate file type
                if (!file.type.startsWith('image/')) {
                    alert('Por favor selecciona solo archivos de imagen.');
                    e.target.value = '';
                    return;
                }
                
                // Validate file size (max 5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('El archivo es demasiado grande. Máximo 5MB.');
                    e.target.value = '';
                    return;
                }
                
                // Show preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    label.style.backgroundImage = `url(${e.target.result})`;
                    label.style.backgroundSize = 'cover';
                    label.style.backgroundPosition = 'center';
                    label.querySelector('i').style.display = 'none';
                    label.querySelector('span').textContent = file.name;
                };
                reader.readAsDataURL(file);
            } else {
                // Reset preview
                label.style.backgroundImage = '';
                label.querySelector('i').style.display = 'block';
                label.querySelector('span').textContent = label.querySelector('span').getAttribute('data-original') || 'Seleccionar imagen';
            }
        });
    });
}

// Initialize form validation
function initializeFormValidation() {
    const form = document.querySelector('.product-form');
    
    form.addEventListener('submit', function(e) {
        if (!validateForm()) {
            e.preventDefault();
            return false;
        }
        
        // Show loading state
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalContent = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
        submitBtn.disabled = true;
        
        // Re-enable after 10 seconds as fallback
        setTimeout(() => {
            submitBtn.innerHTML = originalContent;
            submitBtn.disabled = false;
        }, 10000);
    });
    
    // Real-time validation
    const requiredFields = form.querySelectorAll('[required]');
    requiredFields.forEach(field => {
        field.addEventListener('blur', validateField);
        field.addEventListener('input', clearFieldError);
    });
}

// Validate individual field
function validateField(e) {
    const field = e.target;
    const value = field.value.trim();
    
    // Remove existing error
    clearFieldError(e);
    
    if (field.hasAttribute('required') && !value) {
        showFieldError(field, 'Este campo es obligatorio');
        return false;
    }
    
    // Specific validations
    switch (field.type) {
        case 'email':
            if (value && !isValidEmail(value)) {
                showFieldError(field, 'Ingresa un email válido');
                return false;
            }
            break;
        case 'number':
            if (value && isNaN(value)) {
                showFieldError(field, 'Ingresa un número válido');
                return false;
            }
            if (field.name === 'precio' && parseFloat(value) < 0) {
                showFieldError(field, 'El precio debe ser mayor a 0');
                return false;
            }
            if (field.name === 'stock' && parseInt(value) < 0) {
                showFieldError(field, 'El stock no puede ser negativo');
                return false;
            }
            break;
    }
    
    return true;
}

// Clear field error
function clearFieldError(e) {
    const field = e.target;
    const errorElement = field.parentNode.querySelector('.field-error');
    if (errorElement) {
        errorElement.remove();
    }
    field.classList.remove('error');
}

// Show field error
function showFieldError(field, message) {
    field.classList.add('error');
    
    const errorElement = document.createElement('div');
    errorElement.className = 'field-error';
    errorElement.textContent = message;
    
    field.parentNode.appendChild(errorElement);
}

// Validate entire form
function validateForm() {
    const form = document.querySelector('.product-form');
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!validateField({ target: field })) {
            isValid = false;
        }
    });
    
    return isValid;
}

// Initialize search functionality
function initializeSearch() {
    const searchInput = document.querySelector('input[name="search"]');
    const categorySelect = document.querySelector('select[name="category"]');
    
    if (searchInput) {
        // Debounced search
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                if (this.value.length >= 3 || this.value.length === 0) {
                    // Auto-submit form for live search
                    // this.form.submit();
                }
            }, 500);
        });
    }
    
    if (categorySelect) {
        categorySelect.addEventListener('change', function() {
            // Auto-submit form when category changes
            // this.form.submit();
        });
    }
}

// Auto-hide alerts
function autoHideAlerts() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => {
                alert.remove();
            }, 300);
        }, 5000);
    });
}

// Utility functions
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function formatPrice(price) {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0
    }).format(price);
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    const modal = document.getElementById('productModal');
    if (e.target === modal) {
        hideProductForm();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('productModal');
        if (modal.classList.contains('show')) {
            hideProductForm();
        }
    }
});

// Add CSS for form validation
const validationCSS = `
.field-error {
    color: var(--accent-red);
    font-size: 12px;
    margin-top: 4px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.field-error::before {
    content: '⚠';
    font-size: 10px;
}

input.error,
select.error,
textarea.error {
    border-color: var(--accent-red) !important;
    box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2) !important;
}

.upload-label:hover {
    transform: translateY(-2px);
}

.product-card {
    opacity: 0;
    animation: fadeInUp 0.6s ease-out forwards;
}

.product-card:nth-child(1) { animation-delay: 0.1s; }
.product-card:nth-child(2) { animation-delay: 0.2s; }
.product-card:nth-child(3) { animation-delay: 0.3s; }
.product-card:nth-child(4) { animation-delay: 0.4s; }
.product-card:nth-child(5) { animation-delay: 0.5s; }
.product-card:nth-child(6) { animation-delay: 0.6s; }

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
`;

// Inject validation CSS
const style = document.createElement('style');
style.textContent = validationCSS;
document.head.appendChild(style);

// Initialize tooltips for action buttons
document.querySelectorAll('[title]').forEach(element => {
    element.addEventListener('mouseenter', function(e) {
        const tooltip = document.createElement('div');
        tooltip.className = 'tooltip';
        tooltip.textContent = this.getAttribute('title');
        document.body.appendChild(tooltip);
        
        const rect = this.getBoundingClientRect();
        tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
        tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';
        
        this.setAttribute('data-title', this.getAttribute('title'));
        this.removeAttribute('title');
    });
    
    element.addEventListener('mouseleave', function() {
        const tooltip = document.querySelector('.tooltip');
        if (tooltip) {
            tooltip.remove();
        }
        
        if (this.getAttribute('data-title')) {
            this.setAttribute('title', this.getAttribute('data-title'));
            this.removeAttribute('data-title');
        }
    });
});