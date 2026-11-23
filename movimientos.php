<?php
require_once 'conexion.php';
verificarSesion();

$mensaje = '';
$tipo_alerta = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_producto = intval($_POST['id_producto'] ?? 0);
    $tipo        = $_POST['tipo'] ?? '';
    $cantidad    = intval($_POST['cantidad'] ?? 0);
    $observacion = trim($_POST['observacion'] ?? '');
    $id_usuario  = $_SESSION['id_usuario'];

    try {
        if ($id_producto <= 0 || !in_array($tipo, ['ENTRADA', 'SALIDA']) || $cantidad <= 0) {
            throw new Exception("Seleccione un producto válido, tipo de movimiento y una cantidad mayor a 0.");
        }

        // Transacción para asegurar integridad entre movimiento y stock_actual
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT nombre, stock_actual, estado FROM producto WHERE id_producto = ? FOR UPDATE");
        $stmt->execute([$id_producto]);
        $prod = $stmt->fetch();

        if (!$prod || $prod['estado'] != 1) {
            throw new Exception("El producto no existe o se encuentra desactivado.");
        }

        if ($tipo === 'SALIDA' && $prod['stock_actual'] < $cantidad) {
            throw new Exception("Stock insuficiente para '{$prod['nombre']}'. Disponible: {$prod['stock_actual']}, solicitado: $cantidad.");
        }

        $nuevo_stock = ($tipo === 'ENTRADA')
            ? $prod['stock_actual'] + $cantidad
            : $prod['stock_actual'] - $cantidad;

        // 1. Insertar registro de movimiento
        $ins = $pdo->prepare("INSERT INTO movimiento (tipo, cantidad, observacion, id_producto, id_usuario) VALUES (?, ?, ?, ?, ?)");
        $ins->execute([$tipo, $cantidad, $observacion, $id_producto, $id_usuario]);

        // 2. Actualizar stock del producto automáticamente
        $upd = $pdo->prepare("UPDATE producto SET stock_actual = ? WHERE id_producto = ?");
        $upd->execute([$nuevo_stock, $id_producto]);

        $pdo->commit();
        $mensaje = "Movimiento ($tipo) de $cantidad unidad(es) registrado. Nuevo stock de '{$prod['nombre']}': $nuevo_stock.";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $mensaje = $e->getMessage();
        $tipo_alerta = 'danger';
    }
}

// Productos activos para el selector
$productos_activos = $pdo->query("SELECT id_producto, codigo, nombre, stock_actual, stock_minimo FROM producto WHERE estado = 1 ORDER BY nombre")->fetchAll();

// Historial reciente de movimientos
$historial = $pdo->query("
    SELECT m.*, p.codigo, p.nombre AS producto_nombre, u.nombre AS usuario_nombre
    FROM movimiento m
    INNER JOIN producto p ON m.id_producto = p.id_producto
    INNER JOIN usuario u ON m.id_usuario = u.id_usuario
    ORDER BY m.fecha_hora DESC, m.id_movimiento DESC
    LIMIT 30
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TecnoStock - Entradas y Salidas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">TecnoStock</a>
        <div class="navbar-nav me-auto">
            <a class="nav-link" href="index.php">Productos</a>
            <a class="nav-link active" href="movimientos.php">Entradas y Salidas</a>
        </div>
        <span class="navbar-text text-white me-3">Hola, <?= htmlspecialchars($_SESSION['nombre_usuario']) ?></span>
        <a href="logout.php" class="btn btn-outline-light btn-sm">Salir</a>
    </div>
</nav>

<div class="container">
    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $tipo_alerta ?> alert-dismissible fade show">
            <?= htmlspecialchars($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Formulario de registro de movimiento -->
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-bold">Registrar Movimiento</div>
                <div class="card-body">
                    <form method="POST" id="formMovimiento">
                        <div class="mb-3">
                            <label class="form-label">Producto activo *</label>
                            <select name="id_producto" id="sel_producto" class="form-select" required>
                                <option value="">-- Seleccione --</option>
                                <?php foreach ($productos_activos as $pa): ?>
                                    <option value="<?= $pa['id_producto'] ?>" data-stock="<?= $pa['stock_actual'] ?>">
                                        [<?= htmlspecialchars($pa['codigo']) ?>] <?= htmlspecialchars($pa['nombre']) ?> (Stock: <?= $pa['stock_actual'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tipo de movimiento *</label>
                            <select name="tipo" id="sel_tipo" class="form-select" required>
                                <option value="ENTRADA">ENTRADA (+ Sumar stock)</option>
                                <option value="SALIDA">SALIDA (- Descontar stock)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Cantidad *</label>
                            <input type="number" name="cantidad" id="inp_cantidad" min="1" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Observación</label>
                            <input type="text" name="observacion" class="form-control" placeholder="Ej: Compra proveedor, Venta #102">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Registrar Movimiento</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Historial de movimientos -->
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-bold">Historial de Movimientos Recientes</div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Fecha / Hora</th>
                                <th>Producto</th>
                                <th>Tipo</th>
                                <th class="text-center">Cantidad</th>
                                <th>Responsable</th>
                                <th>Observación</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historial as $h): ?>
                            <tr>
                                <td class="small"><?= htmlspecialchars($h['fecha_hora']) ?></td>
                                <td><strong><?= htmlspecialchars($h['codigo']) ?></strong> - <?= htmlspecialchars($h['producto_nombre']) ?></td>
                                <td>
                                    <span class="badge <?= $h['tipo'] === 'ENTRADA' ? 'bg-success' : 'bg-danger' ?>">
                                        <?= $h['tipo'] ?>
                                    </span>
                                </td>
                                <td class="text-center fw-bold"><?= $h['cantidad'] ?></td>
                                <td class="small"><?= htmlspecialchars($h['usuario_nombre']) ?></td>
                                <td class="small text-muted"><?= htmlspecialchars($h['observacion'] ?? '') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('formMovimiento').addEventListener('submit', function(e) {
    const selectProd = document.getElementById('sel_producto');
    const tipo = document.getElementById('sel_tipo').value;
    const cantidad = parseInt(document.getElementById('inp_cantidad').value, 10);

    if (!selectProd.value || isNaN(cantidad) || cantidad <= 0) {
        e.preventDefault();
        alert('Seleccione un producto e ingrese una cantidad válida mayor a 0.');
        return;
    }

    const stockDisponible = parseInt(selectProd.options[selectProd.selectedIndex].dataset.stock, 10);
    if (tipo === 'SALIDA' && cantidad > stockDisponible) {
        e.preventDefault();
        alert('No puede registrar una salida mayor al stock disponible (' + stockDisponible + ').');
    }
});
</script>
</body>
</html>