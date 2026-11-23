<?php
require_once 'conexion.php';

if (isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo'] ?? '');
    $clave  = $_POST['clave'] ?? '';

    if (empty($correo) || empty($clave)) {
        $error = "Complete todos los campos.";
    } else {
        $stmt = $pdo->prepare("SELECT id_usuario, nombre, correo, clave, estado FROM usuario WHERE correo = ? LIMIT 1");
        $stmt->execute([$correo]);
        $usuario = $stmt->fetch();

        if ($usuario && $usuario['estado'] == 1 && password_verify($clave, $usuario['clave'])) {
            $_SESSION['id_usuario'] = $usuario['id_usuario'];
            $_SESSION['nombre_usuario'] = $usuario['nombre'];
            header("Location: index.php");
            exit;
        } else {
            $error = "Credenciales incorrectas o usuario inactivo.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TecnoStock - Iniciar Sesión</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center vh-100">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h4 class="text-center mb-1 fw-bold text-primary">TecnoStock</h4>
                    <p class="text-center text-muted small mb-4">Control de Inventario</p>

                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST" id="formLogin" novalidate>
                        <div class="mb-3">
                            <label class="form-label">Correo electrónico</label>
                            <input type="email" name="correo" id="correo" class="form-control" value="admin@tecnostock.cl" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contraseña</label>
                            <input type="password" name="clave" id="clave" class="form-control" value="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Ingresar</button>
                    </form>
                    <div class="mt-3 text-center text-muted" style="font-size: 0.8rem;">
                        Demo: <code>admin@tecnostock.cl</code> / <code>password</code>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.getElementById('formLogin').addEventListener('submit', function(e) {
    const correo = document.getElementById('correo').value.trim();
    const clave = document.getElementById('clave').value.trim();
    if (!correo || !clave) {
        e.preventDefault();
        alert('Por favor ingresa correo y contraseña.');
    }
});
</script>
</body>
</html>