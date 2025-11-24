const IVARATE = 0.13;

// 1. Inicialización Global: Cargar desde localStorage o iniciar como objeto vacío
// Usamos window.carrito para asegurar que sea accesible desde cualquier parte
const initialCarrito = localStorage.getItem('facturaCarrito');
window.carrito = initialCarrito ? JSON.parse(initialCarrito) : {};

// Función para guardar el carrito en localStorage
function saveCarrito() {
    if (Object.keys(window.carrito).length > 0) {
        localStorage.setItem('facturaCarrito', JSON.stringify(window.carrito));
    } else {
        localStorage.removeItem('facturaCarrito');
    }
}

// Función para forzar el renderizado al cargar la página
function loadCarritoAndRender() {
    renderCarrito();
}

// AGREGAR PRODUCTO AL CARRITO
document.querySelectorAll(".agregarBtn").forEach(btn => {
    btn.addEventListener("click", function() {
        let fila = this.closest("tr");
        let id = fila.dataset.id;
        let name = fila.dataset.name;
        let price = parseFloat(fila.dataset.price);
        let maxStock = parseInt(fila.querySelector("input[type='number']").getAttribute('max'));
        let qtyInput = fila.querySelector("input[type='number']");
        let qty = parseInt(qtyInput.value);

        if (qty < 1 || isNaN(qty)) return;

        let currentQty = window.carrito[id] ? window.carrito[id].qty : 0;
        let newTotalQty = currentQty + qty;
        
        if (newTotalQty > maxStock) {
            alert(`No puedes agregar más. Stock máximo disponible: ${maxStock}. Ya tienes ${currentQty} en el carrito.`);
            return;
        }

        window.carrito[id] = { name, qty: newTotalQty, price };
        
        saveCarrito(); 
        renderCarrito();
        
        qtyInput.value = '';
    });
});

// ELIMINAR PRODUCTO (debe ser global, por eso no lleva const/let)
function eliminarProducto(id) {
    delete window.carrito[id];
    saveCarrito(); 
    renderCarrito();
}

// RENDER DEL CARRITO (debe ser global)
function renderCarrito() {
    let tbody = document.getElementById("carritoBody");
    tbody.innerHTML = "";

    let subtotal = 0;

    for (let id in window.carrito) {
        let item = window.carrito[id];
        let st = item.qty * item.price;
        subtotal += st;

        tbody.innerHTML += `
            <tr>
                <td>${item.name}</td>
                <td>${item.qty}</td>
                <td>$${st.toFixed(2)}</td>
                <td><button type="button" class="btn" onclick="eliminarProducto('${id}')">X</button></td>

                <input type="hidden" name="items[${id}]" value="${item.qty}">
            </tr>
        `;
    }

    if (subtotal === 0) {
        tbody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:#888;">No hay productos</td></tr>`;
        saveCarrito(); // Asegurar que localStorage se limpia si se borra el último ítem
    }

    let iva = subtotal * IVARATE;
    let total = subtotal + iva;

    // Se corrigen los span para mostrar el subtotal, el IVA y el total
    document.getElementById("subtotalPagar").textContent = subtotal.toFixed(2);
    document.getElementById("ivaMonto").textContent = iva.toFixed(2);
    document.getElementById("totalPagar").textContent = total.toFixed(2);
}

// Ejecutar la carga y renderización al terminar de cargar el documento
document.addEventListener('DOMContentLoaded', loadCarritoAndRender);