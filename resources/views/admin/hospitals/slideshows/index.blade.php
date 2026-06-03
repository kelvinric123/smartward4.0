<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <a href="{{ route('hospitals.index') }}" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>
                <div>
                    <h2 class="font-bold text-2xl text-gray-800 leading-tight">
                        {{ __('Manage Slideshow') }} - {{ $hospital->name }}
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">Upload and manage images and PDFs for the ward dashboard slideshow</p>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-6 bg-gradient-to-r from-green-50 to-emerald-50 border-l-4 border-green-500 text-green-800 px-6 py-4 rounded-lg shadow-md" role="alert">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="font-medium">{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-6 bg-gradient-to-r from-red-50 to-pink-50 border-l-4 border-red-500 text-red-800 px-6 py-4 rounded-lg shadow-md" role="alert">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-blue-100 mb-8">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Upload New File</h3>
                    <form action="{{ route('hospitals.slideshows.store', $hospital) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="file" class="block text-sm font-medium text-gray-700">File (Image/PDF, max 50MB)</label>
                                <input type="file" name="file" id="file" accept=".jpg,.jpeg,.png,.pdf" required class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                            </div>
                            <div>
                                <label for="ward_id" class="block text-sm font-medium text-gray-700">Target Ward</label>
                                <select name="ward_id" id="ward_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">All Wards (Hospital-wide)</option>
                                    @foreach($hospital->wards as $ward)
                                        <option value="{{ $ward->id }}">{{ $ward->ward_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex items-end">
                                <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700 border border-transparent rounded-lg font-semibold text-white shadow-md transition-all">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                    </svg>
                                    Upload
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-blue-100">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Current Slideshow Files</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                        @forelse ($hospital->slideshows as $slide)
                            <div class="border rounded-xl overflow-hidden shadow-sm hover:shadow-md transition-shadow bg-white flex flex-col">
                                <div class="h-40 bg-gray-100 flex items-center justify-center overflow-hidden relative group">
                                    @if(str_contains($slide->file_type, 'pdf'))
                                        <svg class="w-16 h-16 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                        </svg>
                                        <span class="absolute bottom-2 right-2 text-xs font-bold text-gray-500 bg-white/80 px-2 py-1 rounded">PDF</span>
                                    @else
                                        <img src="{{ Storage::url($slide->file_path) }}" alt="Slide" class="object-cover w-full h-full">
                                    @endif
                                </div>
                                <div class="p-4 flex-1 flex flex-col justify-between border-t border-gray-100">
                                    <div>
                                        <p class="text-xs font-medium text-gray-500 mb-1">Target</p>
                                        <p class="font-semibold text-sm text-gray-800 mb-3">
                                            @if($slide->ward_id)
                                                <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-md text-xs">{{ $slide->ward->ward_name }}</span>
                                            @else
                                                <span class="px-2 py-1 bg-purple-100 text-purple-800 rounded-md text-xs">All Wards</span>
                                            @endif
                                        </p>
                                    </div>
                                    <div class="mt-auto">
                                        <form action="{{ route('hospitals.slideshows.destroy', [$hospital, $slide]) }}" method="POST" >
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" onclick="confirmDelete(event, 'Are you sure you want to delete this item?')" class="w-full inline-flex justify-center items-center px-3 py-1.5 bg-red-50 text-red-600 hover:bg-red-100 rounded-lg text-sm font-medium transition-colors border border-red-200">
                                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-span-full py-12 text-center">
                                <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <p class="text-gray-500 font-medium">No slideshow files uploaded yet</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
