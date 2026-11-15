/* kulturvarliklari.com.tr — ortak istemci kodu (listem, sesli anlatım, harita, çevrimdışı) */
(function () {
  'use strict';
  var H = document.documentElement;
  var ROOT = H.getAttribute('data-root') || './';       // site köküne göreli yol
  var LANG = H.getAttribute('lang') || 'tr';
  var LBASE = ROOT + (LANG === 'tr' ? '' : LANG + '/'); // dil köküne göreli yol
  var I = window.KV_I18N || {};
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };

  /* ---------- yerel depolama (hata olursa sessizce bellekte tut) ---------- */
  var MEM = {};
  var LS = {
    get: function (k, d) { try { var v = localStorage.getItem(k); return v ? JSON.parse(v) : d; } catch (e) { return MEM[k] || d; } },
    set: function (k, v) { MEM[k] = v; try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} }
  };
  var KV = window.KV = {
    LS: LS,
    list: function (k) { return LS.get(k, []); },
    has: function (k, sid) { return LS.get(k, []).indexOf(sid) >= 0; },
    toggle: function (k, sid) { var a = LS.get(k, []), i = a.indexOf(sid); if (i < 0) a.push(sid); else a.splice(i, 1); LS.set(k, a); return i < 0; }
  };

  /* ---------- veri ---------- */
  var DATA = null, dataP = null;
  function data() {
    if (DATA) return Promise.resolve(DATA);
    if (!dataP) dataP = fetch(ROOT + 'data.json').then(function (r) { return r.json(); }).then(function (d) { var ty = I.types || {}, rg = I.regions || {}; d.forEach(function (p) { p.typeL = ty[p.type] || p.type; p.regionL = rg[p.region] || p.region; }); DATA = d; return d; });
    return dataP;
  }
  KV.data = data;
  function nm(p) { return (p.n && (p.n[LANG] || p.n.tr)) || p.name; }
  function km(a, b) {
    var t = function (x) { return x * Math.PI / 180; }, dl = t(b[0] - a[0]), dn = t(b[1] - a[1]);
    var h = Math.sin(dl / 2) * Math.sin(dl / 2) + Math.cos(t(a[0])) * Math.cos(t(b[0])) * Math.sin(dn / 2) * Math.sin(dn / 2);
    return 2 * 6371 * Math.asin(Math.sqrt(h));
  }
  KV.km = km;
  function placeUrl(p) { return LBASE + 'yer/' + p.slug + '.html'; }
  function card(p, extra) {
    return '<a class="pcard" href="' + placeUrl(p) + '">' +
      (p.img ? '<img loading="lazy" src="' + ROOT + p.img + '" alt="" width="320" height="200">' : '<span class="noimg"></span>') +
      '<span class="pc-b"><b>' + esc(nm(p)) + '</b><span>' + esc(p.city) + ' · ' + esc(p.typeL || p.type) + (extra ? ' · ' + extra : '') + '</span></span></a>';
  }
  KV.card = card;

  /* ---------- Listem / Gezdim düğmeleri ---------- */
  function syncBtn(b) {
    var k = b.getAttribute('data-k'), sid = b.getAttribute('data-sid'), on = KV.has(k, sid);
    b.classList.toggle('on', on);
    b.textContent = on ? b.getAttribute('data-on') : b.getAttribute('data-off');
  }
  $$('[data-k]').forEach(function (b) {
    syncBtn(b);
    b.addEventListener('click', function () { KV.toggle(b.getAttribute('data-k'), b.getAttribute('data-sid')); syncBtn(b); });
  });

  /* ---------- Service worker + çevrimdışı kaydet ---------- */
  if ('serviceWorker' in navigator && location.protocol === 'https:' && H.hasAttribute('data-sw')) {
    try { navigator.serviceWorker.register(ROOT + 'sw.js', { scope: ROOT }); } catch (e) {}
  }
  function saveOffline(urls, btn) {
    if (!('caches' in window)) { btn.textContent = '—'; return; }
    btn.disabled = true;
    caches.open('kv-pages').then(function (c) {
      return Promise.all(urls.map(function (u) { return fetch(u).then(function (r) { if (r.ok) return c.put(u, r); }).catch(function () {}); }));
    }).then(function () { btn.textContent = btn.getAttribute('data-done'); btn.classList.add('on'); }).catch(function () { btn.disabled = false; });
  }
  $$('[data-offline]').forEach(function (b) {
    b.addEventListener('click', function () {
      var urls = [location.href.split('#')[0]].concat((b.getAttribute('data-offline') || '').split('|').filter(Boolean).map(function (u) { return new URL(u, location.href).href; }));
      saveOffline(urls, b);
    });
  });

  /* ---------- Sesli anlatım (yer sayfaları) ---------- */
  var story = $('#story');
  if (story && window.KV_TEXT) {
    var T = window.KV_TEXT, SID = H.getAttribute('data-sid');
    var VO = { tr: 'tr-TR', en: 'en-US', de: 'de-DE', ru: 'ru-RU', fr: 'fr-FR', es: 'es-ES', ar: 'ar-SA', it: 'it-IT' };
    var cur = LANG, on = false, tok = 0, s = window.speechSynthesis;
    var btn = $('#listen'), note = $('#note'), aiNote = $('#ainote');
    var isIOS = /iP(hone|ad|od)/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    if (s) { try { s.getVoices(); } catch (e) {} }
    var stop = function () { tok++; try { if (s && (s.speaking || s.pending)) s.cancel(); } catch (e) {} on = false; btn.classList.remove('playing'); btn.textContent = I.listen; };
    var show = function (lang, txt, ai) {
      cur = lang; story.textContent = txt; story.lang = lang; story.dir = lang === 'ar' ? 'rtl' : 'ltr';
      if (aiNote) aiNote.hidden = !ai; note.textContent = '';
      $$('.tab').forEach(function (x) { x.classList.toggle('on', x.getAttribute('data-lang') === lang); });
      var sel = $('#moreLang'); if (sel && !ai) sel.value = '';
    };
    $$('.tab').forEach(function (t) { t.addEventListener('click', function () { stop(); var l = t.getAttribute('data-lang'); show(l, T[l], false); }); });
    var sel = $('#moreLang');
    if (sel) sel.addEventListener('change', function () {
      var l = sel.value; if (!l) return; stop();
      var key = 'kv_tr_' + SID + '_' + l, c = null;
      try { c = sessionStorage.getItem(key); } catch (e) {}
      if (c) { show(l, c, true); sel.value = l; return; }
      note.textContent = I.translating;
      fetch(ROOT + 'api/ceviri.php?sid=' + encodeURIComponent(SID) + '&lang=' + l, { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json().then(function (j) { if (!r.ok || !j.text) throw 0; return j.text; }); })
        .then(function (txt) { try { sessionStorage.setItem(key, txt); } catch (e) {} show(l, txt, true); sel.value = l; })
        .catch(function () { note.textContent = I.trans_err; sel.value = ''; });
    });
    btn.addEventListener('click', function () {
      if (!s || typeof SpeechSynthesisUtterance === 'undefined') { note.textContent = I.no_tts; return; }
      if (on) { stop(); return; }
      var my = ++tok, code = VO[cur] || cur;
      try { if (s.speaking || s.pending) s.cancel(); } catch (e) {}
      try { s.resume(); } catch (e) {}
      var vs = s.getVoices();
      var v = vs.filter(function (x) { return x.lang === code; })[0] || vs.filter(function (x) { return x.lang && x.lang.toLowerCase().indexOf(cur) === 0; })[0];
      var txt = story.textContent;
      var parts = (txt.match(/[^.!?;。؟]+[.!?;。؟]*/g) || [txt]).map(function (x) { return x.trim(); }).filter(Boolean);
      parts.forEach(function (p, i) {
        var u = new SpeechSynthesisUtterance(p); u.lang = code; if (v) u.voice = v; u.rate = 0.95;
        if (i === parts.length - 1) u.onend = function () { if (my === tok) stop(); };
        u.onerror = function (e) {
          if (my !== tok || e.error === 'interrupted' || e.error === 'canceled') return; stop();
          note.textContent = (e.error === 'language-unavailable' || e.error === 'voice-unavailable') ? I.no_voice : I.tts_err;
        };
        s.speak(u);
      });
      on = true; btn.classList.add('playing'); btn.textContent = I.stop;
      note.textContent = (v ? '' : I.default_voice + ' ') + (isIOS ? I.ios : '');
    });
    if (/[?&]qr=1/.test(location.search)) { btn.classList.add('pulse'); btn.scrollIntoView({ block: 'center' }); }
  }

  /* ---------- Ek fotoğraflar (Wikimedia Commons, isteğe bağlı) ---------- */
  var gbtn = $('#moreph');
  if (gbtn) gbtn.addEventListener('click', function () {
    var cat = gbtn.getAttribute('data-cat'), box = $('#gallery'), own = gbtn.getAttribute('data-own') || '';
    gbtn.disabled = true; gbtn.textContent = I.loading;
    var u = 'https://commons.wikimedia.org/w/api.php?origin=*&format=json&action=query&generator=categorymembers&gcmtype=file&gcmlimit=40&gcmtitle=' +
      encodeURIComponent('Category:' + cat.replace(/_/g, ' ')) + '&prop=imageinfo&iiprop=url|extmetadata|mime&iiurlwidth=480';
    fetch(u).then(function (r) { return r.json(); }).then(function (j) {
      var ps = j.query ? Object.keys(j.query.pages).map(function (k) { return j.query.pages[k]; }) : [];
      ps = ps.filter(function (p) {
        var ii = p.imageinfo && p.imageinfo[0]; if (!ii || ii.mime !== 'image/jpeg') return false;
        var t = p.title.toLowerCase(); if (/map|plan|logo|harita|karte|diagram|svg/.test(t)) return false;
        return p.title.replace(/ /g, '_') !== own;
      }).slice(0, 8);
      if (!ps.length) { box.innerHTML = '<p class="note">' + esc(I.no_photos) + '</p>'; gbtn.remove(); return; }
      box.innerHTML = ps.map(function (p) {
        var ii = p.imageinfo[0], m = ii.extmetadata || {};
        var a = (m.Artist && m.Artist.value || '').replace(/<[^>]+>/g, '').trim().slice(0, 60) || 'Wikimedia Commons';
        var l = m.LicenseShortName ? m.LicenseShortName.value : '';
        return '<figure><a href="' + esc(ii.descriptionurl) + '" target="_blank" rel="noopener"><img loading="lazy" src="' + esc(ii.thumburl) + '" alt=""></a><figcaption>' + esc(a) + (l ? ' · ' + esc(l) : '') + '</figcaption></figure>';
      }).join('');
      gbtn.remove();
    }).catch(function () { gbtn.disabled = false; gbtn.textContent = I.retry; });
  });

  /* ---------- Harita (Leaflet) ---------- */
  function tiles(map) {
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>' }).addTo(map);
  }
  function popup(p) {
    return '<div class="pop">' + (p.img ? '<img src="' + ROOT + p.img + '" alt="">' : '') + '<b>' + esc(nm(p)) + '</b><span>' + esc(p.city) + ' · ' + esc(p.typeL) + '</span>' +
      (p.ap ? '<em>' + esc(I.approx) + '</em>' : '') +
      '<a href="' + placeUrl(p) + '">' + esc(I.more) + '</a> · <a target="_blank" rel="noopener" href="https://www.google.com/maps/dir/?api=1&destination=' + p.lat + ',' + p.lng + '">' + esc(I.directions) + '</a></div>';
  }
  var mapEl = $('#bigmap');
  if (mapEl && window.L) data().then(function (D) {
    var map = L.map(mapEl, { scrollWheelZoom: true }).setView([39, 35], 6); tiles(map);
    var cl = L.markerClusterGroup ? L.markerClusterGroup({ showCoverageOnHover: false, maxClusterRadius: 45 }) : L.layerGroup();
    var mk = {};
    var ic = function (p) { return L.divIcon({ className: 'pin pin-' + (p.type === 'Müze' ? 'm' : p.type === 'Ören Yeri' ? 'o' : 'a'), iconSize: [18, 18] }); };
    function draw(t) {
      cl.clearLayers();
      D.forEach(function (p) { if (t && p.type !== t) return; var m = L.marker([p.lat, p.lng], { icon: ic(p), title: nm(p) }).bindPopup(popup(p)); mk[p.sid] = m; cl.addLayer(m); });
    }
    draw(''); map.addLayer(cl);
    $$('[data-mt]').forEach(function (b) { b.addEventListener('click', function () { $$('[data-mt]').forEach(function (x) { x.classList.toggle('on', x === b); }); draw(b.getAttribute('data-mt')); }); });
    var h = location.hash.slice(1); if (h && mk[h]) { cl.zoomToShowLayer ? cl.zoomToShowLayer(mk[h], function () { mk[h].openPopup(); }) : (map.setView(mk[h].getLatLng(), 13), mk[h].openPopup()); }
    var nb = $('#nearme'), list = $('#nearlist');
    if (nb) nb.addEventListener('click', function () {
      if (!navigator.geolocation) { list.innerHTML = '<p class="note">' + esc(I.near_err) + '</p>'; return; }
      nb.disabled = true;
      navigator.geolocation.getCurrentPosition(function (pos) {
        nb.disabled = false;
        var me = [pos.coords.latitude, pos.coords.longitude];
        L.circleMarker(me, { radius: 8, color: '#b5532f', fillOpacity: .9 }).addTo(map);
        var near = D.map(function (p) { return { p: p, d: km(me, [p.lat, p.lng]) }; }).sort(function (a, b) { return a.d - b.d; }).slice(0, 12);
        map.fitBounds(L.latLngBounds([me].concat(near.slice(0, 6).map(function (x) { return [x.p.lat, x.p.lng]; }))).pad(0.2));
        list.innerHTML = '<div class="cards">' + near.map(function (x) { return card(x.p, '~' + Math.round(x.d) + ' km'); }).join('') + '</div>';
        list.scrollIntoView({ behavior: 'smooth' });
      }, function () { nb.disabled = false; list.innerHTML = '<p class="note">' + esc(I.near_err) + '</p>'; }, { enableHighAccuracy: false, timeout: 15000, maximumAge: 600000 });
    });
  });

  /* küçük haritalar (yer ve rota sayfaları) */
  $$('.minimap').forEach(function (el) {
    if (!window.L) return;
    var pts = JSON.parse(el.getAttribute('data-pts') || '[]'); if (!pts.length) return;
    var map = L.map(el, { scrollWheelZoom: false, attributionControl: true }); tiles(map);
    var colors = ['#b5532f', '#2f6db5', '#3d8b4f', '#8b3d84', '#c9a24a'];
    var lines = {};
    pts.forEach(function (p, i) {
      var m = L.marker([p[0], p[1]], { icon: L.divIcon({ className: 'num', html: '<span style="background:' + colors[(p[3] || 0) % 5] + '">' + (p[4] || (i + 1)) + '</span>', iconSize: [24, 24] }) }).addTo(map);
      if (p[2]) m.bindPopup(p[2]);
      (lines[p[3] || 0] = lines[p[3] || 0] || []).push([p[0], p[1]]);
    });
    if (el.hasAttribute('data-route')) Object.keys(lines).forEach(function (k) { L.polyline(lines[k], { color: colors[k % 5], weight: 3, opacity: .7, dashArray: '6 6' }).addTo(map); });
    if (pts.length === 1) map.setView([pts[0][0], pts[0][1]], 13); else map.fitBounds(L.latLngBounds(pts.map(function (p) { return [p[0], p[1]]; })).pad(0.15));
  });

  /* ---------- Listem sayfası ---------- */
  var lm = $('#mylist');
  if (lm) data().then(function (D) {
    var by = {}; D.forEach(function (p) { by[p.sid] = p; });
    function render() {
      var fav = KV.list('kv_fav').filter(function (s) { return by[s]; }), vis = KV.list('kv_vis').filter(function (s) { return by[s]; });
      $('#favs').innerHTML = fav.length ? '<div class="cards">' + fav.map(function (s) { return card(by[s]); }).join('') + '</div>' : '<p class="note">' + esc(I.empty_fav) + '</p>';
      $('#viss').innerHTML = vis.length ? '<div class="cards">' + vis.map(function (s) { return card(by[s]); }).join('') + '</div>' : '<p class="note">' + esc(I.empty_vis) + '</p>';
      var cities = {}, allC = {}, reg = {}, regAll = {};
      D.forEach(function (p) { allC[p.city] = 1; regAll[p.regionL] = (regAll[p.regionL] || 0) + 1; });
      vis.forEach(function (s) { cities[by[s].city] = 1; reg[by[s].regionL] = (reg[by[s].regionL] || 0) + 1; });
      var nC = Object.keys(cities).length, tC = Object.keys(allC).length, pct = Math.round(100 * vis.length / D.length);
      $('#progress').innerHTML = '<div class="big"><b>' + vis.length + '</b> / ' + D.length + ' <span>' + esc(I.places) + '</span></div>' +
        '<div class="bar"><i style="width:' + pct + '%"></i></div>' +
        '<p>' + esc(I.prov_line.replace('{n}', nC).replace('{t}', tC)) + '</p>' +
        '<div class="regs">' + Object.keys(regAll).sort().map(function (r) { var v = reg[r] || 0; return '<div class="reg"><span>' + esc(r) + '</span><div class="bar sm"><i style="width:' + Math.round(100 * v / regAll[r]) + '%"></i></div><em>' + v + '/' + regAll[r] + '</em></div>'; }).join('') + '</div>';
    }
    render();
  });

  /* ---------- Keşfet (EN/DE) ve Zaman çizelgesi filtreleri ---------- */
  var xq = $('#xq');
  if (xq) {
    var cards = $$('.xgrid .pcard'), sr = $('#xregion'), st = $('#xtype'), cnt = $('#xcount');
    var fold = function (s) { return (s || '').toLocaleLowerCase('tr').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/ı/g, 'i'); };
    var run = function () {
      var q = fold(xq.value).trim(), r = sr.value, t = st.value, n = 0;
      cards.forEach(function (c) { var ok = (!q || fold(c.getAttribute('data-s')).indexOf(q) >= 0) && (!r || c.getAttribute('data-r') === r) && (!t || c.getAttribute('data-t') === t); c.hidden = !ok; if (ok) n++; });
      cnt.textContent = n;
    };
    [xq, sr, st].forEach(function (e) { e.addEventListener('input', run); e.addEventListener('change', run); });
    run();
  }
  $$('[data-era]').forEach(function (b) {
    b.addEventListener('click', function () { var t = document.getElementById('era-' + b.getAttribute('data-era')); if (t) t.scrollIntoView({ behavior: 'smooth', block: 'start' }); });
  });

  /* ---------- İstatistik (yalnızca ayarlandıysa) ---------- */
  var tokn = H.getAttribute('data-cf');
  if (tokn) { var sc = document.createElement('script'); sc.defer = true; sc.src = 'https://static.cloudflareinsights.com/beacon.min.js'; sc.setAttribute('data-cf-beacon', JSON.stringify({ token: tokn })); document.head.appendChild(sc); }
})();
