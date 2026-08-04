<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Página Não Encontrada - Nodal</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Tailwind via CDN for standalone error page -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-neutral-50 text-neutral-900 font-sans antialiased flex items-center justify-center min-h-screen">
    
    <div class="max-w-2xl w-full px-6 text-center">
        <!-- Logo -->
        <div class="flex justify-center mb-12">
            <img src="{{ asset('images/Nodal-Logo.png') }}" alt="Nodal" class="h-10 w-auto opacity-80" />
        </div>

        <!-- 404 Illustration/Graphic -->
        <div class="relative w-full max-w-sm mx-auto mb-10">
            <!-- Decorative blur background -->
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-48 h-48 bg-primary-100 rounded-full blur-3xl opacity-50"></div>
            
            <h1 class="relative text-9xl font-black text-neutral-200 tracking-tighter select-none">
                404
            </h1>
            <div class="absolute inset-0 flex items-center justify-center">
                <div class="bg-white p-3 rounded-2xl shadow-xl shadow-neutral-200/50 border border-neutral-100 rotate-[-4deg] transition-transform hover:rotate-0 duration-300">
                    <svg class="w-10 h-10 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 15.75l-2.489-2.489m0 0a3.375 3.375 0 10-4.773-4.773 3.375 3.375 0 004.774 4.774zM21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <h2 class="text-3xl font-bold text-neutral-900 mb-4 tracking-tight">Página não encontrada</h2>
        <p class="text-neutral-500 text-lg max-w-md mx-auto mb-10 leading-relaxed">
            Desculpe, a página que você está procurando não existe, foi movida ou você não tem permissão para acessá-la.
        </p>

        <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-neutral-900 text-white font-medium rounded-lg hover:bg-neutral-800 focus:ring-4 focus:ring-neutral-200 transition-all duration-200 shadow-lg shadow-neutral-900/20 group">
            <svg class="w-5 h-5 text-neutral-300 group-hover:-translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Voltar para o Início
        </a>
        
        <div class="mt-16 text-sm text-neutral-400 space-y-2">
            <p>
                &copy; {{ date('Y') }} <a href="https://sacratech.com" target="_blank" class="hover:text-neutral-600 transition-colors underline">Sacratech Softwares</a>. Todos os direitos reservados.
            </p>
            <p class="text-xs">
                <strong>Nodal</strong> é um serviço oferecido pela <a href="https://sacratech.com" target="_blank" class="hover:text-neutral-600 transition-colors underline">Sacratech Softwares</a>. Marca e produto registrados.
            </p>
        </div>
    </div>

</body>
</html>
