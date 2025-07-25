// server.js - Backend API para TechStore
const express = require('express');
const mysql = require('mysql2/promise');
const cors = require('cors');
const path = require('path');
require('dotenv').config();

const app = express();
const PORT = process.env.PORT || 3000;

// Middlewares
app.use(cors());
app.use(express.json());
app.use(express.static('public'));

// Configuración de la base de datos
const dbConfig = {
    host: process.env.DB_HOST || 'localhost',
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME || 'techstore',
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
};

// Pool de conexiones
const pool = mysql.createPool(dbConfig);

// Middleware para manejo de errores de base de datos
const handleDbError = (error, res) => {
    console.error('Database error:', error);
    res.status(500).json({ 
        error: 'Error de base de datos', 
        message: error.message 
    });
};

// RUTAS DE PRODUCTOS

// Obtener todos los productos
app.get('/api/products', async (req, res) => {
    try {
        const { search, category, status } = req.query;
        let query = `
            SELECT 
                p.id,
                p.name,
                c.name as category,
                p.price,
                p.stock,
                p.description,
                p.brand,
                p.model,
                p.sku,
                p.status,
                p.created_at,
                p.updated_at
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE 1=1
        `;
        const params = [];

        if (search) {
            query += ` AND (p.name LIKE ? OR p.brand LIKE ? OR p.model LIKE ?)`;
            params.push(`%${search}%`, `%${search}%`, `%${search}%`);
        }

        if (category) {
            query += ` AND c.name = ?`;
            params.push(category);
        }

        if (status) {
            query += ` AND p.status = ?`;
            params.push(status);
        }

        query += ` ORDER BY p.created_at DESC`;

        const [rows] = await pool.execute(query, params);
        res.json(rows);
    } catch (error) {
        handleDbError(error, res);
    }
});

// Obtener un producto por ID
app.get('/api/products/:id', async (req, res) => {
    try {
        const { id } = req.params;
        const [rows] = await pool.execute(`
            SELECT 
                p.id,
                p.name,
                c.name as category,
                p.price,
                p.stock,
                p.description,
                p.brand,
                p.model,
                p.sku,
                p.status,
                p.created_at,
                p.updated_at
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.id = ?
        `, [id]);

        if (rows.length === 0) {
            return res.status(404).json({ error: 'Producto no encontrado' });
        }

        res.json(rows[0]);
    } catch (error) {
        handleDbError(error, res);
    }
});

// Crear un nuevo producto
app.post('/api/products', async (req, res) => {
    try {
        const { name, category, price, stock, description, brand, model, sku } = req.body;

        // Validaciones
        if (!name || !price || stock === undefined) {
            return res.status(400).json({ 
                error: 'Campos requeridos: name, price, stock' 
            });
        }

        // Obtener category_id
        let categoryId = null;
        if (category) {
            const [categoryRows] = await pool.execute(
                'SELECT id FROM categories WHERE name = ?',
                [category]
            );
            if (categoryRows.length > 0) {
                categoryId = categoryRows[0].id;
            }
        }

        // Insertar producto
        const [result] = await pool.execute(`
            INSERT INTO products (name, category_id, price, stock, description, brand, model, sku)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        `, [name, categoryId, price, stock, description, brand, model, sku]);

        // Obtener el producto creado
        const [newProduct] = await pool.execute(`
            SELECT 
                p.id,
                p.name,
                c.name as category,
                p.price,
                p.stock,
                p.description,
                p.brand,
                p.model,
                p.sku,
                p.status,
                p.created_at,
                p.updated_at
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.id = ?
        `, [result.insertId]);

        res.status(201).json(newProduct[0]);
    } catch (error) {
        if (error.code === 'ER_DUP_ENTRY') {
            res.status(400).json({ error: 'SKU ya existe' });
        } else {
            handleDbError(error, res);
        }
    }
});

// Actualizar un producto
app.put('/api/products/:id', async (req, res) => {
    try {
        const { id } = req.params;
        const { name, category, price, stock, description, brand, model, sku, status } = req.body;

        // Obtener category_id
        let categoryId = null;
        if (category) {
            const [categoryRows] = await pool.execute(
                'SELECT id FROM categories WHERE name = ?',
                [category]
            );
            if (categoryRows.length > 0) {
                categoryId = categoryRows[0].id;
            }
        }

        // Actualizar producto
        const [result] = await pool.execute(`
            UPDATE products 
            SET name = ?, category_id = ?, price = ?, stock = ?, 
                description = ?, brand = ?, model = ?, sku = ?, status = ?
            WHERE id = ?
        `, [name, categoryId, price, stock, description, brand, model, sku, status || 'active', id]);

        if (result.affectedRows === 0) {
            return res.status(404).json({ error: 'Producto no encontrado' });
        }

        // Obtener el producto actualizado
        const [updatedProduct] = await pool.execute(`
            SELECT 
                p.id,
                p.name,
                c.name as category,
                p.price,
                p.stock,
                p.description,
                p.brand,
                p.model,
                p.sku,
                p.status,
                p.created_at,
                p.updated_at
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.id = ?
        `, [id]);

        res.json(updatedProduct[0]);
    } catch (error) {
        handleDbError(error, res);
    }
});

