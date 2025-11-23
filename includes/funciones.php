<?php
// includes/funciones.php - Funciones de utilidad

/**
 * Formatea un valor numérico como moneda (con 2 decimales, punto decimal, coma de miles).
 * @param mixed $v El valor a formatear.
 * @return string El valor formateado.
 */
function money($v){ 
    return number_format((float)$v, 2, '.', ','); 
}

/**
 * Escribe un mensaje en un archivo de registro (log) con fecha y hora.
 * Crea el directorio 'logs' si no existe. El archivo se guarda en ../logs/{$name}.log.
 * @param string $name El nombre del archivo de registro (sin extensión .log).
 * @param string $msg El mensaje a registrar.
 * @return void
 */
function log_write($name, $msg){ 
    // Define la ruta del directorio de logs (un nivel arriba de 'includes')
    $dir = __DIR__ . '/../logs'; 
    
    // Crea el directorio si no existe
    if(!is_dir($dir)) {
        mkdir($dir, 0755, true); 
    }
    
    // Escribe el mensaje, añadiendo la marca de tiempo y un salto de línea
    file_put_contents(
        $dir.'/'.$name.'.log', 
        date('[Y-m-d H:i:s] ').$msg.PHP_EOL, 
        FILE_APPEND // Agrega el contenido al final del archivo
    ); 
}

// ----------------------------------------------------------------------
// NUEVAS FUNCIONES DE HASHING DE CONTRASEÑAS (REQUERIDAS)
// ----------------------------------------------------------------------

/**
 * Genera el hash de una contraseña usando el algoritmo BCRYPT.
 * @param string $password La contraseña en texto plano.
 * @return string El hash de la contraseña.
 */
function hash_password(string $password): string {
    // Usamos PASSWORD_DEFAULT (actualmente BCRYPT) para hashing seguro.
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verifica si una contraseña coincide con un hash dado.
 * @param string $password La contraseña ingresada por el usuario (texto plano).
 * @param string $hash El hash almacenado en la base de datos.
 * @return bool True si la contraseña es correcta, False en caso contrario.
 */
function verify_password(string $password, string $hash): bool {
    return password_verify($password, $hash);
}

// ----------------------------------------------------------------------
?>