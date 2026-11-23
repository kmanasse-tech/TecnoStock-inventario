<?php
require_once 'conexion.php';
verificarSesion();

$mensaje = '';
$tipo_alerta = 'success';

// Procesar acciones POST (Crear, Editar, Desactivar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    try {
        if ($accion === 'crear') {
            $codigo       = strtoupper(trim($_POST['codigo'] ?? ''));
            $nombre       = trim($_POST['nombre'] ?? '');
            $descripcion  = trim($_POST['descripcion'] ?? '');
            $precio       = floatval($_POST['precio'] ?? -1);
            $stock_actual = intval($_POST['stock_actual'] ?? -1);
            $stock_minimo = intval($_POST['stock_minimo'] ?? -1);
            $id_categoria = intval($_POST['id_categoria'] ?? 0);

            if ($codigo === '' || $nombre === '' || $precio < 0 || $stock_actual < 0 || $stock_minimo < 0 || $id_categoria <= 0) {
                throw new Exception("Datos inválidos: revise campos obligatorios y que precio/stocks no sean negativos.");
            }

            // Verificar código único
            $check = $pdo->prepare("SELECT COUNT(*) FROM producto WHERE codigo = ?");
            $check->execute([$codigo]);
            if ($check->fetchColumn() > 0) {
                throw new Exception("El código '$codigo' ya está registrado en otro producto.");
            }

            $stmt = $pdo->prepare("INSERT INTO producto (codigo, nombre, descripcion, precio, stock_actual, stock_minimo, estado, id_categoria) VALUES (?, ?, ?, ?, ?, ?, 1, ?)");
            $stmt->execute([$codigo, $nombre, $descripcion, $precio, $stock_actual, $stock_minimo, $id_categoria]);
            $mensaje = "Producto '$nombre' registrado correctamente.";

        } elseif ($accion === 'editar') {
            $id_producto  = intval($_POST['id_producto'] ?? 0);
            $nombre       = trim($_POST['nombre'] ?? '');
            $descripcion  = trim($_POST['descripcion'] ?? '');
            $precio       = floatval($_POST['precio'] ?? -1);
            $stock_actual = intval($_POST['stock_actual'] ?? -1);
            $stock_minimo = intval($_POST['stock_minimo'] ?? -1);
            $id_categoria = intval($_POST['id_categoria'] ?? 0);

            if ($id_producto <= 0 || $nombre === '' || $precio < 0 || $stock_actual < 0 || $stock_minimo < 0 || $id_categoria <= 0) {
                throw new Exception("Datos inválidos para actualizar el producto.");
            }

            $stmt = $pdo->prepare("UPDATE producto SET nombre = ?, descripcion = ?, precio = ?, stock_actual = ?, stock_minimo = ?, id_categoria = ? WHERE id_producto = ?");
            $stmt->execute([$nombre, $descripcion, $precio, $stock_actual, $stock_minimo, $id_categoria, $id_producto]);
            $mensaje = "Producto actualizado correctamente.";

        } elseif ($accion === 'desactivar') {
            $id_producto = intval($_POST['id_producto'] ?? 0);
            // Regla de negocio: desactivación lógica (estado = 0), nunca eliminación física
            $stmt = $pdo->prepare("UPDATE producto SET estado = 0 WHERE id_producto = ?");
            $stmt->execute([$id_producto]);
            $mensaje = "Producto desactivado del catálogo activo.";
        }
    } catch (Exception $e) {
        $mensaje = $e->getMessage();
        $tipo_alerta = 'danger';
    }
}

// Obtener categorías activas
$categorias = $pdo->query("SELECT id_categoria, nombre FROM categoria WHERE estado = 1 ORDER BY nombre")->fetchAll();

// Filtros de búsqueda por nombre, código o categoría
$busqueda = trim($_GET['q'] ?? '');
$cat_filtro = intval($_GET['categoria'] ?? 0);
$ver_inactivos = isset($_GET['inactivos']) ? 1 : 0;

