<?php

declare(strict_types=1);

namespace SistemaVentas\Models;

final class InventoryModel extends BaseModel
{
    private const TABLE = 'Inventario';

    public function all(): array
    {
        return $this->fetchAll('SELECT * FROM ' . self::TABLE . ' WHERE Activo = 1 ORDER BY Nombre');
    }

    public function find(int $codigo): ?array
    {
        return $this->fetchOne('SELECT * FROM ' . self::TABLE . ' WHERE CodigoProducto = ? AND Activo = 1', [$codigo]);
    }

    public function create(array $data, ?int $userId = null): int
    {
        $sql = 'INSERT INTO ' . self::TABLE . ' (Nombre, TipoVenta, Precio, Stock)
                VALUES (:Nombre, :TipoVenta, :Precio, :Stock)';
        $this->execute($sql, [
            ':Nombre'    => $data['Nombre'],
            ':TipoVenta' => $data['TipoVenta'],
            ':Precio'    => $data['Precio'],
            ':Stock'     => $data['Stock'],
        ]);
        $id = (int) $this->lastInsertId();
        $this->logAction(self::TABLE, 'CREAR', $id, $data, $userId);
        return $id;
    }

    public function update(int $codigo, array $data, ?int $userId = null): int
    {
        $fields = [];
        $params = [];
        foreach (['Nombre', 'TipoVenta', 'Precio', 'Stock'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        if (empty($fields)) {
            return 0;
        }
        $params[':CodigoProducto'] = $codigo;
        $sql = 'UPDATE ' . self::TABLE . ' SET ' . implode(', ', $fields) . ' WHERE CodigoProducto = :CodigoProducto AND Activo = 1';
        $rows = $this->execute($sql, $params);
        if ($rows > 0) {
            $this->logAction(self::TABLE, 'ACTUALIZAR', $codigo, $data, $userId);
        }
        return $rows;
    }

    public function adjustStock(int $codigo, float $cantidad, ?int $userId = null): int
    {
        $sql = 'UPDATE ' . self::TABLE . ' SET Stock = Stock + :Cantidad WHERE CodigoProducto = :CodigoProducto AND Activo = 1';
        $rows = $this->execute($sql, [
            ':Cantidad'        => $cantidad,
            ':CodigoProducto'  => $codigo,
        ]);
        if ($rows > 0) {
            $this->logAction(self::TABLE, 'AJUSTAR_STOCK', $codigo, ['delta' => $cantidad], $userId);
        }
        return $rows;
    }

    public function softDelete(int $codigo, ?int $userId = null): int
    {
        $rows = $this->execute('UPDATE ' . self::TABLE . ' SET Activo = 0 WHERE CodigoProducto = ? AND Activo = 1', [$codigo]);
        if ($rows > 0) {
            $this->logAction(self::TABLE, 'ELIMINAR', $codigo, [], $userId);
        }
        return $rows;
    }

    public function delete(int $codigo, ?int $userId = null): int
    {
        return $this->softDelete($codigo, $userId);
    }
}
