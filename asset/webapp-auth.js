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

  var FP_CDN = 'https://openfpcdn.io/fingerprintjs/v4/iife.min.js';
  var FP_TIMEOUT = 4000;

  /**
   * Resolve the FingerprintJS visitorId, or '' if the library cannot be loaded
   * or is too slow. The fingerprint is a nice-to-have: a blocked CDN, an old
   * WebView or a slow network must never stop the user from signing in.
   */
  function loadFingerprint() {
    return new Promise(function (resolve) {
      var settled = false;

      function finish(value) {
        if (settled) {
          return;
        }
        settled = true;
        resolve(value);
      }

      setTimeout(function () {
        finish('');
      }, FP_TIMEOUT);

      function compute() {
        try {
          window.FingerprintJS.load()
            .then(function (agent) {
              return agent.get();
            })
            .then(function (result) {
              finish(result && result.visitorId ? String(result.visitorId) : '');
            })
            .catch(function () {
              finish('');
            });
        } catch (e) {
          finish('');
        }
      }

      if (window.FingerprintJS) {
        compute();
        return;
      }

      var tag = document.createElement('script');
      tag.src = FP_CDN;
      tag.async = true;
      tag.onload = compute;
      tag.onerror = function () {
        finish('');
      };
      document.head.appendChild(tag);
    });
  }

  loadFingerprint().then(function (visitorId) {
    fetch(base + 'webapp-auth.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        initData: tg.initData,
        fingerprint: visitorId,
        platform: tg.platform || ''
      })
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
  });
})();
