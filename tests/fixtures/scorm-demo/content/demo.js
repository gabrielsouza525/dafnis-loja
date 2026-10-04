// Curso mínimo: usa a API do SCORM 1.2 como o Rise e o Storyline (procura window.API nas janelas de cima).
(function () {
  var win = window, api = null;
  for (var i = 0; i < 10 && win; i++) { if (win.API) { api = win.API; break; } if (win.parent === win) break; win = win.parent; }
  var state = document.getElementById('state');
  if (!api) { state.textContent = 'SEM-API'; return; }
  api.LMSInitialize('');
  var entry = api.LMSGetValue('cmi.core.entry');
  var name = api.LMSGetValue('cmi.core.student_name');
  var count = api.LMSGetValue('cmi.interactions._count');
  var readOnly = api.LMSSetValue('cmi.core.student_name', 'X') === 'false' && api.LMSGetLastError() === '403';
  if (entry === 'ab-initio') {
    api.LMSSetValue('cmi.core.lesson_status', 'incomplete');
    api.LMSSetValue('cmi.core.lesson_location', 'pagina-2');
    api.LMSSetValue('cmi.suspend_data', 'parou-na-pagina-2');
    api.LMSCommit('');
  }
  state.textContent = ['ENTRY=' + entry, 'NAME=' + name, 'LOCATION=' + api.LMSGetValue('cmi.core.lesson_location'),
    'SUSPEND=' + api.LMSGetValue('cmi.suspend_data'), 'COUNT=' + count, 'READONLY=' + readOnly].join(' ');
  document.getElementById('finish').addEventListener('click', function () {
    var n = parseInt(api.LMSGetValue('cmi.interactions._count'), 10) || 0;
    api.LMSSetValue('cmi.interactions.' + n + '.id', 'questao-1');
    api.LMSSetValue('cmi.interactions.' + n + '.type', 'choice');
    api.LMSSetValue('cmi.interactions.' + n + '.student_response', 'b');
    api.LMSSetValue('cmi.interactions.' + n + '.correct_responses.0.pattern', 'b');
    api.LMSSetValue('cmi.interactions.' + n + '.result', 'correct');
    api.LMSSetValue('cmi.core.score.raw', '90');
    api.LMSSetValue('cmi.core.score.max', '100');
    api.LMSSetValue('cmi.core.lesson_status', 'passed');
    api.LMSSetValue('cmi.core.session_time', '0000:12:30.00');
    api.LMSCommit('');
    api.LMSFinish('');
    state.textContent = 'FINISHED';
  });
})();
