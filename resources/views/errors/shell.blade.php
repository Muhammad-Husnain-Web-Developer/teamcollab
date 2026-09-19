<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $code }} — {{ config('app.name', 'TeamCollab') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css'])
</head>
<body class="font-sans antialiased bg-dark-800 text-white min-h-screen flex items-center justify-center overflow-hidden relative">
    <div class="app-atmosphere" aria-hidden="true">
        <div class="aurora-blob" style="width:560px;height:560px;background:radial-gradient(circle,#5c7cfa,transparent 70%);top:-180px;left:-120px;"></div>
        <div class="aurora-blob" style="width:420px;height:420px;background:radial-gradient(circle,#8b5cf6,transparent 70%);bottom:-140px;left:22%;animation-delay:-8s;animation-duration:30s;"></div>
        <div class="aurora-blob" style="width:340px;height:340px;background:radial-gradient(circle,#2dd4bf,transparent 70%);top:35%;right:8%;animation-delay:-4s;animation-duration:22s;"></div>
    </div>

    <div class="relative z-10 max-w-md w-full mx-6 text-center">
        <div class="flex items-center justify-center gap-2.5 mb-10">
            <div class="w-9 h-9 rounded-xl bg-brand-500 flex items-center justify-center shadow-lg shadow-brand-500/30">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
            </div>
            <span class="text-lg font-bold tracking-tight">{{ config('app.name', 'TeamCollab') }}</span>
        </div>

        <div class="glass-elevated p-10">
            <p class="text-sm font-semibold text-brand-400 tracking-widest mb-3">ERROR {{ $code }}</p>
            <h1 class="text-2xl font-bold text-white mb-3">{{ $title }}</h1>
            <p class="text-dark-50/70 text-sm leading-relaxed mb-8">{{ $message }}</p>

            <div class="flex items-center justify-center gap-3">
                <a href="/" class="btn-primary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
                    Take me home
                </a>
                <button onclick="history.back()" class="btn-secondary">
                    Go back
                </button>
            </div>
        </div>
    </div>
</body>
</html>
