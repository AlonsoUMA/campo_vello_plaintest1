<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/autenticacion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: nuevo_cliente.php');
    exit;
}

if (!is_logged() || is_admin()) {
    header('Location: ../index.php');
    exit;
}

if (!verify_csrf($_POST['_csrf'] ?? '')) {
    $_SESSION['mensaje'] = 'Error de seguridad: Token CSRF inválido.';
    header('Location: nuevo_cliente.php');
    exit;
}

// --- Recolección de Datos (Ajustada para recibir 'name', 'phone', 'email', 'address') ---
$name = trim($_POST['name'] ?? ''); 
$phone = trim($_POST['phone'] ?? '');
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$nit = trim($_POST['nit'] ?? '');
$address = trim($_POST['address'] ?? ''); 

// --- Validación Mínima ---
if (empty($name)) {
    $_SESSION['mensaje'] = 'Error: El nombre del cliente es obligatorio.';
    header('Location: nuevo_cliente.php');
    exit;
}

try {
    $pdo = getPDO();
    // Consulta SQL con nombres de columnas de la DB (name, phone, email, nit, address)
    $sql = "INSERT INTO clientes (name, phone, email, nit, address) 
             VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    
    $stmt->execute([
        $name, 
        $phone, 
        $email, 
        $nit, 
        $address
    ]);
    
    $_SESSION['mensaje'] = 'Cliente "' . htmlspecialchars($name) . '" registrado exitosamente.';
    header('Location: nuevo_cliente.php');
    exit;
    
} catch (PDOException $e) {
    error_log("Error al guardar cliente: " . $e->getMessage()); 
    $_SESSION['mensaje'] = 'Error al guardar el cliente en la base de datos.';
    header('Location: nuevo_cliente.php');
    exit;
}
?>