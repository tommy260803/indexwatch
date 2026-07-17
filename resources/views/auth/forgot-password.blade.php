<x-guest-layout>
    <div class="mb-6 text-sm text-gray-600">
        ¿Olvidaste tu contraseña? No hay problema. Solo tienes que indicarnos tu correo electrónico y te enviaremos un enlace para restablecerla que te permitirá elegir una nueva.
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-6 text-center text-green-600" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <!-- Email Address -->
        <div class="mb-5">
            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Correo electrónico</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path>
                    </svg>
                </div>
                <input id="email" type="email" name="email" :value="old('email')" required autofocus
                    class="block w-full pl-12 pr-4 py-4 bg-gray-50 border-2 border-gray-200 rounded-2xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 text-gray-800"
                    placeholder="tu@ejemplo.com" />
            </div>
            <x-input-error :messages="$errors->get('email')" class="mt-2 text-red-500 text-sm" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-medium py-4 px-6 rounded-2xl transition-all duration-300 transform hover:scale-[1.02] md3-elevation-3">
                Enviar enlace de restablecimiento
            </button>
        </div>
    </form>
</x-guest-layout>

