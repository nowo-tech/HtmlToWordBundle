// Presets of the main demo: a preset button fills the HTML textarea and suggests its profile (data attributes on the group).
(function () {
  var group = document.querySelector('[data-demo-presets]');
  if (!group) { return; }
  var presets = JSON.parse(group.getAttribute('data-demo-presets') || '{}');
  var meta = JSON.parse(group.getAttribute('data-demo-preset-meta') || '{}');
  var html = document.getElementById(group.getAttribute('data-demo-html-field'));
  var preset = document.getElementById(group.getAttribute('data-demo-preset-field'));
  var profile = document.getElementById(group.getAttribute('data-demo-profile-field'));
  group.querySelectorAll('[data-load-preset]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = btn.getAttribute('data-load-preset');
      html.value = presets[id] || '';
      preset.value = '';
      if (meta[id] && meta[id].profile && profile) {
        var opt = Array.from(profile.options).find(function (o) { return o.value === meta[id].profile; });
        if (opt) { profile.value = meta[id].profile; }
      }
    });
  });
})();
