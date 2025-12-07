<?php

declare(strict_types=1);

namespace SistemaVentas\Models;

use PDO;
use SistemaVentas\Database\Connection;

abstract class BaseModel
{
    protected PDO $db;
    private ?BitacoraModel $logger = null;

    public function __construct(?PDO $connection = null)
    {
        $this->db = $connection ?? Connection::getInstance();
    }

    protected function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    protected function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result === false ? null : $result;
    }

    protected function execute(string $sql, array $params = []): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    protected function lastInsertId(): string
    {
        return $this->db->lastInsertId();
    }

    protected function logAction(string $table, string $action, int $recordId, array $data = [], ?int $userId = null): void
    {
        if ($this->logger === null) {
            $this->logger = new BitacoraModel($this->db);
        }
        $this->logger->log($table, $action, $recordId, $data, $userId);
    }
}
