<?php
namespace App\Pirotecnicafenix\Model;

use PDO;
use Exception;

class ReportesModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function obtenerMovimientosDiarios($fecha = null, $id_categoria = null, $busqueda = null, $tipo_movimiento = null, $limit = 10, $offset = 0) {
        $whereEntrada = [];
        $whereSalida = [];
        $params = [];

        // Filtro por fecha
        if (!empty($fecha)) {
            $whereEntrada[] = "DATE(ne.Fecha_ingreso) = :fecha";
            $whereSalida[] = "DATE(ns.fecha) = :fecha";
            $params[':fecha'] = $fecha;
        }

        // Filtro por categoría
        if (!empty($id_categoria) && $id_categoria > 0) {
            $whereEntrada[] = "prod.id_categoria = :id_categoria";
            $whereSalida[] = "prod.id_categoria = :id_categoria";
            $params[':id_categoria'] = $id_categoria;
        }

        // Filtro por producto
        if (!empty($busqueda)) {
            $whereEntrada[] = "prod.descripcion LIKE :busqueda";
            $whereSalida[] = "prod.descripcion LIKE :busqueda";
            $params[':busqueda'] = '%' . $busqueda . '%';
        }

        $whereClauseEntrada = !empty($whereEntrada) ? ' WHERE ' . implode(' AND ', $whereEntrada) : '';
        $whereClauseSalida = !empty($whereSalida) ? ' WHERE ' . implode(' AND ', $whereSalida) : '';

        // 🔥 ENTRADAS (con costo)
        $sqlEntrada = "SELECT 
                            prod.descripcion AS nombre_producto,
                            c.nombre_categoria AS categoria,
                            'Entrada' AS tipo_movimiento,
                            de.cantidad AS cantidad,
                            de.costo_unitario AS costo_proveedor,
                            ne.Fecha_ingreso AS fecha_movimiento,
                            COALESCE(CONCAT(per_u.nombre, ' ', per_u.apellido), 'N/A') AS usuario_activo,
                            '' AS motivo_anulacion
                        FROM nota_de_entrada ne
                        INNER JOIN detalle_entrada de ON ne.id_nota_entrada = de.id_nota_entrada
                        INNER JOIN producto prod ON de.id_producto = prod.id_producto
                        LEFT JOIN categoria c ON prod.id_categoria = c.id_categoria
                        LEFT JOIN usuario u ON ne.id_usuario = u.id_usuario
                        LEFT JOIN persona per_u ON u.id_persona = per_u.id_persona
                        $whereClauseEntrada";

        // 🔥 SALIDAS (SIN ds.costo_unitario, usando prod.costo_unitario)
        $sqlSalida = "SELECT 
                            prod.descripcion AS nombre_producto,
                            c.nombre_categoria AS categoria,
                            'Salida' AS tipo_movimiento,
                            ds.cantidad AS cantidad,
                            prod.costo_unitario AS costo_proveedor,  -- ✅ COSTO DESDE PRODUCTO
                            ns.fecha AS fecha_movimiento,
                            COALESCE(CONCAT(per_u.nombre, ' ', per_u.apellido), 'N/A') AS usuario_activo,
                            '' AS motivo_anulacion
                        FROM nota_de_salida ns
                        INNER JOIN detalle_salida ds ON ns.id_nota_salida = ds.id_nota_salida
                        INNER JOIN producto prod ON ds.id_producto = prod.id_producto
                        LEFT JOIN categoria c ON prod.id_categoria = c.id_categoria
                        LEFT JOIN usuario u ON ns.id_usuario = u.id_usuario
                        LEFT JOIN persona per_u ON u.id_persona = per_u.id_persona
                        $whereClauseSalida";

        // Aplicar filtro por tipo de movimiento
        if ($tipo_movimiento === 'Entrada') {
            $sql = $sqlEntrada;
        } elseif ($tipo_movimiento === 'Salida') {
            $sql = $sqlSalida;
        } else {
            $sql = "($sqlEntrada) UNION ALL ($sqlSalida)";
        }

        $sql .= " ORDER BY fecha_movimiento DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contarMovimientosDiarios($fecha = null, $id_categoria = null, $busqueda = null, $tipo_movimiento = null) {
        $movimientos = $this->obtenerMovimientosDiarios($fecha, $id_categoria, $busqueda, $tipo_movimiento, 999999, 0);
        return count($movimientos);
    }

    public function obtenerTotalesGlobales() {
        $sql = "SELECT 
                    SUM(CASE WHEN tipo_movimiento = 'Entrada' THEN cantidad ELSE 0 END) AS total_entradas,
                    SUM(CASE WHEN tipo_movimiento = 'Salida' THEN cantidad ELSE 0 END) AS total_salidas
                FROM (
                    SELECT 'Entrada' AS tipo_movimiento, de.cantidad AS cantidad
                    FROM nota_de_entrada ne
                    INNER JOIN detalle_entrada de ON ne.id_nota_entrada = de.id_nota_entrada
                    
                    UNION ALL
                    
                    SELECT 'Salida' AS tipo_movimiento, ds.cantidad AS cantidad
                    FROM nota_de_salida ns
                    INNER JOIN detalle_salida ds ON ns.id_nota_salida = ds.id_nota_salida
                ) as movimientos_totales";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_entradas' => (int)($row['total_entradas'] ?? 0),
            'total_salidas' => (int)($row['total_salidas'] ?? 0)
        ];
    }
}