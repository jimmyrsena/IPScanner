<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Redes Internet</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
  <div class="container text-center mt-5">
    <h1 class="mb-4"><i class="fas fa-wifi"></i> Redes Internet</h1>
    <button id="scanBtn" class="btn btn-primary btn-lg animate-btn">
      <i class="fas fa-search"></i> Escanear Rede
    </button>
    <div class="progress mt-4" style="height: 25px;">
      <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%">0%</div>
    </div>
    <div id="deviceList" class="mt-4"></div>
  </div>
  <script src="assets/js/script.js"></script>
</body>
</html>