$sql = "SELECT p.*, c.nombre AS categoria_nombre
        FROM producto p
        INNER JOIN categoria c ON p.id_categoria = c.id_categoria
        WHERE 1=1";
$params = [];

if (!$ver_inactivos) {
    $sql .= " AND p.estado = 1";
}
if ($busqueda !== '') {
    $sql .= " AND (p.nombre LIKE ? OR p.codigo LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}
if ($cat_filtro > 0) {
    $sql .= " AND p.id_categoria = ?";
    $params[] = $cat_filtro;
}
$sql .= " ORDER BY p.id_producto DESC";

$stmtProd = $pdo->prepare($sql);
$stmtProd->execute($params);
$productos = $stmtProd->fetchAll();

// Contar productos activos con stock bajo
$bajo_stock_count = 0;
foreach ($productos as $p) {
    if ($p['estado'] == 1 && $p['stock_actual'] < $p['stock_minimo']) {
        $bajo_stock_count++;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TecnoStock - Catálogo de Productos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">TecnoStock</a>
        <div class="navbar-nav me-auto">
            <a class="nav-link active" href="index.php">Productos</a>
            <a class="nav-link" href="movimientos.php">Entradas y Salidas</a>
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

    <?php if ($bajo_stock_count > 0): ?>
        <div class="alert alert-warning d-flex justify-content-between align-items-center">
            <span><strong>Alerta de Stock Bajo:</strong> Hay <?= $bajo_stock_count ?> producto(s) activo(s) con existencias por debajo del mínimo definido.</span>
            <a href="movimientos.php" class="btn btn-sm btn-warning fw-bold">Registrar Entrada</a>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Catálogo de Productos</h3>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalCrear">+ Nuevo Producto</button>
    </div>

    <!-- Buscador -->
    <form method="GET" class="row g-2 mb-4 bg-white p-3 rounded shadow-sm">
        <div class="col-md-5">
            <input type="text" name="q" class="form-control" placeholder="Buscar por código o nombre..." value="<?= htmlspecialchars($busqueda) ?>">
        </div>
        <div class="col-md-4">
            <select name="categoria" class="form-select">
                <option value="0">Todas las categorías</option>
                <?php foreach ($categorias as $cat): ?>
                    <option value="<?= $cat['id_categoria'] ?>" <?= $cat_filtro == $cat['id_categoria'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary w-100">Buscar</button>
            <a href="index.php" class="btn btn-outline-secondary">Limpiar</a>
        </div>
        <div class="col-12">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="inactivos" id="chkInactivos" <?= $ver_inactivos ? 'checked' : '' ?> onchange="this.form.submit()">
                <label class="form-check-label small text-muted" for="chkInactivos">Mostrar también productos desactivados</label>
            </div>
        </div>
    </form>

    <!-- Tabla de productos -->
    <div class="table-responsive bg-white rounded shadow-sm">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
                <tr>
                    <th>Código</th>
                    <th>Producto</th>
                    <th>Categoría</th>
                    <th class="text-end">Precio</th>
                    <th class="text-center">Stock Actual</th>
                    <th class="text-center">Stock Mín.</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($productos)): ?>
                    <tr><td colspan="8" class="text-center py-4 text-muted">No se encontraron productos.</td></tr>
                <?php else: ?>
                    <?php foreach ($productos as $p):
                        $esBajoStock = ($p['estado'] == 1 && $p['stock_actual'] < $p['stock_minimo']);
                    ?>
                    <tr class="<?= $esBajoStock ? 'table-warning' : ($p['estado'] == 0 ? 'table-secondary text-muted' : '') ?>">
                        <td class="fw-bold"><?= htmlspecialchars($p['codigo']) ?></td>
                        <td>
                            <div><?= htmlspecialchars($p['nombre']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($p['descripcion'] ?? '') ?></small>
                        </td>
                        <td><?= htmlspecialchars($p['categoria_nombre']) ?></td>
                        <td class="text-end">$<?= number_format($p['precio'], 0, ',', '.') ?></td>
                        <td class="text-center fw-bold">
                            <?= $p['stock_actual'] ?>
                            <?php if ($esBajoStock): ?>
                                <span class="badge bg-danger ms-1">Stock Bajo</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?= $p['stock_minimo'] ?></td>
                        <td>
                            <?php if ($p['estado'] == 1): ?>
                                <span class="badge bg-success">Activo</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Desactivado</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary"
                                    onclick='abrirEditar(<?= json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Editar</button>
                            <?php if ($p['estado'] == 1): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('¿Desactivar este producto? No se eliminará el historial de movimientos.');">
                                    <input type="hidden" name="accion" value="desactivar">
                                    <input type="hidden" name="id_producto" value="<?= $p['id_producto'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Desactivar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Crear Producto -->
<div class="modal fade" id="modalCrear" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content" onsubmit="return validarProducto(this)">
            <input type="hidden" name="accion" value="crear">
            <div class="modal-header">
                <h5 class="modal-title">Registrar Nuevo Producto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-2">
                    <div class="col-md-5">
                        <label class="form-label">Código Único *</label>
                        <input type="text" name="codigo" class="form-control" placeholder="Ej: TEC-005" required>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label">Categoría *</label>
                        <select name="id_categoria" class="form-select" required>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?= $cat['id_categoria'] ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label">Nombre *</label>
                    <input type="text" name="nombre" class="form-control" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Descripción</label>
                    <textarea name="descripcion" class="form-control" rows="2"></textarea>
                </div>
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label">Precio *</label>
                        <input type="number" name="precio" step="0.01" min="0" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Stock Actual *</label>
                        <input type="number" name="stock_actual" min="0" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Stock Mínimo *</label>
                        <input type="number" name="stock_minimo" min="0" class="form-control" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success">Guardar Producto</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Editar Producto -->
<div class="modal fade" id="modalEditar" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content" onsubmit="return validarProducto(this)">
            <input type="hidden" name="accion" value="editar">
            <input type="hidden" name="id_producto" id="edit_id">
            <div class="modal-header">
                <h5 class="modal-title">Editar Producto (<span id="edit_codigo_label"></span>)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label">Categoría *</label>
                    <select name="id_categoria" id="edit_categoria" class="form-select" required>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id_categoria'] ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label">Nombre *</label>
                    <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Descripción</label>
                    <textarea name="descripcion" id="edit_descripcion" class="form-control" rows="2"></textarea>
                </div>
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label">Precio *</label>
                        <input type="number" name="precio" id="edit_precio" step="0.01" min="0" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Stock Actual *</label>
                        <input type="number" name="stock_actual" id="edit_stock_actual" min="0" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Stock Mínimo *</label>
                        <input type="number" name="stock_minimo" id="edit_stock_minimo" min="0" class="form-control" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Actualizar</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function validarProducto(form) {
    const precio = parseFloat(form.precio.value);
    const stockActual = parseInt(form.stock_actual.value, 10);
    const stockMinimo = parseInt(form.stock_minimo.value, 10);

    if (isNaN(precio) || precio < 0 || isNaN(stockActual) || stockActual < 0 || isNaN(stockMinimo) || stockMinimo < 0) {
        alert('El precio, el stock actual y el stock mínimo no pueden ser negativos.');
        return false;
    }
    return true;
}

function abrirEditar(prod) {
    document.getElementById('edit_id').value = prod.id_producto;
    document.getElementById('edit_codigo_label').textContent = prod.codigo;
    document.getElementById('edit_categoria').value = prod.id_categoria;
    document.getElementById('edit_nombre').value = prod.nombre;
    document.getElementById('edit_descripcion').value = prod.descripcion || '';
    document.getElementById('edit_precio').value = prod.precio;
    document.getElementById('edit_stock_actual').value = prod.stock_actual;
    document.getElementById('edit_stock_minimo').value = prod.stock_minimo;

    new bootstrap.Modal(document.getElementById('modalEditar')).show();
}
</script>
</body>
</html>