<!DOCTYPE html>
<html lang="es">

<style>
    #graficaFaltas {
        max-height: 800px;
        min-height: 300px;
    }
</style>


<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión Taller</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>


<body class="bg-[#f5f5f5] text-[#1d1d1b] font-sans antialiased">

    <!-- NAVBAR -->
    <nav class="bg-[#1d1d1b] border-b border-[#317080] shadow" x-data="{ menuOpen: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">

                <!-- Logo y título -->
                <div class="flex items-center gap-4">
                    <img src="{{ asset('storage/fotos/Recurso 25.png') }}" alt="Logo Tsa" class="h-8">
                    <img src="{{ asset('storage/fotos/Recurso 33.png') }}" alt="Logo Fistex" class="h-8">
                </div>

                <!-- Botón hamburguesa solo visible en móvil -->
                <button @click="menuOpen = !menuOpen" class="text-white sm:hidden focus:outline-none">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>

                <!-- Menús en desktop -->
                <div class="hidden sm:flex items-center gap-6">

                    <!-- Menú Empleados -->
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open"
                            class="!text-white bg-[#872829] px-4 py-2 rounded hover:bg-[#d23e5d] transition">
                            Empleados ▾
                        </button>
                        <div x-show="open" @click.away="open = false" x-transition
                            class="absolute mt-2 w-56 bg-white text-[#1d1d1b] rounded shadow-lg z-50">
                            <a href="{{ route('asistencias.index') }}" class="block px-4 py-2 hover:bg-[#7ebdb3]">Gestionar faltas</a>
                            <a href="{{ route('fichajes.lista') }}" class="block px-4 py-2 hover:bg-[#7ebdb3]">Ver fichajes de asistencia</a>
                            <a href="{{ route('empleados.create') }}" class="block px-4 py-2 hover:bg-[#7ebdb3]">Registrar empleados</a>
                            <a href="{{ route('empleados.index') }}" class="block px-4 py-2 hover:bg-[#7ebdb3]">Gestionar empleados</a>
                            <a href="{{ route('faltas.graficas.global') }}" class="block px-4 py-2 hover:bg-[#7ebdb3]">Gráficas empleados</a>
                        </div>
                    </div>

                    <!-- Menú Usuarios -->
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open"
                            class="!text-white bg-[#872829] px-4 py-2 rounded hover:bg-[#d23e5d] transition">
                            Usuarios ▾
                        </button>
                        <div x-show="open" @click.away="open = false" x-transition
                            class="absolute mt-2 w-56 bg-white text-[#1d1d1b] rounded shadow-lg z-50">
                            <a href="{{ route('usuarios.pendientes') }}" class="block px-4 py-2 hover:bg-[#7ebdb3]">Gestionar Roles</a>
                        </div>
                    </div>

                    <!-- Menú Órdenes -->
                    <div x-data="{ openOrdenes: false }" class="relative">
                        <button @click="openOrdenes = !openOrdenes"
                            class="!text-white bg-[#317080] px-4 py-2 rounded hover:bg-[#7ebdb3] transition">
                            Órdenes ▾
                        </button>
                        <div x-show="openOrdenes" @click.away="openOrdenes = false" x-transition
                            class="absolute mt-2 w-64 bg-white text-[#1d1d1b] rounded shadow-lg z-50">
                            <a href="{{ route('ordenes.index') }}" class="block px-4 py-2 hover:bg-[#7ebdb3]">Gestionar órdenes de reparación</a>
                            <a href="{{ route('ordenes.create') }}" class="block px-4 py-2 hover:bg-[#7ebdb3]">Registrar nueva orden</a>
                            <a href="{{ route('tareas.index') }}" class="block px-4 py-2 hover:bg-[#7ebdb3]">Ver tareas por orden</a>
                        </div>
                    </div>

                    <!-- Usuario -->
                    <div class="text-sm font-medium !text-white flex items-center gap-4">
                        {{ Auth::user()->name ?? 'Invitado' }}
                        @auth
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="underline text-sm hover:text-gray-300">Cerrar sesión</button>
                        </form>
                        @endauth
                    </div>
                </div>
            </div>

            <!-- Menú móvil fijo encima del contenido -->
            <div
                x-show="menuOpen"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 transform -translate-y-4"
                x-transition:enter-end="opacity-100 transform translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 transform translate-y-0"
                x-transition:leave-end="opacity-0 transform -translate-y-4"
                class="sm:hidden absolute left-0 top-16 w-full z-50 bg-white text-[#1d1d1b] shadow-xl border-t border-gray-200"
                @click.away="menuOpen = false">
                <div class="p-4 space-y-4">
                    <div class="space-y-1">
                        <p class="font-bold">Empleados</p>
                        <a href="{{ route('asistencias.index') }}" class="block px-2 py-1 hover:bg-[#7ebdb3]">Gestionar faltas</a>
                        <a href="{{ route('fichajes.lista') }}" class="block px-2 py-1 hover:bg-[#7ebdb3]">Ver fichajes</a>
                        <a href="{{ route('empleados.create') }}" class="block px-2 py-1 hover:bg-[#7ebdb3]">Registrar empleados</a>
                        <a href="{{ route('empleados.index') }}" class="block px-2 py-1 hover:bg-[#7ebdb3]">Gestionar empleados</a>
                        <a href="{{ route('faltas.graficas.global') }}" class="block px-2 py-1 hover:bg-[#7ebdb3]">Gráficas empleados</a>
                    </div>
                    <div class="space-y-1">
                        <p class="font-bold">Usuarios</p>
                        <a href="{{ route('usuarios.pendientes') }}" class="block px-2 py-1 hover:bg-[#7ebdb3]">Gestionar Roles</a>
                    </div>
                    <div class="space-y-1">
                        <p class="font-bold">Órdenes</p>
                        <a href="{{ route('ordenes.index') }}" class="block px-2 py-1 hover:bg-[#7ebdb3]">Gestionar órdenes</a>
                        <a href="{{ route('ordenes.create') }}" class="block px-2 py-1 hover:bg-[#7ebdb3]">Registrar nueva orden</a>
                        <a href="{{ route('tareas.index') }}" class="block px-2 py-1 hover:bg-[#7ebdb3]">Ver tareas por orden</a>
                    </div>
                </div>
            </div>

    </nav>


    <!-- CONTENIDO -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @yield('content')
    </main>
    @stack('scripts')

</body>

</html>
