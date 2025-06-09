// Users JavaScript functionality

// Global variables
let currentEditingUser = null

// Initialize when DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
  initializeUsersPage()
})

function initializeUsersPage() {
  // Initialize form validation
  initializeFormValidation()

  // Initialize search functionality
  initializeSearch()

  // Auto-hide alerts after 5 seconds
  autoHideAlerts()

  // Initialize tooltips
  initializeTooltips()

  // Add user role detection
  detectUserRoles()
}

// Show user form modal
function showUserForm(user = null) {
  const modal = document.getElementById("userModal")
  const modalTitle = document.getElementById("modalTitle")
  const form = modal.querySelector(".user-form")
  const passwordHelp = document.getElementById("password-help")
  const passwordField = document.getElementById("password")

  if (user) {
    modalTitle.textContent = "Editar Usuario"
    populateForm(user)
    currentEditingUser = user
    passwordHelp.style.display = "block"
    passwordField.removeAttribute("required")
  } else {
    modalTitle.textContent = "Nuevo Usuario"
    form.reset()
    document.getElementById("activo").checked = true
    currentEditingUser = null
    passwordHelp.style.display = "none"
    passwordField.setAttribute("required", "required")
  }

  modal.classList.add("show")
  document.body.style.overflow = "hidden"

  // Focus on first input
  setTimeout(() => {
    const firstInput = form.querySelector('input[type="text"]')
    if (firstInput) firstInput.focus()
  }, 300)
}

// Hide user form modal
function hideUserForm() {
  const modal = document.getElementById("userModal")
  modal.classList.remove("show")
  document.body.style.overflow = "auto"
  currentEditingUser = null
}

// Edit user
function editUser(user) {
  showUserForm(user)
}

// Toggle user status
function toggleUserStatus(userId, currentStatus) {
  const action = currentStatus ? "desactivar" : "activar"
  if (confirm(`¿Estás seguro de que quieres ${action} este usuario?`)) {
    // Show loading state
    const toggleBtn = event.target.closest(".btn-toggle")
    const originalContent = toggleBtn.innerHTML
    toggleBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'
    toggleBtn.disabled = true

    // Redirect to toggle status
    window.location.href = `manage_users.php?toggle_status=${userId}`
  }
}

// Delete user
function deleteUser(userId, username) {
  if (confirm(`¿Estás seguro de que quieres eliminar al usuario "${username}"? Esta acción no se puede deshacer.`)) {
    // Show loading state
    const deleteBtn = event.target.closest(".btn-delete")
    const originalContent = deleteBtn.innerHTML
    deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'
    deleteBtn.disabled = true

    // Redirect to delete
    window.location.href = `manage_users.php?delete=${userId}`
  }
}

// Populate form with user data
function populateForm(user) {
  document.getElementById("user_id").value = user.id_usuario || ""
  document.getElementById("username").value = user.username || ""
  document.getElementById("email").value = user.email || ""
  document.getElementById("nombre_completo").value = user.nombre_completo || ""
  document.getElementById("telefono").value = user.telefono || ""
  document.getElementById("direccion").value = user.direccion || ""
  document.getElementById("role").value = user.role || "user"
  document.getElementById("activo").checked = user.activo == 1

  // Clear password fields when editing
  document.getElementById("password").value = ""
  document.getElementById("confirm_password").value = ""
}

// Initialize form validation
function initializeFormValidation() {
  const form = document.querySelector(".user-form")

  form.addEventListener("submit", (e) => {
    if (!validateForm()) {
      e.preventDefault()
      return false
    }

    // Show loading state
    const submitBtn = form.querySelector('button[type="submit"]')
    const originalContent = submitBtn.innerHTML
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...'
    submitBtn.disabled = true

    // Re-enable after 10 seconds as fallback
    setTimeout(() => {
      submitBtn.innerHTML = originalContent
      submitBtn.disabled = false
    }, 10000)
  })

  // Real-time validation
  const requiredFields = form.querySelectorAll("[required]")
  requiredFields.forEach((field) => {
    field.addEventListener("blur", validateField)
    field.addEventListener("input", clearFieldError)
  })

  // Password confirmation validation
  const passwordField = document.getElementById("password")
  const confirmPasswordField = document.getElementById("confirm_password")

  confirmPasswordField.addEventListener("input", function () {
    if (passwordField.value && this.value && passwordField.value !== this.value) {
      showFieldError(this, "Las contraseñas no coinciden")
    } else {
      clearFieldError({ target: this })
    }
  })

  // Username validation (no spaces, special chars)
  const usernameField = document.getElementById("username")
  usernameField.addEventListener("input", function () {
    this.value = this.value.toLowerCase().replace(/[^a-z0-9_]/g, "")
  })

  // Phone formatting
  const phoneField = document.getElementById("telefono")
  phoneField.addEventListener("input", function () {
    // Remove non-numeric characters except + and spaces
    this.value = this.value.replace(/[^\d\s+\-$$$$]/g, "")
  })
}

