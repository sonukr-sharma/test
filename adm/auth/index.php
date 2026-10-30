<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <meta name="theme-color" content="#141627" />
  <title>Refer &amp; Earn - Admin Login</title>
  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Google Fonts: Inter -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <!-- Font Awesome CDN -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

  <style>
    body {
      font-family: 'Inter', sans-serif;
      background-color: #f6f7fb;
      -webkit-tap-highlight-color: transparent;
    }
    .brand-panel {
      background:
        radial-gradient(circle at 15% 15%, rgba(124, 77, 255, 0.55) 0%, transparent 55%),
        radial-gradient(circle at 85% 85%, rgba(99, 57, 249, 0.45) 0%, transparent 55%),
        linear-gradient(160deg, #1b1d33 0%, #141627 55%, #0f1120 100%);
    }
    .brand-grid {
      background-image:
        linear-gradient(rgba(255, 255, 255, 0.045) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.045) 1px, transparent 1px);
      background-size: 44px 44px;
      mask-image: radial-gradient(circle at 50% 40%, #000 0%, transparent 78%);
      -webkit-mask-image: radial-gradient(circle at 50% 40%, #000 0%, transparent 78%);
    }
    .btn-primary {
      background: linear-gradient(135deg, #6c3cf7 0%, #5a29e4 100%);
      box-shadow: 0 10px 24px -8px rgba(90, 41, 228, 0.65);
    }
    .btn-primary:hover:not(:disabled) {
      filter: brightness(1.06);
    }
    .btn-primary:disabled {
      opacity: 0.75;
      cursor: not-allowed;
    }
    .field-input {
      transition: border-color .18s ease, box-shadow .18s ease, background-color .18s ease;
    }
    .field-input:focus {
      outline: none;
      border-color: #6c3cf7;
      box-shadow: 0 0 0 4px rgba(108, 60, 247, 0.12);
    }
    .field-input.is-invalid {
      border-color: #f43f5e;
      box-shadow: 0 0 0 4px rgba(244, 63, 94, 0.1);
    }
    .field-input:-webkit-autofill,
    .field-input:-webkit-autofill:hover,
    .field-input:-webkit-autofill:focus {
      -webkit-text-fill-color: #0f172a;
      -webkit-box-shadow: 0 0 0 1000px #ffffff inset;
      transition: background-color 5000s ease-in-out 0s;
    }
    /* Checkmark lives inside the sibling span, so peer-checked can't reach it */
    .peer:checked ~ .check-box i {
      opacity: 1;
    }
    .shake {
      animation: shake .38s cubic-bezier(.36, .07, .19, .97) both;
    }
    @keyframes shake {
      10%, 90% { transform: translateX(-1px); }
      20%, 80% { transform: translateX(2px); }
      30%, 50%, 70% { transform: translateX(-4px); }
      40%, 60% { transform: translateX(4px); }
    }
    .fade-up {
      animation: fadeUp .5s ease both;
    }
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(12px); }
      to { opacity: 1; transform: translateY(0); }
    }
    @media (prefers-reduced-motion: reduce) {
      .fade-up, .shake { animation: none !important; }
      * { transition-duration: .01ms !important; }
    }
    /* Safe-area padding for notched phones */
    .safe-bottom { padding-bottom: max(1rem, env(safe-area-inset-bottom)); }
    .safe-top { padding-top: max(1rem, env(safe-area-inset-top)); }
  </style>
</head>
<body class="min-h-screen text-slate-800 antialiased">

  <div class="min-h-screen w-full flex flex-col lg:flex-row">

    <!-- ================= Brand Panel (desktop) ================= -->
    <aside class="brand-panel relative hidden lg:flex lg:w-[46%] xl:w-[42%] flex-col justify-between overflow-hidden p-10 xl:p-14 text-white">
      <div class="absolute inset-0 brand-grid pointer-events-none"></div>

      <!-- Brand -->
      <div class="relative flex items-center gap-3">
        <div class="w-11 h-11 rounded-full bg-gradient-to-b from-amber-300 to-amber-500 p-0.5 shadow-lg flex items-center justify-center shrink-0">
          <div class="w-full h-full rounded-full bg-amber-400 border-2 border-amber-200 flex items-center justify-center text-amber-900 font-extrabold text-lg">
            T
          </div>
        </div>
        <div>
          <h1 class="font-bold text-base leading-tight">Refer &amp; Earn</h1>
          <p class="text-xs text-slate-400">Admin Panel</p>
        </div>
      </div>

      <!-- Headline -->
      <div class="relative max-w-md">
        <span class="inline-flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wider text-indigo-200 bg-white/10 border border-white/10 rounded-full px-3 py-1.5 mb-6">
          <i class="fa-solid fa-shield-halved text-[10px]"></i>
          Secure Admin Access
        </span>
        <h2 class="text-3xl xl:text-4xl font-extrabold leading-tight tracking-tight">
          Manage your rewards<br class="hidden xl:block" /> platform with confidence.
        </h2>
        <p class="mt-4 text-sm text-slate-300/90 leading-relaxed">
          Broadcast announcements, approve withdrawals and fine-tune referral settings — all from one dashboard.
        </p>

        <ul class="mt-8 space-y-3.5 text-sm text-slate-200">
          <li class="flex items-center gap-3">
            <span class="w-8 h-8 rounded-lg bg-white/10 border border-white/10 flex items-center justify-center shrink-0">
              <i class="fa-solid fa-paper-plane text-xs text-indigo-200"></i>
            </span>
            Instant Telegram broadcasts
          </li>
          <li class="flex items-center gap-3">
            <span class="w-8 h-8 rounded-lg bg-white/10 border border-white/10 flex items-center justify-center shrink-0">
              <i class="fa-solid fa-wallet text-xs text-indigo-200"></i>
            </span>
            One-tap withdrawal approvals
          </li>
          <li class="flex items-center gap-3">
            <span class="w-8 h-8 rounded-lg bg-white/10 border border-white/10 flex items-center justify-center shrink-0">
              <i class="fa-solid fa-chart-line text-xs text-indigo-200"></i>
            </span>
            Live referral analytics
          </li>
        </ul>
      </div>

      <!-- Footer -->
      <div class="relative flex items-center justify-between text-xs text-slate-400">
        <span>&copy; <span id="year">2026</span> Refer &amp; Earn</span>
        <span class="flex items-center gap-1.5">
          <i class="fa-solid fa-lock text-[10px]"></i>
          Encrypted session
        </span>
      </div>
    </aside>

    <!-- ================= Form Panel ================= -->
    <main class="flex-1 flex flex-col bg-[#f6f7fb]">

      <!-- Mobile brand bar -->
      <div class="brand-panel lg:hidden relative overflow-hidden safe-top px-5 pb-14 pt-6 text-white">
        <div class="absolute inset-0 brand-grid pointer-events-none"></div>
        <div class="relative flex items-center gap-3">
          <div class="w-10 h-10 rounded-full bg-gradient-to-b from-amber-300 to-amber-500 p-0.5 shadow-md flex items-center justify-center shrink-0">
            <div class="w-full h-full rounded-full bg-amber-400 border-2 border-amber-200 flex items-center justify-center text-amber-900 font-bold text-base">
              T
            </div>
          </div>
          <div>
            <h1 class="font-bold text-sm leading-tight">Refer &amp; Earn</h1>
            <p class="text-[11px] text-slate-400">Admin Panel</p>
          </div>
        </div>
        <p class="relative mt-5 text-lg font-bold leading-snug max-w-xs">
          Sign in to manage your platform.
        </p>
      </div>

      <!-- Card -->
      <div class="flex-1 flex items-start lg:items-center justify-center px-4 sm:px-6 -mt-9 lg:mt-0 pb-8 safe-bottom">
        <div class="w-full max-w-[420px] fade-up">

          <div class="bg-white rounded-2xl sm:rounded-3xl shadow-xl shadow-slate-900/5 border border-slate-100 p-5 sm:p-8">

            <!-- Heading -->
            <div class="mb-6">
              <h2 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">Welcome back</h2>
              <p class="text-xs sm:text-sm text-slate-500 mt-1.5">Enter your admin credentials to continue.</p>
            </div>

            <!-- Error banner -->
            <div id="formError" class="hidden mb-5 items-start gap-2.5 rounded-xl bg-rose-50 border border-rose-200 px-3.5 py-3 text-xs text-rose-700">
              <i class="fa-solid fa-circle-exclamation mt-0.5 shrink-0"></i>
              <span id="formErrorText">Invalid credentials.</span>
            </div>

            <form id="loginForm" novalidate class="space-y-4">

              <!-- Email / Username -->
              <div>
                <label for="identifier" class="block text-xs font-semibold text-slate-700 mb-1.5">Email or Username</label>
                <div class="relative">
                  <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                    <i class="fa-regular fa-user text-sm"></i>
                  </span>
                  <input
                    id="identifier"
                    name="identifier"
                    type="text"
                    inputmode="email"
                    autocomplete="username"
                    autocapitalize="none"
                    spellcheck="false"
                    placeholder="admin@referearn.com"
                    class="field-input w-full rounded-xl border border-slate-200 bg-slate-50/60 pl-10 pr-3.5 py-3 text-sm text-slate-900 placeholder:text-slate-400"
                  />
                </div>
                <p data-error-for="identifier" class="hidden mt-1.5 text-[11px] font-medium text-rose-600"></p>
              </div>

              <!-- Password -->
              <div>
                <div class="mb-1.5">
                  <label for="password" class="block text-xs font-semibold text-slate-700">Password</label>
                </div>
                <div class="relative">
                  <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                    <i class="fa-solid fa-lock text-sm"></i>
                  </span>
                  <input
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    placeholder="Enter your password"
                    class="field-input w-full rounded-xl border border-slate-200 bg-slate-50/60 pl-10 pr-12 py-3 text-sm text-slate-900 placeholder:text-slate-400"
                  />
                  <button
                    type="button"
                    id="togglePassword"
                    aria-label="Show password"
                    aria-pressed="false"
                    class="absolute inset-y-0 right-0 flex items-center px-3.5 text-slate-400 hover:text-slate-700 rounded-r-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-[#6c3cf7]/40"
                  >
                    <i id="togglePasswordIcon" class="fa-regular fa-eye text-sm"></i>
                  </button>
                </div>
                <p data-error-for="password" class="hidden mt-1.5 text-[11px] font-medium text-rose-600"></p>
              </div>

              <!-- Remember / Caps lock hint -->
              <div class="flex items-center justify-between gap-3 pt-0.5">
                <label class="flex items-center gap-2.5 cursor-pointer select-none">
                  <input id="remember" type="checkbox" class="peer sr-only" />
                  <span class="check-box w-[18px] h-[18px] rounded-[5px] border border-slate-300 bg-white flex items-center justify-center text-white transition peer-checked:bg-[#6c3cf7] peer-checked:border-[#6c3cf7] peer-focus-visible:ring-2 peer-focus-visible:ring-[#6c3cf7]/40">
                    <i class="fa-solid fa-check text-[9px] opacity-0 transition-opacity"></i>
                  </span>
                  <span class="text-xs font-medium text-slate-600">Keep me signed in</span>
                </label>
                <span id="capsHint" class="hidden text-[11px] font-semibold text-amber-600">
                  <i class="fa-solid fa-triangle-exclamation mr-1"></i>Caps Lock
                </span>
              </div>

              <!-- Submit -->
              <button
                type="submit"
                id="submitBtn"
                class="btn-primary w-full rounded-xl text-white text-sm font-semibold py-3.5 flex items-center justify-center gap-2 transition focus:outline-none focus-visible:ring-4 focus-visible:ring-[#6c3cf7]/30"
              >
                <span id="submitLabel">Sign In</span>
                <i id="submitIcon" class="fa-solid fa-arrow-right-to-bracket text-xs"></i>
                <i id="submitSpinner" class="fa-solid fa-circle-notch fa-spin text-sm hidden"></i>
              </button>
            </form>

          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- Toast -->
  <div id="toast" class="fixed bottom-5 left-1/2 -translate-x-1/2 z-50 max-w-[92vw] bg-slate-900/95 text-white text-xs font-semibold px-4 py-2.5 rounded-full shadow-lg flex items-center gap-2 transition-all duration-300 opacity-0 pointer-events-none translate-y-3">
    <i id="toastIcon" class="fa-solid fa-circle-check text-emerald-400"></i>
    <span id="toastMsg">Notification</span>
  </div>

  <script>
    tailwind.config = {
      theme: {
        extend: {
          screens: { 'xs': '360px' }
        }
      }
    };

    (function () {
      'use strict';

      const form = document.getElementById('loginForm');
      const identifier = document.getElementById('identifier');
      const password = document.getElementById('password');
      const remember = document.getElementById('remember');
      const submitBtn = document.getElementById('submitBtn');
      const submitLabel = document.getElementById('submitLabel');
      const submitIcon = document.getElementById('submitIcon');
      const submitSpinner = document.getElementById('submitSpinner');
      const formError = document.getElementById('formError');
      const formErrorText = document.getElementById('formErrorText');
      const togglePassword = document.getElementById('togglePassword');
      const togglePasswordIcon = document.getElementById('togglePasswordIcon');
      const capsHint = document.getElementById('capsHint');
      const toast = document.getElementById('toast');
      const toastMsg = document.getElementById('toastMsg');
      const toastIcon = document.getElementById('toastIcon');

      document.getElementById('year').textContent = new Date().getFullYear();

      /* ---------- Toast ---------- */
      let toastTimer;
      function showToast(message, type) {
        toastMsg.textContent = message;
        toastIcon.className = type === 'error'
          ? 'fa-solid fa-circle-exclamation text-rose-400'
          : 'fa-solid fa-circle-check text-emerald-400';
        toast.classList.remove('opacity-0', 'translate-y-3', 'pointer-events-none');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () {
          toast.classList.add('opacity-0', 'translate-y-3', 'pointer-events-none');
        }, 3200);
      }

      /* ---------- Field errors ---------- */
      function setFieldError(input, message) {
        const hint = document.querySelector('[data-error-for="' + input.id + '"]');
        if (message) {
          input.classList.add('is-invalid');
          input.setAttribute('aria-invalid', 'true');
          if (hint) {
            hint.textContent = message;
            hint.classList.remove('hidden');
          }
        } else {
          input.classList.remove('is-invalid');
          input.removeAttribute('aria-invalid');
          if (hint) {
            hint.textContent = '';
            hint.classList.add('hidden');
          }
        }
      }

      function clearFormError() {
        formError.classList.add('hidden');
        formError.classList.remove('flex');
      }

      function showFormError(message) {
        formErrorText.textContent = message;
        formError.classList.remove('hidden');
        formError.classList.add('flex');
        form.classList.add('shake');
        setTimeout(function () { form.classList.remove('shake'); }, 420);
      }

      /* ---------- Validation ---------- */
      function validateIdentifier() {
        const value = identifier.value.trim();
        if (!value) {
          setFieldError(identifier, 'Please enter your email or username.');
          return false;
        }
        const looksLikeEmail = value.indexOf('@') !== -1;
        if (looksLikeEmail && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value)) {
          setFieldError(identifier, 'Enter a valid email address.');
          return false;
        }
        if (!looksLikeEmail && value.length < 3) {
          setFieldError(identifier, 'Username must be at least 3 characters.');
          return false;
        }
        setFieldError(identifier, '');
        return true;
      }

      function validatePassword() {
        if (!password.value) {
          setFieldError(password, 'Please enter your password.');
          return false;
        }
        if (password.value.length < 6) {
          setFieldError(password, 'Password must be at least 6 characters.');
          return false;
        }
        setFieldError(password, '');
        return true;
      }

      identifier.addEventListener('blur', function () {
        if (identifier.value.trim()) validateIdentifier();
      });
      password.addEventListener('blur', function () {
        if (password.value) validatePassword();
      });
      identifier.addEventListener('input', function () {
        if (identifier.classList.contains('is-invalid')) validateIdentifier();
        clearFormError();
      });
      password.addEventListener('input', function () {
        if (password.classList.contains('is-invalid')) validatePassword();
        clearFormError();
      });

      /* ---------- Caps lock hint ---------- */
      function handleCapsLock(event) {
        if (typeof event.getModifierState !== 'function') return;
        capsHint.classList.toggle('hidden', !event.getModifierState('CapsLock'));
      }
      password.addEventListener('keydown', handleCapsLock);
      password.addEventListener('keyup', handleCapsLock);
      password.addEventListener('blur', function () { capsHint.classList.add('hidden'); });

      /* ---------- Password visibility ---------- */
      togglePassword.addEventListener('click', function () {
        const isHidden = password.type === 'password';
        password.type = isHidden ? 'text' : 'password';
        togglePasswordIcon.className = isHidden ? 'fa-regular fa-eye-slash text-sm' : 'fa-regular fa-eye text-sm';
        togglePassword.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
        togglePassword.setAttribute('aria-pressed', String(isHidden));
        password.focus({ preventScroll: true });
      });

      /* ---------- Loading state ---------- */
      function setLoading(isLoading) {
        submitBtn.disabled = isLoading;
        submitLabel.textContent = isLoading ? 'Signing in…' : 'Sign In';
        submitIcon.classList.toggle('hidden', isLoading);
        submitSpinner.classList.toggle('hidden', !isLoading);
      }

      /* ---------- Submit ---------- */
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        clearFormError();

        const okIdentifier = validateIdentifier();
        const okPassword = validatePassword();

        if (!okIdentifier || !okPassword) {
          (!okIdentifier ? identifier : password).focus();
          return;
        }

        setLoading(true);

        // Demo authentication — replace with a real API call.
        setTimeout(function () {
          setLoading(false);
          if (password.value === 'admin123') {
            showToast('Signed in successfully. Redirecting…', 'success');
            setTimeout(function () { window.location.href = '../broadcast.php'; }, 900);
          } else {
            showFormError('Incorrect email or password. Please try again.');
            password.value = '';
            password.focus();
          }
        }, 1100);
      });

      /* ---------- Restore remembered identifier ---------- */
      try {
        const saved = localStorage.getItem('re_admin_identifier');
        if (saved) {
          identifier.value = saved;
          remember.checked = true;
          password.focus({ preventScroll: true });
        }
      } catch (e) { /* storage unavailable */ }

      form.addEventListener('submit', function () {
        try {
          if (remember.checked) {
            localStorage.setItem('re_admin_identifier', identifier.value.trim());
          } else {
            localStorage.removeItem('re_admin_identifier');
          }
        } catch (e) { /* storage unavailable */ }
      });
    })();
  </script>
</body>
</html>
