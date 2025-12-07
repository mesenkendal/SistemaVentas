<?php

declare(strict_types=1);

use SistemaVentas\Models\BitacoraModel;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$config = require __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/session_guard.php';

$baseUrl = rtrim($config['app']['base_url'] ?? '/SistemaVentas', '/');
$publicBase = rtrim($config['app']['public_url'] ?? $baseUrl . '/public', '/');

if (empty($_SESSION['usuario'])) {
    header('Location: ' . $publicBase . '/login.php');
    exit;
}

enforce_session_timeout($publicBase);
ensure_access('reportes.php', $publicBase);

$format = strtolower((string) ($_GET['format'] ?? ''));
if ($format !== 'excel') {
    http_response_code(400);
    echo 'Formato no soportado.';
    exit;
}

$filters = (static function (): array {
    $tabla = trim((string) ($_GET['tabla'] ?? ''));
    $accion = trim((string) ($_GET['accion'] ?? ''));
    $usuario = filter_input(INPUT_GET, 'usuario', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
    $desde = trim((string) ($_GET['desde'] ?? ''));
    $hasta = trim((string) ($_GET['hasta'] ?? ''));
    $datePattern = '/^\d{4}-\d{2}-\d{2}$/';

    return [
        'table'     => $tabla !== '' ? substr($tabla, 0, 50) : null,
        'action'    => $accion !== '' ? strtoupper(substr($accion, 0, 20)) : null,
        'user'      => $usuario,
        'date_from' => ($desde !== '' && preg_match($datePattern, $desde)) ? $desde : null,
        'date_to'   => ($hasta !== '' && preg_match($datePattern, $hasta)) ? $hasta : null,
    ];
})();

$bitacoraModel = new BitacoraModel();
$records = $bitacoraModel->listForExport($filters, 5000);

exportExcel($records);
exit;

/**
 * @param array<int, array<string, mixed>> $records
 */
function exportExcel(array $records): void
{
    $filename = 'bitacora_' . date('Ymd_His') . '.xls';
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $headers = ['Fecha', 'Tabla', 'Accion', 'Registro', 'Usuario', 'Datos'];
    echo implode("\t", $headers) . "\r\n";

    foreach ($records as $row) {
        $fecha = isset($row['FechaEvento']) ? date('Y-m-d H:i:s', strtotime((string) $row['FechaEvento'])) : '';
        $tabla = (string) ($row['Tabla'] ?? '');
        $accion = (string) ($row['Accion'] ?? '');
        $registro = (string) ($row['RegistroId'] ?? '');
        $usuario = $row['NombreUsuario'] ?? ($row['IdUsuario'] ? 'Usuario #' . $row['IdUsuario'] : 'Sistema');
        $payload = formatPayload($row['Datos'] ?? null);

        $line = [
            $fecha,
            $tabla,
            $accion,
            $registro,
            (string) $usuario,
            $payload,
        ];

        echo implode("\t", array_map('cleanExportField', $line)) . "\r\n";
    }
}

function cleanExportField(string $value): string
{
    $value = str_replace(["\t", "\r", "\n"], ' ', $value);
    return trim($value);
}

function describeFilters(array $filters): string
{
    $parts = [];
    if (!empty($filters['table'])) {
        $parts[] = 'Tabla: ' . $filters['table'];
    }
    if (!empty($filters['action'])) {
        $parts[] = 'Acción: ' . $filters['action'];
    }
    if (!empty($filters['user'])) {
        $parts[] = 'Usuario: ' . $filters['user'];
    }
    if (!empty($filters['date_from'])) {
        $parts[] = 'Desde: ' . $filters['date_from'];
    }
    if (!empty($filters['date_to'])) {
        $parts[] = 'Hasta: ' . $filters['date_to'];
    }

    return $parts ? implode(' | ', $parts) : 'Sin filtros';
}

function formatPayload(?string $json, bool $compact = false): string
{
    if ($json === null || $json === '') {
        return '';
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return (string) $json;
    }

    $pairs = [];
    foreach ($decoded as $key => $value) {
        $display = is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pairs[] = $key . '=' . $display;
    }

    $result = $compact ? implode('; ', $pairs) : json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($compact && strlen($result) > 180) {
        $result = substr($result, 0, 177) . '...';
    }

    return $result;
}
