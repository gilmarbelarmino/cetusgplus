<?php
$file = 'assets/css/style.css';
$content = file_get_contents($file);

$newChatWidgetStyles = '/* --- CHAT ARRASTAO TECH (WIDGET STYLE) --- */
.chat-pinned-bar {
    position: fixed;
    bottom: 25px;
    right: 25px;
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #2c4a7c, #1a3560);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 10001;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    color: white;
}

.chat-pinned-bar:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(0,0,0,0.3);
}

.chat-pinned-bar i {
    font-size: 24px;
    color: white !important;
}

.chat-pinned-bar .chat-badge {
    position: absolute;
    top: -2px;
    right: -2px;
    width: 20px;
    height: 20px;
    background: #ef4444;
    color: white;
    border-radius: 50%;
    border: 2px solid white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: 900;
}

.chat-pinned-content span {
    display: none; /* Esconder texto da barra antiga */
}

.chat-panel {
    position: fixed;
    bottom: 95px;
    right: 25px;
    width: 400px;
    max-width: 90vw;
    height: 600px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(15px);
    border-radius: 24px;
    box-shadow: 0 15px 50px rgba(0,0,0,0.25);
    border: 1px solid rgba(255,255,255,0.3);
    overflow: hidden;
    display: none;
    flex-direction: row;
    z-index: 10000;
    transition: all 0.4s ease;
}

/* Ajuste do layout interno para formato Widget */
.chat-user-list {
    width: 100% !important;
    transition: all 0.3s ease;
}

.chat-main {
    position: absolute;
    top: 0;
    left: 100%;
    width: 100%;
    height: 100%;
    background: white;
    transition: all 0.3s ease;
    z-index: 10;
}

/* Quando uma conversa está ativa, deslizar a tela */
.convo-active .chat-user-list {
    transform: translateX(-100%);
}
.convo-active .chat-main {
    left: 0;
}

.chat-header-list, .chat-header-main {
    background: linear-gradient(135deg, #2c4a7c, #1a3560);
    color: white;
    padding: 1rem !important;
}

.chat-header-user span, .chat-user-name {
    color: white !important;
}

.chat-messages-container {
    padding: 1.5rem !important;
    background: #f8fafc;
}

.message-bubble {
    border-radius: 16px !important;
}

/* Mobile */
@media (max-width: 768px) {
    .chat-panel {
        bottom: 0;
        right: 0;
        width: 100vw;
        height: 100vh;
        border-radius: 0;
    }
}';

// Substituir o bloco que acabamos de criar por esse novo estilo widgetizado
$pattern = '/\/\* --- MODERN CHAT SYSTEM \(BÚSSOLA STYLE\) --- \*\/.*?\/\* --- INTERNAL CHAT COMPONENTS --- \*\//s';
$replacement = $newChatWidgetStyles . "\n\n/* --- INTERNAL CHAT COMPONENTS --- */";

$updatedContent = preg_replace($pattern, $replacement, $content);
file_put_contents($file, $updatedContent);
echo "Sucesso: Chat Arrastão Tech transformado em Widget!";
?>