// Eliminar un producto
app.delete('/api/products/:id', async (req, res) => {
    try {
        const { id } = req.params;
        const [result] = await pool.execute('DELETE FROM products WHERE id = ?', [id]);

        if (result.affectedRows === 0) {
            return res.status(404).json({ error: 'Producto no encontrado' });
        }

        res.json({ message: 'Producto eliminado exitosamente' });
    } catch (error) {
        handleDbError(error, res);
    }
});

// RUTAS DE CATEGORÍAS

// Obtener todas las categorías
app.get('/api/categories', async (req, res) => {
    try {
        const [rows] = await pool.execute('SELECT * FROM categories ORDER BY name');
        res.json(rows);
    } catch (error) {
        handleDbError(error, res);
    }
});

// Crear una nueva categoría
app.post('/api/categories', async (req, res) => {
    try {
        const { name, description } = req.body;
        
        if (!name) {
            return res.status(400).json({ error: 'El nombre es requerido' });
        }

        const [result] = await pool.execute(
            'INSERT INTO categories (name, description) VALUES (?, ?)',
            [name, description]
        );

        const [newCategory] = await pool.execute(
            'SELECT * FROM categories WHERE id = ?',
            [result.insertId]
        );

        res.status(201).json(newCategory[0]);
    } catch (error) {
        if (error.code === 'ER_DUP_ENTRY') {
            res.status(400).json({ error: 'La categoría ya existe' });
        } else {
            handleDbError(error, res);
        }
    }
});

// RUTAS DE INVENTARIO

// Actualizar stock de un producto
app.post('/api/products/:id/stock', async (req, res) => {
    try {
        const { id } = req.params;
        const { quantity, movementType, reason } = req.body;

        if (!quantity || !movementType) {
            return res.status(400).json({ 
                error: 'Campos requeridos: quantity, movementType' 
            });
        }

        // Usar el procedimiento almacenado
        await pool.execute(
            'CALL UpdateStock(?, ?, ?, ?)',
            [id, quantity, movementType, reason]
        );

        // Obtener el producto actualizado
        const [updatedProduct] = await pool.execute(`
            SELECT 
                p.id,
                p.name,
                c.name as category,
                p.price,
                p.stock,
                p.description,
                p.brand,
                p.model,
                p.sku,
                p.status,
                p.created_at,
                p.updated_at
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.id = ?
        `, [id]);

        res.json(updatedProduct[0]);
    } catch (error) {
        if (error.message === 'Stock insuficiente') {
            res.status(400).json({ error: 'Stock insuficiente' });
        } else {
            handleDbError(error, res);
        }
    }
});

// Obtener movimientos de inventario
app.get('/api/inventory/movements', async (req, res) => {
    try {
        const { productId, limit = 100 } = req.query;
        
        let query = `
            SELECT 
                im.*,
                p.name as product_name,
                p.sku
            FROM inventory_movements im
            JOIN products p ON im.product_id = p.id
            WHERE 1=1
        `;
        const params = [];

        if (productId) {
            query += ` AND im.product_id = ?`;
            params.push(productId);
        }

        query += ` ORDER BY im.created_at DESC LIMIT ?`;
        params.push(parseInt(limit));

        const [rows] = await pool.execute(query, params);
        res.json(rows);
    } catch (error) {
        handleDbError(error, res);
    }
});

// RUTAS DE REPORTES

// Productos con bajo stock
app.get('/api/reports/low-stock', async (req, res) => {
    try {
        const [rows] = await pool.execute(`
            SELECT * FROM low_stock_products
            ORDER BY stock ASC
        `);
        res.json(rows);
    } catch (error) {
        handleDbError(error, res);
    }
});

// Resumen de inventario
app.get('/api/reports/inventory-summary', async (req, res) => {
    try {
        const [summary] = await pool.execute(`
            SELECT 
                COUNT(*) as total_products,
                SUM(stock) as total_stock,
                SUM(price * stock) as total_value,
                COUNT(CASE WHEN stock <= 5 THEN 1 END) as low_stock_products,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_products,
                COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_products
            FROM products
        `);

        const [categoryStats] = await pool.execute(`
            SELECT 
                c.name as category,
                COUNT(p.id) as product_count,
                SUM(p.stock) as total_stock,
                SUM(p.price * p.stock) as category_value
            FROM categories c
            LEFT JOIN products p ON c.id = p.category_id
            GROUP BY c.id, c.name
            ORDER BY category_value DESC
        `);

        res.json({
            summary: summary[0],
            categoryStats
        });
    } catch (error) {
        handleDbError(error, res);
    }
});

// Ruta para servir el archivo HTML
app.get('/', (req, res) => {
    res.sendFile(path.join(__dirname, 'public', 'index.html'));
});

// Middleware de manejo de errores global
app.use((err, req, res, next) => {
    console.error(err.stack);
    res.status(500).json({ error: 'Error interno del servidor' });
});

// Iniciar servidor
app.listen(PORT, () => {
    console.log(`Servidor ejecutándose en http://localhost:${PORT}`);
});

// Manejo de cierre graceful
process.on('SIGINT', async () => {
    console.log('Cerrando servidor...');
    await pool.end();
    process.exit(0);
});

module.exports = app;