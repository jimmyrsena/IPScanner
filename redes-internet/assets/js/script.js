let totalIPs = 50;
let currentCount = 0;

document.getElementById('scanBtn').addEventListener('click', () => {
  const deviceList = document.getElementById('deviceList');
  const progressBar = document.getElementById('progressBar');
  deviceList.innerHTML = '';
  currentCount = 0;
  progressBar.style.width = '0%';
  progressBar.textContent = '0%';

  fetch('includes/scan_network.php')
    .then(response => response.json())
    .then(data => {
      totalIPs = data.total;
      data.devices.forEach((device, index) => {
        setTimeout(() => {
          const icon = device.type === 'Wi-Fi' ? 'fa-wifi text-primary' :
                       device.type === 'Ethernet' ? 'fa-network-wired text-success' :
                       'fa-question-circle text-secondary';
          const div = document.createElement('div');
          div.className = 'device-item';
          div.innerHTML = `<i class="fas ${icon}"></i> ${device.ip} (${device.type})`;
          deviceList.appendChild(div);
          currentCount++;
          const percent = Math.round((currentCount / totalIPs) * 100);
          progressBar.style.width = percent + '%';
          progressBar.textContent = percent + '%';
        }, index * 100);
      });
    })
    .catch(error => {
      deviceList.innerHTML = '<div class="text-danger">Erro ao escanear a rede.</div>';
      console.error(error);
    });
});
