(function () {
  var b = document.querySelector('[data-menu]'), n = document.querySelector('[data-nav]');
  if (b && n) b.addEventListener('click', function () {
    var o = n.classList.toggle('open'); b.setAttribute('aria-expanded', o ? 'true' : 'false');
  });
  var c = document.querySelector('[data-fav-count]');
  if (c) { try { c.textContent = (JSON.parse(localStorage.getItem('favs') || '[]') || []).length; } catch (e) {} }
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (ev) { if (!confirm(f.getAttribute('data-confirm'))) ev.preventDefault(); });
  });
})();
(function () {
  function favs() { try { return JSON.parse(localStorage.getItem('favs') || '[]') || []; } catch (e) { return []; } }
  function save(a) { try { localStorage.setItem('favs', JSON.stringify(a)); } catch (e) {} var c = document.querySelector('[data-fav-count]'); if (c) c.textContent = a.length; }
  function toast(t) { var d = document.createElement('div'); d.className = 'toast'; d.setAttribute('role', 'status'); d.textContent = t; document.body.appendChild(d); setTimeout(function () { d.remove(); }, 2200); }
  document.querySelectorAll('[data-fav]').forEach(function (b) {
    var id = +b.getAttribute('data-fav'); b.setAttribute('aria-pressed', favs().indexOf(id) > -1 ? 'true' : 'false');
    b.addEventListener('click', function () {
      var a = favs(), i = a.indexOf(id);
      if (i > -1) { a.splice(i, 1); toast('Removido dos favoritos'); } else { a.push(id); toast('Adicionado aos favoritos'); }
      save(a); b.setAttribute('aria-pressed', i > -1 ? 'false' : 'true');
    });
  });
  document.querySelectorAll('[data-gal]').forEach(function (b) { b.addEventListener('click', function () { var m = document.getElementById('galMain'); if (m) m.src = b.getAttribute('data-gal'); }); });
  document.querySelectorAll('[data-copy]').forEach(function (b) { b.addEventListener('click', function () { if (navigator.clipboard) navigator.clipboard.writeText(b.getAttribute('data-copy')).then(function () { toast('Link copiado'); }); }); });
  var box = document.getElementById('links'), tpl = document.getElementById('linkTpl'), add = document.querySelector('[data-add-link]');
  if (box && tpl && add) {
    var n = box.children.length + 100;
    add.addEventListener('click', function () { box.insertAdjacentHTML('beforeend', tpl.innerHTML.replace(/__i__/g, n++)); });
    box.addEventListener('click', function (e) { if (e.target.hasAttribute('data-del-row')) e.target.closest('[data-row]').remove(); });
    if (!box.children.length && !add.disabled) add.click();
  }
})();
(function () {
  document.querySelectorAll('[data-out]').forEach(function (r) { var o = r.parentNode.querySelector('output'); r.addEventListener('input', function () { if (o) o.textContent = r.value; }); });
  var bn = document.querySelector('[data-bn]'); if (!bn) return;
  var tr = bn.querySelector('.bn-track'), dots = bn.querySelectorAll('.bn-d button'), n = tr.children.length, t;
  function idx() { return Math.round(tr.scrollLeft / tr.clientWidth); }
  function go(i) { tr.scrollTo({ left: ((i + n) % n) * tr.clientWidth, behavior: 'smooth' }); }
  function mark() { var i = idx(); dots.forEach(function (d, k) { d.classList.toggle('on', k === i); }); }
  tr.addEventListener('scroll', mark, { passive: true }); mark();
  var p = bn.querySelector('.prev'), x = bn.querySelector('.next');
  if (p) p.addEventListener('click', function () { go(idx() - 1); }); if (x) x.addEventListener('click', function () { go(idx() + 1); });
  dots.forEach(function (d, k) { d.addEventListener('click', function () { go(k); }); });
  function play() { if (n > 1 && !matchMedia('(prefers-reduced-motion:reduce)').matches) t = setInterval(function () { if (!document.hidden) go(idx() + 1); }, 5000); }
  bn.addEventListener('mouseenter', function () { clearInterval(t); }); bn.addEventListener('mouseleave', play); bn.addEventListener('touchstart', function () { clearInterval(t); }, { passive: true }); play();
})();
(function () {
  var v = document.getElementById('galVideo'), img = document.getElementById('galMain'), f = v && v.querySelector('iframe'), vid = v && v.querySelector('video');
  function showVideo() { if (img) img.hidden = true; v.hidden = false; if (f && !f.src.startsWith('http')) f.src = f.dataset.src; }
  function showImg() { if (img) img.hidden = false; if (v) { v.hidden = true; if (f) f.src = 'about:blank'; if (vid) vid.pause(); } }
  if (v) { showVideo(); var gv = document.querySelector('[data-galv]'); if (gv) gv.addEventListener('click', showVideo); document.querySelectorAll('[data-gal]').forEach(function (b) { b.addEventListener('click', showImg); }); }
  var dlg = document.getElementById('mailDlg'), btn = document.querySelector('[data-mail]');
  if (dlg && btn) {
    var form = document.getElementById('mailForm'), out = form.querySelector('.msg-out');
    btn.addEventListener('click', function () { out.textContent = ''; if (dlg.showModal) dlg.showModal(); else dlg.setAttribute('open', ''); });
    form.querySelector('[data-close]').addEventListener('click', function () { dlg.close(); });
    form.addEventListener('submit', function (e) {
      e.preventDefault(); out.textContent = 'Enviando...';
      fetch('/compartilhar-email', { method: 'POST', body: new FormData(form) }).then(function (r) { return r.json(); })
        .then(function (j) { out.textContent = j.msg; if (j.ok) setTimeout(function () { dlg.close(); }, 1500); }).catch(function () { out.textContent = 'Erro de conexão. Tente de novo.'; });
    });
  }
})();
(function () {
  var b = document.getElementById('impBtn'); if (!b) return; var out = document.getElementById('impOut');
  b.addEventListener('click', function () {
    var u = document.getElementById('impUrl').value.trim(); if (!u) { out.textContent = 'Cole o link primeiro.'; return; }
    out.textContent = 'Buscando na Shopee...'; b.disabled = true;
    var fd = new FormData(); fd.append('csrf', document.querySelector('input[name=csrf]').value); fd.append('url', u);
    fetch('/admin/importar', { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(function (j) {
      b.disabled = false; if (!j.ok) { out.textContent = j.msg; return; }
      document.querySelector('[name=title]').value = j.title;
      var add = document.querySelector('[data-add-link]'), rows = document.querySelectorAll('#links [data-row]'), row = rows[rows.length - 1];
      if (!row || row.querySelector('input[type=url]').value) { add.click(); rows = document.querySelectorAll('#links [data-row]'); row = rows[rows.length - 1]; }
      if (j.store_id) row.querySelector('select').value = j.store_id;
      row.querySelector('input[type=url]').value = j.url || '';
      row.querySelector('[name$="[price]"]').value = j.price != null ? String(j.price).replace('.', ',') : '';
      row.querySelector('[name$="[old_price]"]').value = j.old_price != null ? String(j.old_price).replace('.', ',') : '';
      if (j.image && window.__addImg) window.__addImg(j.image);
      out.textContent = (j.warn ? j.warn + ' ' : '') + (j.store_id ? '' : 'Cadastre a loja "Shopee" em Lojas para ligar o link automaticamente. ') + 'Pronto! Confira os dados, escolha a categoria e clique em Salvar produto.';
    }).catch(function () { b.disabled = false; out.textContent = 'Erro de conexão. Tente de novo.'; });
  });
})();
(function () { /* tamanho da caixa de descrição */
  var t = document.getElementById('descBox'); if (!t) return;
  var H = { s: '110px', m: '280px', l: '560px' }, manual = false, btns = document.querySelectorAll('[data-size]');
  function set(k) { t.style.height = H[k]; btns.forEach(function (b) { b.classList.toggle('on', b.dataset.size === k); }); }
  function auto() { if (manual) return; var n = t.value.length; set(n < 500 ? 's' : n < 2000 ? 'm' : 'l'); }
  btns.forEach(function (b) { b.addEventListener('click', function () { manual = true; set(b.dataset.size); }); });
  t.addEventListener('input', auto); auto();
})();
(function () { /* categorias: lista, Nenhuma, OK e criar na hora */
  var box = document.querySelector('[data-cats]'); if (!box) return;
  var panel = box.querySelector('[data-cat-panel]'), sum = box.querySelector('[data-cat-sum]'), edit = box.querySelector('[data-cat-edit]'), ni = document.getElementById('catNew'), msg = document.getElementById('catMsg');
  function upd() { var n = []; panel.querySelectorAll('.cat-i').forEach(function (l) { var c = l.querySelector('input'); l.classList.toggle('on', c.checked); if (c.checked) n.push(l.textContent.trim()); }); sum.innerHTML = ''; if (!n.length) { sum.textContent = '— Nenhuma categoria selecionada'; return; } n.forEach(function (x) { var t = document.createElement('span'); t.className = 'chip-sel'; t.textContent = x; sum.appendChild(t); }); }
  panel.addEventListener('change', upd);
  box.querySelector('[data-cat-none]').addEventListener('click', function () { panel.querySelectorAll('input[type=checkbox]').forEach(function (c) { c.checked = false; }); upd(); });
  box.querySelector('[data-cat-ok]').addEventListener('click', function () { panel.hidden = true; edit.hidden = false; });
  edit.addEventListener('click', function () { panel.hidden = false; edit.hidden = true; });
  function create() {
    var v = ni.value.trim(); if (!v) { msg.textContent = 'Digite o nome da categoria.'; return; }
    var fd = new FormData(); fd.append('csrf', document.querySelector('input[name=csrf]').value); fd.append('name', v);
    fetch('/admin/categorias/rapida', { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(function (j) {
      if (!j.ok) { msg.textContent = j.msg; return; }
      var ex = panel.querySelector('input[value="' + j.id + '"]');
      if (ex) ex.checked = true; else { var l = document.createElement('label'); l.className = 'cat-i'; var c = document.createElement('input'); c.type = 'checkbox'; c.name = 'cats[]'; c.value = j.id; c.checked = true; l.appendChild(c); l.appendChild(document.createTextNode(' ' + j.name)); panel.querySelector('.cat-list').appendChild(l); }
      ni.value = ''; msg.textContent = 'Categoria criada e marcada.'; upd();
    }).catch(function () { msg.textContent = 'Erro de conexão. Tente de novo.'; });
  }
  document.getElementById('catNewBtn').addEventListener('click', create);
  ni.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); create(); } });
  upd();
})();
(function () { /* fotos: prévia, ampliar e reordenar antes de salvar */
  var grid = document.getElementById('imgGrid'); if (!grid) return;
  var items = (window.IMGS || []).map(function (x) { return { t: 'e', id: x.id, src: x.src }; }), dels = [], drag = -1;
  var real = document.getElementById('imgReal'), vis = document.getElementById('imgInput'), ord = document.getElementById('imgOrder'), hid = document.getElementById('imgHidden'), dlg = document.getElementById('zoomDlg'), zi = document.getElementById('zoomImg');
  function hidden(n, v) { var i = document.createElement('input'); i.type = 'hidden'; i.name = n; i.value = v; hid.appendChild(i); }
  function sync() {
    var dt = new DataTransfer(), k = 0, o = [];
    items.forEach(function (it) { if (it.t === 'n') { dt.items.add(it.file); o.push('n:' + (k++)); } else if (it.t === 'e') o.push('e:' + it.id); else o.push('i:' + it.path); });
    real.files = dt.files; ord.value = JSON.stringify(o); hid.innerHTML = '';
    dels.forEach(function (id) { hidden('del_img[]', id); }); items.forEach(function (it) { if (it.t === 'i') hidden('import_images[]', it.path); });
    render();
  }
  function move(i, j) { if (j < 0 || j >= items.length) return; var it = items.splice(i, 1)[0]; items.splice(j, 0, it); sync(); }
  function render() {
    grid.innerHTML = '';
    items.forEach(function (it, i) {
      var d = document.createElement('div'); d.className = 'im'; d.draggable = true;
      var im = document.createElement('img'); im.src = it.src; im.alt = 'Foto ' + (i + 1); im.addEventListener('click', function () { zi.src = it.src; if (dlg.showModal) dlg.showModal(); });
      var tag = document.createElement('span'); tag.className = 'im-n'; tag.textContent = i === 0 ? '★ Capa' : String(i + 1);
      var bar = document.createElement('span'); bar.className = 'im-b';
      [['◀', 'Mover para antes', function () { move(i, i - 1); }], ['▶', 'Mover para depois', function () { move(i, i + 1); }],
       ['✕', 'Remover foto', function () { var r = items.splice(i, 1)[0]; if (r.t === 'e') dels.push(r.id); if (r.t === 'n') URL.revokeObjectURL(r.src); sync(); }]].forEach(function (b) {
        var bt = document.createElement('button'); bt.type = 'button'; bt.textContent = b[0]; bt.title = b[1]; bt.setAttribute('aria-label', b[1]); bt.addEventListener('click', b[2]); bar.appendChild(bt);
      });
      d.addEventListener('dragstart', function (e) { drag = i; e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', String(i)); } catch (x) {} });
      d.addEventListener('dragover', function (e) { e.preventDefault(); });
      d.addEventListener('drop', function (e) { e.preventDefault(); if (drag > -1 && drag !== i) move(drag, i); drag = -1; });
      d.appendChild(im); d.appendChild(tag); d.appendChild(bar); grid.appendChild(d);
    });
  }
  vis.addEventListener('change', function () { Array.prototype.forEach.call(vis.files, function (f) { items.push({ t: 'n', file: f, src: URL.createObjectURL(f) }); }); vis.value = ''; sync(); });
  window.__addImg = function (p) { items = items.filter(function (x) { return x.t !== 'i'; }); items.push({ t: 'i', path: p, src: p }); sync(); };
  if (dlg) dlg.addEventListener('click', function (e) { if (e.target === dlg || e.target.hasAttribute('data-close')) dlg.close(); });
  sync();
})();
(function () { /* avisa ANTES de enviar se os arquivos passam do limite do servidor (nada é perdido) */
  var f = document.getElementById('prodForm'); if (!f) return; var mf = +f.dataset.maxfile, mp = +f.dataset.maxpost;
  function mb(n) { return Math.round(n / 1048576 * 10) / 10; }
  f.addEventListener('submit', function (e) {
    var tot = 0, bad = '';
    f.querySelectorAll('input[type=file]').forEach(function (i) { Array.prototype.forEach.call(i.files, function (x) { tot += x.size; if (mf && x.size > mf) bad = x.name; }); });
    if (bad) { e.preventDefault(); alert('O arquivo "' + bad + '" passa do limite do servidor (' + mb(mf) + ' MB por arquivo). Escolha um menor, ou aumente upload_max_filesize no php.ini.'); }
    else if (mp && tot > mp * 0.95) { e.preventDefault(); alert('O total dos arquivos (' + mb(tot) + ' MB) passa do limite de envio do servidor (' + mb(mp) + ' MB). Envie menos arquivos de uma vez, ou aumente post_max_size no php.ini.'); }
  });
})();
(function () { /* carrossel dos destaques: setas nas laterais e um pontinho por produto */
  document.querySelectorAll('[data-hs]').forEach(function (hs) {
    var tr = hs.querySelector('.hscroll'), items = tr.children, dots = hs.querySelector('.hs-d'), prev = hs.querySelector('.prev'), next = hs.querySelector('.next');
    for (var i = 0; i < items.length; i++) (function (i) { var b = document.createElement('button'); b.type = 'button'; b.setAttribute('aria-label', 'Ir para o produto ' + (i + 1)); b.addEventListener('click', function () { tr.scrollTo({ left: items[i].offsetLeft, behavior: 'smooth' }); }); dots.appendChild(b); })(i);
    function cur() { var best = 0, min = 1e9; for (var i = 0; i < items.length; i++) { var d = Math.abs(items[i].offsetLeft - tr.scrollLeft); if (d < min) { min = d; best = i; } } return best; }
    function mark() { var c = cur(), end = tr.scrollLeft + tr.clientWidth >= tr.scrollWidth - 2; if (end) c = items.length - 1; dots.querySelectorAll('button').forEach(function (b, k) { b.classList.toggle('on', k === c); }); if (prev) prev.disabled = tr.scrollLeft <= 2; if (next) next.disabled = end; }
    function step(dir) { var w = items[0].getBoundingClientRect().width + 12; tr.scrollBy({ left: dir * w * Math.max(1, Math.floor(tr.clientWidth / w)), behavior: 'smooth' }); }
    if (prev) prev.addEventListener('click', function () { step(-1); }); if (next) next.addEventListener('click', function () { step(1); });
    tr.addEventListener('scroll', mark, { passive: true }); window.addEventListener('resize', mark); mark();
  });
})();
(function () { /* destaques: rolagem automática (pausa ao passar o mouse ou tocar) */
  document.querySelectorAll('[data-hs]').forEach(function (hs) {
    var tr = hs.querySelector('.hscroll'); if (!tr || tr.children.length < 2 || matchMedia('(prefers-reduced-motion:reduce)').matches) return;
    var t;
    function tick() {
      if (document.hidden) return;
      if (tr.scrollLeft + tr.clientWidth >= tr.scrollWidth - 2) tr.scrollTo({ left: 0, behavior: 'smooth' });
      else tr.scrollBy({ left: tr.children[0].getBoundingClientRect().width + 12, behavior: 'smooth' });
    }
    function stop() { clearInterval(t); } function start() { stop(); t = setInterval(tick, 3500); }
    hs.addEventListener('mouseenter', stop); hs.addEventListener('mouseleave', start); hs.addEventListener('focusin', stop); hs.addEventListener('focusout', start);
    hs.addEventListener('touchstart', stop, { passive: true }); hs.addEventListener('touchend', function () { setTimeout(start, 4000); }, { passive: true });
    start();
  });
})();
