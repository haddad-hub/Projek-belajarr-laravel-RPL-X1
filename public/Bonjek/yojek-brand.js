/* Shared visual identity for the Yojek customer, courier, and admin apps. */
(() => {
  const style = document.createElement('style');
  style.textContent = `
    :root, body, body.dark-mode, body.theme-maroon, body.theme-neon { --accent:#dc2626!important; --accent-2:#ef4444!important; --accent-light:#fca5a5!important; --accent-glow:#fecaca!important; --accent-dim:#fef2f2!important; --pinksoft:#fff5f5!important; --bg:#fffafa!important; --surface:#fff!important; --text:#2f1717!important; --muted:#8b5e5e!important; --border:#fecaca!important; --border-strong:#fca5a5!important; --shadow:rgba(185,28,28,.12)!important; }
    body, body.dark-mode, body.theme-maroon, body.theme-neon { color:var(--text)!important; background:radial-gradient(circle at top right,rgba(252,165,165,.25),transparent 32%),linear-gradient(115deg,#fffafa 0%,#fff 52%,#fff1f2 100%)!important; }
    .top,.topbar,.btn,.btn-primary,.sidebar-collapsed + .sidebar-toggle,.shell.sidebar-collapsed ~ .sidebar-logo-toggle { background:linear-gradient(135deg,#b91c1c,#ef4444 58%,#fca5a5)!important; }
    .side,.sidebar,body.dark-mode .side,body.dark-mode .sidebar,body.theme-maroon .side,body.theme-neon .side,body.theme-maroon .sidebar,body.theme-neon .sidebar { background:linear-gradient(180deg,#fff,#fff5f5)!important; color:var(--text)!important; }
    .card,.metric-card,.order,.empty,.sidebar-panel,.sidebar-note,.setting-row,body.dark-mode .card,body.dark-mode .metric-card,body.dark-mode .order,body.dark-mode .empty,body.dark-mode .sidebar-panel,body.dark-mode .sidebar-note { background:#fff!important; color:var(--text)!important; border-color:var(--border)!important; }
    .nav-btn.active,.nav-btn:hover,.tab.active,.metric-icon,.badge,body.dark-mode .nav-btn.active,body.dark-mode .nav-btn:hover,body.dark-mode .metric-icon,body.dark-mode .badge { background:#fef2f2!important; color:#dc2626!important; }
    .field,.btn-ghost,body.dark-mode .field,body.dark-mode .btn-ghost { background:#fff!important; color:var(--text)!important; border-color:var(--border)!important; }
    .logo,.brand-mark { box-shadow:0 12px 26px rgba(220,38,38,.14)!important; }
  `;
  document.head.appendChild(style);
  const replaceBrand = value => value.replace(/Pink Bonjek/gi, 'Merah Putih Yojek').replace(/Bonjek/gi, match => match === match.toUpperCase() ? 'YOJEK' : 'Yojek');
  const applyName = () => {
    document.title = replaceBrand(document.title);
    document.querySelectorAll('img[src$="logo.png"]').forEach(image => {
      image.src = 'Cokelat Minimalis Kedai Kopi Logo.png';
      image.alt = 'Logo Yojek';
    });
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    const nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);
    nodes.forEach(node => { node.nodeValue = replaceBrand(node.nodeValue); });
    document.querySelectorAll('[alt],[title],[placeholder],[aria-label]').forEach(el => ['alt','title','placeholder','aria-label'].forEach(attr => {
      if (el.hasAttribute(attr)) el.setAttribute(attr, replaceBrand(el.getAttribute(attr)));
    }));
  };
  document.addEventListener('DOMContentLoaded', applyName);
  new MutationObserver(applyName).observe(document.documentElement, {childList:true, subtree:true});
})();
