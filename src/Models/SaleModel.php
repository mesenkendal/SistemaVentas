<?php

declare(strict_types=1);

namespace SistemaVentas\Models;

use PDOException;
use SistemaVentas\Models\InventoryModel;

final class SaleModel extends BaseModel
{
    private const TABLE = 'Ventas';

    public function all(): array
    {
        $sql = 'SELECT v.*, u.NombreUsuario FROM ' . self::TABLE . ' v
            JOIN Usuarios u ON u.IdUsuario = v.IdUsuario
            WHERE v.Activo = 1 AND u.Activo = 1
                ORDER BY v.Fecha DESC, v.IdVenta DESC';
        return $this->fetchAll($sql);
    }

    public function find(int $id): ?array
    {
        $sql = 'SELECT v.*, u.NombreUsuario FROM ' . self::TABLE . ' v
                JOIN Usuarios u ON u.IdUsuario = v.IdUsuario
            WHERE v.IdVenta = ? AND v.Activo = 1 AND u.Activo = 1';
        return $this->fetchOne($sql, [$id]);
    }

    /**
     * Crea una venta y sus detalles en una transacción.
     *
     * @param array $ventaData datos de la tabla Ventas (Fecha, Cliente, Total, IdUsuario)
     * @param array $detalles  array de items con keys CodigoProducto, Cantidad, Precio
     */
    public function createWithDetails(array $ventaData, array $detalles, ?int $userId = null): int
    {
        $this->db->beginTransaction();
        try {
            $this->execute(
                'INSERT INTO ' . self::TABLE . ' (Fecha, Cliente, Total, IdUsuario)
                VALUES (:Fecha, :Cliente, :Total, :IdUsuario)',
                [
                    ':Fecha'     => $ventaData['Fecha'],
                    ':Cliente'   => $ventaData['Cliente'] ?? null,
                    ':Total'     => $ventaData['Total'],
                    ':IdUsuario' => $ventaData['IdUsuario'],
                ]
            );
            $ventaId = (int) $this->lastInsertId();

            $detalleSql = 'INSERT INTO DetallesVenta (IdVenta, CodigoProducto, Cantidad, Precio)
                           VALUES (:IdVenta, :CodigoProducto, :Cantidad, :Precio)';
            foreach ($detalles as $detalle) {
                $params = [
                    ':IdVenta'        => $ventaId,
                    ':CodigoProducto' => $detalle['CodigoProducto'],
                    ':Cantidad'       => $detalle['Cantidad'],
                    ':Precio'         => $detalle['Precio'],
                ];
                $this->execute($detalleSql, $params);
                $this->logAction('DetallesVenta', 'CREAR', (int) $this->lastInsertId(), $detalle, $userId);
            }

            // Descontar stock por cada detalle
            $this->adjustInventoryStock($detalles, -1.0, $userId);

            $this->db->commit();
            $this->logAction(self::TABLE, 'CREAR', $ventaId, $ventaData, $userId);
            return $ventaId;
        } catch (PDOException $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updateWithDetails(int $ventaId, array $ventaData, array $detalles, ?int $userId = null): int
    {
        $this->db->beginTransaction();
        try {
            // Reponer stock de los detalles anteriores antes de reemplazar
            $oldDetails = $this->getActiveDetails($ventaId);
            if (!empty($oldDetails)) {
                $this->adjustInventoryStock($oldDetails, +1.0, $userId);
            }

            $rows = $this->execute(
                'UPDATE ' . self::TABLE . ' SET Fecha = :Fecha, Cliente = :Cliente, Total = :Total, IdUsuario = :IdUsuario
                 WHERE IdVenta = :IdVenta AND Activo = 1',
                [
                    ':Fecha'     => $ventaData['Fecha'],
                    ':Cliente'   => $ventaData['Cliente'] ?? null,
                    ':Total'     => $ventaData['Total'],
                    ':IdUsuario' => $ventaData['IdUsuario'],
                    ':IdVenta'   => $ventaId,
                ]
            );

            if ($rows === 0) {
                $this->db->rollBack();
                return 0;
            }

            $this->execute('UPDATE DetallesVenta SET Activo = 0 WHERE IdVenta = :IdVenta AND Activo = 1', [':IdVenta' => $ventaId]);

            $detalleSql = 'INSERT INTO DetallesVenta (IdVenta, CodigoProducto, Cantidad, Precio)
                           VALUES (:IdVenta, :CodigoProducto, :Cantidad, :Precio)';
            foreach ($detalles as $detalle) {
                $params = [
                    ':IdVenta'        => $ventaId,
                    ':CodigoProducto' => $detalle['CodigoProducto'],
                    ':Cantidad'       => $detalle['Cantidad'],
                    ':Precio'         => $detalle['Precio'],
                ];
                $this->execute($detalleSql, $params);
                $this->logAction('DetallesVenta', 'ACTUALIZAR_DESDE_VENTA', (int) $this->lastInsertId(), $detalle, $userId);
            }

            // Descontar stock con los nuevos detalles
            $this->adjustInventoryStock($detalles, -1.0, $userId);

            $this->db->commit();
            $this->logAction(self::TABLE, 'ACTUALIZAR', $ventaId, $ventaData, $userId);
            return $rows;
        } catch (PDOException $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function softDelete(int $id, ?int $userId = null): int
    {
        $this->db->beginTransaction();
        try {
            // Antes de desactivar la venta, reponer stock de sus detalles activos
            $oldDetails = $this->getActiveDetails($id);
            if (!empty($oldDetails)) {
                $this->adjustInventoryStock($oldDetails, +1.0, $userId);
            }

            $rows = $this->execute('UPDATE ' . self::TABLE . ' SET Activo = 0 WHERE IdVenta = ? AND Activo = 1', [$id]);
            if ($rows > 0) {
                $this->execute('UPDATE DetallesVenta SET Activo = 0 WHERE IdVenta = ? AND Activo = 1', [$id]);
                $this->logAction(self::TABLE, 'ELIMINAR', $id, [], $userId);
                $this->logAction('DetallesVenta', 'ELIMINAR_POR_VENTA', $id, ['IdVenta' => $id], $userId);
            }
            $this->db->commit();
            return $rows;
        } catch (PDOException $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function delete(int $id, ?int $userId = null): int
    {
        return $this->softDelete($id, $userId);
    }

    /**
     * Obtiene los detalles activos de una venta (CodigoProducto, Cantidad).
     *
     * @return array<int, array{CodigoProducto:int,Cantidad:float}>
     */
    private function getActiveDetails(int $ventaId): array
    {
        return $this->fetchAll(
            'SELECT CodigoProducto, Cantidad FROM DetallesVenta WHERE IdVenta = :IdVenta AND Activo = 1',
            [':IdVenta' => $ventaId]
        );
    }

    /**
     * Ajusta stock del inventario según los detalles de venta.
     * sign = -1 descuenta; sign = +1 repone.
     */
    private function adjustInventoryStock(array $detalles, float $sign, ?int $userId = null): void
    {
        $inventory = new InventoryModel();
        foreach ($detalles as $detalle) {
            $codigo = (int) $detalle['CodigoProducto'];
            $cantidad = (float) $detalle['Cantidad'] * $sign;
            $inventory->adjustStock($codigo, $cantidad, $userId);
        }
    }
}
