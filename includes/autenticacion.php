<?php
/**
 * includes/autenticacion.php - funciones de autenticación y roles
 * NOTA: Utiliza contraseñas en texto plano para pruebas locales según la descripción original.
 */
require_once __DIR__ . '/config.php';

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

    // Verificación de la contraseña (en texto plano)
    if ($u && $u['password'] === $password) {
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
    return $_SESSION['user'] ?? null; 
}

/**
 * Verifica si el usuario actualmente logueado tiene el rol de 'admin'.
 *
 * @return bool Devuelve true si es administrador, false en caso contrario.
 */
function is_admin()
{ 
    $u = current_user(); 
    return $u && ($u['role'] === 'admin'); 
}
?>