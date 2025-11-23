<?php
/**
 * includes/autenticacion.php - funciones de autenticación y roles
 */
require_once __DIR__ . '/config.php';
// Requerimos funciones.php para acceder a hash_password, verify_password y getPDO
require_once __DIR__ . '/funciones.php'; 

// Asegúrate de iniciar la sesión antes de cualquier salida
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/**
 * Intenta iniciar sesión con el correo electrónico y la contraseña proporcionados.
 *
 * @param string $email Correo electrónico del usuario.
 * @param string $password Contraseña del usuario (texto plano).
 * @return bool Devuelve true si la autenticación es exitosa, false en caso contrario.
 */
function login_user($email, $password)
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $u = $stmt->fetch();

    // CAMBIO CLAVE: Usamos verify_password para comparar el texto plano con el hash
    if ($u && verify_password($password, $u['password'])) {
        unset($u['password']); // Elimina la contraseña de la sesión por seguridad
        $_SESSION['user'] = $u;
        return true;
    }
    return false;
}

/**
 * Cierra la sesión del usuario actual.
 */
function logout_user()
{
    // Asegúrate de iniciar la sesión si no lo está
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    unset($_SESSION['user']);
    session_destroy();
}

/**
 * Verifica si hay un usuario logueado en la sesión actual.
 *
 * @return bool Devuelve true si hay un usuario logueado, false en caso contrario.
 */
function is_logged()
{ 
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return !empty($_SESSION['user']); 
}

/**
 * Requiere que el usuario esté logueado. Si no lo está, redirige a la página de inicio.
 */
function require_login()
{ 
    if (!is_logged()) { 
        header('Location: /index.php'); 
        exit; 
    } 
}

/**
 * Obtiene la información del usuario actualmente logueado.
 *
 * @return array|null Devuelve un array con los datos del usuario o null si no hay sesión.
 */
function current_user()
{ 
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return $_SESSION['user'] ?? null; 
}

/**
 * Verifica si el usuario actualmente logueado tiene el rol de administrador.
 *
 * @return bool Devuelve true si es administrador, false en caso contrario.
 */
function is_admin()
{
    $user = current_user();
    return $user && ($user['role'] === 'admin');
}