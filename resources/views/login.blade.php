<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso API</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
        function setStatus(type, text) {
            const box = document.getElementById('status');
            box.className = 'mt-4 text-sm';
            if (type === 'ok') {
                box.classList.add('text-emerald-600');
            } else {
                box.classList.add('text-rose-600');
            }
            box.textContent = text;
        }
        function setToken(token) {
            const wrap = document.getElementById('tokenWrap');
            const value = document.getElementById('tokenValue');
            if (!token) {
                wrap.classList.add('hidden');
                value.textContent = '';
                return;
            }
            wrap.classList.remove('hidden');
            value.textContent = 'Bearer ' + token;
        }
        async function login(e) {
            e.preventDefault();
            const btn = document.getElementById('loginBtn');
            btn.disabled = true;
            btn.classList.add('opacity-60');
            setStatus('ok', 'Autenticando...');
            try {
                const email = document.getElementById('email').value;
                const password = document.getElementById('password').value;
                const res = await fetch('/api/login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email, password })
                });
                const data = await res.json();
                if (!res.ok) {
                    setStatus('err', data.message || 'Error de autenticación');
                    setToken('');
                } else {
                    localStorage.setItem('api_token', data.token);
                    setStatus('ok', 'Autenticado como ' + (data.user?.email || ''));
                    setToken(data.token);
                }
            } catch (_) {
                setStatus('err', 'Error de red');
                setToken('');
            } finally {
                btn.disabled = false;
                btn.classList.remove('opacity-60');
            }
        }
        function copyToken() {
            const t = document.getElementById('tokenValue').textContent.trim();
            if (!t) return;
            navigator.clipboard.writeText(t);
            const copied = document.getElementById('copied');
            copied.classList.remove('opacity-0');
            setTimeout(() => copied.classList.add('opacity-0'), 1200);
        }
        function loadSaved() {
            const token = localStorage.getItem('api_token');
            if (token) setToken(token);
        }
        function clearToken() {
            localStorage.removeItem('api_token');
            setToken('');
            setStatus('ok', 'Token limpiado');
        }
        function openSwagger() {
            window.open('/api/documentation', '_blank');
        }
        document.addEventListener('DOMContentLoaded', loadSaved);
    </script>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 text-slate-100">
<div class="mx-auto max-w-6xl px-6 py-16">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-center">
        <div class="space-y-6">
            <div class="inline-flex items-center gap-3">
                <div class="h-10 w-10 rounded-lg bg-emerald-500/20 flex items-center justify-center text-emerald-400 font-semibold">API</div>
                <h1 class="text-3xl font-semibold tracking-tight">Acceso y Token</h1>
            </div>
            <p class="text-slate-300 leading-relaxed">Inicia sesión para obtener un token Bearer y úsalo en la documentación interactiva. En la ventana de Swagger, pulsa <span class="font-semibold">Authorize</span> y pega el token completo.</p>
            <div class="rounded-2xl bg-slate-800/60 ring-1 ring-slate-700 p-6 backdrop-blur">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-medium text-slate-200">Token actual</h2>
                    <div class="flex gap-2">
                        <button onclick="copyToken()" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 px-3 py-1.5 text-sm font-medium text-white transition">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"><path d="M8 8h10a2 2 0 0 1 2 2v8m-6-12H6a2 2 0 0 0-2 2v8m12-12V6a2 2 0 0 0-2-2H8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Copiar
                        </button>
                        <button onclick="clearToken()" class="inline-flex items-center gap-2 rounded-lg bg-slate-700 hover:bg-slate-600 px-3 py-1.5 text-sm font-medium text-slate-100 transition">Limpiar</button>
                        <button onclick="openSwagger()" class="inline-flex items-center gap-2 rounded-lg bg-sky-600 hover:bg-sky-500 px-3 py-1.5 text-sm font-medium text-white transition">Abrir Swagger</button>
                    </div>
                </div>
                <div id="tokenWrap" class="rounded-lg border border-slate-700 bg-slate-900/60 p-4 font-mono text-xs overflow-x-auto hidden">
                    <span id="tokenValue"></span>
                </div>
                <div id="copied" class="text-emerald-400 text-xs mt-2 transition-opacity opacity-0">Copiado al portapapeles</div>
            </div>
            <div class="text-slate-400 text-sm">
                Admin sembrado: <span class="font-medium text-slate-200">test@example.com</span> / <span class="font-medium text-slate-200">password</span>
            </div>
        </div>
        <div class="rounded-2xl bg-slate-800/60 ring-1 ring-slate-700 p-8 backdrop-blur">
            <form onsubmit="login(event)" class="space-y-5">
                <div class="space-y-2">
                    <label for="email" class="text-sm text-slate-300">Email</label>
                    <input id="email" type="email" placeholder="test@example.com" required class="w-full rounded-lg border border-slate-700 bg-slate-900/70 px-4 py-2.5 text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                </div>
                <div class="space-y-2">
                    <label for="password" class="text-sm text-slate-300">Password</label>
                    <input id="password" type="password" placeholder="password" required class="w-full rounded-lg border border-slate-700 bg-slate-900/70 px-4 py-2.5 text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                </div>
                <button id="loginBtn" type="submit" class="w-full rounded-lg bg-emerald-600 hover:bg-emerald-500 px-4 py-2.5 text-white font-medium transition">Acceder</button>
                <div id="status" class="mt-4 text-sm"></div>
                <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <a href="javascript:void(0)" onclick="document.getElementById('email').value='test@example.com'; document.getElementById('password').value='password';" class="rounded-lg border border-slate-700 bg-slate-900/60 px-4 py-2.5 text-center text-slate-300 hover:bg-slate-800 transition">Autocompletar Admin</a>
                    <a href="/api/documentation" target="_blank" class="rounded-lg border border-slate-700 bg-slate-900/60 px-4 py-2.5 text-center text-slate-300 hover:bg-slate-800 transition">Abrir Documentación</a>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
