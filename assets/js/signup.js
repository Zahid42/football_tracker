/* Sign-up form: live e-mail check, password strength and confirmation. */
(function () {
  'use strict';
  var form = document.getElementById('signupForm'); if (!form) { return; }
  var em = document.getElementById('email'), msg = document.getElementById('emailMsg');
  var pw = document.getElementById('password'), pw2 = document.getElementById('password2');
  var bar = document.getElementById('pwBar'), pwMsg = document.getElementById('pwMsg'), pw2Msg = document.getElementById('pw2Msg');
  var emailOk = false, timer = null, last = '';
  function say(el, text, cls) { el.textContent = text; el.className = 'form-text ' + (cls || ''); }
  function fmtOk(v) { return /^[^\s@]+@[^\s@]+\.[A-Za-z]{2,}$/.test(v); }
  function checkEmail() {
    var v = em.value.trim().toLowerCase(); emailOk = false; em.classList.remove('is-valid', 'is-invalid');
    if (!v) { say(msg, 'We check the address as you type. Only the admin can see it.'); return; }
    if (!fmtOk(v)) { say(msg, 'Keep typing: use the form name@example.com', 'text-muted-ft'); return; }
    last = v; say(msg, 'Checking...', 'text-muted-ft');
    fetch(window.FT_EMAIL_CHECK + '?e=' + encodeURIComponent(v), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
      if (v !== last) { return; }
      emailOk = !!j.ok; em.classList.toggle('is-valid', emailOk); em.classList.toggle('is-invalid', !emailOk);
      say(msg, j.message || '', emailOk ? 'text-success' : 'text-danger');
      if (j.suggest) { var a = document.createElement('a'); a.href = '#'; a.textContent = ' Use ' + j.suggest; a.onclick = function (e) { e.preventDefault(); em.value = j.suggest; checkEmail(); }; msg.appendChild(a); }
    }).catch(function () { emailOk = true; say(msg, 'Could not check online; the server will check it on submit.', 'text-muted-ft'); });
  }
  em.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(checkEmail, 450); });
  em.addEventListener('blur', checkEmail);
  function strength() {
    var v = pw.value, s = 0;
    if (v.length >= 8) { s++; } if (/[A-Z]/.test(v) && /[a-z]/.test(v)) { s++; } if (/\d/.test(v)) { s++; } if (/[^A-Za-z0-9]/.test(v) || v.length >= 12) { s++; }
    var cols = ['#ef4444', '#f59e0b', '#84cc16', '#22c55e'], names = ['Weak', 'Fair', 'Good', 'Strong'];
    bar.style.width = (v ? s * 25 : 0) + '%'; bar.style.backgroundColor = cols[Math.max(0, s - 1)];
    var ok = v.length >= 8 && /[A-Za-z]/.test(v) && /\d/.test(v);
    say(pwMsg, v ? (ok ? names[Math.max(0, s - 1)] + ' password' : 'Needs 8+ characters with a letter and a number') : 'At least 8 characters with a letter and a number.', v ? (ok ? 'text-success' : 'text-danger') : '');
    match();
  }
  function match() { if (!pw2.value) { say(pw2Msg, ''); return; } var same = pw.value === pw2.value; say(pw2Msg, same ? 'Passwords match' : 'Passwords do not match', same ? 'text-success' : 'text-danger'); }
  pw.addEventListener('input', strength); pw2.addEventListener('input', match);
  document.getElementById('pwToggle').addEventListener('click', function () { var t = pw.type === 'password' ? 'text' : 'password'; pw.type = t; pw2.type = t; this.querySelector('i').className = 'bi ' + (t === 'text' ? 'bi-eye-slash' : 'bi-eye'); });
  form.addEventListener('submit', function (e) {
    if (!fmtOk(em.value.trim())) { e.preventDefault(); em.focus(); say(msg, 'Please enter a valid e-mail address.', 'text-danger'); }
  });
})();
