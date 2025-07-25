<?php
// products.php - API para gestionar productos
require_once 'config.php';

class ProductsAPI {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    // Obtener todos los productos
    public function getProducts($search = '') {
        try {
            $query = "SELECT p.*, c.name as category_name 
                     FROM products p 
                     LEFT JOIN categories c ON p.category_id = c.id";
            
            if (!empty($search)) {
                $query .= " WHERE p.name LIKE :search OR c.name LIKE :search OR p.brand LIKE :search";
            }
            
            $query .= " ORDER BY p.created_at DESC";
            
            $stmt = $this->conn->prepare($query);
            
            if (!empty($search)) {
                $searchParam = "%" . $search . "%";
                $stmt->bindParam(":search", $searchParam);
            }
            
            $stmt->execute();
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $this->formatResponse('success', $products);
            
        } catch(Exception $e) {
            return $this->formatResponse('error', null, 'Error al obtener productos: ' . $e->getMessage());
        }
    }
    
    // Obtener un producto por ID
    public function getProduct($id) {
        try {
            $query = "SELECT p.*, c.name as category_name 
                     FROM products p 
                     LEFT JOIN categories c ON p.category_id = c.id 
                     WHERE p.id = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $id);
            $stmt->execute();
            
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($product) {
                return $this->formatResponse('success', $product);
            } else {
                return $this->formatResponse('error', null, 'Producto no encontrado');
            }
            
        } catch(Exception $e) {
            return $this->formatResponse('error', null, 'Error al obtener producto: ' . $e->getMessage());
        }
    }
    
    // Crear nuevo producto
    public function createProduct($data) {
        try {
            // Obtener category_id
            $categoryId = $this->getCategoryId($data['category']);
            
            $query = "INSERT INTO products (name, category_id, price, stock, description, brand, model, sku, status) 
                     VALUES (:name, :category_id, :price, :stock, :description, :brand, :model, :sku, 'active')";
            
            $stmt = $this->conn->prepare($query);
            
            // Generar SKU si no se proporciona
            $sku = isset($data['sku']) ? $data['sku'] : $this->generateSKU($data['brand'], $data['name']);
            
            $stmt->bindParam(":name", $data['name']);
            $stmt->bindParam(":category_id", $categoryId);
            $stmt->bindParam(":price", $data['price']);
            $stmt->bindParam(":stock", $data['stock']);
            $stmt->bindParam(":description", $data['description']);
            $stmt->bindParam(":brand", $data['brand']);
            $stmt->bindParam(":model", $data['model']);
            $stmt->bindParam(":sku", $sku);
            
            if ($stmt->execute()) {
                $productId = $this->conn->lastInsertId();
                return $this->formatResponse('success', ['id' => $productId], 'Producto creado correctamente');
            }
            
        } catch(Exception $e) {
            return $this->formatResponse('error', null, 'Error al crear producto: ' . $e->getMessage());
        }
    }
    
    // Actualizar producto
    public function updateProduct($id, $data) {
        try {
            // Obtener category_id
            $categoryId = $this->getCategoryId($data['category']);
            
            $query = "UPDATE products SET 
                     name = :name, 
                     category_id = :category_id, 
                     price = :price, 
                     stock = :stock, 
                     description = :description, 
                     brand = :brand, 
                     model = :model
                     WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(":id", $id);
            $stmt->bindParam(":name", $data['name']);
            $stmt->bindParam(":category_id", $categoryId);
            $stmt->bindParam(":price", $data['price']);
            $stmt->bindParam(":stock", $data['stock']);
            $stmt->bindParam(":description", $data['description']);
            $stmt->bindParam(":brand", $data['brand']);
            $stmt->bindParam(":model", $data['model']);
            
            if ($stmt->execute()) {
                return $this->formatResponse('success', null, 'Producto actualizado correctamente');
            }
            
        } catch(Exception $e) {
            return $this->formatResponse('error', null, 'Error al actualizar producto: ' . $e->getMessage());
        }
    }
    
    // Eliminar producto
    public function deleteProduct($id) {
        try {
            $query = "DELETE FROM products WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $id);
            
            if ($stmt->execute()) {
                return $this->formatResponse('success', null, 'Producto eliminado correctamente');
            }
            
        } catch(Exception $e) {
            return $this->formatResponse('error', null, 'Error al eliminar producto: ' . $e->getMessage());
        }
    }
    
    // Obtener categorías
    public function getCategories() {
        try {
            $query = "SELECT * FROM categories ORDER BY name";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $this->formatResponse('success', $categories);
            
        } catch(Exception $e) {
            return $this->formatResponse('error', null, 'Error al obtener categorías: ' . $e->getMessage());
        }
    }
    
    // Actualizar stock
    public function updateStock($productId, $quantity, $movementType, $reason) {
        try {
            $query = "CALL UpdateStock(:product_id, :quantity, :movement_type, :reason)";
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(":product_id", $productId);
            $stmt->bindParam(":quantity", $quantity);
            $stmt->bindParam(":movement_type", $movementType);
            $stmt->bindParam(":reason", $reason);
            
            if ($stmt->execute()) {
                return $this->formatResponse('success', null, 'Stock actualizado correctamente');
            }
            
        } catch(Exception $e) {
            return $this->formatResponse('error', null, 'Error al actualizar stock: ' . $e->getMessage());
        }
    }
    
    // Funciones auxiliares
    private function getCategoryId($categoryName) {
        $query = "SELECT id FROM categories WHERE name = :name";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":name", $categoryName);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['id'] : null;
    }
    
    private function generateSKU($brand, $name) {
        $brandCode = strtoupper(substr($brand, 0, 3));
        $nameCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 3));
        $timestamp = time();
        return $brandCode . '-' . $nameCode . '-' . $timestamp;
    }
    
    private function formatResponse($status, $data = null, $message = '') {
        return [
            'status' => $status,
            'data' => $data,
            'message' => $message
        ];
    }
}

// Manejar las solicitudes HTTP
$api = new ProductsAPI();
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            echo json_encode($api->getProduct($_GET['id']));
        } elseif (isset($_GET['categories'])) {
            echo json_encode($api->getCategories());
        } else {
            $search = isset($_GET['search']) ? $_GET['search'] : '';
            echo json_encode($api->getProducts($search));
        }
        break;
        
    case 'POST':
        if (isset($input['action']) && $input['action'] === 'updateStock') {
            echo json_encode($api->updateStock(
                $input['productId'], 
                $input['quantity'], 
                $input['movementType'], 
                $input['reason']
            ));
        } else {
            echo json_encode($api->createProduct($input));
        }
        break;
        
    case 'PUT':
        $id = isset($_GET['id']) ? $_GET['id'] : null;
        if ($id) {
            echo json_encode($api->updateProduct($id, $input));
        }
        break;
        
    case 'DELETE':
        $id = isset($_GET['id']) ? $_GET['id'] : null;
        if ($id) {
            echo json_encode($api->deleteProduct($id));
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
        break;
}
?>