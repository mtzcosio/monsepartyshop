-- =========================================================
-- Monse Party Shop - Esquema de base de datos
-- Ejecutar directamente sobre la base de datos ya creada
-- en el panel de hosting (no requiere CREATE DATABASE).
-- =========================================================

-- ---------------------------------------------------------
-- Configuración de marca (editable desde el admin)
-- ---------------------------------------------------------
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Categorías (administrables sin tocar código)
-- ---------------------------------------------------------
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    icon VARCHAR(20) DEFAULT '',
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Productos digitales
-- ---------------------------------------------------------
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    short_description VARCHAR(255) DEFAULT '',
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    old_price DECIMAL(10,2) DEFAULT NULL,
    image VARCHAR(255) DEFAULT '',
    is_new TINYINT(1) DEFAULT 0,
    is_bestseller TINYINT(1) DEFAULT 0,
    is_kit TINYINT(1) DEFAULT 0,
    is_offer TINYINT(1) DEFAULT 0,
    rating DECIMAL(2,1) DEFAULT 5.0,
    what_includes TEXT,
    show_what_includes TINYINT(1) DEFAULT 1,
    what_for TEXT,
    show_what_for TINYINT(1) DEFAULT 1,
    what_you_need TEXT,
    show_what_you_need TINYINT(1) DEFAULT 1,
    how_to_use TEXT,
    show_how_to_use TINYINT(1) DEFAULT 1,
    difficulty_level VARCHAR(50) DEFAULT '',
    file_format VARCHAR(50) DEFAULT '',
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Relación producto <-> categorías (un producto puede
-- pertenecer a varias categorías)
-- ---------------------------------------------------------
CREATE TABLE product_categories (
    product_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (product_id, category_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Galería de imágenes adicionales por producto
-- ---------------------------------------------------------
CREATE TABLE product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Archivos descargables por producto (un producto puede
-- tener más de un archivo, ej. PDF + Canva + PNG)
-- ---------------------------------------------------------
CREATE TABLE product_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    label VARCHAR(150) DEFAULT '',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Servicios (renta de mobiliario, decoración, cabinas, etc.)
-- A diferencia de los productos, no se compran por carrito/checkout:
-- se muestran como catálogo y el cliente solicita cotización por
-- contacto.php (ver reason=cotizacion en contact_reason_options()).
-- ---------------------------------------------------------
CREATE TABLE services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    short_description VARCHAR(255) DEFAULT '',
    description TEXT,
    price_from DECIMAL(10,2) DEFAULT NULL,
    image VARCHAR(255) DEFAULT '',
    sort_order INT DEFAULT 0,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Galería de imágenes adicionales por servicio
-- ---------------------------------------------------------
CREATE TABLE service_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    image VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Pedidos
-- ---------------------------------------------------------
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_code VARCHAR(20) NOT NULL UNIQUE,
    customer_name VARCHAR(150) NOT NULL,
    customer_email VARCHAR(150) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    tax DECIMAL(10,2) DEFAULT 0,
    total DECIMAL(10,2) NOT NULL,
    status ENUM('pending','paid','cancelled') DEFAULT 'pending',
    download_token VARCHAR(64) DEFAULT NULL,
    payment_method VARCHAR(20) DEFAULT 'card',
    payment_gateway VARCHAR(20) DEFAULT 'demo',
    conekta_order_id VARCHAR(60) DEFAULT NULL,
    stripe_session_id VARCHAR(120) DEFAULT NULL,
    payment_reference VARCHAR(120) DEFAULT NULL,
    payment_expires_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(150) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    quantity INT DEFAULT 1,
    download_count INT DEFAULT 0,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Plantillas de correo (reutilizables, con variables {{...}})
-- ---------------------------------------------------------
CREATE TABLE email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(100) NOT NULL UNIQUE,
    type VARCHAR(50) NOT NULL DEFAULT 'personalizado',
    status ENUM('draft','active','inactive') NOT NULL DEFAULT 'draft',
    sender_name VARCHAR(150) DEFAULT '',
    sender_email VARCHAR(150) DEFAULT '',
    subject VARCHAR(255) NOT NULL DEFAULT '',
    preheader VARCHAR(255) DEFAULT '',
    content_html LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Variables dinámicas personalizadas, agregadas por el admin
-- (se combinan con el catálogo integrado en admin/includes/email_variables.php)
-- ---------------------------------------------------------
CREATE TABLE email_custom_variables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(100) NOT NULL,
    name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT '',
    sample_value VARCHAR(255) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Biblioteca de archivos (repositorio reutilizable de imágenes/archivos)
-- ---------------------------------------------------------
CREATE TABLE media_library (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(20) NOT NULL,
    mime_type VARCHAR(100) DEFAULT '',
    file_size INT DEFAULT 0,
    category VARCHAR(100) NOT NULL DEFAULT 'Sin categoría',
    alt_text VARCHAR(255) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Mensajes del formulario de contacto público (contacto.php)
-- ---------------------------------------------------------
CREATE TABLE contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(30) DEFAULT '',
    company VARCHAR(150) DEFAULT '',
    reason VARCHAR(30) NOT NULL,
    message TEXT NOT NULL,
    preferred_contact VARCHAR(20) DEFAULT '',
    status ENUM('new','read','archived') NOT NULL DEFAULT 'new',
    ip_address VARCHAR(45) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Administradores
-- ---------------------------------------------------------
CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) DEFAULT NULL UNIQUE,
    phone VARCHAR(20) DEFAULT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(100) DEFAULT '',
    role ENUM('super_admin', 'admin', 'editor') NOT NULL DEFAULT 'admin',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    failed_attempts INT DEFAULT 0,
    locked_until DATETIME DEFAULT NULL,
    last_login_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Solicitudes de recuperación de contraseña del admin (tokens de un solo uso).
-- Se guarda el hash SHA-256 del token, nunca el token crudo (ese solo viaja en el
-- correo), para que una fuga de la BD no sirva por sí sola para tomar la cuenta.
CREATE TABLE admin_password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_user_id INT NOT NULL,
    token_hash VARCHAR(64) NOT NULL UNIQUE,
    ip_address VARCHAR(45) DEFAULT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- DATOS SEMILLA
-- =========================================================

-- Usuario admin por defecto -> usuario: admin (cambiar la contraseña tras el primer login)
INSERT INTO admin_users (username, password_hash, name, role) VALUES
('admin', '$2y$10$PryU.sHGH8VzyMU01R2JrOlAsCL04TWG5mpVikakhxhujvvWNwwrG', 'Monse', 'super_admin');

-- Configuración de marca
INSERT INTO settings (setting_key, setting_value) VALUES
('store_name', 'Monse Party Shop'),
('store_description', 'Todo lo que necesitas para crear momentos especiales.'),
('logo', ''),
('favicon', ''),
('primary_color', '#FF6F91'),
('secondary_color', '#FFC75F'),
('accent_color', '#845EC2'),
('email', 'hola@monsepartyshop.com'),
('phone', ''),
('whatsapp', ''),
('business_hours', ''),
('address', ''),
('recaptcha_enabled', '0'),
('recaptcha_site_key', ''),
('recaptcha_secret_key', ''),
('instagram', ''),
('facebook', ''),
('tiktok', ''),
('tax_rate', '0'),
('currency', 'MXN'),
('download_expiration', '30'),
('max_downloads', '5'),
('smtp_enabled', '0'),
('smtp_host', ''),
('smtp_port', '587'),
('smtp_username', ''),
('smtp_password', ''),
('smtp_encryption', 'tls'),
('conekta_enabled', '0'),
('conekta_card_enabled', '1'),
('conekta_oxxo_enabled', '1'),
('conekta_spei_enabled', '1'),
('conekta_public_key', ''),
('conekta_private_key', ''),
('stripe_enabled', '0'),
('stripe_card_enabled', '1'),
('stripe_publishable_key', ''),
('stripe_secret_key', ''),
('stripe_webhook_secret', ''),
('stripe_oxxo_enabled', '0'),
('stripe_spei_enabled', '0');

-- Categorías iniciales
INSERT INTO categories (name, slug, icon, sort_order) VALUES
('Baby Shower', 'baby-shower', '🎀', 1),
('Cumpleaños', 'cumpleanos', '🎂', 2),
('Regalos', 'regalos', '🎁', 3),
('Cajitas', 'cajitas', '📦', 4),
('Mesa de Dulces', 'mesa-de-dulces', '🍬', 5),
('Decoración', 'decoracion', '🎉', 6),
('Invitaciones', 'invitaciones', '💌', 7),
('Etiquetas', 'etiquetas', '🏷️', 8),
('Fiestas', 'fiestas', '🎈', 9),
('Kits y Packs', 'kits-y-packs', '⭐', 10);

-- Productos de ejemplo
INSERT INTO products (name, slug, short_description, description, price, old_price, image, is_new, is_bestseller, is_kit, is_offer, rating, what_includes, what_for, what_you_need, how_to_use, difficulty_level, file_format) VALUES
('Cajita Osito Baby', 'cajita-osito-baby', 'Una linda cajita para dulces y detalles de Baby Shower.', 'Una linda cajita para colocar dulces, chocolates o pequeños detalles en tu Baby Shower.', 49.00, 69.00, '', 1, 1, 0, 1, 5.0,
 'Plantilla digital en PDF lista para imprimir, con líneas de corte y doblado marcadas.',
 'Perfecta para colocar dulces, chocolates o pequeños regalos en tu Baby Shower.',
 'Impresora, papel cartulina, tijeras y pegamento.',
 'Descarga el archivo, imprímelo en cartulina, recorta por las líneas marcadas y arma siguiendo los dobleces.',
 'Fácil', 'PDF'),
('Kit Cumpleaños Arcoíris', 'kit-cumpleanos-arcoiris', 'Set completo de decoración para cumpleaños con temática arcoíris.', 'Set completo de decoración para cumpleaños con temática arcoíris: banderines, topper de pastel y etiquetas.', 99.00, 149.00, '', 0, 1, 1, 1, 4.5,
 'Banderines, topper para pastel, etiquetas para dulces y tarjetas de invitación.',
 'Ideal para decorar la mesa principal y la mesa de dulces de un cumpleaños.',
 'Impresora, cartulina o papel fotográfico, tijeras.',
 'Descarga el kit, imprime cada elemento y sigue las guías de armado incluidas.',
 'Intermedio', 'PDF + PNG'),
('Invitación Digital Unicornio', 'invitacion-digital-unicornio', 'Invitación editable con temática de unicornio.', 'Invitación digital editable con temática de unicornio, ideal para fiestas infantiles.', 39.00, NULL, '', 1, 0, 0, 0, 5.0,
 'Archivo editable en Canva + versión para imprimir en PDF.',
 'Enviar por WhatsApp o redes sociales, o imprimir para entregar en mano.',
 'Cuenta gratuita de Canva (para editar) o impresora (para imprimir).',
 'Abre el enlace de edición, cambia los datos de tu evento y descarga o comparte.',
 'Fácil', 'Canva + PDF'),
('Etiquetas Mesa de Dulces Floral', 'etiquetas-mesa-dulces-floral', 'Set de etiquetas florales para mesa de dulces.', 'Set de etiquetas florales para identificar los dulces y postres de tu mesa de dulces.', 29.00, NULL, '', 0, 0, 0, 0, 4.8,
 '12 diseños de etiquetas en PDF listas para imprimir y recortar.',
 'Identificar dulces, postres y bebidas en la mesa de dulces de tu evento.',
 'Impresora, papel adhesivo o cartulina, tijeras.',
 'Imprime, recorta cada etiqueta y colócala en tus recipientes o dulces.',
 'Fácil', 'PDF');

-- Asignar categorías a los productos de ejemplo (relación muchos-a-muchos)
INSERT INTO product_categories (product_id, category_id)
SELECT p.id, c.id FROM products p, categories c WHERE p.slug='cajita-osito-baby' AND c.slug='baby-shower';
INSERT INTO product_categories (product_id, category_id)
SELECT p.id, c.id FROM products p, categories c WHERE p.slug='kit-cumpleanos-arcoiris' AND c.slug='cumpleanos';
INSERT INTO product_categories (product_id, category_id)
SELECT p.id, c.id FROM products p, categories c WHERE p.slug='kit-cumpleanos-arcoiris' AND c.slug='kits-y-packs';
INSERT INTO product_categories (product_id, category_id)
SELECT p.id, c.id FROM products p, categories c WHERE p.slug='invitacion-digital-unicornio' AND c.slug='invitaciones';
INSERT INTO product_categories (product_id, category_id)
SELECT p.id, c.id FROM products p, categories c WHERE p.slug='etiquetas-mesa-dulces-floral' AND c.slug='mesa-de-dulces';
