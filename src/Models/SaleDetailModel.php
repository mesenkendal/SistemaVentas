<?php

declare(strict_types=1);

namespace SistemaVentas\Models;

final class SaleDetailModel extends BaseModel
{
    private const TABLE = 'DetallesVenta';

    public function forSale(int $ventaId): array
    {
        $sql = 'SELECT dv.*, i.Nombre FROM ' . self::TABLE . ' dv
                JOIN Inventario i ON i.CodigoProducto = dv.CodigoProducto
                WHERE dv.IdVenta = ? AND dv.Activo = 1 AND i.Activo = 1';
        return $this->fetchAll($sql, [$ventaId]);
    }

    public function softDelete(int $detalleId, ?int $userId = null): int
    {
        $rows = $this->execute('UPDATE ' . self::TABLE . ' SET Activo = 0 WHERE IdDetalle = ? AND Activo = 1', [$detalleId]);
        if ($rows > 0) {
            $this->logAction(self::TABLE, 'ELIMINAR', $detalleId, [], $userId);
        }
        return $rows;
    }

    public function delete(int $detalleId, ?int $userId = null): int
    {
        return $this->softDelete($detalleId, $userId);
    }
}
