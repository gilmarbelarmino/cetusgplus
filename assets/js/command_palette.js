/**
 * Cetusg Command Palette
 * Shortcut: CTRL + K
 */
document.addEventListener('DOMContentLoaded', () => {
    console.log('Command Palette Initialized');
    const paletteHtml = `
        <div id="commandPalette" class="palette-overlay" style="display: none;">
            <div class="palette-container">
                <div class="palette-header">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="paletteInput" placeholder="O que você deseja fazer? (ex: 'criar ticket', 'ver rh', 'usuarios')" autocomplete="off">
                    <kbd>ESC</kbd>
                </div>
                <div id="paletteResults" class="palette-results">
                    <!-- Results will be injected here -->
                </div>
                <div class="palette-footer">
                    <span><kbd>↑↓</kbd> Navegar</span>
                    <span><kbd>ENTER</kbd> Selecionar</span>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', paletteHtml);

    const palette = document.getElementById('commandPalette');
    const input = document.getElementById('paletteInput');
    const results = document.getElementById('paletteResults');

    // Menu items mapping (derived from sidebar)
    const commands = [
        { title: 'Dashboard', icon: 'fa-house', url: 'index.php?page=dashboard', category: 'Principal' },
        { title: 'Recursos Humanos', icon: 'fa-users', url: 'index.php?page=rh', category: 'Operacional' },
        { title: 'Voluntariado', icon: 'fa-heart', url: 'index.php?page=voluntariado', category: 'Operacional' },
        { title: 'Patrimônio', icon: 'fa-box-archive', url: 'index.php?page=patrimonio', category: 'Gestão de Ativos' },
        { title: 'Empréstimos', icon: 'fa-handshake', url: 'index.php?page=emprestimos', category: 'Gestão de Ativos' },
        { title: 'Chamados (Tickets)', icon: 'fa-ticket', url: 'index.php?page=chamados', category: 'Gestão de Ativos' },
        { title: 'Novo Chamado', icon: 'fa-plus', action: () => document.getElementById('ticketModal')?.style.display ? (document.getElementById('ticketModal').style.display='flex') : (window.location.href='index.php?page=chamados'), category: 'Ações Rápidas' },
        { title: 'Orçamentos', icon: 'fa-receipt', url: 'index.php?page=orcamentos', category: 'Infraestrutura' },
        { title: 'Salas', icon: 'fa-door-closed', url: 'index.php?page=salas', category: 'Infraestrutura' },
        { title: 'Usuários', icon: 'fa-user-gear', url: 'index.php?page=usuarios', category: 'Configurações' },
        { title: 'Configurações', icon: 'fa-gear', url: 'index.php?page=configuracoes', category: 'Configurações' },
        { title: 'Alternar Tema', icon: 'fa-circle-half-stroke', action: () => window.toggleTheme(), category: 'Sistema' },
        { title: 'Sair do Sistema', icon: 'fa-arrow-right-from-bracket', url: 'logout.php', category: 'Sistema' }
    ];

    let selectedIndex = 0;

    window.openPalette = function() {
        palette.style.display = 'flex';
        input.focus();
        renderResults('');
    };

    window.closePalette = function() {
        palette.style.display = 'none';
        input.value = '';
    };

    function renderResults(search) {
        const filtered = commands.filter(c => 
            c.title.toLowerCase().includes(search.toLowerCase()) || 
            c.category.toLowerCase().includes(search.toLowerCase())
        );

        if (filtered.length === 0) {
            results.innerHTML = '<div class="palette-empty">Nenhum resultado encontrado.</div>';
            return;
        }

        results.innerHTML = filtered.map((c, i) => `
            <div class="palette-item ${i === selectedIndex ? 'active' : ''}" onclick="executeCommand(${commands.indexOf(c)})">
                <i class="fa-solid ${c.icon}"></i>
                <div class="palette-item-info">
                    <span class="palette-item-title">${c.title}</span>
                    <span class="palette-item-category">${c.category}</span>
                </div>
                ${i === selectedIndex ? '<i class="fa-solid fa-chevron-right palette-chevron"></i>' : ''}
            </div>
        `).join('');
    }

    window.executeCommand = function(index) {
        const cmd = commands[index];
        closePalette();
        if (cmd.url) window.location.href = cmd.url;
        if (cmd.action) cmd.action();
    };

    // Keyboard Shortcuts
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            openPalette();
        }
        if (e.key === 'Escape') closePalette();

        if (palette.style.display === 'flex') {
            const items = results.querySelectorAll('.palette-item');
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedIndex = (selectedIndex + 1) % items.length;
                renderResults(input.value);
            }
            if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedIndex = (selectedIndex - 1 + items.length) % items.length;
                renderResults(input.value);
            }
            if (e.key === 'Enter') {
                e.preventDefault();
                const active = results.querySelector('.palette-item.active');
                if (active) active.click();
            }
        }
    });

    input.addEventListener('input', (e) => {
        selectedIndex = 0;
        renderResults(e.target.value);
    });

    // Close on click outside
    palette.addEventListener('click', (e) => {
        if (e.target === palette) closePalette();
    });
});
