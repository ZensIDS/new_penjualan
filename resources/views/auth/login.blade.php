<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk — VoltStock</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        display: ['"Space Grotesk"', 'sans-serif'],
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: { ink: '#111214' },
                },
            },
        }
    </script>
</head>
<body class="h-full bg-white font-sans text-ink antialiased">
    <div class="min-h-full lg:grid lg:grid-cols-2">

        {{-- Panel kiri: brand, hanya tampil di layar besar --}}
        <div class="hidden lg:flex flex-col justify-between bg-ink text-white p-12">
            <div class="font-display font-semibold text-xl tracking-tight">
                Volt<span class="text-white/40">Stock</span>
            </div>
            <div class="max-w-sm">
                <p class="font-display text-3xl leading-snug mb-4">
                    Satu sistem untuk stok, penjualan, dan kas bisnismu.
                </p>
                <p class="text-white/50 text-sm leading-relaxed">
                    Pelacakan batch FIFO, hutang-piutang, dan laporan laba rugi —
                    tercatat rapi dari pembelian sampai laporan.
                </p>
            </div>
            <p class="text-white/30 text-xs">&copy; {{ date('Y') }} VoltStock Internal System</p>
        </div>

        {{-- Panel kanan: form login --}}
        <div class="flex items-center justify-center p-6 sm:p-12">
            <div class="w-full max-w-sm">

                <div class="lg:hidden font-display font-semibold text-xl tracking-tight mb-10">
                    Volt<span class="text-ink/40">Stock</span>
                </div>

                <h1 class="font-display font-semibold text-2xl mb-1">Masuk</h1>
                <p class="text-ink/50 text-sm mb-8">Masukkan username dan kata sandi akunmu.</p>

                @if ($errors->any())
                    <div class="mb-6 border border-red-900/20 bg-red-50 text-red-900 text-sm px-4 py-3">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label for="username" class="block text-sm font-medium mb-1.5">Username</label>
                        <input
                            id="username"
                            name="username"
                            type="text"
                            value="{{ old('username') }}"
                            required
                            autofocus
                            class="w-full border border-ink/15 focus:border-ink focus:outline-none px-3.5 py-2.5 text-sm transition-colors"
                            placeholder="cth. budi.admin"
                        >
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium mb-1.5">Kata sandi</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            required
                            class="w-full border border-ink/15 focus:border-ink focus:outline-none px-3.5 py-2.5 text-sm transition-colors"
                            placeholder="••••••••"
                        >
                    </div>

                    <label class="flex items-center gap-2 text-sm text-ink/60">
                        <input type="checkbox" name="remember" class="border-ink/30">
                        Ingat saya
                    </label>

                    <button
                        type="submit"
                        class="w-full bg-ink text-white py-2.5 text-sm font-medium hover:bg-ink/90 transition-colors"
                    >
                        Masuk
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
