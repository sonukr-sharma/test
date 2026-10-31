/**
 * Telegram WebApp auth handshake.
 *
 * Telegram passes `initData` in the URL fragment (#tgWebAppData=...), which the
 * browser never sends to the server. This script forwards it once to
 * webapp-auth.php, which verifies the signature and stores it in the session,
 * then reloads so the server-rendered page can resolve the user.
 */
(function () {
  var tg = window.Telegram && window.Telegram.WebApp;

  if (!tg || !tg.initData) {
    return;
  }

  var script = document.currentScript;
  var base = script && script.src ? script.src.replace(/asset\/webapp-auth\.js.*$/, '') : '';

  var STORAGE_KEY = 'tg_auth_fingerprint';
  var fingerprint = tg.initData.length + ':' + tg.initData.slice(-24);

  // Guard against a reload loop when the server keeps rejecting the payload
  // (for example when the bot token is not configured yet).
  try {
    if (window.sessionStorage.getItem(STORAGE_KEY) === fingerprint) {
      return;
    }
    window.sessionStorage.setItem(STORAGE_KEY, fingerprint);
  } catch (e) {
    // Private mode without storage: fall through and try once per page load.
  }

  fetch(base + 'webapp-auth.php', {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ initData: tg.initData })
  })
    .then(function (response) {
      return response.json();
    })
    .then(function (result) {
      if (result && result.ok) {
        window.location.reload();
        return;
      }

      try {
        window.sessionStorage.removeItem(STORAGE_KEY);
      } catch (e) {}
    })
    .catch(function () {
      try {
        window.sessionStorage.removeItem(STORAGE_KEY);
      } catch (e) {}
    });
})();
