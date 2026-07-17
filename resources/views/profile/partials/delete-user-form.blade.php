<section class="space-y-6">
    <header class="mb-8">
        <h2 class="text-xl font-semibold text-gray-900">
            Eliminar cuenta
        </h2>
        <p class="mt-2 text-sm text-gray-600">
            Una vez que eliminas tu cuenta, todos sus recursos y datos se borrarán de forma permanente. Antes de eliminar tu cuenta, descarga cualquier dato o información que desees conservar.
        </p>
    </header>

    <button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
        class="bg-gradient-to-r from-red-600 to-pink-600 hover:from-red-700 hover:to-pink-700 text-white font-medium py-4 px-6 rounded-2xl transition-all duration-300 transform hover:scale-[1.02] md3-elevation-3"
    >
        Eliminar cuenta
    </button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <h2 class="text-lg font-semibold text-gray-900">
                ¿Estás seguro de que quieres eliminar tu cuenta?
            </h2>

            <p class="mt-2 text-sm text-gray-600">
                Una vez que eliminas tu cuenta, todos sus recursos y datos se borrarán de forma permanente. Por favor, ingresa tu contraseña para confirmar que quieres eliminar tu cuenta de forma permanente.
            </p>

            <div class="mt-6">
                <label for="password" value="Contraseña" class="sr-only" />

                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                    </div>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        class="block w-full pl-12 pr-4 py-4 bg-gray-50 border-2 border-gray-200 rounded-2xl focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-all duration-200 text-gray-800"
                        placeholder="••••••••"
                    />
                </div>

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2 text-red-500 text-sm" />
            </div>

            <div class="mt-8 flex justify-end gap-4">
                <button type="button" x-on:click="$dispatch('close')" class="text-sm text-gray-600 hover:text-gray-800 font-medium transition-colors">
                    Cancelar
                </button>

                <button type="submit" class="bg-gradient-to-r from-red-600 to-pink-600 hover:from-red-700 hover:to-pink-700 text-white font-medium py-3 px-6 rounded-2xl transition-all duration-300">
                    Eliminar cuenta
                </button>
            </div>
        </form>
    </x-modal>
</section>