// Validate individual field
function validateField(e) {
  const field = e.target
  const value = field.value.trim()

  // Remove existing error
  clearFieldError(e)

  if (field.hasAttribute("required") && !value) {
    showFieldError(field, "Este campo es obligatorio")
    return false
  }

  // Specific validations
  switch (field.name) {
    case "username":
      if (value.length < 3) {
        showFieldError(field, "El nombre de usuario debe tener al menos 3 caracteres")
        return false
      }
      if (value.length > 50) {
        showFieldError(field, "El nombre de usuario no puede exceder 50 caracteres")
        return false
      }
      if (!/^[a-z0-9_]+$/.test(value)) {
        showFieldError(field, "Solo se permiten letras minúsculas, números y guiones bajos")
        return false
      }
      break

    case "email":
      if (value && !isValidEmail(value)) {
        showFieldError(field, "Ingresa un email válido")
        return false
      }
      break

    case "nombre_completo":
      if (value.length < 2) {
        showFieldError(field, "El nombre debe tener al menos 2 caracteres")
        return false
      }
      if (value.length > 100) {
        showFieldError(field, "El nombre no puede exceder 100 caracteres")
        return false
      }
      break

    case "password":
      if (value && value.length < 6) {
        showFieldError(field, "La contraseña debe tener al menos 6 caracteres")
        return false
      }
      break

    case "confirm_password":
      const passwordField = document.getElementById("password")
      if (passwordField.value && value && passwordField.value !== value) {
        showFieldError(field, "Las contraseñas no coinciden")
        return false
      }
      break

    case "telefono":
      if (value && value.length < 7) {
        showFieldError(field, "Ingresa un número de teléfono válido")
        return false
      }
      break
  }

  return true
}

// Clear field error
function clearFieldError(e) {
  const field = e.target
  const errorElement = field.parentNode.querySelector(".field-error")
  if (errorElement) {
    errorElement.remove()
  }
  field.classList.remove("error")
}

