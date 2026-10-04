// API do SCORM 1.2 para os cursos próprios (Rise, Storyline...) abertos na loja.
// O pacote roda no iframe e procura window.API nas janelas de cima. O que ele grava vai para a loja
// em LMSCommit/LMSFinish (só o que mudou), e a página avisa a cada minuto se o participante está
// estudando: aba visível e alguma atividade nos últimos minutos. Na pré-visualização nada é enviado.
(function () {
  'use strict';
  var root = document.querySelector('[data-study]');
  var configEl = document.getElementById('scorm-config');
  if (!root || !configEl) return;

  var cfg = JSON.parse(configEl.textContent);
  var csrfMeta = document.querySelector('meta[name="csrf-token"]');
  var csrf = csrfMeta ? csrfMeta.content : '';
  var statusEl = root.querySelector('[data-study-status]');
  var alertEl = root.querySelector('[data-study-alert]');
  var frame = root.querySelector('[data-study-frame]');
  var idleEl = root.querySelector('[data-study-idle]');

  var IDLE_MS = 10 * 60 * 1000;
  var HEARTBEAT_MS = 60 * 1000;

  var ERRORS = {
    '0': 'No error', '101': 'General exception', '201': 'Invalid argument error',
    '202': 'Element cannot have children', '203': 'Element not an array - cannot have count',
    '301': 'Not initialized', '401': 'Not implemented error', '402': 'Invalid set value, element is a keyword',
    '403': 'Element is read only', '404': 'Element is write only', '405': 'Incorrect data type'
  };
  var CHILDREN = {
    'cmi.core._children': 'student_id,student_name,lesson_location,credit,lesson_status,entry,score,total_time,lesson_mode,exit,session_time',
    'cmi.core.score._children': 'raw,min,max',
    'cmi.objectives._children': 'id,score,status',
    'cmi.student_data._children': 'mastery_score,max_time_allowed,time_limit_action',
    'cmi.student_preference._children': 'audio,language,speed,text',
    'cmi.interactions._children': 'id,objectives,time,type,correct_responses,weighting,student_response,result,latency'
  };
  var KNOWN = /^cmi\.(core\.(student_id|student_name|lesson_location|credit|lesson_status|entry|score\.(raw|min|max)|total_time|lesson_mode|exit|session_time)|suspend_data|launch_data|comments|comments_from_lms|student_data\.(mastery_score|max_time_allowed|time_limit_action)|student_preference\.(audio|language|speed|text)|objectives\.\d+\.(id|score\.(raw|min|max)|status)|interactions\.\d+\.(id|objectives\.\d+\.id|time|type|correct_responses\.\d+\.pattern|weighting|student_response|result|latency))$/;
  var READ_ONLY = /^cmi\.(core\.(student_id|student_name|credit|entry|total_time|lesson_mode)|launch_data|comments_from_lms|student_data\.(mastery_score|max_time_allowed|time_limit_action))$/;
  var WRITE_ONLY = /^cmi\.(core\.(exit|session_time)|interactions\.\d+\..+)$/;
  var STATUS_VALUES = ['passed', 'completed', 'failed', 'incomplete', 'browsed'];
  var TIME = /^\d{2,4}:\d{2}:\d{2}(\.\d{1,2})?$/;

  var values = {};
  Object.keys(cfg.cmi || {}).forEach(function (k) { values[k] = String(cfg.cmi[k]); });
  var counts = { interactions: parseInt(values['cmi.interactions._count'] || '0', 10) || 0, objectives: 0 };
  delete values['cmi.interactions._count'];

  var initialized = false;
  var finished = false;
  var lastError = '0';
  var dirty = {};          // elementos alterados desde o último envio
  var interactions = {};   // índice -> campos das respostas alteradas

  // ---------------------------------------------------------------- estado na barra
  function setStatus(text, tone) {
    if (!statusEl) return;
    statusEl.textContent = text;
    statusEl.setAttribute('data-tone', tone || '');
  }
  function showAlert(html) {
    if (!alertEl) return;
    alertEl.innerHTML = html;
    alertEl.hidden = false;
  }
  function clock() {
    try { return new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }); } catch (e) { return ''; }
  }
  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
  }

  // ---------------------------------------------------------------- envio para a loja
  var sending = false;
  var again = false;
  var retryTimer = null;
  var expired = false;

  function hasChanges() {
    return Object.keys(dirty).length > 0 || Object.keys(interactions).length > 0;
  }

  function buildPayload() {
    var p = { session: cfg.session, _token: csrf };
    if (dirty['cmi.core.lesson_status']) p.status = values['cmi.core.lesson_status'];
    if (dirty['cmi.core.lesson_location']) p.location = values['cmi.core.lesson_location'];
    if (dirty['cmi.suspend_data']) p.suspend = values['cmi.suspend_data'];
    if (dirty['cmi.core.score.raw'] || dirty['cmi.core.score.max']) {
      p.score = { raw: values['cmi.core.score.raw'] || '', max: values['cmi.core.score.max'] || '' };
    }
    if (dirty['cmi.core.session_time']) p.session_time = values['cmi.core.session_time'];
    if (dirty['cmi.core.exit']) p.exit = values['cmi.core.exit'];
    if (Object.keys(interactions).length) p.interactions = interactions;
    return p;
  }

  function send(keepalive) {
    if (cfg.preview || expired) { dirty = {}; interactions = {}; return; }
    if (!hasChanges()) return;
    if (sending) { again = true; return; }
    var body = buildPayload();
    var sentDirty = dirty;
    var sentInteractions = interactions;
    dirty = {};
    interactions = {};
    sending = true;
    setStatus('Salvando…', 'busy');
    fetch(cfg.commit, {
      method: 'POST',
      credentials: 'same-origin',
      keepalive: !!keepalive && JSON.stringify(body).length < 60000,
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify(body)
    }).then(function (r) {
      if (r.status === 401 || r.status === 419) { sessionExpired(); throw new Error('expired'); }
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    }).then(function (res) {
      setStatus((res && res.status === 'passed' ? 'Aprovado · ' : '') + 'Andamento salvo às ' + clock(), 'ok');
    }).catch(function () {
      // Devolve o que não foi gravado (sem passar por cima do que mudou nesse meio-tempo).
      Object.keys(sentDirty).forEach(function (k) { if (!dirty[k]) dirty[k] = true; });
      Object.keys(sentInteractions).forEach(function (i) {
        interactions[i] = Object.assign({}, sentInteractions[i], interactions[i] || {});
      });
      if (!expired) {
        setStatus('Sem conexão. Tentando salvar de novo…', 'warn');
        clearTimeout(retryTimer);
        retryTimer = setTimeout(function () { send(false); }, 15000);
      }
    }).then(function () {
      sending = false;
      if (again) { again = false; send(keepalive); }
    });
  }

  function sessionExpired() {
    if (expired) return;
    expired = true;
    setStatus('Sessão expirada', 'warn');
    showAlert('Sua sessão na loja expirou, então o andamento parou de ser salvo. <a href="' + escapeHtml(cfg.login) + '">Entre de novo</a> para continuar de onde o curso salvou por último.');
  }

  // ---------------------------------------------------------------- API do SCORM 1.2
  function fail(code, ret) { lastError = code; return ret; }
  function ok(ret) { lastError = '0'; return ret; }

  function countOf(element) {
    if (element === 'cmi.interactions._count') return String(counts.interactions);
    if (element === 'cmi.objectives._count') return String(counts.objectives);
    var prefix = element.slice(0, -'_count'.length);
    var seen = {};
    Object.keys(values).forEach(function (k) {
      if (k.indexOf(prefix) === 0) {
        var m = /^(\d+)\./.exec(k.slice(prefix.length));
        if (m) seen[m[1]] = true;
      }
    });
    return String(Object.keys(seen).length);
  }

  function recordInteraction(element, value) {
    var m = /^cmi\.interactions\.(\d+)\.(.+)$/.exec(element);
    if (!m) return;
    var i = m[1];
    var field = m[2];
    var row = interactions[i] || (interactions[i] = {});
    var list = /^(correct_responses|objectives)\.(\d+)\.(pattern|id)$/.exec(field);
    if (list) {
      // As listas vão inteiras: junta tudo o que o curso já gravou nesta resposta.
      var all = [];
      Object.keys(values).forEach(function (k) {
        var mm = new RegExp('^cmi\\.interactions\\.' + i + '\\.' + list[1] + '\\.(\\d+)\\.').exec(k);
        if (mm) all[parseInt(mm[1], 10)] = values[k];
      });
      row[list[1]] = all;
    } else {
      row[field] = value;
    }
    counts.interactions = Math.max(counts.interactions, parseInt(i, 10) + 1);
  }

  function validValue(element, value) {
    if (element === 'cmi.core.lesson_status') return STATUS_VALUES.indexOf(value) >= 0;
    if (element === 'cmi.core.exit') return ['', 'time-out', 'suspend', 'logout'].indexOf(value) >= 0;
    if (element === 'cmi.core.session_time') return TIME.test(value);
    if (/score\.(raw|min|max)$/.test(element)) return value === '' || (!isNaN(parseFloat(value)) && isFinite(value));
    if (element === 'cmi.core.lesson_location') return value.length <= 255;
    return true;
  }

  var API = {
    LMSInitialize: function () {
      // Alguns cursos chamam duas vezes; tratar como sucesso evita travar o pacote.
      initialized = true;
      finished = false;
      if (!cfg.preview) setStatus('Curso aberto', '');
      return ok('true');
    },
    LMSFinish: function () {
      if (!initialized) return fail('301', 'false');
      initialized = false;
      finished = true;
      send(true);
      return ok('true');
    },
    LMSGetValue: function (element) {
      element = String(element || '');
      if (!initialized) return fail('301', '');
      if (CHILDREN[element]) return ok(CHILDREN[element]);
      if (/\._children$/.test(element)) return fail('202', '');
      if (/\._count$/.test(element)) {
        return /^cmi\.(interactions|objectives)(\.\d+\.(objectives|correct_responses))?\._count$/.test(element) ? ok(countOf(element)) : fail('203', '');
      }
      if (!KNOWN.test(element)) return fail('401', '');
      if (WRITE_ONLY.test(element)) return fail('404', '');
      return ok(values[element] !== undefined ? values[element] : '');
    },
    LMSSetValue: function (element, value) {
      element = String(element || '');
      value = value === undefined || value === null ? '' : String(value);
      if (!initialized) return fail('301', 'false');
      if (/\.(_children|_count)$/.test(element)) return fail('402', 'false');
      if (!KNOWN.test(element)) return fail('401', 'false');
      if (READ_ONLY.test(element)) return fail('403', 'false');
      if (!validValue(element, value)) return fail('405', 'false');
      values[element] = value;
      if (element.indexOf('cmi.interactions.') === 0) {
        recordInteraction(element, value);
      } else {
        dirty[element] = true;
        var obj = /^cmi\.objectives\.(\d+)\./.exec(element);
        if (obj) counts.objectives = Math.max(counts.objectives, parseInt(obj[1], 10) + 1);
      }
      return ok('true');
    },
    LMSCommit: function () {
      if (!initialized) return fail('301', 'false');
      send(false);
      return ok('true');
    },
    LMSGetLastError: function () { return lastError; },
    LMSGetErrorString: function (code) { return ERRORS[String(code)] || ''; },
    LMSGetDiagnostic: function (code) { return ERRORS[String(code === '' || code == null ? lastError : code)] || ''; }
  };
  window.API = API;

  // Salva de tempos em tempos e ao sair da página, mesmo se o curso não chamar LMSCommit.
  setInterval(function () { send(false); }, 30000);
  window.addEventListener('pagehide', function () { send(true); });

  // ---------------------------------------------------------------- presença (tempo de estudo)
  var lastActivity = Date.now();
  var tickStart = Date.now();
  var visibleMs = 0;
  var visibleSince = document.visibilityState === 'visible' ? Date.now() : null;
  var idleShown = false;

  function activity() {
    lastActivity = Date.now();
    if (idleShown) hideIdle();
  }
  var throttled = (function () {
    var last = 0;
    return function () { var now = Date.now(); if (now - last > 2000) { last = now; activity(); } };
  })();
  var EVENTS = ['pointerdown', 'pointermove', 'keydown', 'wheel', 'touchstart', 'scroll'];
  function watch(win) {
    try {
      if (!win || win.__dafnisWatched) return;
      win.__dafnisWatched = true;
      EVENTS.forEach(function (ev) { win.addEventListener(ev, throttled, { passive: true, capture: true }); });
    } catch (e) { /* janela de outro domínio */ }
  }
  function watchFrames(win, depth) {
    if (!win || depth > 5) return;
    watch(win);
    try {
      for (var i = 0; i < win.frames.length; i++) watchFrames(win.frames[i], depth + 1);
    } catch (e) { /* janela de outro domínio */ }
  }
  watch(window);
  frame.addEventListener('load', function () { watchFrames(frame.contentWindow, 0); });
  setInterval(function () { watchFrames(frame.contentWindow, 0); }, 5000);

  function showIdle() {
    if (idleShown || !idleEl) return;
    idleShown = true;
    idleEl.hidden = false;
    var btn = idleEl.querySelector('[data-study-resume]');
    if (btn) btn.focus();
  }
  function hideIdle() {
    idleShown = false;
    if (idleEl) idleEl.hidden = true;
  }
  if (idleEl) {
    var resume = idleEl.querySelector('[data-study-resume]');
    if (resume) resume.addEventListener('click', function () { activity(); hideIdle(); try { frame.focus(); } catch (e) {} });
  }

  function visibleTime() {
    return visibleMs + (visibleSince ? Date.now() - visibleSince : 0);
  }

  function heartbeat() {
    var now = Date.now();
    var elapsed = now - tickStart;
    var idle = now - lastActivity >= IDLE_MS;
    var active = elapsed > 0 && visibleTime() >= elapsed / 2 && !idle;
    tickStart = now;
    visibleMs = 0;
    if (visibleSince) visibleSince = now;
    if (idle && document.visibilityState === 'visible') showIdle();
    if (cfg.preview || expired) return;
    fetch(cfg.heartbeat, {
      method: 'POST',
      credentials: 'same-origin',
      keepalive: true,
      // Sem atividade, o aviso não renova a sessão da loja (ela expira como qualquer página parada).
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf, 'X-Background': active ? '0' : '1' },
      body: JSON.stringify({ session: cfg.session, active: active, _token: csrf })
    }).then(function (r) {
      if (r.status === 401 || r.status === 419) sessionExpired();
    }).catch(function () { /* o próximo aviso tenta de novo */ });
  }
  setInterval(heartbeat, HEARTBEAT_MS);
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible') {
      visibleSince = Date.now();
    } else {
      if (visibleSince) { visibleMs += Date.now() - visibleSince; visibleSince = null; }
      heartbeat(); // credita o tempo até aqui antes de a aba sair de cena
    }
  });

  // ---------------------------------------------------------------- abre o curso
  frame.src = cfg.launch;
})();
