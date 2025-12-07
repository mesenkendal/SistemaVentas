/*
    Script de creacion del esquema para el sistema de ventas en MySQL.
    Ejecuta este archivo completo en el cliente mysql o alguna GUI compatible.
*/

CREATE DATABASE IF NOT EXISTS SistemaVentas
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE SistemaVentas;

/* Limpieza segura para permitir recrear las tablas desde cero */
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS DetallesVenta;
DROP TABLE IF EXISTS Ventas;
DROP TABLE IF EXISTS Inventario;
DROP TABLE IF EXISTS Usuarios;
DROP TABLE IF EXISTS RolVistas;
DROP TABLE IF EXISTS Vistas;
DROP TABLE IF EXISTS Roles;
DROP TABLE IF EXISTS Bitacora;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE Roles
(
    IdRol           TINYINT UNSIGNED    NOT NULL AUTO_INCREMENT,
    NombreRol       VARCHAR(30)         NOT NULL,
    Descripcion     VARCHAR(150)        NULL,
    Activo          TINYINT(1)          NOT NULL DEFAULT 1,
    CONSTRAINT PK_Roles PRIMARY KEY (IdRol),
    CONSTRAINT UQ_Roles_NombreRol UNIQUE (NombreRol),
    CONSTRAINT CK_Roles_Activo CHECK (Activo IN (0,1))
) ENGINE=InnoDB;

CREATE TABLE Vistas
(
    IdVista      TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    NombreVista  VARCHAR(50)      NOT NULL,
    Ruta         VARCHAR(100)     NOT NULL,
    Activo       TINYINT(1)       NOT NULL DEFAULT 1,
    CONSTRAINT PK_Vistas PRIMARY KEY (IdVista),
    CONSTRAINT UQ_Vistas_Ruta UNIQUE (Ruta),
    CONSTRAINT CK_Vistas_Activo CHECK (Activo IN (0,1))
) ENGINE=InnoDB;

