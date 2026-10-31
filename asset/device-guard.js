/**
 * =====================================================================
 *  device-guard.js — one Telegram account per device
 *
 *  - Creates a random device id and stores it redundantly in a cookie,
 *    localStorage and IndexedDB (if one is cleared, the others restore it).
 *  - Builds a SHA-256 browser fingerprint as a secondary signal.
 *  - Sends both + Telegram.WebApp.initData to device-check.php, which does
 *    the real enforcement. The overlay here is only the visible part.
 *
 *  Usage — load BEFORE your app scripts:
 *      <script src="https://telegram.org/js/telegram-web-app.js"></script>
 *      <script src="device-guard.js"></script>
 *      <script>
 *        DeviceGuard.ready.then(function (r) {
 *          if (r.blocked) return;       // overlay already shown
 *          startApp();
 *        });
 *      </script>
 * =====================================================================
 */
(function (global) {
  'use strict';

  var CFG = {
    endpoint: 'asset/device-check.php',
    key: 'rae_did',                 // cookie / localStorage / IndexedDB key
    cookieDays: 730,
    dbName: 'rae_guard',
    recheckMs: 5 * 60 * 1000,       // re-verify every 5 minutes while open
    blockedTitle: 'Access blocked',
    blockedText:
      'Another Telegram account is already using this device. ' +
      'Only one account per device is allowed.'
  };

  var blocked = false;

  /* ---------- helpers ---------- */

  function uuid() {
    if (global.crypto && crypto.randomUUID) return crypto.randomUUID();
    var b = new Uint8Array(16);
    crypto.getRandomValues(b);
    b[6] = (b[6] & 0x0f) | 0x40;
    b[8] = (b[8] & 0x3f) | 0x80;
    var h = Array.prototype.map.call(b, function (x) {
      return ('0' + x.toString(16)).slice(-2);
    }).join('');
    return h.slice(0, 8) + '-' + h.slice(8, 12) + '-' + h.slice(12, 16) + '-' +
           h.slice(16, 20) + '-' + h.slice(20);
  }

  var UUID_RE = /^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i;

  /* ---------- cookie ---------- */

  function getCookie() {
    try {
      var m = document.cookie.match(new RegExp('(?:^|; )' + CFG.key + '=([^;]*)'));
      return m ? decodeURIComponent(m[1]) : null;
    } catch (e) { return null; }
  }

  function setCookie(v) {
    try {
      var secure = location.protocol === 'https:';
      document.cookie = CFG.key + '=' + encodeURIComponent(v) +
        '; max-age=' + (CFG.cookieDays * 86400) + '; path=/' +
        (secure ? '; Secure; SameSite=None' : '; SameSite=Lax');
    } catch (e) { /* ignore */ }
  }

  /* ---------- localStorage ---------- */

  function getLS() {
    try { return localStorage.getItem(CFG.key); } catch (e) { return null; }
  }
  function setLS(v) {
    try { localStorage.setItem(CFG.key, v); } catch (e) { /* ignore */ }
  }

  /* ---------- IndexedDB ---------- */

  function idbOpen() {
    return new Promise(function (resolve, reject) {
      if (!global.indexedDB) return reject(new Error('no idb'));
      var req = indexedDB.open(CFG.dbName, 1);
      req.onupgradeneeded = function () { req.result.createObjectStore('kv'); };
      req.onsuccess = function () { resolve(req.result); };
      req.onerror = function () { reject(req.error); };
    });
  }

  function getIDB() {
    return idbOpen().then(function (db) {
      return new Promise(function (resolve) {
        var r = db.transaction('kv', 'readonly').objectStore('kv').get(CFG.key);
        r.onsuccess = function () { resolve(r.result || null); };
        r.onerror = function () { resolve(null); };
      });
    }).catch(function () { return null; });
  }

  function setIDB(v) {
    return idbOpen().then(function (db) {
      return new Promise(function (resolve) {
        var tx = db.transaction('kv', 'readwrite');
        tx.objectStore('kv').put(v, CFG.key);
        tx.oncomplete = tx.onerror = function () { resolve(); };
      });
    }).catch(function () { /* ignore */ });
  }

  /* ---------- device id (stored in all three places) ---------- */

  function getDeviceId() {
    return getIDB().then(function (idbVal) {
      var found = [getCookie(), getLS(), idbVal].filter(function (v) {
        return v && UUID_RE.test(v);
      });
      var id = found.length ? found[0].toLowerCase() : uuid();
      setCookie(id);
      setLS(id);
      return setIDB(id).then(function () { return id; });
    });
  }

  /* ---------- fingerprint ---------- */

  function sha256Hex(str) {
    if (global.crypto && crypto.subtle) {
      return crypto.subtle.digest('SHA-256', new TextEncoder().encode(str))
        .then(function (buf) {
          return Array.prototype.map.call(new Uint8Array(buf), function (x) {
            return ('0' + x.toString(16)).slice(-2);
          }).join('');
        });
    }
    // Fallback (non-secure contexts): stretch a simple hash to 64 hex chars.
    var h = 5381, out = '';
    for (var r = 0; r < 8; r++) {
      for (var i = 0; i < str.length; i++) {
        h = ((h << 5) + h + str.charCodeAt(i) + r) | 0;
      }
      out += ('00000000' + (h >>> 0).toString(16)).slice(-8);
    }
    return Promise.resolve(out);
  }

  function canvasTrait() {
    try {
      var c = document.createElement('canvas');
      c.width = 200; c.height = 40;
      var x = c.getContext('2d');
      x.textBaseline = 'top';
      x.font = '14px Arial';
      x.fillStyle = '#f60';
      x.fillRect(10, 5, 80, 20);
      x.fillStyle = '#069';
      x.fillText('Refer&Earn \u2728 guard', 4, 12);
      return c.toDataURL();
    } catch (e) { return 'no-canvas'; }
  }

  function webglTrait() {
    try {
      var gl = document.createElement('canvas').getContext('webgl');
      var ext = gl && gl.getExtension('WEBGL_debug_renderer_info');
      return ext ? gl.getParameter(ext.UNMASKED_RENDERER_WEBGL) : 'no-webgl';
    } catch (e) { return 'no-webgl'; }
  }

  function fingerprint() {
    var n = global.navigator || {};
    var s = global.screen || {};
    var parts = [
      n.userAgent, n.language, (n.languages || []).join(','), n.platform,
      n.hardwareConcurrency, n.deviceMemory, n.maxTouchPoints,
      s.width + 'x' + s.height + 'x' + s.colorDepth, global.devicePixelRatio,
      new Date().getTimezoneOffset(),
      (Intl.DateTimeFormat().resolvedOptions() || {}).timeZone,
      canvasTrait(), webglTrait()
    ];
    return sha256Hex(parts.join('||'));
  }

  /* ---------- blocked overlay ---------- */

  function showBlocked(text) {
    if (blocked) return;
    blocked = true;

    try { document.body.innerHTML = ''; } catch (e) { /* ignore */ }

    var o = document.createElement('div');
    o.setAttribute('style',
      'position:fixed;inset:0;z-index:2147483647;display:flex;flex-direction:column;' +
      'align-items:center;justify-content:center;padding:24px;text-align:center;' +
      'background:#0f172a;color:#fff;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;');

    var icon = document.createElement('div');
    icon.textContent = '\uD83D\uDEAB';
    icon.style.fontSize = '56px';

    var h = document.createElement('h2');
    h.textContent = CFG.blockedTitle;
    h.style.margin = '16px 0 8px';

    var p = document.createElement('p');
    p.textContent = text || CFG.blockedText;
    p.style.cssText = 'max-width:320px;line-height:1.5;opacity:.8;margin:0 0 20px;';

    var b = document.createElement('button');
    b.textContent = 'Close';
    b.setAttribute('style',
      'padding:10px 22px;border:0;border-radius:10px;background:#ef4444;color:#fff;font-size:15px;');
    b.onclick = function () {
      try { Telegram.WebApp.close(); } catch (e) { /* ignore */ }
    };

    o.appendChild(icon); o.appendChild(h); o.appendChild(p); o.appendChild(b);
    document.body.appendChild(o);
  }

  /* ---------- main check ---------- */

  function check() {
    var tg = global.Telegram && Telegram.WebApp;
    var initData = tg ? tg.initData : '';

    if (!initData) {
      showBlocked('Please open this app from inside Telegram.');
      return Promise.resolve({ ok: false, blocked: true, reason: 'no_telegram' });
    }

    return Promise.all([getDeviceId(), fingerprint()]).then(function (r) {
      return fetch(CFG.endpoint, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ initData: initData, deviceId: r[0], fingerprint: r[1] })
      });
    }).then(function (res) {
      return res.json().catch(function () { return { ok: false }; });
    }).then(function (data) {
      if (data && data.blocked) {
        showBlocked(data.reason === 'multiple_accounts_on_device' || data.reason === 'account_blocked'
          ? null
          : 'Your session could not be verified. Reopen the app from Telegram.');
      }
      return data || { ok: false };
    }).catch(function () {
      // Network error: fail open (server still enforces on API calls).
      return { ok: true, blocked: false, error: 'network' };
    });
  }

  /* ---------- boot ---------- */

  var ready = check();

  // Re-verify periodically and when the app returns to the foreground.
  setInterval(function () { if (!blocked) check(); }, CFG.recheckMs);
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden && !blocked) check();
  });

  global.DeviceGuard = { ready: ready, check: check, config: CFG };
})(window);
