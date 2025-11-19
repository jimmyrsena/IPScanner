<?php
set_time_limit(0);

// Define o caminho dos arquivos temporários
$temp_scan_file = 'temp_scan_state.txt';
$temp_network_file = 'temp_network_state.txt';

// Lista de faixas de IP a serem varridas no modo automático
$network_ranges = ['192.168.0.', '192.168.1.', '192.168.2.', '192.168.3.', '192.168.4.', '192.168.50.'];

// Função para varrer a rede silenciosamente (usada na busca por MAC)
function ping_all_ips($base_ip) {
    $os = strtoupper(PHP_OS);
    for ($i = 1; $i <= 254; $i++) {
        $full_ip = $base_ip . $i;
        if (strpos($os, 'WIN') !== false) {
            shell_exec("start /b ping -n 1 -w 500 " . $full_ip . " > NUL 2>&1");
        } else {
            shell_exec("ping -c 1 -W 1 " . $full_ip . " > /dev/null 2>&1 &");
        }
    }
}

// Lógica para busca por MAC
if (isset($_GET['mac_search'])) {
    header('Content-Type: text/plain');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');

    function stream_mac_data($data) {
        echo "data: " . json_encode($data) . "\n\n";
        ob_flush();
        flush();
    }
    
    $mac_to_search = strtoupper(str_replace(['-', ':'], '', $_GET['mac_search']));
    
    $os = strtoupper(PHP_OS);
    $ip_base = '192.168.1.';
    if (strpos($os, 'WIN') !== false) {
        $output = shell_exec('ipconfig');
        if (preg_match('/IPv4 Address.*: (\d+\.\d+\.\d+\.\d+)/', $output, $matches)) {
            $ip_base = implode('.', array_slice(explode('.', $matches[1]), 0, 3)) . '.';
        }
    } else {
        $output = shell_exec('ifconfig || ip addr');
        if (preg_match('/inet (\d+\.\d+\.\d+\.\d+)/', $output, $matches)) {
            $ip_base = implode('.', array_slice(explode('.', $matches[1]), 0, 3)) . '.';
        }
    }

    // Ping para popular a tabela ARP e obter os dispositivos online
    stream_mac_data(['message' => 'Pingando dispositivos da rede local...']);
    ping_all_ips($ip_base);
    sleep(3); // Aguarda a tabela ARP ser populada

    $arp_output = shell_exec('arp -a');
    $lines = explode("\n", $arp_output);
    $total_lines = count($lines);
    
    stream_mac_data(['message' => 'Verificando a tabela ARP...']);

    $found = false;
    for ($i = 0; $i < $total_lines; $i++) {
        if (connection_aborted()) {
            exit();
        }

        $line = $lines[$i];
        if (preg_match('/(\d+\.\d+\.\d+\.\d+).*?([0-9a-fA-F]{2}(?::|-[0-9a-fA-F]{2}){5})/', $line, $matches)) {
            $ip = $matches[1];
            $current_mac = strtoupper(str_replace(['-', ':'], '', $matches[2]));
            if ($current_mac === $mac_to_search) {
                $mac = strtoupper($matches[2]);
                $hostname = gethostbyaddr($ip);
                if ($hostname === $ip) {
                    $hostname = 'N/A';
                }
                stream_mac_data(['status' => 'success', 'ip' => $ip, 'mac' => $mac, 'hostname' => $hostname, 'message' => 'Dispositivo encontrado!']);
                $found = true;
                break;
            }
        }
        $progress = ($i / $total_lines) * 100;
        stream_mac_data(['progress' => round($progress), 'message' => "Analisando... $i de $total_lines entradas"]);
        usleep(50000);
    }
    
    if (!$found) {
        stream_mac_data(['status' => 'error', 'message' => 'Dispositivo não encontrado.']);
    }

} else {
    // Modo de varredura normal (streaming)
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');

    function stream_data($data, $event_type = 'message') {
        echo "event: $event_type\n";
        echo 'data: ' . json_encode($data) . "\n\n";
        ob_flush();
        flush();
    }
    
    $start_ip = '';
    $base_ip = '';
    $start_from_octet = 1;
    $method = 'automático';

    if (isset($_GET['continue_scan']) && file_exists($temp_scan_file)) {
        $state = file_get_contents($temp_scan_file);
        list($base_ip, $current_octet) = explode(',', $state);
        $start_from_octet = intval($current_octet);
        $method = 'manual';
    } elseif (isset($_GET['start_ip']) && !empty($_GET['start_ip'])) {
        $start_ip = $_GET['start_ip'];
        $method = 'manual';
        if (filter_var($start_ip, FILTER_VALIDATE_IP)) {
            $base_ip_parts = explode('.', $start_ip);
            $base_ip = implode('.', array_slice($base_ip_parts, 0, 3)) . '.';
            $start_from_octet = intval(end($base_ip_parts));
        } else {
            $base_ip = $start_ip;
            if (substr($base_ip, -1) !== '.') {
                $base_ip .= '.';
            }
        }
    } else {
        // Lógica alterada: Força a faixa 192.168.0. quando o modo é automático
        $base_ip = '192.168.0.';
        $method = 'automático';
        // A próxima linha é opcional, mas garante que o 'continue' retome da mesma faixa.
        @unlink($temp_network_file);
    }
    
    stream_data(['message' => 'Iniciando varredura...', 'total' => 254 - $start_from_octet + 1, 'base_ip' => $base_ip . 'xxx', 'method' => $method], 'progress');

    $os = strtoupper(PHP_OS);
    $total_ips = 254;

    for ($i = $start_from_octet; $i <= $total_ips; $i++) {
        $full_ip = $base_ip . $i;
        if (connection_aborted()) {
            break;
        }

        if (strpos($os, 'WIN') !== false) {
            $command = "ping -n 1 -w 500 " . $full_ip;
        } else {
            $command = "ping -c 1 -W 1 " . $full_ip;
        }
        
        $output = shell_exec($command);
        
        if (strpos($output, 'TTL=') !== false || strpos($output, 'tempo=') !== false || strpos(strtolower($output), 'received, 0% packet loss') !== false) {
            $mac = 'N/A';
            $hostname = 'N/A';

            $arp_output = shell_exec("arp -a " . $full_ip);
            if (preg_match('/([0-9a-fA-F]{2}(?:-|:)[0-9a-fA-F]{2}(?:-|:)[0-9a-fA-F]{2}(?:-|:)[0-9a-fA-F]{2}(?:-|:)[0-9a-fA-F]{2}(?:-|:)[0-9a-fA-F]{2})/', $arp_output, $matches)) {
                $mac = strtoupper($matches[1]);
            }
            
            $hostname = gethostbyaddr($full_ip);
            if ($hostname === $full_ip) {
                $hostname = 'N/A';
            }
            
            stream_data(['ip' => $full_ip, 'mac' => $mac, 'hostname' => $hostname, 'status' => 'online', 'found' => true], 'scan_status');
            
            if ($method === 'manual') {
                file_put_contents($temp_scan_file, $base_ip . ',' . ($i + 1));
                stream_data(['message' => 'Dispositivo encontrado. Deseja continuar a varredura?'], 'pause_scan');
                exit();
            }
        } else {
            stream_data(['ip' => $full_ip, 'status' => 'offline', 'found' => false], 'scan_status');
        }
        
        $progress = round((($i - $start_from_octet + 1) / ($total_ips - $start_from_octet + 1)) * 100);
        stream_data(['progress' => $progress], 'progress');
    }

    if ($method === 'automático') {
        // Remove a lógica de loop entre as faixas
        stream_data(['message' => 'Varredura da faixa ' . $base_ip . ' concluída.'], 'end_range');
    } else {
        stream_data(['message' => 'Varredura concluída.'], 'end');
    }
    @unlink($temp_scan_file);
}
?>