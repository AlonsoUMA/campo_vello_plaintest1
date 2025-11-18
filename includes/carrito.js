
        const carrito = {};
        const IVARATE = 0.13;

        // =============================================================
        // AGREGAR PRODUCTO AL CARRITO
        // =============================================================
        document.querySelectorAll(".agregarBtn").forEach(btn => {
            btn.addEventListener("click", function() {
                let fila = this.closest("tr");
                let id = fila.dataset.id;
                let name = fila.dataset.name;
                let price = parseFloat(fila.dataset.price);
                let qty = parseInt(fila.querySelector("input[type='number']").value);

                if (qty < 1) return;

                if (!carrito[id]) {
                    carrito[id] = { name, qty, price };
                } else {
                    carrito[id].qty += qty;
                }

                renderCarrito();
            });
        });

        // =============================================================
        // ELIMINAR PRODUCTO
        // =============================================================
        function eliminarProducto(id) {
            delete carrito[id];
            renderCarrito();
        }

        // =============================================================
        // RENDER DEL CARRITO
        // =============================================================
        function renderCarrito() {
            let tbody = document.getElementById("carritoBody");
            tbody.innerHTML = "";

            let subtotal = 0;

            for (let id in carrito) {
                let item = carrito[id];
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
            }

            let iva = subtotal * IVARATE;
            let total = subtotal + iva;

            document.getElementById("subtotalPagar").textContent = subtotal.toFixed(2);
            document.getElementById("ivaMonto").textContent = iva.toFixed(2);
            document.getElementById("totalPagar").textContent = total.toFixed(2);
        }

        
