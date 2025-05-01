document.addEventListener("DOMContentLoaded", () => {
    const priceRange = document.getElementById("price-range")
    const priceOutput = document.querySelector(".price-range output")
    const addToCartButtons = document.querySelectorAll(".add-to-cart")
    const paginationButtons = document.querySelectorAll(".pagination button")
    const filterForm = document.getElementById("filter-form")
    const filterInputs = document.querySelectorAll("#filter-form input")
  
    // Actualizar el valor mostrado del rango de precios
    if (priceRange && priceOutput) {
      priceRange.addEventListener("input", function () {
        // Formatear el valor como pesos colombianos
        const value = Number.parseInt(this.value)
        const formattedValue = "$ " + value.toLocaleString("es-CO")
        priceOutput.textContent = formattedValue
      })
    }
  
    // Manejar clics en los botones de "Añadir al Carrito"
    addToCartButtons?.forEach((button) => {
      button.addEventListener("click", function () {
        const productName = this.closest(".product-card").querySelector("h3").textContent
        alert(`"${productName}" ha sido añadido al carrito.`)
      })
    })
  
    // Manejar clics en los botones de paginación
    paginationButtons?.forEach((button) => {
      button.addEventListener("click", function () {
        if (!this.disabled) {
          if (this.classList.contains("prev")) {
            // Lógica para ir a la página anterior
            const currentPage = Number.parseInt(document.querySelector(".current-page").textContent.match(/\d+/)[0])
            if (currentPage > 1) {
              // Implementar navegación a la página anterior
              console.log("Navegando a la página", currentPage - 1)
            }
          } else if (this.classList.contains("next")) {
            // Lógica para ir a la página siguiente
            const currentPage = Number.parseInt(document.querySelector(".current-page").textContent.match(/\d+/)[0])
            const totalPages = Number.parseInt(document.querySelector(".current-page").textContent.match(/de (\d+)/)[1])
            if (currentPage < totalPages) {
              // Implementar navegación a la página siguiente
              console.log("Navegando a la página", currentPage + 1)
            }
          }
        }
      })
    })
  
    // Aplicar filtros automáticamente al cambiar los inputs de radio
    filterInputs?.forEach((input) => {
      if (input.type === "radio") {
        input.addEventListener("change", () => {
          filterForm.submit()
        })
      }
    })
  
    // Aplicar filtro de precio cuando el usuario suelta el control deslizante
    priceRange?.addEventListener("change", () => {
      filterForm.submit()
    })
  })
  