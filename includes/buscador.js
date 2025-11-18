// =============================================================
        // BUSCADOR
        // =============================================================
        document.getElementById("buscador").addEventListener("input", function () {
            let filtro = this.value.toLowerCase();
            document.querySelectorAll("table tbody tr").forEach(fila => {
                let nombre = fila.dataset.name?.toLowerCase() || "";
                fila.style.display = nombre.includes(filtro) ? "" : "none";
            });
        });