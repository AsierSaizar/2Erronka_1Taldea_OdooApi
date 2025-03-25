<?php

require("db_parameters.php");

$sql = "SELECT * FROM langilea WHERE deleted_at IS NULL";
$result = $conn->query($sql);

$langileak = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $langileak[] = $row;
    }
} else {
    echo json_encode(["error" => "Ez dira langileak aurkitu"]);
    exit;
}
$conn->close();

echo json_encode($langileak, JSON_PRETTY_PRINT);

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
    
    if (!is_array($fitxategia)) {
        die("Error: Los datos proporcionados no son un array válido.");
    }

    foreach ($fitxategia as $datu) {
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

odooraBidali($langileak, "hr.employee"); 

?>
