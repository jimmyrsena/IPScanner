<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rastreador de IP da Rede Local</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .container {
            margin-top: 50px;
            flex: 1;
        }
        .list-group-item.online { background-color: #d4edda; }
        .list-group-item.offline { background-color: #f8d7da; }
        .spinner { display: none; }
        .progress-bar { width: 0%; }
        #status-list { max-height: 400px; overflow-y: auto; }
        .scan-actions { display: flex; justify-content: center; gap: 10px; flex-wrap: wrap; }
        .ip-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 10px; padding: 0.875rem; }
        .online-card, .offline-card {
            border: 1px solid #ccc;
            border-radius: 8px;
            padding: 10px;
            word-wrap: break-word;
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .online-card:hover, .offline-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .online-card { background-color: #d4edda; }
        .offline-card { background-color: #f8d7da; }
        .online-card strong, .offline-card strong { display: block; }
        .card-body.no-padding { padding: 0 !important; }
        #mac-scan-progress { margin-top: 10px; }
        .material-symbols-outlined {
            font-variation-settings:
            'FILL' 0,
            'wght' 400,
            'GRAD' 0,
            'opsz' 24;
        }
        .card-header .material-symbols-outlined {
            vertical-align: middle;
            margin-right: 8px;
        }
        #ip-input:disabled {
            font-size: 0.875rem;
            background-color: #e9ecef;
            cursor: not-allowed;
        }
        .btn { transition: transform 0.2s ease-in-out; }
        .btn:hover { transform: scale(1.05); }
        .footer { background-color: #0f3153; color: white; padding: 5px 0; margin-top: 5px; }
        .footer .container { display: flex; justify-content: space-between; align-items: center; position: relative; }
        .footer .center-text { position: absolute; left: 50%; transform: translateX(-50%); }
        .footer a { color: #ecf0f1; text-decoration: none; transition: color 0.2s; }
        .footer a:hover { color: #3498db; }
        .whatsapp-logo { height: 90px; vertical-align: middle; transition: transform 0.2s ease-in-out; }
        .whatsapp-logo:hover { transform: scale(1.1); }

        /* Regras para dispositivos móveis */
        @media (max-width: 576px) {
            .scan-actions { flex-direction: column; align-items: center; }
            .scan-actions .btn { width: 100%; margin-bottom: 10px; }
            .input-group { flex-wrap: wrap; }
            .input-group > .form-control, .input-group > .input-group-text, .input-group > .btn {
                width: 100%;
                margin-bottom: 5px;
            }
            .input-group .form-control { margin-bottom: 10px; }
            #toggle-mode { width: 100%; }
        }

        /* Ajuste do footer para telas pequenas */
        @media (max-width: 768px) {
            .footer .container { flex-direction: column; text-align: center; }
            .footer .center-text {
                position: static;
                transform: none;
                margin-bottom: 10px;
            }
        }
    </style>
</head>

<body>

<div class="container">
    <h1 class="mb-4 text-center">Rastreador de IP da Rede Local</h1>

    <?php
    // --- NOVO BLOCO: identifica IP real do cliente, hostname e tenta MAC local ---

    // 1) Função para obter IP do cliente considerando proxies
    function obter_ip_cliente(): string {
        $candidatos = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',  // Proxy reverso
            'HTTP_X_REAL_IP',        // Nginx
            'REMOTE_ADDR'
        ];
        foreach ($candidatos as $h) {
            if (!empty($_SERVER[$h])) {
                $valor = $_SERVER[$h];
                // X-Forwarded-For pode ter lista de IPs
                if ($h === 'HTTP_X_FORWARDED_FOR') {
                    foreach (explode(',', $valor) as $ipPossivel) {
                        $ipPossivel = trim($ipPossivel);
                        if (filter_var($ipPossivel, FILTER_VALIDATE_IP)) {
                            return $ipPossivel;
                        }
                    }
                } else {
                    if (filter_var($valor, FILTER_VALIDATE_IP)) {
                        return $valor;
                    }
                }
            }
        }
        return 'N/A';
    }

    // 2) Verifica se IP é privado (mesma LAN/NAT)
    function ip_privado(string $ip): bool {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) return false;
        // Faixas privadas + link-local
        $faixas = [
            '10.0.0.0|10.255.255.255',
            '172.16.0.0|172.31.255.255',
            '192.168.0.0|192.168.255.255',
            '169.254.0.0|169.254.255.255', // link-local
            '127.0.0.0|127.255.255.255'    // loopback
        ];
        $ipLong = ip2long($ip);
        foreach ($faixas as $fx) {
            [$ini, $fim] = explode('|', $fx);
            if ($ipLong >= ip2long($ini) && $ipLong <= ip2long($fim)) return true;
        }
        return false;
    }

    $client_ip = obter_ip_cliente();

    // 3) Hostname via DNS reverso (pode retornar o próprio IP)
    $client_hostname = 'N/A';
    if ($client_ip !== 'N/A') {
        $tmpHost = @gethostbyaddr($client_ip);
        $client_hostname = ($tmpHost && $tmpHost !== $client_ip) ? $tmpHost : 'N/A';
    }

    // 4) Tenta MAC APENAS se IP for privado e não for loopback
    $client_mac = 'N/A';
    if ($client_ip !== 'N/A' && ip_privado($client_ip) && $client_ip !== '127.0.0.1') {
        $os = strtoupper(PHP_OS);

        // Dá um ping rápido para popular a ARP
        if (strpos($os, 'WIN') !== false) {
            @shell_exec('ping -n 1 -w 400 ' . escapeshellarg($client_ip) . ' > NUL 2>&1');
            $arp_output = @shell_exec('arp -a');
        } else {
            @shell_exec('ping -c 1 -W 1 ' . escapeshellarg($client_ip) . ' > /dev/null 2>&1');
            // Tenta ip neigh (moderno). Se falhar, cai para arp -an
            $arp_output = @shell_exec('ip neigh show ' . escapeshellarg($client_ip) . ' 2>/dev/null');
            if (!$arp_output) {
                $arp_output = @shell_exec('arp -an 2>/dev/null');
            }
        }

        if ($arp_output) {
            $linhas = explode("\n", $arp_output);
            $linha_ip = '';
            foreach ($linhas as $ln) {
                if (strpos($ln, $client_ip) !== false) { $linha_ip = $ln; break; }
            }
            if (!$linha_ip) { $linha_ip = $arp_output; } // fallback

            // Extrai MAC nos formatos 00:11:22:33:44:55 ou 00-11-22-33-44-55
            if (preg_match('/([0-9a-fA-F]{2}(?:[:-][0-9a-fA-F]{2}){5})/', $linha_ip, $m)) {
                $client_mac = strtoupper(str_replace('-', ':', $m[1]));
            }
        }
    }
    ?>
    <div class="text-center mb-4">
        <small>
            <strong>Seu IP:</strong> <?= htmlspecialchars($client_ip) ?> |
            <strong>Hostname:</strong> <?= htmlspecialchars($client_hostname) ?> |
            <strong>MAC:</strong> <?= htmlspecialchars($client_mac) ?>
        </small>
    </div>

    <p class="text-muted text-center">Modo de varredura: <strong id="scan-mode">Automático</strong></p>
    <p id="ip-range-info" class="text-center"></p>

    <div class="input-group mb-3">
        <span class="input-group-text">IP inicial:</span>
        <input type="text" id="ip-input" class="form-control" placeholder="Ex: 192.168.1.">
        <button class="btn btn-outline-secondary" type="button" id="toggle-mode"><span class="material-symbols-outlined">settings</span> Alternar para Manual</button>
    </div>

    <div class="scan-actions mb-4">
        <button id="scan-button" class="btn btn-primary"><span class="material-symbols-outlined">search</span> Escanear Rede</button>
        <button id="stop-button" class="btn btn-danger" disabled><span class="material-symbols-outlined">stop</span> Parar</button>
        <button id="continue-button" class="btn btn-success" disabled style="display:none;"><span class="material-symbols-outlined">play_arrow</span> Continuar</button>
        <div id="loading-spinner" class="spinner-border text-primary spinner" role="status">
            <span class="visually-hidden">Carregando...</span>
        </div>
    </div>

    <div class="progress mb-3" style="height: 20px;">
        <div id="scan-progress" class="progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <div class="card h-100">
                <div class="card-header bg-success text-white">
                    <span class="material-symbols-outlined">network_check</span> Dispositivos Online
                </div>
                <div id="online-list" class="card-body no-padding">
                    <p class="text-center mt-3">Nenhum dispositivo online encontrado.</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card h-100">
                <div class="card-header bg-danger text-white">
                    <span class="material-symbols-outlined">network_locked</span> Dispositivos Offline
                </div>
                <div id="offline-list" class="card-body no-padding">
                    <p class="text-center mt-3">Nenhum dispositivo offline encontrado.</p>
                </div>
            </div>
        </div>
    </div>

    <hr class="my-5">

    <div class="card mb-3">
        <div class="card-header">
            <span class="material-symbols-outlined">router</span> Buscar por Endereço MAC
        </div>
        <div class="card-body">
            <div class="input-group mb-3">
                <span class="input-group-text">Endereço MAC:</span>
                <input type="text" id="mac-input" class="form-control" placeholder="Ex: 00-1B-44-11-3A-B7">
            </div>
            <div class="text-center">
                <button id="mac-scan-button" class="btn btn-success"><span class="material-symbols-outlined">find_in_page</span> Procurar por MAC</button>
                <button id="mac-stop-button" class="btn btn-danger" style="display:none;"><span class="material-symbols-outlined">stop_circle</span> Parar Busca</button>
            </div>
            <div id="mac-scan-progress" class="progress" style="display:none;">
                <div id="mac-progress-bar" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <div id="mac-status-text" class="text-center mt-2" style="display:none;"></div>
        </div>
        <ul id="mac-result-list" class="list-group list-group-flush">
            <li class="list-group-item text-center">Insira um endereço MAC e clique em "Procurar".</li>
        </ul>
    </div>
</div>

<footer class="footer">
    <div class="container">
        <div class="center-text">
            <p class="mb-0">© EDTI - Escritório Digital TI - 2025</p>
        </div>
        <div>
            <a href="https://wa.me/5591981599659" target="_blank">
                <img src="images/whatsapp_logo.png" alt="WhatsApp" class="whatsapp-logo">
            </a>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const scanButton = document.getElementById('scan-button');
    const stopButton = document.getElementById('stop-button');
    const continueButton = document.getElementById('continue-button');
    const toggleButton = document.getElementById('toggle-mode');
    const ipInput = document.getElementById('ip-input');
    const scanModeText = document.getElementById('scan-mode');

    const onlineList = document.getElementById('online-list');
    const offlineList = document.getElementById('offline-list');
    const progressBar = document.getElementById('scan-progress');
    const ipInfo = document.getElementById('ip-range-info');
    const spinner = document.getElementById('loading-spinner');

    const macInput = document.getElementById('mac-input');
    const macScanButton = document.getElementById('mac-scan-button');
    const macStopButton = document.getElementById('mac-stop-button');
    const macResultList = document.getElementById('mac-result-list');
    const macProgressBar = document.getElementById('mac-progress-bar');
    const macScanProgressContainer = document.getElementById('mac-scan-progress');
    const macStatusText = document.getElementById('mac-status-text');

    const MAX_LOG_ITEMS = 20;

    let isManualMode = false;
    let eventSource;
    let macScanAbortController;

    function resetUI() {
        stopButton.disabled = true;
        scanButton.disabled = false;
        toggleButton.disabled = false;
        continueButton.disabled = true;
        continueButton.style.display = 'none';
        spinner.style.display = 'none';
    }

    toggleButton.addEventListener('click', () => {
        isManualMode = !isManualMode;
        if (isManualMode) {
            scanModeText.textContent = 'Manual';
            ipInput.removeAttribute('disabled');
            toggleButton.innerHTML = '<span class="material-symbols-outlined">settings</span> Alternar para Automático';
            ipInput.focus();
        } else {
            scanModeText.textContent = 'Automático';
            ipInput.setAttribute('disabled', 'disabled');
            ipInput.value = '';
            toggleButton.innerHTML = '<span class="material-symbols-outlined">settings</span> Alternar para Manual';
        }
    });

    ipInput.setAttribute('disabled', 'disabled');

    scanButton.addEventListener('click', function() {
        const ipToScan = ipInput.value.trim();

        scanButton.disabled = true;
        stopButton.disabled = false;
        toggleButton.disabled = true;
        spinner.style.display = 'inline-block';

        onlineList.innerHTML = '<p class="text-center mt-3">Nenhum dispositivo online encontrado.</p>';
        offlineList.innerHTML = '<p class="text-center mt-3">Nenhum dispositivo offline encontrado.</p>';

        let url = 'scan.php';
        if (isManualMode && ipToScan) {
            url += `?start_ip=${ipToScan}`;
        }

        eventSource = new EventSource(url);

        eventSource.addEventListener('progress', function(e) {
            const data = JSON.parse(e.data);
            if (data.message) {
                console.log(data.message);
            }
            if (data.base_ip) {
                ipInfo.textContent = `Varrendo a faixa: ${data.base_ip} (método ${data.method})`;
            }
            if (data.progress) {
                progressBar.style.width = `${data.progress}%`;
                progressBar.setAttribute('aria-valuenow', data.progress);
                progressBar.textContent = `${data.progress}%`;
            }
        });

        eventSource.addEventListener('scan_status', function(e) {
            const data = JSON.parse(e.data);

            if (data.status === 'online') {
                const onlineItem = document.createElement('div');
                onlineItem.className = 'online-card';
                onlineItem.innerHTML = `<strong>IP:</strong> ${data.ip} <br> <strong>MAC:</strong> ${data.mac} <br> <strong>Hostname:</strong> ${data.hostname}`;

                if (onlineList.innerHTML.includes('Nenhum dispositivo online encontrado.')) {
                    onlineList.innerHTML = '<div class="ip-grid"></div>';
                }
                onlineList.querySelector('.ip-grid').appendChild(onlineItem);
            } else {
                const offlineItem = document.createElement('div');
                offlineItem.className = 'offline-card';
                offlineItem.innerHTML = `<strong>IP:</strong> ${data.ip} <br> <strong>Status:</strong> Offline`;

                if (offlineList.innerHTML.includes('Nenhum dispositivo offline encontrado.')) {
                    offlineList.innerHTML = '<div class="ip-grid"></div>';
                }
                offlineList.querySelector('.ip-grid').appendChild(offlineItem);
            }
        });

        eventSource.addEventListener('pause_scan', function(e) {
            const data = JSON.parse(e.data);
            eventSource.close();
            console.log(data.message);
            stopButton.disabled = false;
            scanButton.disabled = true;
            toggleButton.disabled = true;
            continueButton.disabled = false;
            continueButton.style.display = 'inline-block';
            spinner.style.display = 'none';
        });

        eventSource.addEventListener('end_range', function(e) {
            const data = JSON.parse(e.data);
            eventSource.close();
            resetUI();
            console.log(data.message);
        });

        eventSource.addEventListener('end', function(e) {
            eventSource.close();
            resetUI();
            console.log('Varredura concluída.');
        });

        eventSource.onerror = function(e) {
            console.error('EventSource failed:', e);
            eventSource.close();
            resetUI();
        };
    });

    stopButton.addEventListener('click', function() {
        if (eventSource) {
            eventSource.close();
            console.log('Varredura interrompida pelo usuário.');
            resetUI();
        }
    });

    continueButton.addEventListener('click', function() {
        resetUI();
        scanButton.disabled = true;
        stopButton.disabled = false;
        spinner.style.display = 'inline-block';
        eventSource = new EventSource('scan.php?continue_scan=1');
    });

    macScanButton.addEventListener('click', function() {
        const macAddress = macInput.value.trim();
        if (!macAddress) {
            macResultList.innerHTML = `<li class="list-group-item text-center text-danger"><span class="material-symbols-outlined">warning</span> Por favor, insira um endereço MAC.</li>`;
            return;
        }

        macScanButton.disabled = true;
        macStopButton.style.display = 'inline-block';
        macResultList.innerHTML = `<li class="list-group-item text-center">Procurando por ${macAddress}...</li>`;
        macScanProgressContainer.style.display = 'block';
        macStatusText.style.display = 'block';
        macStatusText.textContent = `Procurando...`;
        macProgressBar.style.width = '0%';
        macProgressBar.textContent = '';

        macScanAbortController = new AbortController();
        const signal = macScanAbortController.signal;

        fetch(`scan.php?mac_search=${encodeURIComponent(macAddress)}`, { signal })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erro na resposta do servidor.');
                }
                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let result = '';

                function processChunk({ done, value }) {
                    if (done) {
                        return;
                    }

                    result += decoder.decode(value, { stream: true });
                    const lines = result.split('\n');
                    result = lines.pop();

                    for (const line of lines) {
                        if (line.trim().startsWith('data:')) {
                            const data = JSON.parse(line.substring(5));
                            if (data.progress !== undefined) {
                                macProgressBar.style.width = `${data.progress}%`;
                                macProgressBar.textContent = `${data.progress}%`;
                            }
                            if (data.message) {
                                macStatusText.textContent = data.message;
                            }
                            if (data.status === 'success') {
                                macResultList.innerHTML = `<li class="list-group-item online">
                                    <span class="material-symbols-outlined">check_circle</span>
                                    <strong>IP:</strong> ${data.ip} <br>
                                    <strong>MAC:</strong> ${data.mac} <br>
                                    <strong>Hostname:</strong> ${data.hostname}
                                </li>`;
                            } else if (data.status === 'error') {
                                macResultList.innerHTML = `<li class="list-group-item offline"><span class="material-symbols-outlined">error_circle</span> Dispositivo não encontrado ou erro.</li>`;
                            }
                        }
                    }
                    return reader.read().then(processChunk);
                }

                return reader.read().then(processChunk);
            })
            .catch(error => {
                if (error.name === 'AbortError') {
                    macResultList.innerHTML = `<li class="list-group-item text-warning"><span class="material-symbols-outlined">cancel</span> Busca por MAC interrompida.</li>`;
                    macStatusText.textContent = 'Busca interrompida.';
                } else {
                    macResultList.innerHTML = `<li class="list-group-item offline"><span class="material-symbols-outlined">error</span> Erro na busca por MAC.</li>`;
                    macStatusText.textContent = 'Ocorreu um erro.';
                    console.error('Erro na busca por MAC:', error);
                }
            })
            .finally(() => {
                macScanButton.disabled = false;
                macStopButton.style.display = 'none';
                macProgressBar.style.width = '100%';
            });
    });

    macStopButton.addEventListener('click', function() {
        if (macScanAbortController) {
            macScanAbortController.abort();
            macScanButton.disabled = false;
            macStopButton.style.display = 'none';
            macScanProgressContainer.style.display = 'none';
        }
    });
</script>

</body>
</html>
