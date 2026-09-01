<?php
namespace App\Pirotecnicafenix\Controller;

use App\Pirotecnicafenix\Config\Connect\ConnectDB;
use App\Pirotecnicafenix\Model\ReportesModel;
use Exception;

class ReportesController {
    private $db;
    private $modelo;

    public function __construct($db) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $this->db = $db;
        $this->modelo = new ReportesModel($this->db);
    }

    public function index() {
        // Obtener filtros desde GET
        $fecha = isset($_GET['fecha']) ? trim($_GET['fecha']) : null;
        $busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : null;
        $id_categoria = isset($_GET['id_categoria']) ? (int)$_GET['id_categoria'] : null;
        $tipo_movimiento = isset($_GET['tipo_movimiento']) ? trim($_GET['tipo_movimiento']) : null;
        $pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
        $porPagina = isset($_GET['por_pagina']) ? (int)$_GET['por_pagina'] : 10;

        // Calcular offset para paginación
        $offset = ($pagina - 1) * $porPagina;

        // Obtener datos del modelo (AHORA CON FECHA ÚNICA)
        $movimientos = $this->modelo->obtenerMovimientosDiarios($fecha, $id_categoria, $busqueda, $tipo_movimiento, $porPagina, $offset);
        $totalRegistros = $this->modelo->contarMovimientosDiarios($fecha, $id_categoria, $busqueda, $tipo_movimiento);
        $totalesGlobales = $this->modelo->obtenerTotalesGlobales();

        // Calcular paginación
        $totalPaginas = ceil($totalRegistros / $porPagina);
        $paginaActual = $pagina;
        $porPaginaActual = $porPagina;
        $totalEntradas = $totalesGlobales['total_entradas'] ?? 0;
        $totalSalidas = $totalesGlobales['total_salidas'] ?? 0;

        // Pasar $db a la vista para obtener categorías
        $db = $this->db;

        // Cargar vista
        require_once __DIR__ . '/../view/reportes/reportesView.php';
    }

    /**
     * Exportar a CSV (Excel)
     */
    public function exportarCSV() {
        // Obtener todos los movimientos sin paginación
        $movimientos = $this->modelo->obtenerMovimientosDiarios(null, null, null, null, 999999, 0);
        
        // Configurar cabeceras para descarga
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="reporte_movimientos_' . date('Y-m-d') . '.csv"');
        
        // Crear archivo CSV
        $output = fopen('php://output', 'w');
        
        // Agregar BOM para UTF-8 (para que Excel lo lea bien)
        fwrite($output, "\xEF\xBB\xBF");
        
        // Encabezados del CSV
        fputcsv($output, ['Producto', 'Tipo de Movimiento', 'Cantidad', 'Costo Unitario', 'Fecha', 'Responsable']);
        
        // Agregar datos
        foreach ($movimientos as $m) {
            $cantidad = $m['cantidad'];
            if ($m['tipo_movimiento'] === 'Salida') {
                $cantidad = '-' . abs($cantidad);
            }
            
            fputcsv($output, [
                $m['nombre_producto'],
                $m['tipo_movimiento'],
                $cantidad,
                $m['costo_proveedor'] ?? 0,
                $m['fecha_movimiento'],
                $m['usuario_activo']
            ]);
        }
        
        fclose($output);
        exit();
    }

    /**
     * Exportar a PDF (requiere Dompdf)
     */
    public function exportarPDF() {
        // Esta función requiere Dompdf
        // Por ahora redirige con mensaje
        $_SESSION['mensaje'] = "Funcionalidad de exportación a PDF en desarrollo";
        $_SESSION['tipo_mensaje'] = "info";
        header('Location: ?url=reportes');
        exit();
    }
}