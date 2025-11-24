<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/autenticacion.php';
require_once __DIR__ . '/../includes/funciones.php';

if (!is_admin()) {
    header('Location: ../index.php');
    exit;
}

$pdo = getPDO();
$error = '';

// Procesamiento de formulario POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verificación CSRF
    if (!verify_csrf($_POST['_csrf'] ?? '')) {
        $error = 'CSRF inválido';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            $password = $_POST['password'] ?? '';
            
            // Validación simple de la contraseña
            if (empty($password)) {
                $error = 'La contraseña no puede estar vacía.';
            } else {
                $hashed_password = hash_password($password);

                try {
                    $pdo->prepare('INSERT INTO usuarios (nombre, email, password, role) VALUES (?, ?, ?, ?)')
                        ->execute([
                            $_POST['nombre'],
                            $_POST['email'],
                            $hashed_password,
                            $_POST['role']
                        ]);
                    header('Location: gestionar_usuarios.php');
                    exit;
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        $error = 'El email o nombre de usuario ya existe.';
                    } else {
                        $error = 'Error al agregar usuario: ' . $e->getMessage();
                    }
                }
            }
        }

        // Acción: Eliminar Usuario
        if ($action === 'delete') {
            $pdo->prepare('DELETE FROM usuarios WHERE id=?')->execute([$_POST['id']]);
            header('Location: gestionar_usuarios.php');
            exit;
        }
        
        if ($action === 'update') {
        }
    }
}

// Obtener datos para la vista
$users = $pdo->query('SELECT * FROM usuarios ORDER BY id DESC')->fetchAll();

?>

<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Gestionar Usuarios</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<nav>
    <div style="display:flex;gap:12px;align-items:center">
        <img src="../assets/img/logo.svg" style="height:36px" alt="logo">
        <strong>Campo Vello - Admin</strong>
    </div>
    <div>
        <a href="dashboard.php" style="color:#fff" class="btn">Volver al panel</a>
    </div>
</nav>

<div class="container">
    <h2>Usuarios</h2>
    
    <?php if (!empty($error)) echo '<div class="alert">'.htmlspecialchars($error).'</div>'; ?>

    <!-- Formulario para agregar usuario -->
    <form method="post" style="display:flex;gap:8px;margin-bottom:12px">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="add">
        
        <input name="nombre" placeholder="Nombre" required style="flex-grow: 1; min-width: 150px;" pattern="[a-zA-ZñÑáÁéÉíÍóÓÚú\s]+" maxlength="50">
        <input name="email" placeholder="Email" required type="email" pattern="^[\w.-]+@[\w.-]+\.[a-zA-Z]{2,15}$" title="Debe tener formato de correo electrónico (ej: nombre@dominio.com)" style="flex-grow: 1; min-width: 150px;">
        <input name="password" placeholder="Contraseña" required type="password" pattern="(?=.*[A-Za-zñÑ])(?=.*\d)[A-Za-zñÑ\d]{8,15}" title="Debe tener entre 8 y 15 caracteres, incluyendo al menos una letra mayuscula, una minuscula, sin tildes y un número." style="flex-grow: 1; min-width: 150px;">
        
        <select name="role">
            <option value="cajero">Cajero</option>
            <option value="admin">Administrador</option>
        </select>
        
        <button class="btn">Agregar</button>
    </form>

    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Email</th>
                <th>Rol</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u) : ?>
            <tr>
                <td><?=$u['id']?></td>
                <td><?=htmlspecialchars($u['nombre'])?></td>
                <td><?=htmlspecialchars($u['email'])?></td>
                <td><?=$u['role']?></td>
                <td>
                    <form method="post" style="display:inline-block" onsubmit="return confirm('¿Eliminar al usuario <?= htmlspecialchars($u['nombre']) ?>?');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?=$u['id']?>">
                        <button class="btn" style="background:#c62828">Eliminar</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>