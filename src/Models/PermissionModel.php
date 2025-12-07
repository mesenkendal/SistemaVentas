<?php

declare(strict_types=1);

namespace SistemaVentas\Models;

use PDOException;

final class PermissionModel extends BaseModel
{
    public function getAllViews(): array
    {
        $sql = 'SELECT IdVista, NombreVista, Ruta, Activo FROM Vistas WHERE Activo = 1 ORDER BY NombreVista';
        return $this->fetchAll($sql);
    }

    public function getViewIdsByRole(int $roleId): array
    {
        $sql = 'SELECT IdVista FROM RolVistas WHERE IdRol = :rol';
        $rows = $this->fetchAll($sql, [':rol' => $roleId]);
        return array_map(static fn(array $row): int => (int) $row['IdVista'], $rows);
    }

    public function getAllowedRoutesForRole(int $roleId): array
    {
        $sql = 'SELECT v.Ruta
                FROM RolVistas rv
                JOIN Vistas v ON v.IdVista = rv.IdVista
                WHERE rv.IdRol = :rol AND v.Activo = 1';
        $rows = $this->fetchAll($sql, [':rol' => $roleId]);
        $routes = array_map(static fn(array $row): string => strtolower((string) $row['Ruta']), $rows);
        return array_values(array_unique($routes));
    }

    public function syncRoleViews(int $roleId, array $viewIds, ?int $userId = null): void
    {
        $cleanIds = array_values(array_unique(array_filter(array_map(static fn($value): ?int =>
            is_numeric($value) ? (int) $value : null,
        $viewIds))));

        $this->db->beginTransaction();
        try {
            $this->execute('DELETE FROM RolVistas WHERE IdRol = :rol', [':rol' => $roleId]);
            if (!empty($cleanIds)) {
                $sql = 'INSERT INTO RolVistas (IdRol, IdVista) VALUES (:rol, :vista)';
                $stmt = $this->db->prepare($sql);
                foreach ($cleanIds as $viewId) {
                    $stmt->execute([':rol' => $roleId, ':vista' => $viewId]);
                }
            }
            $this->db->commit();
        } catch (PDOException $exception) {
            $this->db->rollBack();
            throw $exception;
        }

        $this->logAction('RolVistas', 'SINCRONIZAR', $roleId, ['Vistas' => $cleanIds], $userId);
    }
}
