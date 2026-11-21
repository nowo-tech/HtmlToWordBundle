// Custom-config demo: the profile row only applies to the "merge" mode (the inline mode ignores YAML profiles).
(function () {
  var row = document.getElementById('profile-row');
  if (!row) { return; }
  var modeName = row.getAttribute('data-demo-mode-field');
  var radios = document.querySelectorAll('input[name="' + modeName + '"]');
  var select = document.getElementById(row.getAttribute('data-demo-profile-field'));
  function sync() {
    var inline = Array.from(radios).some(function (r) { return r.checked && r.value === 'inline'; });
    row.hidden = inline;
    if (select) { select.disabled = inline; }
  }
  radios.forEach(function (radio) { radio.addEventListener('change', sync); });
  sync();
})();
