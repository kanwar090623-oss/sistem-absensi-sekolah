/* SiAbsen - app.js (tanpa library, semua interaksi dalam satu file) */
(() => {
  'use strict';

  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- Toast ---------- */
  const wrap = $('#toasts');
  function toast(msg, type = 'ok') {
    if (!wrap || !msg) return;
    const t = document.createElement('div');
    t.className = 'toast ' + (type === 'err' ? 'err' : 'ok');
    t.setAttribute('role', type === 'err' ? 'alert' : 'status');
    const ic = document.createElement('span');
    ic.className = 'toast-ic';
    const tx = document.createElement('span');
    tx.textContent = msg;
    t.append(ic, tx);
    wrap.appendChild(t);
    requestAnimationFrame(() => t.classList.add('in'));
    setTimeout(() => {
      t.classList.remove('in');
      setTimeout(() => t.remove(), 320);
    }, 4500);
  }
  const fl = $('#flash');
  if (fl) toast(fl.dataset.msg, fl.dataset.type);

  /* ---------- Sidebar (mobile) ---------- */
  const sb = $('#sidebar');
  const scrim = $('#scrim');
  if (sb) {
    const setSb = (open) => {
      sb.classList.toggle('open', open);
      scrim.classList.toggle('show', open);
    };
    $('#menuBtn')?.addEventListener('click', () => setSb(!sb.classList.contains('open')));
    scrim.addEventListener('click', () => setSb(false));
  }

  /* ---------- Modal ---------- */
  let lastFocus = null;
  const openModal = (m) => {
    lastFocus = document.activeElement;
    m.classList.add('open');
    m.setAttribute('aria-hidden', 'false');
    const f = $('input:not([type=hidden]):not([type=radio]), select', m);
    if (f) setTimeout(() => f.focus(), 80);
  };
  const closeModal = (m) => {
    m.classList.remove('open');
    m.setAttribute('aria-hidden', 'true');
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  };

  document.addEventListener('click', (e) => {
    const op = e.target.closest('[data-modal]');
    if (op) {
      const m = $(op.dataset.modal);
      const form = $('form', m);
      if (form) {
        form.reset();
        const data = op.dataset.fill ? JSON.parse(op.dataset.fill) : {};
        $$('[name]', form).forEach((el) => {
          if (!(el.name in data)) return;
          if (el.type === 'radio') el.checked = el.value === String(data[el.name]);
          else if (el.type !== 'file') el.value = data[el.name];
        });
        // pratinjau foto (reset dulu, lalu isi foto yang sudah ada)
        const pv = $('[data-preview]', form);
        if (pv) {
          pv.src = data.foto_url || '';
          pv.hidden = !data.foto_url;
          const ic = $('svg', pv.parentElement);
          if (ic) ic.style.display = data.foto_url ? 'none' : '';
        }
        const dw = $('[data-del-wrap]', form);
        if (dw) dw.hidden = !data.foto_url;
      }
      if (op.dataset.title) $('.modal-title', m).textContent = op.dataset.title;
      openModal(m);
      return;
    }
    if (e.target.closest('[data-close]') || e.target.classList.contains('modal')) {
      closeModal(e.target.closest('.modal'));
    }
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') $$('.modal.open').forEach(closeModal);
  });

  /* ---------- Pratinjau foto sebelum diunggah ---------- */
  document.addEventListener('change', (e) => {
    const inp = e.target.closest('[data-preview-input]');
    if (!inp) return;
    const box = inp.closest('form');
    const pv = $('[data-preview]', box);
    const f = inp.files && inp.files[0];
    if (!pv || !f) return;
    if (f.size > 20 * 1024 * 1024) {
      toast('Ukuran foto maksimal 20 MB.', 'err');
      inp.value = '';
      return;
    }
    pv.src = URL.createObjectURL(f);
    pv.hidden = false;
    const ic = $('svg', pv.parentElement);
    if (ic) ic.style.display = 'none';
  });

  /* ---------- Konfirmasi hapus ---------- */
  const cm = $('#confirmModal');
  let pending = null;
  document.addEventListener('submit', (e) => {
    const f = e.target;
    if (!f.dataset.confirm || f.dataset.ok) return;
    e.preventDefault();
    pending = f;
    $('#confirmMsg').textContent = f.dataset.confirm;
    openModal(cm);
  });
  $('#confirmOk')?.addEventListener('click', () => {
    if (pending) {
      pending.dataset.ok = '1';
      pending.submit();
    }
  });

  /* ---------- Pencarian langsung ---------- */
  $$('[data-filter]').forEach((inp) => {
    const items = $$(inp.dataset.filter);
    const empty = inp.dataset.empty ? $(inp.dataset.empty) : null;
    const emptyDefault = empty ? empty.hidden : true;
    inp.addEventListener('input', () => {
      const q = inp.value.trim().toLowerCase();
      let n = 0;
      items.forEach((it) => {
        const hit = !q || (it.dataset.search || '').includes(q);
        it.hidden = !hit;
        if (hit) n++;
      });
      if (empty) empty.hidden = n > 0;
    });
  });

  /* ---------- Filter otomatis kirim ---------- */
  $$('[data-autosubmit]').forEach((el) => el.addEventListener('change', () => el.form.submit()));

  /* ---------- Hitung naik angka ---------- */
  $$('[data-count]').forEach((el) => {
    const to = Number(el.dataset.count) || 0;
    if (reduce || !to) { el.textContent = to; return; }
    const t0 = performance.now();
    const dur = 800;
    const tick = (t) => {
      const p = Math.min(1, (t - t0) / dur);
      el.textContent = Math.round(to * (1 - Math.pow(1 - p, 3)));
      if (p < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  });

  /* ---------- Jam di halaman login ---------- */
  const ck = $('[data-clock]');
  const dt = $('[data-date]');
  if (ck) {
    const tick = () => {
      const n = new Date();
      ck.textContent = n.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':');
      if (dt) dt.textContent = n.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    };
    tick();
    setInterval(tick, 1000);
  }

  /* ---------- Login & daftar ---------- */
  $$('[data-toggle-pass]').forEach((b) => b.addEventListener('click', () => {
    const i = b.parentElement.querySelector('input');
    const show = i.type === 'password';
    i.type = show ? 'text' : 'password';
    b.classList.toggle('on', show);
    b.setAttribute('aria-label', show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
  }));

  const pw = $('#pw');
  const meter = $('#meter');
  if (pw && meter) {
    const txt = $('#meterTxt');
    const lab = ['', 'Lemah', 'Cukup', 'Kuat'];
    pw.addEventListener('input', () => {
      const v = pw.value;
      let s = 0;
      if (v.length >= 6) s = 1;
      if (v.length >= 9 && /[A-Za-z]/.test(v) && /\d/.test(v)) s = 2;
      if (v.length >= 11 && /[A-Z]/.test(v) && /[^A-Za-z0-9]/.test(v)) s = 3;
      meter.dataset.lv = s;
      txt.textContent = v ? (lab[s] || 'Terlalu pendek') : '';
    });
  }
  const pw2 = $('#pw2');
  if (pw && pw2) {
    const mt = $('#matchTxt');
    const check = () => {
      if (!pw2.value) { mt.textContent = ''; mt.className = 'hint'; return; }
      const ok = pw.value === pw2.value;
      mt.textContent = ok ? 'Kata sandi sama' : 'Belum sama dengan kata sandi di atas';
      mt.className = 'hint ' + (ok ? 'good' : 'bad');
    };
    pw.addEventListener('input', check);
    pw2.addEventListener('input', check);
  }

  $$('form[data-loading]').forEach((f) => f.addEventListener('submit', () => {
    const b = $('button[type=submit]', f);
    if (!b) return;
    const s = $('span', b);
    if (s) s.textContent = 'Memproses';
    b.classList.add('loading');
  }));

  /* ---------- Halaman absensi ---------- */
  const af = $('#absenForm');
  if (af) {
    const rows = $$('.student', af);
    const out = { H: $('[data-n=H]'), I: $('[data-n=I]'), S: $('[data-n=S]'), A: $('[data-n=A]') };
    const note = $('#dirtyNote');
    let dirty = false;

    const tally = () => {
      const c = { H: 0, I: 0, S: 0, A: 0 };
      rows.forEach((r) => { c[r.dataset.status]++; });
      Object.keys(c).forEach((k) => { out[k].textContent = c[k]; });
    };
    const setDirty = () => { dirty = true; note.hidden = false; };
    const setRow = (r, v) => {
      r.dataset.status = v;
      $('.ket', r).hidden = v === 'H';
    };

    af.addEventListener('change', (e) => {
      const t = e.target;
      if (t.type === 'radio') {
        setRow(t.closest('.student'), t.value);
        tally();
        setDirty();
      }
    });
    af.addEventListener('input', (e) => {
      if (e.target.closest('.ket')) setDirty();
    });

    $('#allPresent')?.addEventListener('click', () => {
      rows.forEach((r) => {
        $('input[value=H]', r).checked = true;
        setRow(r, 'H');
      });
      tally();
      setDirty();
      toast('Semua siswa ditandai hadir. Ubah yang tidak hadir, lalu simpan.');
    });

    af.addEventListener('submit', () => {
      dirty = false;
      const b = $('#saveBtn');
      $('span', b).textContent = 'Menyimpan';
      b.classList.add('loading');
    });
    window.addEventListener('beforeunload', (e) => {
      if (dirty) { e.preventDefault(); e.returnValue = ''; }
    });
    tally();
  }
})();