// Show field error
function showFieldError(field, message) {
  field.classList.add("error")

  const errorElement = document.createElement("div")
  errorElement.className = "field-error"
  errorElement.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${message}`

  field.parentNode.appendChild(errorElement)
}

// Validate entire form
function validateForm() {
  const form = document.querySelector(".user-form")
  const requiredFields = form.querySelectorAll("[required]")
  let isValid = true

  requiredFields.forEach((field) => {
    if (!validateField({ target: field })) {
      isValid = false
    }
  })

  // Additional password validation
  const passwordField = document.getElementById("password")
  const confirmPasswordField = document.getElementById("confirm_password")

  if (passwordField.value && !validateField({ target: confirmPasswordField })) {
    isValid = false
  }

  return isValid
}

// Initialize search functionality
function initializeSearch() {
  const searchInput = document.querySelector('input[name="search"]')
  const roleSelect = document.querySelector('select[name="role_filter"]')
  const statusSelect = document.querySelector('select[name="status_filter"]')

  if (searchInput) {
    // Debounced search
    let searchTimeout
    searchInput.addEventListener("input", function () {
      clearTimeout(searchTimeout)
      searchTimeout = setTimeout(() => {
        if (this.value.length >= 2 || this.value.length === 0) {
          // Auto-submit form for live search
          // this.form.submit();
        }
      }, 500)
    })
  }

  if (roleSelect) {
    roleSelect.addEventListener("change", () => {
      // Auto-submit form when role filter changes
      // this.form.submit();
    })
  }

  if (statusSelect) {
    statusSelect.addEventListener("change", () => {
      // Auto-submit form when status filter changes
      // this.form.submit();
    })
  }
}

// Auto-hide alerts
function autoHideAlerts() {
  const alerts = document.querySelectorAll(".alert")
  alerts.forEach((alert) => {
    setTimeout(() => {
      alert.style.opacity = "0"
      alert.style.transform = "translateY(-10px)"
      setTimeout(() => {
        alert.remove()
      }, 300)
    }, 5000)
  })
}

// Initialize tooltips
function initializeTooltips() {
  document.querySelectorAll("[title]").forEach((element) => {
    element.addEventListener("mouseenter", function (e) {
      const tooltip = document.createElement("div")
      tooltip.className = "tooltip"
      tooltip.textContent = this.getAttribute("title")
      document.body.appendChild(tooltip)

      const rect = this.getBoundingClientRect()
      tooltip.style.left = rect.left + rect.width / 2 - tooltip.offsetWidth / 2 + "px"
      tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + "px"

      this.setAttribute("data-title", this.getAttribute("title"))
      this.removeAttribute("title")
    })

    element.addEventListener("mouseleave", function () {
      const tooltip = document.querySelector(".tooltip")
      if (tooltip) {
        tooltip.remove()
      }

      if (this.getAttribute("data-title")) {
        this.setAttribute("title", this.getAttribute("data-title"))
        this.removeAttribute("data-title")
      }
    })
  })
}

// Detect user roles and add data attributes
function detectUserRoles() {
  const userCards = document.querySelectorAll(".user-card")
  userCards.forEach((card) => {
    const roleBadge = card.querySelector(".role-badge")
    if (roleBadge) {
      const role = roleBadge.classList.contains("admin") ? "admin" : "user"
      card.setAttribute("data-role", role)
    }
  })
}

// Utility functions
function isValidEmail(email) {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  return emailRegex.test(email)
}

function formatDate(dateString) {
  const date = new Date(dateString)
  return date.toLocaleDateString("es-ES", {
    year: "numeric",
    month: "long",
    day: "numeric",
  })
}

// Close modal when clicking outside
document.addEventListener("click", (e) => {
  const modal = document.getElementById("userModal")
  if (e.target === modal) {
    hideUserForm()
  }
})

// Close modal with Escape key
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") {
    const modal = document.getElementById("userModal")
    if (modal.classList.contains("show")) {
      hideUserForm()
    }
  }
})

// Keyboard shortcuts
document.addEventListener("keydown", (e) => {
  // Ctrl/Cmd + N for new user
  if ((e.ctrlKey || e.metaKey) && e.key === "n") {
    e.preventDefault()
    showUserForm()
  }

  // Ctrl/Cmd + F for search focus
  if ((e.ctrlKey || e.metaKey) && e.key === "f") {
    e.preventDefault()
    const searchInput = document.querySelector('input[name="search"]')
    if (searchInput) {
      searchInput.focus()
      searchInput.select()
    }
  }
})

// Add CSS for form validation and animations
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
textarea.error,
select.error {
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

/* Enhanced hover effects */
.user-card:hover .detail-item {
    transform: translateX(4px);
}

.btn-action:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

/* Loading states */
.btn-action:disabled {
    pointer-events: none;
    opacity: 0.6;
}

/* Tooltip styles */
.tooltip {
    position: absolute;
    background: rgba(0, 0, 0, 0.9);
    color: white;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 12px;
    white-space: nowrap;
    z-index: 1000;
    pointer-events: none;
    opacity: 0;
    animation: fadeIn 0.3s ease-out forwards;
}

@keyframes fadeIn {
    to {
        opacity: 1;
    }
}
`

// Inject validation CSS
const style = document.createElement("style")
style.textContent = validationCSS
document.head.appendChild(style)

// Performance optimization: Intersection Observer for animations
const observer = new IntersectionObserver(
  (entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add("animate-in")
      }
    })
  },
  {
    threshold: 0.1,
  },
)

// Observe user cards for animation
document.querySelectorAll(".user-card").forEach((card) => {
  observer.observe(card)
})
