<?php
header('Content-Type: application/json');

// Simulação de dispositivos conectados
$devices = [
    ['ip' => '192.168.0.2', 'type' => 'Wi-Fi'],
    ['ip' => '192.168.0.3', 'type' => 'Ethernet'],
    ['ip' => '192.168.0.4', 'type' => 'Wi-Fi'],
    ['ip' => '192.168.0.5', 'type' => 'Ethernet'],
    ['ip' => '192.168.0.6', 'type' => 'Desconhecido']
];

echo json_encode([
    'total' => count($devices),
    'devices' => $devices
]);
?>
