<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CetusgTech - Plataforma de Gestão Inteligente</title>
    <meta name="description" content="Transforme a gestão da sua organização com a plataforma mais completa do mercado. Patrimônio, RH, TI e Voluntariado em um só lugar.">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:Arial,sans-serif;background:#020617;color:#fff;overflow-x:hidden;scroll-behavior:smooth}
        
        /* Animated Background */
        .bg-grid{position:fixed;inset:0;z-index:-1;background:radial-gradient(ellipse at 20% 50%,#1e1b4b 0%,#020617 70%)}
        .blob{position:fixed;border-radius:50%;filter:blur(120px);opacity:.08;animation:float 20s infinite alternate}
        .blob-1{width:600px;height:600px;background:#FBBF24;top:-100px;left:-100px}
        .blob-2{width:400px;height:400px;background:#3B82F6;bottom:-50px;right:-50px;animation-delay:5s}
        
        @keyframes float{0%{transform:translate(0,0) scale(1)}50%{transform:translate(30px,20px) scale(1.1)}100%{transform:translate(-20px,40px) scale(.95)}}
        @keyframes fadeUp{from{opacity:0;transform:translateY(40px)}to{opacity:1;transform:translateY(0)}}
        @keyframes countUp{from{opacity:0;transform:scale(.5)}to{opacity:1;transform:scale(1)}}
        @keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.05)}}
        
        /* Header */
        header{position:fixed;top:0;width:100%;padding:1.5rem 5%;display:flex;justify-content:space-between;align-items:center;z-index:1000;transition:all .3s}
        header.scrolled{background:rgba(2,6,23,.95);backdrop-filter:blur(20px);padding:1rem 5%;border-bottom:1px solid rgba(255,255,255,.05)}
        .logo{font-size:1.8rem;font-weight:900;letter-spacing:-1px;text-decoration:none;color:#fff}.logo span{color:#FBBF24}
        .header-btns{display:flex;gap:1rem;align-items:center}
        .btn-login{background:#fff;color:#020617;padding:.7rem 2rem;border-radius:100px;text-decoration:none;font-weight:800;font-size:.9rem;transition:all .3s;border:none;cursor:pointer}
        .btn-login:hover{background:#FBBF24;transform:translateY(-2px);box-shadow:0 10px 30px rgba(251,191,36,.3)}
        
        /* Hero */
        .hero{min-height:100vh;display:flex;align-items:center;justify-content:center;text-align:center;padding:120px 5% 80px;position:relative}
        .hero-content{max-width:900px;animation:fadeUp 1s ease-out}
        .hero-badge{display:inline-flex;align-items:center;gap:8px;background:rgba(251,191,36,.1);border:1px solid rgba(251,191,36,.2);padding:.5rem 1.2rem;border-radius:100px;font-size:.8rem;color:#FBBF24;font-weight:700;margin-bottom:2rem}
        .hero-badge i{animation:pulse 2s infinite}
        h1{font-size:clamp(2.5rem,5vw,4.5rem);font-weight:900;line-height:1.05;margin-bottom:1.5rem;background:linear-gradient(135deg,#fff 0%,#94a3b8 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
        h1 em{font-style:normal;background:linear-gradient(135deg,#FBBF24,#F59E0B);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
        .hero-sub{font-size:1.2rem;color:#94a3b8;line-height:1.7;max-width:700px;margin:0 auto 3rem}
        .hero-ctas{display:flex;gap:1rem;justify-content:center;flex-wrap:wrap}
        .btn-cta{padding:1rem 2.5rem;border-radius:100px;font-weight:800;font-size:1rem;text-decoration:none;transition:all .3s;cursor:pointer;border:none}
        .btn-primary-cta{background:linear-gradient(135deg,#FBBF24,#F59E0B);color:#000}.btn-primary-cta:hover{transform:translateY(-3px);box-shadow:0 15px 40px rgba(251,191,36,.3)}
        .btn-secondary-cta{background:rgba(255,255,255,.05);color:#fff;border:1px solid rgba(255,255,255,.1)}.btn-secondary-cta:hover{background:rgba(255,255,255,.1);transform:translateY(-3px)}
        
        /* Stats */
        .stats{display:flex;justify-content:center;gap:4rem;padding:3rem 5%;flex-wrap:wrap}
        .stat{text-align:center;animation:countUp .8s ease-out}
        .stat-number{font-size:3rem;font-weight:900;color:#FBBF24;display:block}
        .stat-label{font-size:.85rem;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:1px}
        
        /* Section Title */
        .section{padding:6rem 5%}
        .section-title{text-align:center;margin-bottom:4rem}
        .section-title h2{font-size:clamp(1.8rem,3vw,2.8rem);font-weight:900;margin-bottom:1rem}
        .section-title p{color:#94a3b8;max-width:600px;margin:0 auto;font-size:1.05rem}
        
        /* Modules Grid */
        .modules{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:2rem;max-width:1200px;margin:0 auto}
        .module-card{background:rgba(255,255,255,.02);border:1px solid rgba(255,255,255,.05);border-radius:24px;padding:2.5rem;transition:all .4s;position:relative;overflow:hidden}
        .module-card::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:var(--accent);opacity:0;transition:opacity .3s}
        .module-card:hover{transform:translateY(-10px);border-color:rgba(251,191,36,.2);background:rgba(255,255,255,.04)}.module-card:hover::before{opacity:1}
        .module-icon{width:60px;height:60px;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;margin-bottom:1.5rem}
        .module-card h3{font-size:1.3rem;font-weight:800;margin-bottom:.8rem}
        .module-card p{color:#94a3b8;line-height:1.6;font-size:.95rem;margin-bottom:1.2rem}
        .module-features{list-style:none;padding:0}
        .module-features li{padding:.4rem 0;color:#cbd5e1;font-size:.85rem;display:flex;align-items:center;gap:8px}
        .module-features li i{color:#10B981;font-size:.7rem}
        
        /* Benefits */
        .benefits{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:2rem;max-width:1200px;margin:0 auto}
        .benefit{text-align:center;padding:2rem}
        .benefit i{font-size:2.5rem;color:#FBBF24;margin-bottom:1rem;display:block}
        .benefit h4{font-size:1.1rem;margin-bottom:.5rem}
        .benefit p{color:#94a3b8;font-size:.9rem;line-height:1.5}
        
        /* Testimonial / Social Proof */
        .social-proof{background:rgba(251,191,36,.03);border-top:1px solid rgba(251,191,36,.1);border-bottom:1px solid rgba(251,191,36,.1);padding:5rem 5%;text-align:center}
        .quote{font-size:1.4rem;font-style:italic;color:#e2e8f0;max-width:800px;margin:0 auto 2rem;line-height:1.7}
        .quote-author{color:#FBBF24;font-weight:800}
        
        /* CTA Final */
        .cta-final{text-align:center;padding:6rem 5%;background:linear-gradient(180deg,transparent 0%,rgba(251,191,36,.03) 100%)}
        .cta-final h2{font-size:clamp(2rem,4vw,3rem);font-weight:900;margin-bottom:1rem}
        .cta-final p{color:#94a3b8;font-size:1.1rem;margin-bottom:2.5rem;max-width:600px;margin-inline:auto}
        .cta-box{display:inline-flex;flex-direction:column;align-items:center;gap:1rem;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:24px;padding:3rem}
        .cta-box .price{font-size:1rem;color:#94a3b8}.cta-box .price strong{font-size:2.5rem;color:#FBBF24}
        
        /* Footer */
        footer{padding:3rem 5%;display:flex;justify-content:space-between;align-items:center;border-top:1px solid rgba(255,255,255,.05);flex-wrap:wrap;gap:1rem}
        .btn-admin{color:#374151;text-decoration:none;font-size:.8rem;font-weight:600;display:flex;align-items:center;gap:6px;transition:color .3s}
        .btn-admin:hover{color:#FBBF24}
        
        /* Responsive */
        @media(max-width:768px){
            .hero{padding-top:100px}
            .stats{gap:2rem}
            .stat-number{font-size:2rem}
            .header-btns{gap:.5rem}
            .btn-login{padding:.5rem 1.2rem;font-size:.8rem}
            .hero-ctas{flex-direction:column;align-items:center}
            footer{flex-direction:column;text-align:center}
        }
    </style>
</head>
<body>
    <div class="bg-grid"></div>
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>

    <!-- HEADER -->
    <header id="mainHeader">
        <a href="#" class="logo">CETUSG<span>TECH</span></a>
        <div class="header-btns">
            <a href="login.php" class="btn-login"><i class="fa-solid fa-right-to-bracket"></i> Acessar Sistema</a>
        </div>
    </header>

    <!-- HERO -->
    <section class="hero">
        <div class="hero-content">
            <div class="hero-badge"><i class="fa-solid fa-bolt"></i> Plataforma líder em gestão integrada</div>
            <h1>Pare de <em>perder tempo</em> com planilhas. Gerencie <em>tudo</em> em um só lugar.</h1>
            <p class="hero-sub">Imagine ter o controle total de patrimônio, equipe, chamados de TI e voluntariado em uma única plataforma inteligente. <strong>Sem complicação. Sem papel. Sem desculpas.</strong></p>
            <div class="hero-ctas">
                <a href="#modulos" class="btn-cta btn-primary-cta"><i class="fa-solid fa-rocket"></i> Descubra o que podemos fazer</a>
                <a href="login.php" class="btn-cta btn-secondary-cta"><i class="fa-solid fa-play"></i> Entrar na plataforma</a>
            </div>
        </div>
    </section>

    <!-- STATS -->
    <div class="stats">
        <div class="stat"><span class="stat-number" data-target="6">0</span><span class="stat-label">Módulos Integrados</span></div>
        <div class="stat"><span class="stat-number" data-target="99">0</span><span class="stat-label">% Uptime Garantido</span></div>
        <div class="stat"><span class="stat-number" data-target="500">0</span><span class="stat-label">Ativos Gerenciados</span></div>
        <div class="stat"><span class="stat-number" data-target="24">0</span><span class="stat-label">Horas de Suporte</span></div>
    </div>

    <!-- MÓDULOS -->
    <section class="section" id="modulos">
        <div class="section-title">
            <h2>Tudo que sua organização precisa. <span style="color:#FBBF24">Em uma só tela.</span></h2>
            <p>Cada módulo foi projetado para resolver um problema real. Juntos, eles formam a plataforma de gestão mais completa do mercado.</p>
        </div>
        <div class="modules">
            <div class="module-card" style="--accent:#3B82F6">
                <div class="module-icon" style="background:rgba(59,130,246,.1);color:#3B82F6"><i class="fa-solid fa-box-archive"></i></div>
                <h3>Gestão de Patrimônio</h3>
                <p>Controle cada ativo da sua organização com rastreabilidade completa e histórico de movimentações.</p>
                <ul class="module-features">
                    <li><i class="fa-solid fa-circle"></i> Cadastro completo com fotos e QR Code</li>
                    <li><i class="fa-solid fa-circle"></i> Controle de empréstimos e devoluções</li>
                    <li><i class="fa-solid fa-circle"></i> Relatórios de depreciação e valor total</li>
                    <li><i class="fa-solid fa-circle"></i> Alertas de manutenção preventiva</li>
                </ul>
            </div>
            <div class="module-card" style="--accent:#8B5CF6">
                <div class="module-icon" style="background:rgba(139,92,246,.1);color:#8B5CF6"><i class="fa-solid fa-users"></i></div>
                <h3>Recursos Humanos</h3>
                <p>Gerencie seu capital humano de forma centralizada, do cadastro à documentação completa.</p>
                <ul class="module-features">
                    <li><i class="fa-solid fa-circle"></i> Ficha completa de colaboradores</li>
                    <li><i class="fa-solid fa-circle"></i> Controle de documentos e validades</li>
                    <li><i class="fa-solid fa-circle"></i> Organograma por setores e unidades</li>
                    <li><i class="fa-solid fa-circle"></i> Gestão de benefícios e acessos</li>
                </ul>
            </div>
            <div class="module-card" style="--accent:#10B981">
                <div class="module-icon" style="background:rgba(16,185,129,.1);color:#10B981"><i class="fa-solid fa-headset"></i></div>
                <h3>Chamados de TI & Suporte</h3>
                <p>Sistema de tickets inteligente com SLA dinâmico para garantir a continuidade do seu negócio.</p>
                <ul class="module-features">
                    <li><i class="fa-solid fa-circle"></i> Abertura rápida com prioridade automática</li>
                    <li><i class="fa-solid fa-circle"></i> SLA com cronômetro e alertas de atraso</li>
                    <li><i class="fa-solid fa-circle"></i> Dashboard de desempenho por técnico</li>
                    <li><i class="fa-solid fa-circle"></i> Histórico completo de resoluções</li>
                </ul>
            </div>
            <div class="module-card" style="--accent:#F59E0B">
                <div class="module-icon" style="background:rgba(245,158,11,.1);color:#F59E0B"><i class="fa-solid fa-heart"></i></div>
                <h3>Voluntariado</h3>
                <p>Engaje sua comunidade com controle total de horas, atividades e certificação automática.</p>
                <ul class="module-features">
                    <li><i class="fa-solid fa-circle"></i> Cadastro de voluntários com perfil completo</li>
                    <li><i class="fa-solid fa-circle"></i> Registro de horas e atividades</li>
                    <li><i class="fa-solid fa-circle"></i> Geração automática de certificados</li>
                    <li><i class="fa-solid fa-circle"></i> Relatórios para prestação de contas</li>
                </ul>
            </div>
            <div class="module-card" style="--accent:#EC4899">
                <div class="module-icon" style="background:rgba(236,72,153,.1);color:#EC4899"><i class="fa-solid fa-chart-line"></i></div>
                <h3>Relatórios & Analytics</h3>
                <p>Transforme dados em decisões com dashboards visuais e relatórios exportáveis.</p>
                <ul class="module-features">
                    <li><i class="fa-solid fa-circle"></i> Gráficos interativos em tempo real</li>
                    <li><i class="fa-solid fa-circle"></i> Exportação para PDF e Excel</li>
                    <li><i class="fa-solid fa-circle"></i> Indicadores de performance (KPIs)</li>
                    <li><i class="fa-solid fa-circle"></i> Visão consolidada multi-unidade</li>
                </ul>
            </div>
            <div class="module-card" style="--accent:#06B6D4">
                <div class="module-icon" style="background:rgba(6,182,212,.1);color:#06B6D4"><i class="fa-solid fa-door-closed"></i></div>
                <h3>Infraestrutura & Salas</h3>
                <p>Reserve espaços, gerencie orçamentos e mantenha o controle total da infraestrutura física.</p>
                <ul class="module-features">
                    <li><i class="fa-solid fa-circle"></i> Reserva de salas com calendário visual</li>
                    <li><i class="fa-solid fa-circle"></i> Controle de orçamentos e aprovações</li>
                    <li><i class="fa-solid fa-circle"></i> Manutenção preventiva programada</li>
                    <li><i class="fa-solid fa-circle"></i> Gestão de contratos e fornecedores</li>
                </ul>
            </div>
        </div>
    </section>

    <!-- BENEFÍCIOS / PNL -->
    <section class="section">
        <div class="section-title">
            <h2>Por que organizações <span style="color:#FBBF24">inteligentes</span> nos escolhem?</h2>
            <p>Não é apenas tecnologia. É a transformação completa da forma como você trabalha.</p>
        </div>
        <div class="benefits">
            <div class="benefit"><i class="fa-solid fa-bolt"></i><h4>Resultados Imediatos</h4><p>Em menos de 24 horas, sua equipe já estará operando com mais eficiência e controle.</p></div>
            <div class="benefit"><i class="fa-solid fa-shield-halved"></i><h4>Segurança Total</h4><p>Dados criptografados, backups automáticos e isolamento completo entre empresas.</p></div>
            <div class="benefit"><i class="fa-solid fa-cloud"></i><h4>100% na Nuvem</h4><p>Acesse de qualquer lugar, a qualquer hora. Sem instalação, sem complicação.</p></div>
            <div class="benefit"><i class="fa-solid fa-mobile-screen"></i><h4>Responsivo</h4><p>Funciona perfeitamente no computador, tablet ou celular. Sempre acessível.</p></div>
            <div class="benefit"><i class="fa-solid fa-puzzle-piece"></i><h4>Tudo Integrado</h4><p>Os módulos conversam entre si. Uma mudança no RH reflete automaticamente nos chamados.</p></div>
            <div class="benefit"><i class="fa-solid fa-headset"></i><h4>Suporte Humanizado</h4><p>Equipe dedicada para te ajudar a tirar o máximo da plataforma.</p></div>
        </div>
    </section>

    <!-- SOCIAL PROOF / PNL -->
    <section class="social-proof">
        <div class="hero-badge" style="margin-bottom:2rem"><i class="fa-solid fa-star"></i> Depoimento real</div>
        <p class="quote">"Antes da CetusgTech, perdíamos horas com planilhas e papéis. Hoje, tudo está a um clique de distância. A gestão de patrimônio e voluntariado nunca foi tão simples."</p>
        <p class="quote-author">— Gestão, Projeto Arrastão</p>
    </section>

    <!-- CTA FINAL / PNL - Escassez e Urgência -->
    <section class="cta-final">
        <h2>Pronto para <span style="color:#FBBF24">transformar</span> sua gestão?</h2>
        <p>Junte-se às organizações que já economizam tempo e dinheiro com a CetusgTech.</p>
        <div class="cta-box">
            <p class="price">A partir de <strong>R$ consulte</strong>/mês</p>
            <a href="login.php" class="btn-cta btn-primary-cta" style="font-size:1.1rem"><i class="fa-solid fa-rocket"></i> Começar Agora — É Rápido!</a>
            <p style="font-size:.8rem;color:#64748b">Sem fidelidade. Cancele quando quiser.</p>
        </div>
    </section>

    <!-- FOOTER -->
    <footer>
        <div style="color:#374151;font-size:.85rem">&copy; <?= date('Y') ?> CetusgTech. Todos os direitos reservados.</div>
        <a href="login.php" class="btn-admin"><i class="fa-solid fa-shield-halved"></i> Área do Administrador</a>
    </footer>

    <script>
        // Header scroll effect
        window.addEventListener('scroll',()=>{
            document.getElementById('mainHeader').classList.toggle('scrolled',window.scrollY>50);
        });
        // Counter animation
        const counters=document.querySelectorAll('.stat-number');
        const observer=new IntersectionObserver(entries=>{
            entries.forEach(e=>{if(e.isIntersecting){
                const t=+e.target.dataset.target;let c=0;
                const inc=t/40;
                const timer=setInterval(()=>{c+=inc;if(c>=t){e.target.textContent=t+'+';clearInterval(timer)}else{e.target.textContent=Math.ceil(c)+'+'}},30);
                observer.unobserve(e.target);
            }});
        },{threshold:.5});
        counters.forEach(c=>observer.observe(c));
        // Smooth reveal on scroll
        const cards=document.querySelectorAll('.module-card,.benefit');
        const revealObs=new IntersectionObserver(entries=>{
            entries.forEach(e=>{if(e.isIntersecting){e.target.style.opacity='1';e.target.style.transform='translateY(0)';revealObs.unobserve(e.target)}});
        },{threshold:.1});
        cards.forEach(c=>{c.style.opacity='0';c.style.transform='translateY(30px)';c.style.transition='all .6s ease-out';revealObs.observe(c)});
    </script>
</body>
</html>