CREATE TABLE RolVistas
(
    IdRol    TINYINT UNSIGNED NOT NULL,
    IdVista  TINYINT UNSIGNED NOT NULL,
    FechaAsignacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT PK_RolVistas PRIMARY KEY (IdRol, IdVista),
    CONSTRAINT FK_RolVistas_Roles FOREIGN KEY (IdRol) REFERENCES Roles(IdRol) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT FK_RolVistas_Vistas FOREIGN KEY (IdVista) REFERENCES Vistas(IdVista) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE Usuarios
(
    IdUsuario       INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    IdRol           TINYINT UNSIGNED    NOT NULL,
    NombreUsuario   VARCHAR(50)         NOT NULL,
    Apellido        VARCHAR(50)         NOT NULL,
    Clave           VARCHAR(255)        NOT NULL, -- almacenar aqui el hash (bcrypt, Argon2, etc.)
    FechaCreacion   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    Activo          TINYINT(1)          NOT NULL DEFAULT 1,
    CONSTRAINT PK_Usuarios PRIMARY KEY (IdUsuario),
    CONSTRAINT UQ_Usuarios_NombreUsuario UNIQUE (NombreUsuario),
    CONSTRAINT FK_Usuarios_Roles FOREIGN KEY (IdRol) REFERENCES Roles(IdRol) ON UPDATE CASCADE,
    CONSTRAINT CK_Usuarios_Activo CHECK (Activo IN (0,1))
) ENGINE=InnoDB;

CREATE TABLE Inventario
(
    CodigoProducto  INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    Nombre          VARCHAR(100)        NOT NULL,
    TipoVenta       VARCHAR(10)         NOT NULL,
    Precio          DECIMAL(10,2)       NOT NULL,
    Stock           DECIMAL(10,2)       NOT NULL,
    FechaActualiza  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    Activo          TINYINT(1)          NOT NULL DEFAULT 1,
    CONSTRAINT PK_Inventario PRIMARY KEY (CodigoProducto),
    CONSTRAINT CK_Inventario_Precio CHECK (Precio >= 0),
    CONSTRAINT CK_Inventario_Stock CHECK (Stock >= 0),
    CONSTRAINT CK_Inventario_TipoVenta CHECK (TipoVenta IN ('Kilo','Unidad')),
    CONSTRAINT CK_Inventario_Activo CHECK (Activo IN (0,1))
) ENGINE=InnoDB;

CREATE TABLE Ventas
(
    IdVenta     INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    Fecha       DATE                NOT NULL DEFAULT (CURRENT_DATE),
    Cliente     VARCHAR(100)        NULL,
    Total       DECIMAL(18,2)       NOT NULL,
    IdUsuario   INT UNSIGNED        NOT NULL,
    Activo      TINYINT(1)          NOT NULL DEFAULT 1,
    CONSTRAINT PK_Ventas PRIMARY KEY (IdVenta),
    CONSTRAINT FK_Ventas_Usuarios FOREIGN KEY (IdUsuario) REFERENCES Usuarios(IdUsuario) ON UPDATE CASCADE,
    CONSTRAINT CK_Ventas_Total CHECK (Total >= 0),
    CONSTRAINT CK_Ventas_Activo CHECK (Activo IN (0,1))
) ENGINE=InnoDB;

CREATE TABLE DetallesVenta
(
    IdDetalle       INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    IdVenta         INT UNSIGNED        NOT NULL,
    CodigoProducto  INT UNSIGNED        NOT NULL,
    Cantidad        DECIMAL(10,2)       NOT NULL,
    Precio          DECIMAL(18,2)       NOT NULL,
    Subtotal        DECIMAL(20,4)       AS (Cantidad * Precio) STORED,
    Activo          TINYINT(1)          NOT NULL DEFAULT 1,
    CONSTRAINT PK_DetallesVenta PRIMARY KEY (IdDetalle),
    CONSTRAINT FK_DetallesVenta_Ventas FOREIGN KEY (IdVenta) REFERENCES Ventas(IdVenta) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT FK_DetallesVenta_Inventario FOREIGN KEY (CodigoProducto) REFERENCES Inventario(CodigoProducto) ON UPDATE CASCADE,
    CONSTRAINT CK_DetallesVenta_Cantidad CHECK (Cantidad > 0),
    CONSTRAINT CK_DetallesVenta_Precio CHECK (Precio >= 0),
    CONSTRAINT CK_DetallesVenta_Activo CHECK (Activo IN (0,1))
) ENGINE=InnoDB;

/* Indices complementarios para agilizar filtros por llaves foraneas */
CREATE INDEX IX_Ventas_IdUsuario ON Ventas (IdUsuario);
CREATE INDEX IX_DetallesVenta_IdVenta ON DetallesVenta (IdVenta);
CREATE INDEX IX_DetallesVenta_CodigoProducto ON DetallesVenta (CodigoProducto);

/* Vista de apoyo para obtener cifras agregadas por venta */
CREATE OR REPLACE VIEW VentasConDetalle AS
SELECT
    v.IdVenta,
    v.Fecha,
    v.Cliente,
    v.Total,
    u.NombreUsuario,
    r.NombreRol,
    dv.Cantidad,
    dv.Precio,
    dv.Subtotal,
    i.Nombre AS Producto
FROM Ventas v
JOIN Usuarios u ON u.IdUsuario = v.IdUsuario
JOIN Roles r ON r.IdRol = u.IdRol
JOIN DetallesVenta dv ON dv.IdVenta = v.IdVenta
JOIN Inventario i ON i.CodigoProducto = dv.CodigoProducto
WHERE v.Activo = 1
    AND u.Activo = 1
    AND r.Activo = 1
    AND dv.Activo = 1
    AND i.Activo = 1;

CREATE TABLE Bitacora
(
        IdBitacora      BIGINT UNSIGNED    NOT NULL AUTO_INCREMENT,
        Tabla           VARCHAR(50)        NOT NULL,
        Accion          VARCHAR(20)        NOT NULL,
        RegistroId      BIGINT UNSIGNED    NOT NULL,
        Datos           JSON               NULL,
        IdUsuario       INT UNSIGNED       NULL,
        FechaEvento     DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT PK_Bitacora PRIMARY KEY (IdBitacora)
) ENGINE=InnoDB;

INSERT INTO Vistas (NombreVista, Ruta) VALUES
    ('Dashboard', 'index.php'),
    ('Inventario', 'inventario.php'),
    ('Ventas', 'ventas.php'),
    ('Usuarios', 'usuarios.php'),
    ('Reportes', 'reportes.php'),
    ('Permisos', 'permisos.php'),
    ('Acerca de', 'acerca.php');

/* =============================================================
   Procedimiento y triggers de auditoría para MySQL
   ------------------------------------------------
   - Registra automáticamente toda operación INSERT/UPDATE/DELETE
     ejecutada sobre las tablas principales.
   - Utiliza la variable de sesión opcional @app_user_id para
     identificar al usuario autenticado desde la aplicación.
   ============================================================= */

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_log_bitacora $$
CREATE PROCEDURE sp_log_bitacora (
    IN pTabla VARCHAR(50),
    IN pAccion VARCHAR(20),
    IN pRegistroId BIGINT UNSIGNED,
    IN pDatos JSON
)
BEGIN
    INSERT INTO Bitacora (Tabla, Accion, RegistroId, Datos, IdUsuario)
    VALUES (pTabla, pAccion, pRegistroId, pDatos, COALESCE(@app_user_id, NULL));
END$$

/* Roles */
DROP TRIGGER IF EXISTS trg_roles_ai $$
CREATE TRIGGER trg_roles_ai AFTER INSERT ON Roles
FOR EACH ROW
BEGIN
    CALL sp_log_bitacora('Roles', 'INSERT', NEW.IdRol,
        JSON_OBJECT('NombreRol', NEW.NombreRol, 'Descripcion', NEW.Descripcion, 'Activo', NEW.Activo));
END$$

DROP TRIGGER IF EXISTS trg_roles_au $$
CREATE TRIGGER trg_roles_au AFTER UPDATE ON Roles
FOR EACH ROW
BEGIN
    CALL sp_log_bitacora('Roles', 'UPDATE', NEW.IdRol,
        JSON_OBJECT('NombreRol', NEW.NombreRol, 'Descripcion', NEW.Descripcion, 'Activo', NEW.Activo));
END$$

DROP TRIGGER IF EXISTS trg_roles_ad $$
CREATE TRIGGER trg_roles_ad AFTER DELETE ON Roles
FOR EACH ROW
BEGIN
    CALL sp_log_bitacora('Roles', 'DELETE', OLD.IdRol,
        JSON_OBJECT('NombreRol', OLD.NombreRol, 'Descripcion', OLD.Descripcion, 'Activo', OLD.Activo));
END$$

/* Usuarios */
DROP TRIGGER IF EXISTS trg_usuarios_ai $$
CREATE TRIGGER trg_usuarios_ai AFTER INSERT ON Usuarios
FOR EACH ROW
BEGIN
    CALL sp_log_bitacora('Usuarios', 'INSERT', NEW.IdUsuario,
        JSON_OBJECT('IdRol', NEW.IdRol, 'NombreUsuario', NEW.NombreUsuario, 'Apellido', NEW.Apellido, 'Activo', NEW.Activo));
END$$

DROP TRIGGER IF EXISTS trg_usuarios_au $$
CREATE TRIGGER trg_usuarios_au AFTER UPDATE ON Usuarios
FOR EACH ROW
BEGIN
    CALL sp_log_bitacora('Usuarios', 'UPDATE', NEW.IdUsuario,
        JSON_OBJECT('IdRol', NEW.IdRol, 'NombreUsuario', NEW.NombreUsuario, 'Apellido', NEW.Apellido, 'Activo', NEW.Activo));
END$$

DROP TRIGGER IF EXISTS trg_usuarios_ad $$
CREATE TRIGGER trg_usuarios_ad AFTER DELETE ON Usuarios
FOR EACH ROW
BEGIN
    CALL sp_log_bitacora('Usuarios', 'DELETE', OLD.IdUsuario,
        JSON_OBJECT('IdRol', OLD.IdRol, 'NombreUsuario', OLD.NombreUsuario, 'Apellido', OLD.Apellido, 'Activo', OLD.Activo));
END$$

/* Inventario */
DROP TRIGGER IF EXISTS trg_inventario_ai $$
CREATE TRIGGER trg_inventario_ai AFTER INSERT ON Inventario
FOR EACH ROW
BEGIN
    CALL sp_log_bitacora('Inventario', 'INSERT', NEW.CodigoProducto,
        JSON_OBJECT('Nombre', NEW.Nombre, 'TipoVenta', NEW.TipoVenta, 'Precio', NEW.Precio, 'Stock', NEW.Stock, 'Activo', NEW.Activo));
END$$

DROP TRIGGER IF EXISTS trg_inventario_au $$
CREATE TRIGGER trg_inventario_au AFTER UPDATE ON Inventario
FOR EACH ROW
BEGIN
    CALL sp_log_bitacora('Inventario', 'UPDATE', NEW.CodigoProducto,
        JSON_OBJECT('Nombre', NEW.Nombre, 'TipoVenta', NEW.TipoVenta, 'Precio', NEW.Precio, 'Stock', NEW.Stock, 'Activo', NEW.Activo));
END$$

DROP TRIGGER IF EXISTS trg_inventario_ad $$
CREATE TRIGGER trg_inventario_ad AFTER DELETE ON Inventario
FOR EACH ROW
BEGIN
    CALL sp_log_bitacora('Inventario', 'DELETE', OLD.CodigoProducto,
        JSON_OBJECT('Nombre', OLD.Nombre, 'TipoVenta', OLD.TipoVenta, 'Precio', OLD.Precio, 'Stock', OLD.Stock, 'Activo', OLD.Activo));
END$$

/* Ventas */
DROP TRIGGER IF EXISTS trg_ventas_ai $$
CREATE TRIGGER trg_ventas_ai AFTER INSERT ON Ventas
FOR EACH ROW
BEGIN
    CALL sp_log_bitacora('Ventas', 'INSERT', NEW.IdVenta,
        JSON_OBJECT('Fecha', NEW.Fecha, 'Cliente', NEW.Cliente, 'Total', NEW.Total, 'IdUsuario', NEW.IdUsuario, 'Activo', NEW.Activo));
END$$

DROP TRIGGER IF EXISTS trg_ventas_au $$
CREATE TRIGGER trg_ventas_au AFTER UPDATE ON Ventas
FOR EACH ROW
BEGIN
    CALL sp_log_bitacora('Ventas', 'UPDATE', NEW.IdVenta,
        JSON_OBJECT('Fecha', NEW.Fecha, 'Cliente', NEW.Cliente, 'Total', NEW.Total, 'IdUsuario', NEW.IdUsuario, 'Activo', NEW.Activo));
END$$

DROP TRIGGER IF EXISTS trg_ventas_ad $$
CREATE TRIGGER trg_ventas_ad AFTER DELETE ON Ventas
FOR EACH ROW
BEGIN
    CALL sp_log_bitacora('Ventas', 'DELETE', OLD.IdVenta,
        JSON_OBJECT('Fecha', OLD.Fecha, 'Cliente', OLD.Cliente, 'Total', OLD.Total, 'IdUsuario', OLD.IdUsuario, 'Activo', OLD.Activo));
END$$

/* DetallesVenta */
DROP TRIGGER IF EXISTS trg_detalles_ai $$
CREATE TRIGGER trg_detalles_ai AFTER INSERT ON DetallesVenta
FOR EACH ROW
BEGIN
    CALL sp_log_bitacora('DetallesVenta', 'INSERT', NEW.IdDetalle,
        JSON_OBJECT('IdVenta', NEW.IdVenta, 'CodigoProducto', NEW.CodigoProducto, 'Cantidad', NEW.Cantidad, 'Precio', NEW.Precio, 'Activo', NEW.Activo));
END$$

DROP TRIGGER IF EXISTS trg_detalles_au $$
CREATE TRIGGER trg_detalles_au AFTER UPDATE ON DetallesVenta
FOR EACH ROW
BEGIN
    CALL sp_log_bitacora('DetallesVenta', 'UPDATE', NEW.IdDetalle,
        JSON_OBJECT('IdVenta', NEW.IdVenta, 'CodigoProducto', NEW.CodigoProducto, 'Cantidad', NEW.Cantidad, 'Precio', NEW.Precio, 'Activo', NEW.Activo));
END$$

DROP TRIGGER IF EXISTS trg_detalles_ad $$
CREATE TRIGGER trg_detalles_ad AFTER DELETE ON DetallesVenta
FOR EACH ROW
BEGIN
    CALL sp_log_bitacora('DetallesVenta', 'DELETE', OLD.IdDetalle,
        JSON_OBJECT('IdVenta', OLD.IdVenta, 'CodigoProducto', OLD.CodigoProducto, 'Cantidad', OLD.Cantidad, 'Precio', OLD.Precio, 'Activo', OLD.Activo));
END$$

/* Vistas */
DROP TRIGGER IF EXISTS trg_vistas_ai $$
CREATE TRIGGER trg_vistas_ai AFTER INSERT ON Vistas
FOR EACH ROW
BEGIN
    CALL sp_log_bitacora('Vistas', 'INSERT', NEW.IdVista,
        JSON_OBJECT('NombreVista', NEW.NombreVista, 'Ruta', NEW.Ruta, 'Activo', NEW.Activo));
END$$

DROP TRIGGER IF EXISTS trg_vistas_au $$
CREATE TRIGGER trg_vistas_au AFTER UPDATE ON Vistas
FOR EACH ROW
BEGIN
    CALL sp_log_bitacora('Vistas', 'UPDATE', NEW.IdVista,
        JSON_OBJECT('NombreVista', NEW.NombreVista, 'Ruta', NEW.Ruta, 'Activo', NEW.Activo));
END$$

DROP TRIGGER IF EXISTS trg_vistas_ad $$
CREATE TRIGGER trg_vistas_ad AFTER DELETE ON Vistas
FOR EACH ROW
BEGIN
    CALL sp_log_bitacora('Vistas', 'DELETE', OLD.IdVista,
        JSON_OBJECT('NombreVista', OLD.NombreVista, 'Ruta', OLD.Ruta, 'Activo', OLD.Activo));
END$$

/* RolVistas */
DROP TRIGGER IF EXISTS trg_rolvistas_ai $$
CREATE TRIGGER trg_rolvistas_ai AFTER INSERT ON RolVistas
FOR EACH ROW
BEGIN
    CALL sp_log_bitacora('RolVistas', 'INSERT', NEW.IdRol,
        JSON_OBJECT('IdVista', NEW.IdVista, 'FechaAsignacion', NEW.FechaAsignacion));
END$$

DROP TRIGGER IF EXISTS trg_rolvistas_au $$
CREATE TRIGGER trg_rolvistas_au AFTER UPDATE ON RolVistas
FOR EACH ROW
BEGIN
    CALL sp_log_bitacora('RolVistas', 'UPDATE', NEW.IdRol,
        JSON_OBJECT('IdVista', NEW.IdVista, 'FechaAsignacion', NEW.FechaAsignacion));
END$$

DROP TRIGGER IF EXISTS trg_rolvistas_ad $$
CREATE TRIGGER trg_rolvistas_ad AFTER DELETE ON RolVistas
FOR EACH ROW
BEGIN
    CALL sp_log_bitacora('RolVistas', 'DELETE', OLD.IdRol,
        JSON_OBJECT('IdVista', OLD.IdVista, 'FechaAsignacion', OLD.FechaAsignacion));
END$$

DELIMITER ;
