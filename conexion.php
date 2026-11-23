
<?php
session_start();

$host = '127.0.0.1';
$db   = 'tecnostock';
$user = 'root';
$pass = ''; // Igual que en XAMPP por defecto para compatibilidad con el docente
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Error de conexión a la base de datos: " . htmlspecialchars($e->getMessage()));
}

function verificarSesion() {
    if (!isset($_SESSION['id_usuario'])) {
        header("Location: login.php");
        exit;
    }
}
?>