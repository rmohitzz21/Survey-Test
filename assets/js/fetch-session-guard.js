// ── One place that notices the session is gone ────────────────────────────────
// require_auth_json() answers 401 + {expired:true}. api() handled that, but ~36
// call sites use raw fetch() and only checked `ok`, so _startPolling() would sit
// there re-firing the same 401 every 10s for as long as the tab stayed open —
// which is the console flood of "api/ping.php 401" seen on the live server.
// Patching fetch once fixes every caller instead of 36 separate edits.
// ponytail: one guard at the boundary, not a check per call site.
(() => {
  const realFetch = window.fetch.bind(window);
  let leaving = false;
  window.fetch = async (...args) => {
    const res = await realFetch(...args);
    if (res.status === 401 && !leaving) {
      const url = typeof args[0] === 'string' ? args[0] : (args[0] && args[0].url) || '';
      if (url.includes('api/')) {
        leaving = true;
        window.location.replace('login.php?expired=1');
      }
    }
    return res;
  };
})();
