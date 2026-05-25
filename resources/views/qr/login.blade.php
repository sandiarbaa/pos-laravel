<!DOCTYPE html>
<html lang="id">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — QR Generator</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-zinc-100 min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-sm">

        {{-- Logo / Title --}}
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 bg-zinc-900 rounded-2xl mb-4 shadow-lg">
                <svg class="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 3.5a.5.5 0 11-1 0 .5.5 0 011 0zM6 8H4m2 0V6m0 2v2m0-2h2M4 4h2v2H4V4z" />
                </svg>
            </div>
            <h1 class="text-zinc-900 text-2xl font-bold tracking-tight">QR Generator</h1>
            <p class="text-zinc-500 text-sm mt-1">Login untuk generate & print QR menu</p>
        </div>

        {{-- Card --}}
        <div class="bg-white border border-zinc-300 rounded-2xl p-6 shadow-2xl">
            @if ($errors->any())
            <div class="bg-red-50 border border-red-200 rounded-xl p-3 mb-5">
                <p class="text-red-600 text-sm font-medium">{{ $errors->first() }}</p>
            </div>
            @endif

            <form method="POST" action="{{ route('qr.login.post') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-zinc-500 text-xs font-semibold uppercase tracking-wider mb-1.5">
                        Email
                    </label>
                    <input
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        placeholder="kasir@restoran.com"
                        required
                        class="w-full bg-zinc-50 border border-zinc-300 text-zinc-900 placeholder-zinc-400
                            rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-zinc-900
                            focus:ring-1 focus:ring-zinc-900 transition-colors"
                    >
                </div>

                <div>
                    <label class="block text-zinc-500 text-xs font-semibold uppercase tracking-wider mb-1.5">
                        Password
                    </label>
                    <div class="relative">
                        <input
                            type="password"
                            name="password"
                            id="password"
                            placeholder="••••••••"
                            required
                            class="w-full bg-zinc-50 border border-zinc-300 text-zinc-900 placeholder-zinc-400
                                rounded-xl px-4 py-3 pr-12 text-sm focus:outline-none focus:border-zinc-900
                                focus:ring-1 focus:ring-zinc-900 transition-colors"
                        >
                        <button
                            type="button"
                            onclick="togglePassword()"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600 transition-colors p-1"
                        >
                            <svg id="icon-eye" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <svg id="icon-eye-off" class="w-5 h-5 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21" />
                            </svg>
                        </button>
                    </div>
                </div>

                <button
                    type="submit"
                    class="w-full bg-zinc-900 text-white font-bold py-3 rounded-xl text-sm
                        hover:bg-zinc-700 active:scale-95 transition-all mt-2"
                >
                    Masuk
                </button>
            </form>
        </div>

        <p class="text-center text-zinc-400 text-xs mt-6">QR Menu Generator — POS System</p>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const eyeIcon = document.getElementById('icon-eye');
            const eyeOffIcon = document.getElementById('icon-eye-off');

            if (input.type === 'password') {
                input.type = 'text';
                eyeIcon.classList.add('hidden');
                eyeOffIcon.classList.remove('hidden');
            } else {
                input.type = 'password';
                eyeIcon.classList.remove('hidden');
                eyeOffIcon.classList.add('hidden');
            }
        }
    </script>

</body>
</html>
