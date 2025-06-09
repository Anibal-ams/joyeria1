// Categories JavaScript functionality (without activo column)

// Global variables
let currentEditingCategory = null;

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeCategoriesPage();
});

function initializeCategoriesPage() {
    // Initialize form validation
    initializeFormValidation();
    
    // Initialize search functionality
    initializeSearch();
    
    // Auto-hide alerts after 5 seconds
    autoHideAlerts();
    
    // Initialize tooltips
    initializeTooltips();
}

// Show category form modal
function showCategoryForm(category = null) {
    const modal = document.getElementById('categoryModal');
    const modalTitle = document.getElementById('modalTitle');
    const form = modal.querySelector('.category-form');
    
    if (category) {
        modalTitle.textContent = 'Editar Categoría';
        populateForm(category);
        currentEditingCategory = category;
    } else {
        modalTitle.textContent = 'Nueva Categoría';
        form.reset();
        currentEditingCategory = null;
    }
    
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    
    // Focus on first input
    setTimeout(() => {
        const firstInput = form.querySelector('input[type="text"]');
        if (firstInput) firstInput.focus();
    }, 300);
}

// Hide category form modal
function hideCategoryForm() {
    const modal = document.getElementById('categoryModal');
    modal.classList.remove('show');
    document.body.style.overflow = 'auto';
    currentEditingCategory = null;
}

// Edit category
function editCategory(category) {
    showCategoryForm(category);
}

// View category products
function viewCategoryProducts(categoryId) {
    // Redirect to products page with category filter
    window.location.href = `products.php?category=${categoryId}`;
}

// Delete category
function deleteCategory(categoryId) {
    if (confirm('¿Estás seguro de que quieres eliminar esta categoría? Esta acción no se puede deshacer.')) {
        // Show loading state
        const deleteBtn = event.target.closest('.btn-delete');
        const originalContent = deleteBtn.innerHTML;
        deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        deleteBtn.disabled = true;
        
        // Redirect to delete
        window.location.href = `categories.php?delete=${categoryId}`;
    }
}

// Populate form with category data
function populateForm(category) {
    document.getElementById('category_id').value = category.id_categoria || '';
    document.getElementById('nombre').value = category.nombre || '';
    document.getElementById('descripcion').value = category.descripcion || '';
}

// Initialize form validation
function initializeFormValidation() {
    const form = document.querySelector('.category-form');
    
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
    if (field.name === 'nombre') {
        if (value.length < 2) {
            showFieldError(field, 'El nombre debe tener al menos 2 caracteres');
            return false;
        }
        if (value.length > 100) {
            showFieldError(field, 'El nombre no puede exceder 100 caracteres');
            return false;
        }
    }
    
    if (field.name === 'descripcion' && value.length > 500) {
        showFieldError(field, 'La descripción no puede exceder 500 caracteres');
        return false;
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
    errorElement.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${message}`;
    
    field.parentNode.appendChild(errorElement);
}

// Validate entire form
function validateForm() {
    const form = document.querySelector('.category-form');
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
    
    if (searchInput) {
        // Debounced search
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                if (this.value.length >= 2 || this.value.length === 0) {
                    // Auto-submit form for live search
                    // this.form.submit();
                }
            }, 500);
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

// Initialize tooltips
function initializeTooltips() {
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
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    const modal = document.getElementById('categoryModal');
    if (e.target === modal) {
        hideCategoryForm();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('categoryModal');
        if (modal.classList.contains('show')) {
            hideCategoryForm();
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
    animation: slideDown 0.3s ease-out;
}

input.error,
textarea.error {
    border-color: var(--accent-red) !important;
    box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2) !important;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-5px);
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