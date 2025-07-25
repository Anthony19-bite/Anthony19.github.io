<!-- tech_store/index.php -->
<?php
// Conexión a la base de datos
$conn = new mysqli("localhost", "root", "", "tech_store");
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Crear producto
if (isset($_POST['crear'])) {
    $nombre = $_POST['nombre'];
    $precio = $_POST['precio'];
    $descripcion = $_POST['descripcion'];
    if ($nombre && $precio) {
        $stmt = $conn->prepare("INSERT INTO productos (nombre, precio, descripcion) VALUES (?, ?, ?)");
        $stmt->bind_param("sds", $nombre, $precio, $descripcion);
        $stmt->execute();
    }
}

// Actualizar producto
if (isset($_POST['actualizar'])) {
    $id = $_POST['id'];
    $nombre = $_POST['nombre'];
    $precio = $_POST['precio'];
    $descripcion = $_POST['descripcion'];
    if ($id && $nombre && $precio) {
        $stmt = $conn->prepare("UPDATE productos SET nombre=?, precio=?, descripcion=? WHERE id=?");
        $stmt->bind_param("sdsi", $nombre, $precio, $descripcion, $id);
        $stmt->execute();
    }
}

// Eliminar producto
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    $stmt = $conn->prepare("DELETE FROM productos WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
}

// Obtener lista de productos
$result = $conn->query("SELECT * FROM productos");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Tech Store - Administración</title>
    <style>
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table, th, td { border: 1px solid black; }
        th, td { padding: 10px; text-align: left; }
        form { margin-top: 20px; }
    </style>
</head>
<body>
<h1>Tech Store - Gestión de Productos</h1>

<!-- Maestro: Tabla de productos -->
<table>
    <tr><th>ID</th><th>Nombre</th><th>Precio</th><th>Acciones</th></tr>
    <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?= $row['id'] ?></td>
            <td><a href="?detalle=<?= $row['id'] ?>"><?= $row['nombre'] ?></a></td>
            <td>$<?= $row['precio'] ?></td>
            <td>
                <a href="?detalle=<?= $row['id'] ?>">Editar</a> |
                <a href="?eliminar=<?= $row['id'] ?>" onclick="return confirm('¿Eliminar producto?');">Eliminar</a>
            </td>
        </tr>
    <?php endwhile; ?>
</table>

<!-- Detalle: Formulario crear/editar -->
<?php
$detalle = ['id' => '', 'nombre' => '', 'precio' => '', 'descripcion' => ''];
if (isset($_GET['detalle'])) {
    $id = $_GET['detalle'];
    $res = $conn->query("SELECT * FROM productos WHERE id=$id");
    $detalle = $res->fetch_assoc();
}
?>
<h2><?= $detalle['id'] ? 'Editar Producto' : 'Crear Producto' ?></h2>
<form method="post">
    <input type="hidden" name="id" value="<?= $detalle['id'] ?>">
    <label>Nombre: <input type="text" name="nombre" value="<?= $detalle['nombre'] ?>" required></label><br><br>
    <label>Precio: <input type="number" step="0.01" name="precio" value="<?= $detalle['precio'] ?>" required></label><br><br>
    <label>Descripción:<br><textarea name="descripcion"><?= $detalle['descripcion'] ?></textarea></label><br><br>
    <button type="submit" name="<?= $detalle['id'] ? 'actualizar' : 'crear' ?>">
        <?= $detalle['id'] ? 'Actualizar' : 'Crear' ?>
    </button>
</form>

</body>
</html>
