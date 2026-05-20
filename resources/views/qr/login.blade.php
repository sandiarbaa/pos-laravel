<!DOCTYPE html>
<html lang="id">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — QR Generator</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-zinc-950 min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-sm">

        {{-- Logo / Title --}}
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 bg-white rounded-2xl mb-4 shadow-lg">
                <svg class="w-8 h-8 text-zinc-900" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 3.5a.5.5 0 11-1 0 .5.5 0 011 0zM6 8H4m2 0V6m0 2v2m0-2h2M4 4h2v2H4V4z" />
                </svg>
            </div>
            <h1 class="text-white text-2xl font-bold tracking-tight">QR Generator</h1>
            <p class="text-zinc-500 text-sm mt-1">Login untuk generate & print QR menu</p>
        </div>

        {{-- Card --}}
        <div class="bg-zinc-900 border border-zinc-800 rounded-2xl p-6 shadow-xl">

            @if ($errors->any())
            <div class="bg-red-500/10 border border-red-500/20 rounded-xl p-3 mb-5">
                <p class="text-red-400 text-sm font-medium">{{ $errors->first() }}</p>
            </div>
            @endif

            <form method="POST" action="{{ route('qr.login.post') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-zinc-400 text-xs font-semibold uppercase tracking-wider mb-1.5">
                        Email
                    </label>
                    <input
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        placeholder="kasir@restoran.com"
                        required
                        class="w-full bg-zinc-800 border border-zinc-700 text-white placeholder-zinc-600
                               rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-white
                               focus:ring-1 focus:ring-white transition-colors"
                    >
                </div>

                <div>
                    <label class="block text-zinc-400 text-xs font-semibold uppercase tracking-wider mb-1.5">
                        Password
                    </label>
                    <input
                        type="password"
                        name="password"
                        placeholder="••••••••"
                        required
                        class="w-full bg-zinc-800 border border-zinc-700 text-white placeholder-zinc-600
                               rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-white
                               focus:ring-1 focus:ring-white transition-colors"
                    >
                </div>

                <button
                    type="submit"
                    class="w-full bg-white text-zinc-900 font-bold py-3 rounded-xl text-sm
                           hover:bg-zinc-100 active:scale-95 transition-all mt-2"
                >
                    Masuk
                </button>
            </form>
        </div>

        <p class="text-center text-zinc-700 text-xs mt-6">QR Menu Generator — POS System</p>
    </div>

</body>
</html>
