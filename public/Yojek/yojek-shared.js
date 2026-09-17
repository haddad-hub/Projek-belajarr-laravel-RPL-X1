(function () {
  // The browser only keeps a short-lived cache of the API response for
  // rendering. MySQL is the source of truth for every operational action.
  const STORAGE_KEY = 'yojek_ops_data_v2';
  const DEFAULT_DATA = { orders: [], finance: [], couriers: [], actor: null, syncedAt: null };
  const SERVER_MODE = /^https?:$/.test(window.location.protocol);
  const yojekIndex = window.location.pathname.toLowerCase().indexOf('/yojek/');
  const APP_ROOT = yojekIndex >= 0 ? window.location.pathname.slice(0, yojekIndex + 1) : '/';
  const API_ROOT = APP_ROOT.replace(/\/$/, '') + '/api/yojek';
  const TOKEN_KEY = /dashboard/i.test(window.location.pathname) ? 'yojek_token_admin_v1' : /kurir/i.test(window.location.pathname) ? 'yojek_token_courier_v1' : 'yojek_token_customer_v1';
  const POLL_INTERVAL = 5000;
  let pollHandle = null;
  let authRedirectQueued = false;
  let sessionActor = null;

  // Version 1 and the old profile records were browser-only operational data.
  // They must not reappear after the server-side reset.
  [
    'yojek_ops_data_v1',
    'yojek_customer_profiles_v2',
    'yojek_customer_active_username_v2',
    'yojek_courier_profiles_v2',
    'yojek_courier_active_username_v2',
  ].forEach((key) => localStorage.removeItem(key));

  function clone(value) {
    return JSON.parse(JSON.stringify(value));
  }

  function read() {
    try {
      const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
      if (!saved) return clone(DEFAULT_DATA);

      return {
        orders: Array.isArray(saved.orders) ? saved.orders : [],
        finance: Array.isArray(saved.finance) ? saved.finance : [],
        couriers: Array.isArray(saved.couriers) ? saved.couriers : [],
        actor: saved.actor && typeof saved.actor === 'object' ? saved.actor : null,
        syncedAt: saved.syncedAt || null,
      };
    } catch (error) {
      return clone(DEFAULT_DATA);
    }
  }

  function write(data) {
    const next = {
      orders: Array.isArray(data.orders) ? data.orders : [],
      finance: Array.isArray(data.finance) ? data.finance : [],
      couriers: Array.isArray(data.couriers) ? data.couriers : [],
      actor: data.actor && typeof data.actor === 'object' ? data.actor : null,
      syncedAt: data.syncedAt || new Date().toISOString(),
    };
    localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
    window.dispatchEvent(new CustomEvent('yojek:datachange', { detail: next }));
    return next;
  }

  function applicationUrl(path) {
    return APP_ROOT.replace(/\/$/, '') + '/' + String(path || '').replace(/^\//, '');
  }

  function queueLoginRedirect() {
    if (authRedirectQueued || !SERVER_MODE) return;
    authRedirectQueued = true;
    window.dispatchEvent(new CustomEvent('yojek:authrequired'));
    const isDashboard = /dashboard/i.test(window.location.pathname);
    window.setTimeout(() => {
      window.location.assign(applicationUrl(isDashboard ? 'admin/login' : 'login'));
    }, 900);
  }

  function offlineError() {
    return new Error('Aplikasi harus dibuka melalui server Yojek setelah login. Data lokal lama tidak digunakan.');
  }

  async function request(path, options = {}) {
    if (!SERVER_MODE) throw offlineError();

    const response = await fetch(API_ROOT + path, {
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(localStorage.getItem(TOKEN_KEY) ? { 'X-Yojek-Token': localStorage.getItem(TOKEN_KEY) } : {}),
        ...(options.headers || {}),
      },
      ...options,
    });

    if (!response.ok) {
      let message = 'Permintaan Yojek gagal (' + response.status + ')';
      try {
        const body = await response.json();
        message = body.message || Object.values(body.errors || {}).flat()[0] || message;
      } catch (error) {
        // Use the safe generic message when a proxy returns non-JSON content.
      }
      const failure = new Error(message);
      failure.status = response.status;
      if (response.status === 401) {
        failure.message = 'Sesi login sudah berakhir. Mengarahkan ke halaman login…';
        queueLoginRedirect();
      }
      throw failure;
    }

    return response.json();
  }

  async function sync() {
    if (!localStorage.getItem(TOKEN_KEY)) {
      const tokenResponse = await request('/token', { method: 'POST', body: '{}' });
      localStorage.setItem(TOKEN_KEY, tokenResponse.token);
    }
    const state = await request('/state');
    sessionActor = state.actor && typeof state.actor === 'object' ? state.actor : null;
    return write({
      orders: state.orders,
      finance: state.finance,
      couriers: state.couriers,
      actor: state.actor,
      syncedAt: state.syncedAt,
    });
  }

  function mutate(path, payload) {
    return request(path, {
      method: 'POST',
      body: JSON.stringify(payload || {}),
    }).then(sync);
  }

  function updateProfile(profile) {
    return mutate('/profile', {
      phone: profile && profile.phone ? profile.phone : null,
      address: profile && profile.address ? profile.address : null,
      vehicle: profile && profile.vehicle ? profile.vehicle : null,
    });
  }

  function createOrder(input) {
    return mutate('/orders', input);
  }

  function acceptOrder(orderId) {
    return mutate('/orders/' + encodeURIComponent(orderId) + '/accept');
  }

  function completeOrder(orderId, amount) {
    return mutate('/orders/' + encodeURIComponent(orderId) + '/complete', { amount });
  }

  function confirmOrder(orderId) {
    return mutate('/orders/' + encodeURIComponent(orderId) + '/confirm');
  }

  function rateOrder(orderId, rating) {
    return mutate('/orders/' + encodeURIComponent(orderId) + '/rate', { rating });
  }

  function setCourierAvailability(courierId, isActive, attendancePhoto, attendanceLocation) {
    return mutate('/courier/availability', {
      isActive: Boolean(isActive),
      attendancePhoto: attendancePhoto || null,
      attendanceLocation: attendanceLocation || null,
    });
  }

  function setCourierAdminDisabled(courierId, disabled) {
    return mutate('/couriers/' + encodeURIComponent(courierId) + '/disabled', { disabled: Boolean(disabled) });
  }

  function upsertCourier(profile) {
    return updateProfile(profile || {});
  }

  async function logout() {
    try {
      await request('/logout', { method: 'POST', body: '{}' });
    } finally {
      localStorage.removeItem(STORAGE_KEY);
      localStorage.removeItem(TOKEN_KEY);
    }
    const loginPath = /dashboard/i.test(window.location.pathname) ? 'admin/login' : 'login';
    window.location.assign(applicationUrl(loginPath));
  }

  function startRealtime() {
    if (!SERVER_MODE || pollHandle) return;
    const refresh = () => {
      if (document.visibilityState === 'visible') sync().catch(() => {});
    };
    document.addEventListener('visibilitychange', refresh);
    pollHandle = window.setInterval(refresh, POLL_INTERVAL);
    refresh();
  }

  window.YojekStore = {
    STORAGE_KEY,
    SERVER_MODE,
    APP_ROOT,
    read,
    write,
    sync,
    startRealtime,
    // Do not expose a cached actor before the current HTTP session has been
    // verified. This prevents a customer identity from being reused briefly
    // when the same browser switches to a courier account.
    currentUser: () => sessionActor,
    createOrder,
    acceptOrder,
    completeOrder,
    confirmOrder,
    rateOrder,
    updateProfile,
    upsertCourier,
    setCourierAvailability,
    setCourierAdminDisabled,
    logout,
    toAnalyticsPayload: read,
  };

  startRealtime();
})();
