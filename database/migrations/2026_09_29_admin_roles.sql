-- Roles y estado para usuarios del panel (sección Usuarios).
-- Ejecutar UNA sola vez sobre la base existente. Es compatible con el código anterior
-- (solo agrega columnas), así que se puede correr antes de subir los archivos nuevos.

ALTER TABLE admin_users
    ADD COLUMN role ENUM('super_admin', 'admin', 'editor') NOT NULL DEFAULT 'admin' AFTER name,
    ADD COLUMN status ENUM('active', 'inactive') NOT NULL DEFAULT 'active' AFTER role,
    ADD COLUMN last_login_at DATETIME DEFAULT NULL AFTER locked_until;

-- Las cuentas que ya existían conservan acceso total (incluida la gestión de usuarios).
UPDATE admin_users SET role = 'super_admin';
