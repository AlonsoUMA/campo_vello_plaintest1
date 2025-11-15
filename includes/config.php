<?php
// includes/config.php - Configuración y helpers esenciales

// ===================================
// 1. CONFIGURACIÓN DE BASE DE DATOS
// ===================================
$DB_HOST = '127.0.0.1';
$DB_NAME = 'campo_vello';
$DB_USER = 'root';
$DB_PASS = '';

/**
 * Establece y retorna una conexión PDO a la base de datos.
 */
function getPDO(){
    global $DB_HOST,$DB_NAME,$DB_USER,$DB_PASS;
    
    $dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";
    
    // Opciones para PDO: lanzar excepciones en caso de error y usar arrays asociativos
    $opt = [
        PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION, 
        PDO::ATTR_DEFAULT_FETCH_MODE=> PDO::FETCH_ASSOC
    ];
    
    return new PDO($dsn,$DB_USER,$DB_PASS,$opt);
}

// ===================================
// 2. GESTIÓN DE SESIÓN
// ===================================
// Inicia la sesión solo si no está activa
if(session_status()===PHP_SESSION_NONE) session_start();

// ===================================
// 3. HELPERS DE SEGURIDAD CSRF
// ===================================

/**
 * Retorna el token CSRF y lo genera si no existe en la sesión.
 * @return string El token CSRF.
 */
function csrf_token(){ 
    if(empty($_SESSION['csrf_token'])) {
        // Genera un token aleatorio criptográficamente seguro
        $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf_token']; 
}

/**
 * Genera el campo input oculto para incluir el token CSRF en formularios.
 * @return string Campo input HTML.
 */
function csrf_field(){ 
    return '<input type="hidden" name="_csrf" value="'.htmlspecialchars(csrf_token()).'">'; 
}

/**
 * Verifica si el token enviado ($t) coincide con el almacenado en la sesión.
 * Usa hash_equals() para una comparación segura (anti-timing attacks).
 * @param string $t El token enviado desde el formulario.
 * @return bool True si los tokens coinciden.
 */
function verify_csrf($t){ 
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'],$t); 
}

// ===================================
// 4. INICIALIZACIÓN DE CONEXIÓN GLOBAL
// ===================================
try {
    // Llama a la función getPDO() y asigna el objeto de conexión a la variable $pdo
    // Esto hace que $pdo esté disponible globalmente para guardar_cliente.php
    $pdo = getPDO(); 
} catch (PDOException $e) {
    // Si la conexión falla, detiene el script y muestra un error
    die("Error de conexión a la base de datos: " . $e->getMessage());
}
?>