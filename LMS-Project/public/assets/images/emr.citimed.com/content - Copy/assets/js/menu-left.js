async function loadMenuLeft() {
  const shell = document.getElementById('menu-left-shell');
  if (!shell) return;

  shell.innerHTML = '<aside class="sidebar" id="menu-left" aria-label="Main navigation"><div class="brand"><span class="brand-mark"></span><span>CITIMED</span></div><div id="menu-left-content"></div></aside>';

  const menu = await apiGet('menu-left.php');
  const target = document.getElementById('menu-left-content');
  if (!target) return;

  target.innerHTML = menu.map(g => `
    <div class="nav-group">
      <div class="nav-title">${escapeHtml(g.group)}</div>
      ${g.items.map(x => `
        <a class="nav-item ${x.active ? 'active' : ''}" href="${escapeHtml(x.href)}">
          <span class="nav-icon">${iconSvg(x.icon)}</span>
          <span>${escapeHtml(x.label)}</span>
        </a>
      `).join('')}
    </div>
  `).join('');
}