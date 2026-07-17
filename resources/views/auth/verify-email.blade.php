<x-guest-layout>
    <div class="mb-6 text-sm text-gray-600">
        ¡Gracias por registrarte! Antes de comenzar, ¿podrías verificar tu dirección de correo electrónico haciendo clic en el enlace que te acabamos de enviar? Si no recibiste el correo, con gusto te enviaremos otro.
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-6 p-4 rounded-2xl bg-green-50 text-green-800 text-sm text-center">
            Se ha enviado un nuevo enlace de verificación a la dirección de correo electrónico que proporcionaste durante el registro.
        </div>
    @endif

    <div class="mt-4 flex items-center flex-col gap-4">
        <form method="POST" action="{{ route('verification.send') }}" class="w-full">
            @csrf
            <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-medium py-4 px-6 rounded-2xl transition-all duration-300 transform hover:scale-[1.02] md3-elevation-3">
                Reenviar correo de verificación
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="w-full">
            @csrf
            <button type="submit" class="w-full text-center text-sm text-gray-600 hover:text-gray-800 font-medium transition-colors">
                Cerrar sesión
            </button>
        </form>
    </div>
</x-guest-layout>

