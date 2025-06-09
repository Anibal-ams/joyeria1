// JavaScript para funcionalidad responsiva de la tienda
document.addEventListener("DOMContentLoaded", () => {
  // Toggle de filtros móvil
  const mobileFilterToggle = document.createElement("button")
  mobileFilterToggle.className = "mobile-filter-toggle"
  mobileFilterToggle.innerHTML = "🔍 Filtros"
  mobileFilterToggle.setAttribute("aria-label", "Mostrar/ocultar filtros")

  const shopSidebar = document.querySelector(".shop-sidebar")
  const productGrid = document.querySelector(".product-grid")

  if (shopSidebar && productGrid) {
    productGrid.parentNode.insertBefore(mobileFilterToggle, productGrid)

    mobileFilterToggle.addEventListener("click", function () {
      shopSidebar.classList.toggle("active")
      const isActive = shopSidebar.classList.contains("active")
      this.innerHTML = isActive ? "✕ Cerrar filtros" : "🔍 Filtros"
      this.setAttribute("aria-expanded", isActive)
    })
  }

  // Mejorar la funcionalidad del rango de precio
  const priceRange = document.getElementById("price-range")
  const priceOutput = document.querySelector('output[for="price-range"]')

  if (priceRange && priceOutput) {
    // Actualizar valor en tiempo real
    priceRange.addEventListener("input", function () {
      const value = Number.parseInt(this.value)
      const formattedValue = "$ " + value.toLocaleString("es-CO")
      priceOutput.textContent = formattedValue
    })

    // Mejorar accesibilidad
    priceRange.setAttribute("aria-describedby", "price-output")
    priceOutput.id = "price-output"
  }

  // Funcionalidad de galería de imágenes mejorada
  document.querySelectorAll(".image-indicator").forEach((indicator) => {
    indicator.addEventListener("click", function () {
      const imageIndex = Number.parseInt(this.dataset.imageIndex)
      const gallery = this.closest(".product-image-gallery")
      const images = gallery.querySelectorAll("img")
      const indicators = gallery.querySelectorAll(".image-indicator")

      // Ocultar todas las imágenes
      images.forEach((img) => (img.style.display = "none"))
      // Mostrar imagen seleccionada
      if (images[imageIndex]) {
        images[imageIndex].style.display = "block"
      }

      // Actualizar indicadores
      indicators.forEach((ind) => ind.classList.remove("active"))
      this.classList.add("active")
    })

    // Mejorar accesibilidad
    indicator.setAttribute("role", "button")
    indicator.setAttribute("tabindex", "0")
    indicator.addEventListener("keydown", function (e) {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault()
        this.click()
      }
    })
  })

  // Auto-rotación de imágenes en hover (solo en desktop)
  if (window.innerWidth > 768) {
    document.querySelectorAll(".product-image-gallery").forEach((gallery) => {
      const images = gallery.querySelectorAll("img")
      const indicators = gallery.querySelectorAll(".image-indicator")
      let currentIndex = 0
      let rotateInterval

      if (images.length > 1) {
        gallery.addEventListener("mouseenter", () => {
          rotateInterval = setInterval(() => {
            currentIndex = (currentIndex + 1) % images.length

            images.forEach((img) => (img.style.display = "none"))
            images[currentIndex].style.display = "block"

            indicators.forEach((ind) => ind.classList.remove("active"))
            if (indicators[currentIndex]) {
              indicators[currentIndex].classList.add("active")
            }
          }, 2000)
        })

        gallery.addEventListener("mouseleave", () => {
          clearInterval(rotateInterval)
          currentIndex = 0

          images.forEach((img) => (img.style.display = "none"))
          if (images[0]) images[0].style.display = "block"

          indicators.forEach((ind) => ind.classList.remove("active"))
          if (indicators[0]) indicators[0].classList.add("active")
        })
      }
    })
  }

  // Vista rápida mejorada
  document.querySelectorAll(".quick-view").forEach((button) => {
    button.addEventListener("click", function (e) {
      e.preventDefault()
      const productId = this.dataset.productId
      const productCard = this.closest(".product-card")
      const productName = productCard.querySelector("h3").textContent
      const productPrice = productCard.querySelector(".price").textContent
      const productImage = productCard.querySelector("img").src

      // Crear modal simple para vista rápida
      showQuickViewModal({
        id: productId,
        name: productName,
        price: productPrice,
        image: productImage,
      })
    })
  })

  // Funcionalidad de favoritos
  document.querySelectorAll(".favorite-btn").forEach((button) => {
    button.addEventListener("click", function (e) {
      e.preventDefault()
      const productId = this.dataset.productId

      this.classList.toggle("active")
      if (this.classList.contains("active")) {
        this.style.backgroundColor = "var(--primary-color)"
        this.style.color = "white"
        this.setAttribute("aria-label", "Quitar de favoritos")
        showToast("Producto añadido a favoritos")
      } else {
        this.style.backgroundColor = "white"
        this.style.color = "#555"
        this.setAttribute("aria-label", "Añadir a favoritos")
        showToast("Producto quitado de favoritos")
      }
    })
  })

  // Funcionalidad de comparación
  document.querySelectorAll(".compare-btn").forEach((button) => {
    button.addEventListener("click", function (e) {
      e.preventDefault()
      const productId = this.dataset.productId

      this.classList.toggle("active")
      if (this.classList.contains("active")) {
        this.style.backgroundColor = "var(--accent-color)"
        this.style.color = "white"
        this.setAttribute("aria-label", "Quitar de comparación")
        showToast("Producto añadido a comparación")
      } else {
        this.style.backgroundColor = "white"
        this.style.color = "#555"
        this.setAttribute("aria-label", "Añadir a comparación")
        showToast("Producto quitado de comparación")
      }
    })
  })

  // Auto-submit de filtros
  document.querySelectorAll('input[name="categoria"], input[name="material"]').forEach((input) => {
    input.addEventListener("change", () => {
      showLoadingSpinner()
      setTimeout(() => {
        document.getElementById("filter-form").submit()
      }, 300)
    })
  })

  // Búsqueda con debounce
  const searchInput = document.querySelector('input[name="buscar"]')
  if (searchInput) {
    let searchTimeout
    searchInput.addEventListener("input", function () {
      clearTimeout(searchTimeout)
      const searchTerm = this.value.trim()

      searchTimeout = setTimeout(() => {
        if (searchTerm.length >= 3 || searchTerm.length === 0) {
          showLoadingSpinner()
          document.getElementById("filter-form").submit()
        }
      }, 500)
    })
  }

  // Lazy loading de imágenes
  if ("IntersectionObserver" in window) {
    const imageObserver = new IntersectionObserver((entries, observer) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          const img = entry.target
          img.src = img.dataset.src || img.src
          img.classList.remove("lazy")
          observer.unobserve(img)
        }
      })
    })

    document.querySelectorAll('img[loading="lazy"]').forEach((img) => {
      imageObserver.observe(img)
    })
  }

  // Funciones auxiliares
  function showQuickViewModal(product) {
    // Crear modal simple
    const modal = document.createElement("div")
    modal.className = "quick-view-modal"
    modal.innerHTML = `
            <div class="modal-content">
                <button class="modal-close" aria-label="Cerrar">&times;</button>
                <div class="modal-body">
                    <img src="${product.image}" alt="${product.name}">
                    <div class="modal-info">
                        <h3>${product.name}</h3>
                        <p class="price">${product.price}</p>
                        <a href="descripcion.php?id=${product.id}" class="btn btn-primary">Ver detalles completos</a>
                    </div>
                </div>
            </div>
        `

    // Estilos del modal
    modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            animation: fadeIn 0.3s ease;
        `

    const modalContent = modal.querySelector(".modal-content")
    modalContent.style.cssText = `
            background: white;
            border-radius: 8px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
        `

    const modalBody = modal.querySelector(".modal-body")
    modalBody.style.cssText = `
            display: flex;
            gap: 1rem;
            padding: 2rem;
        `

    const modalClose = modal.querySelector(".modal-close")
    modalClose.style.cssText = `
            position: absolute;
            top: 10px;
            right: 15px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            z-index: 1001;
        `

    document.body.appendChild(modal)

    // Cerrar modal
    modalClose.addEventListener("click", () => {
      document.body.removeChild(modal)
    })

    modal.addEventListener("click", (e) => {
      if (e.target === modal) {
        document.body.removeChild(modal)
      }
    })
  }

  function showToast(message) {
    const toast = document.createElement("div")
    toast.textContent = message
    toast.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--primary-color);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            z-index: 1000;
            animation: slideInUp 0.3s ease;
        `

    document.body.appendChild(toast)

    setTimeout(() => {
      toast.style.animation = "slideOutDown 0.3s ease"
      setTimeout(() => {
        if (document.body.contains(toast)) {
          document.body.removeChild(toast)
        }
      }, 300)
    }, 3000)
  }

  function showLoadingSpinner() {
    const spinner = document.createElement("div")
    spinner.className = "loading-spinner"
    spinner.innerHTML = '<div class="spinner"></div>'
    spinner.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 999;
        `

    const spinnerElement = spinner.querySelector(".spinner")
    spinnerElement.style.cssText = `
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        `

    document.body.appendChild(spinner)

    // Remover después de 5 segundos como fallback
    setTimeout(() => {
      if (document.body.contains(spinner)) {
        document.body.removeChild(spinner)
      }
    }, 5000)
  }

  // Añadir animaciones CSS
  const style = document.createElement("style")
  style.textContent = `
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideInUp {
            from { transform: translateY(100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        @keyframes slideOutDown {
            from { transform: translateY(0); opacity: 1; }
            to { transform: translateY(100%); opacity: 0; }
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    `
  document.head.appendChild(style)
})

// Optimización de rendimiento
window.addEventListener(
  "resize",
  debounce(() => {
    // Reajustar layout si es necesario
    const sidebar = document.querySelector(".shop-sidebar")
    if (window.innerWidth > 768 && sidebar) {
      sidebar.classList.remove("active")
    }
  }, 250),
)

function debounce(func, wait) {
  let timeout
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout)
      func(...args)
    }
    clearTimeout(timeout)
    timeout = setTimeout(later, wait)
  }
}
