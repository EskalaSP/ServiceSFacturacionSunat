<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
    <div class="mb-4">
        <h3 class="text-base font-semibold text-gray-900">{{ $title }}</h3>
        @isset($subtitle)
            <p class="text-sm text-gray-500">{{ $subtitle }}</p>
        @endisset
    </div>
</div>
