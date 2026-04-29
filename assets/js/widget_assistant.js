/**
 * Cetusg Plus - Assistente Virtual (Widget Onboarding)
 * Lógica de Fluxo e Integração com Peixinho IA
 */

const WidgetAssistant = {
    isOpen: false,
    currentStep: 0,
    flow: [
        {
            id: 1,
            mensagem: "Olá! 👋 Sou o assistente do Cetusg Plus. Como posso te ajudar hoje?",
            tipo: "mensagem",
            next: 2
        },
        {
            id: 2,
            mensagem: "Você está acessando o sistema como:",
            tipo: "opcoes",
            opcoes: ["Usuário do sistema", "Gestor", "Visitante", "Suporte"],
            next: 3
        },
        {
            id: 3,
            mensagem: "Qual módulo você precisa entender melhor agora?",
            tipo: "opcoes",
            opcoes: ["Chat Corporativo", "Relatórios", "Gestão de Usuários", "Patrimônio"],
            next: 'ai_mode'
        }
    ],

    init() {
        const launcher = document.getElementById('widget-launcher');
        const panel = document.getElementById('widget-panel');
        const msgContainer = document.getElementById('widget-messages');

        if (!launcher || !panel || !msgContainer) return;

        this.renderLauncher();
        this.bindEvents();
        
        // Se houver histórico no sessionStorage, carregar
        const history = sessionStorage.getItem('widget_history');
        if (history) {
            msgContainer.innerHTML = history;
        } else {
            // Iniciar fluxo pela primeira vez
            setTimeout(() => this.processStep(0), 1000);
        }
    },

    renderLauncher() {
        const launcher = document.getElementById('widget-launcher');
        if (launcher) launcher.style.display = 'flex';
    },

    toggle() {
        const panel = document.getElementById('widget-panel');
        const launcher = document.getElementById('widget-launcher');
        
        this.isOpen = !this.isOpen;
        
        if (this.isOpen) {
            panel.classList.add('open');
            launcher.innerHTML = '<i class="fa-solid fa-xmark"></i>';
            launcher.classList.add('active');
            // Remover badge ao abrir
            const badge = launcher.querySelector('.widget-badge');
            if (badge) badge.remove();
        } else {
            panel.classList.remove('open');
            launcher.innerHTML = '<i class="fa-solid fa-robot"></i>';
            launcher.classList.remove('active');
        }
    },

    bindEvents() {
        const launcher = document.getElementById('widget-launcher');
        const closeBtn = document.getElementById('widget-close');
        const input = document.getElementById('widget-input-text');
        const sendBtn = document.getElementById('widget-send-btn');

        if (launcher) launcher.addEventListener('click', () => this.toggle());
        if (closeBtn) closeBtn.addEventListener('click', () => this.toggle());
        
        if (input && sendBtn) {
            const sendMsg = () => {
                const text = input.value.trim();
                if (text) {
                    this.addUserMessage(text);
                    this.sendToAI(text);
                    input.value = '';
                }
            };

            sendBtn.addEventListener('click', sendMsg);
            input.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') sendMsg();
            });
        }
    },

    processStep(stepIndex) {
        if (stepIndex === 'ai_mode') {
            this.addBotMessage("Entendi! 😊 Agora você pode me fazer qualquer pergunta sobre o Cetusg ou pedir ajuda com essas tarefas.");
            return;
        }

        const step = this.flow[stepIndex];
        if (!step) return;

        this.addBotMessage(step.mensagem);

        if (step.tipo === 'opcoes') {
            setTimeout(() => {
                this.renderOptions(step.opcoes, step.next);
            }, 600);
        } else if (step.next) {
            setTimeout(() => this.processStep(stepIndex + 1), 1500);
        }
    },

    addBotMessage(text) {
        const container = document.getElementById('widget-messages');
        const msgDiv = document.createElement('div');
        msgDiv.className = 'widget-msg bot';
        msgDiv.innerHTML = text;
        container.appendChild(msgDiv);
        this.scrollToBottom();
        this.saveHistory();
    },

    addUserMessage(text) {
        const container = document.getElementById('widget-messages');
        const msgDiv = document.createElement('div');
        msgDiv.className = 'widget-msg user';
        msgDiv.innerText = text;
        container.appendChild(msgDiv);
        this.scrollToBottom();
    },

    renderOptions(options, nextStep) {
        const container = document.getElementById('widget-messages');
        const optDiv = document.createElement('div');
        optDiv.className = 'widget-options';
        
        options.forEach(opt => {
            const btn = document.createElement('button');
            btn.className = 'opt-btn';
            btn.innerText = opt;
            btn.onclick = () => {
                this.addUserMessage(opt);
                optDiv.remove();
                
                // Se o próximo passo for um índice numérico ou string 'ai_mode'
                const nextIndex = typeof nextStep === 'number' ? nextStep - 1 : nextStep;
                setTimeout(() => this.processStep(nextIndex), 600);
            };
            optDiv.appendChild(btn);
        });
        
        container.appendChild(optDiv);
        this.scrollToBottom();
    },

    showTyping() {
        const container = document.getElementById('widget-messages');
        const typingDiv = document.createElement('div');
        typingDiv.className = 'widget-msg bot typing-indicator';
        typingDiv.id = 'widget-typing';
        typingDiv.innerHTML = '<span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span>';
        container.appendChild(typingDiv);
        this.scrollToBottom();
    },

    hideTyping() {
        const typing = document.getElementById('widget-typing');
        if (typing) typing.remove();
    },

    async sendToAI(text) {
        this.showTyping();
        
        try {
            const formData = new FormData();
            formData.append('content', text);

            const response = await fetch('chat_api.php?action=widget_message', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            this.hideTyping();

            if (data.success) {
                this.addBotMessage(data.response);
            } else {
                this.addBotMessage("Desculpe, tive um problema ao processar sua pergunta. Pode tentar novamente?");
            }
        } catch (e) {
            this.hideTyping();
            this.addBotMessage("Estou offline no momento. Por favor, verifique sua conexão.");
        }
    },

    scrollToBottom() {
        const container = document.getElementById('widget-messages');
        container.scrollTop = container.scrollHeight;
    },

    saveHistory() {
        const container = document.getElementById('widget-messages');
        sessionStorage.setItem('widget_history', container.innerHTML);
    }
};

// Inicializar quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => WidgetAssistant.init());
