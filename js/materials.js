// Materials Modal JavaScript - FIXED VERSION

// Global variables
let currentEditingMaterial = null
let modalInitialized = false

// Initialize when DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
  console.log("DOM loaded, initializing materials page...")
  initializeMaterialsPage()
})

function initializeMaterialsPage() {
  // Initialize modal first
  initializeModal()

  // Initialize form validation
  initializeFormValidation()

  // Initialize search functionality
  initializeSearch()

  // Auto-hide alerts after 5 seconds
  autoHideAlerts()

  // Initialize tooltips
  initializeTooltips()

  console.log("Materials page initialized successfully")
}

// Initialize modal functionality
function initializeModal() {
  const modal = document.getElementById("materialModal")
  if (!modal) {
    console.error("Modal element not found!")
    return
  }

  console.log("Initializing modal...")

  // Ensure modal is properly hidden on page load
  modal.classList.remove("show")
  modal.style.display = "flex" // Ensure it's always flex

  // Add event listeners for modal close buttons
  const closeButtons = modal.querySelectorAll(".modal-close")
  closeButtons.forEach((button) => {
    button.addEventListener("click", (e) => {
      e.preventDefault()
      e.stopPropagation()
      hideMaterialForm()
    })
  })

  // Close modal when clicking outside
  modal.addEventListener("click", (e) => {
    if (e.target === modal) {
      hideMaterialForm()
    }
  })

  // Close modal with Escape key
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && modal.classList.contains("show")) {
      hideMaterialForm()
    }
  })

  modalInitialized = true
  console.log("Modal initialized successfully")
}

// Show material form modal
function showMaterialForm(material = null) {
  console.log("Showing material form...", material)

  const modal = document.getElementById("materialModal")
  const modalTitle = document.getElementById("modalTitle")
  const form = modal.querySelector(".material-form")
  const materialIdField = document.getElementById("material_id")

  if (!modal) {
    console.error("Modal not found!")
    return
  }

  if (!modalInitialized) {
    console.log("Modal not initialized, initializing now...")
    initializeModal()
  }

  // Clear any existing errors
  clearAllErrors()

  if (material) {
    console.log("Editing material:", material)
    modalTitle.textContent = "Editar Material"
    populateForm(material)
    currentEditingMaterial = material
  } else {
    console.log("Creating new material")
    modalTitle.textContent = "Nuevo Material"
    form.reset()
    if (materialIdField) materialIdField.value = ""
    currentEditingMaterial = null
  }

  // Show modal with animation
  modal.classList.add("show")
  document.body.style.overflow = "hidden"

  // Focus on first input after animation
  setTimeout(() => {
    const firstInput = form.querySelector('input[type="text"]')
    if (firstInput) {
      firstInput.focus()
      firstInput.select()
    }
  }, 300)

  console.log("Modal shown successfully")
}

// Hide material form modal
function hideMaterialForm() {
  console.log("Hiding material form...")

  const modal = document.getElementById("materialModal")
  if (!modal) {
    console.error("Modal not found!")
    return
  }

  modal.classList.remove("show")
  document.body.style.overflow = "auto"
  currentEditingMaterial = null

  // Clear any validation errors
  clearAllErrors()

  console.log("Modal hidden successfully")
}

// Clear all form errors
function clearAllErrors() {
  const modal = document.getElementById("materialModal")
  if (!modal) return

  const errorElements = modal.querySelectorAll(".field-error")
  errorElements.forEach((el) => el.remove())

  const errorFields = modal.querySelectorAll(".error")
  errorFields.forEach((field) => field.classList.remove("error"))
}

// Edit material
function editMaterial(material) {
  console.log("Edit material called:", material)
  showMaterialForm(material)
}

// View material products
function viewMaterialProducts(materialId) {
  console.log("Viewing products for material:", materialId)
  window.location.href = `products.php?material=${materialId}`
}

// Delete material
function deleteMaterial(materialId) {
  console.log("Delete material called:", materialId)

  if (confirm("¿Estás seguro de que quieres eliminar este material? Esta acción no se puede deshacer.")) {
    // Show loading state
    const deleteBtn = event.target.closest(".btn-delete")
    if (deleteBtn) {
      const originalContent = deleteBtn.innerHTML
      deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'
      deleteBtn.disabled = true
    }

    // Redirect to delete
    window.location.href = `materials.php?delete=${materialId}`
  }
}

// Populate form with material data
function populateForm(material) {
  console.log("Populating form with:", material)

  const fields = {
    material_id: material.id_material || "",
    nombre: material.nombre || "",
    descripcion: material.descripcion || "",
  }

  Object.keys(fields).forEach((fieldName) => {
    const field = document.getElementById(fieldName)
    if (field) {
      field.value = fields[fieldName]
    } else {
      console.warn(`Field ${fieldName} not found`)
    }
  })
}

// Initialize form validation
function initializeFormValidation() {
  const form = document.querySelector(".material-form")
  if (!form) {
    console.warn("Material form not found")
    return
  }

  console.log("Initializing form validation...")

  form.addEventListener("submit", (e) => {
    console.log("Form submitted")

    if (!validateForm()) {
      console.log("Form validation failed")
      e.preventDefault()
      return false
    }

    // Show loading state
    const submitBtn = form.querySelector('button[type="submit"]')
    if (submitBtn) {
      const originalContent = submitBtn.innerHTML
      submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...'
      submitBtn.disabled = true

      // Re-enable after 10 seconds as fallback
      setTimeout(() => {
        submitBtn.innerHTML = originalContent
        submitBtn.disabled = false
      }, 10000)
    }
  })

  // Real-time validation
  const requiredFields = form.querySelectorAll("[required]")
  requiredFields.forEach((field) => {
    field.addEventListener("blur", validateField)
    field.addEventListener("input", clearFieldError)
  })

  console.log("Form validation initialized")
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
  if (field.name === "nombre") {
    if (value.length < 2) {
      showFieldError(field, "El nombre debe tener al menos 2 caracteres")
      return false
    }
    if (value.length > 100) {
      showFieldError(field, "El nombre no puede exceder 100 caracteres")
      return false
    }
  }

  if (field.name === "descripcion" && value.length > 1000) {
    showFieldError(field, "La descripción no puede exceder 1000 caracteres")
    return false
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
  const form = document.querySelector(".material-form")
  const requiredFields = form.querySelectorAll("[required]")
  let isValid = true

  requiredFields.forEach((field) => {
    if (!validateField({ target: field })) {
      isValid = false
    }
  })

  return isValid
}

// Initialize search functionality
function initializeSearch() {
  const searchInput = document.querySelector('input[name="search"]')

  if (searchInput) {
    let searchTimeout
    searchInput.addEventListener("input", function () {
      clearTimeout(searchTimeout)
      searchTimeout = setTimeout(() => {
        if (this.value.length >= 2 || this.value.length === 0) {
          // Could implement live search here
        }
      }, 500)
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

// Make functions globally available
window.showMaterialForm = showMaterialForm
window.hideMaterialForm = hideMaterialForm
window.editMaterial = editMaterial
window.viewMaterialProducts = viewMaterialProducts
window.deleteMaterial = deleteMaterial

console.log("Materials modal script loaded successfully")
