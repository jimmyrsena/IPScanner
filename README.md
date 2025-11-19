🖥️ Rastreador de IP da Rede Local
📌 Descrição
O Rastreador de IP da Rede Local é uma ferramenta para monitoramento e diagnóstico de dispositivos conectados à rede interna (LAN). Ele permite identificar dispositivos online e offline, além de localizar equipamentos específicos pelo endereço IP ou MAC.
Ideal para administradores de rede, profissionais de TI e usuários que desejam ter maior controle sobre os dispositivos conectados.

✅ Funcionalidades

Escaneamento da Rede

Detecta dispositivos conectados à rede local.
Exibe status Online ou Offline.
Busca por Endereço IP
Permite iniciar a varredura a partir de um IP base (ex.: 192.168.1.x).

Busca por Endereço MAC
Localiza dispositivos específicos pelo endereço físico da placa de rede.

Modos de Varredura

Automático: Escaneia a rede sem necessidade de configuração manual.
Manual: Permite ajustes personalizados.

🛠️ Tecnologias Utilizadas

Frontend: HTML5, CSS3, JavaScript
Backend: Node.js ou Python (dependendo da implementação)
Bibliotecas:
Para varredura: arp, ping, ou bibliotecas específicas de rede
Para interface: Bootstrap ou Tailwind CSS

🚀 Instalação e Uso
Pré-requisitos

Node.js (>= 14) ou Python (>= 3.8)
Acesso à rede local
Permissões administrativas para executar comandos de rede

Instalação
Clone o repositório:
Shellgit clone https://github.com/seuusuario/rastreador-ip-rede-local.gitMostrar mais linhas
Instale as dependências:
Shell# Se for Node.jsnpm install# Se for Pythonpip install -r requirements.txtMostrar mais linhas

Execução
Shell# Node.jsnpm start# Pythonpython app.pyMostrar mais linhas
Acesse no navegador:
http://localhost:3000

🔍 Como Usar
Insira o IP inicial (ex.: 192.168.1.).
Clique em Escanear Rede para iniciar a varredura.
Visualize os dispositivos Online e Offline.
Para busca específica, insira o endereço MAC e clique em Procurar por MAC.

📷 Interface
!Tela Principal

⚠️ Avisos

Use apenas em redes autorizadas.
Ferramenta destinada a fins de monitoramento legítimos.

🤝 Contribuição
Contribuições são bem-vindas!
Faça um fork, crie uma branch e envie um pull request.

📄 Licença
Este projeto está licenciado sob a MIT License.
