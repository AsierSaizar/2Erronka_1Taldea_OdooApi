<?php

require("db_parameters.php");
header('Content-Type: application/json');

// --- Consultas adicionales ---
$datos = [];

// Consulta para obtener los pedidos por plato
$sql_platos = "SELECT platera_id, COUNT(*) AS total_pedidos FROM eskaera_platera GROUP BY platera_id";
$result_platos = $conn->query($sql_platos);

$pedidos_por_plato = [];
if ($result_platos && $result_platos->num_rows > 0) {
    while ($row = $result_platos->fetch_assoc()) {
        $pedidos_por_plato[] = $row;
    }
}

// Consulta para obtener el día de la semana con más pedidos
$sql_dia = "SELECT DAYNAME(eskaera_ordua) AS dia_semana, COUNT(*) AS total_pedidos 
            FROM eskaera_platera 
            GROUP BY dia_semana 
            ORDER BY total_pedidos DESC 
            LIMIT 1";

$result_dia = $conn->query($sql_dia);

$dia_mas_pedidos = [];
if ($result_dia && $result_dia->num_rows > 0) {
    $dia_mas_pedidos = $result_dia->fetch_assoc();
}

// Consulta para obtener el día de la semana con mayor facturación
$sql_facturacion = "SELECT DAYNAME(e.eskaera_ordua) AS dia_semana, SUM(p.prezioa) AS total_facturado
                    FROM eskaera_platera e
                    JOIN platera p ON e.platera_id = p.id
                    GROUP BY dia_semana
                    ORDER BY total_facturado DESC
                    LIMIT 1";

$result_facturacion = $conn->query($sql_facturacion);

$dia_mas_facturacion = [];
if ($result_facturacion && $result_facturacion->num_rows > 0) {
    $dia_mas_facturacion = $result_facturacion->fetch_assoc();
}

// --- Nueva consulta para obtener el día del mes con más pedidos ---
$sql_dia_mes = "SELECT DAY(eskaera_ordua) AS dia_mes, COUNT(*) AS total_pedidos
                FROM eskaera_platera
                GROUP BY dia_mes
                ORDER BY total_pedidos DESC
                LIMIT 1";

$result_dia_mes = $conn->query($sql_dia_mes);

$dia_mes_mas_pedidos = [];
if ($result_dia_mes && $result_dia_mes->num_rows > 0) {
    $dia_mes_mas_pedidos = $result_dia_mes->fetch_assoc();
}

// --- Consulta original para obtener los pedidos ---
$sql = "SELECT * FROM eskaera_platera";
$result = $conn->query($sql);

$eskariak = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $eskariak[] = $row;
}
}

// --- Cerrar la conexión de la base de datos después de las consultas ---
$conn->close();

// Combinar los datos en un solo array
$datos['pedidos_por_plato'] = $pedidos_por_plato;
$datos['dia_mas_pedidos'] = $dia_mas_pedidos;
$datos['dia_mas_facturacion'] = $dia_mas_facturacion;
$datos['dia_mes_mas_pedidos'] = $dia_mes_mas_pedidos; // Agregar el nuevo dato
$datos['eskariak'] = $eskariak;

// Devolver la respuesta en JSON, con todos los datos combinados
echo json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// --- Función para enviar datos a Odoo ---
function odooraBidali($fitxategia, $modelo) {
    $url = "https://localhost:8085/jsonrpc";
    $db = "urko-gelan";
    $username = "urk_loz_ceb@goierrieskola.org";
    $password = "123456";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "$url/web/session/authenticate");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        "jsonrpc" => "2.0",
        "method" => "call",
        "params" => [
            "db" => $db,
            "login" => $username,
            "password" => $password
        ]
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $responseJson = curl_exec($ch);
    if (curl_errno($ch)) {
        die("Error en la conexión: " . curl_error($ch));
    }

    $response = json_decode($responseJson, true);
    if (!$response || !isset($response['result']['uid'])) {
        die("Error en la autenticación: " . print_r($responseJson, true));
    }

    $uid = $response['result']['uid'];
    $datuak = json_decode(file_get_contents($fitxategia), true);
    if (!is_array($datuak)) {
        die("Error: No se pudo leer o parsear el archivo $fitxategia");
    }

    foreach ($datuak as $datu) {
        $data = [
            "jsonrpc" => "2.0",
            "method" => "call",
            "params" => [
                "service" => "object",
                "method" => "execute_kw",
                "args" => [
                    $db,
                    $uid,
                    $password,
                    $modelo,
                    "create",
                    [$datu]
                ]
            ]
        ];

        curl_setopt($ch, CURLOPT_URL, "$url/object");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $responseJson = curl_exec($ch);
        $response = json_decode($responseJson, true);

        if (curl_errno($ch)) {
            echo "Error en la solicitud: " . curl_error($ch) . "\n";
        } else {
            echo "Respuesta de Odoo para el modelo '$modelo': " . print_r($response, true) . "\n";
        }
    }

    curl_close($ch);
}

odooraBidali('eskariak.json', "sale.order");

?>
