<x-app-layout>
    <div class="py-10">
        <div class="max-w-3xl mx-auto px-6 space-y-8">
            <div class="p-8 bg-white rounded-2xl shadow-sm border border-gray-100">
                @include('profile.partials.update-profile-information-form')
            </div>

            <div class="p-8 bg-white rounded-2xl shadow-sm border border-gray-100">
                @include('profile.partials.update-password-form')
            </div>

            <div class="p-8 bg-white rounded-2xl shadow-sm border border-gray-100">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
</x-app-layout>

