<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Patient ECG - {{ $patient ? $patient->name : 'ECG Viewer' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            margin: 0;
            padding: 0;
            overflow: hidden;
        }

        .ecg-container {
            background: linear-gradient(135deg, #f8fafc 0%, #f0fdf4 100%);
        }

        .ecg-item.active {
            background: linear-gradient(90deg, #10b981 0%, #10b981 4px, #ecfdf5 4px);
        }

        .pdf-viewer {
            border: none;
            background: #fff;
        }

        /* Fullscreen mode */
        .fullscreen-mode {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 9999;
            background: #1f2937;
        }

        .fullscreen-mode .pdf-container {
            height: calc(100vh - 50px);
        }
    </style>
    @include('components.autofill-guard')
</head>

<body class="bg-gray-100">
    <div id="ecgApp" class="ecg-container h-screen flex flex-col">
        @if(!$patient)
            <!-- No Patient Selected -->
            <div class="flex-1 flex items-center justify-center">
                <div class="bg-white rounded-xl shadow-lg p-10 text-center max-w-md">
                    <div class="bg-gray-100 rounded-full w-20 h-20 mx-auto flex items-center justify-center mb-5">
                        <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-700 mb-2">No Patient Selected</h2>
                    <p class="text-gray-500 text-sm">Please select a patient to view their ECG records.</p>
                </div>
            </div>
        @elseif(empty($ecgFiles))
            <!-- No ECG Records -->
            <div class="flex-1 flex items-center justify-center">
                <div class="bg-white rounded-xl shadow-lg p-10 text-center max-w-md">
                    <div class="bg-amber-50 rounded-full w-20 h-20 mx-auto flex items-center justify-center mb-5">
                        <svg class="w-10 h-10 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-700 mb-2">No ECG Yet</h2>
                    <p class="text-gray-500 text-sm mb-4">No ECG records found for this patient.</p>
                    <div class="bg-gray-50 rounded-lg px-4 py-3 text-left text-sm mb-4">
                        <p class="text-gray-600"><span class="font-medium text-gray-700">Patient:</span>
                            {{ $patient->name }}</p>
                        <p class="text-gray-600"><span class="font-medium text-gray-700">MRN:</span> {{ $patient->mrn }}</p>
                    </div>
                    <button onclick="triggerEcgUpload()" id="emptyUploadBtn"
                        class="inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg transition-colors">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        Upload ECG PDF
                    </button>
                </div>
            </div>
        @else
            <!-- Normal View -->
            <div id="normalView" class="flex-1 flex overflow-hidden">
                <!-- Left: Compact ECG Selection -->
                <div class="w-48 bg-white border-r border-gray-200 flex flex-col">
                    <!-- Header -->
                    <div class="bg-gradient-to-r from-emerald-600 to-teal-600 px-3 py-2.5">
                        <div class="flex items-center text-white">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 12h4l3-9 4 18 3-9h4" />
                            </svg>
                            <span class="text-sm font-semibold">ECG</span>
                            <span class="ml-auto bg-white/20 text-xs px-1.5 py-0.5 rounded">{{ count($ecgFiles) }}</span>
                        </div>
                    </div>

                    <!-- Patient Info -->
                    <div class="px-3 py-2 bg-gray-50 border-b border-gray-200">
                        <p class="text-xs text-gray-500 truncate" title="{{ $patient->name }}">{{ $patient->name }}</p>
                        <p class="text-xs font-semibold text-emerald-700">MRN: {{ $patient->mrn }}</p>
                    </div>

                    <!-- Upload Button -->
                    <div class="px-2 py-2 border-b border-gray-200">
                        <button onclick="triggerEcgUpload()" id="sidebarUploadBtn"
                            class="w-full flex items-center justify-center px-2 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium rounded-lg transition-colors">
                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                            Upload PDF
                        </button>
                    </div>

                    <!-- ECG List -->
                    <div class="flex-1 overflow-y-auto">
                        @foreach($ecgFiles as $index => $ecg)
                            <div onclick="selectEcg({{ $index }}, '{{ $ecg['pdf_file'] ?? '' }}', {{ $ecg['has_pdf'] ? 'true' : 'false' }})"
                                class="ecg-item w-full px-3 py-2 text-left cursor-pointer hover:bg-emerald-50 transition-colors border-b border-gray-100 {{ $index === 0 ? 'active' : '' }}"
                                data-index="{{ $index }}"
                                @if(($ecg['source'] ?? '') === 'manual') title="Manually uploaded" @endif>
                                <div class="flex items-center justify-between">
                                    <div class="min-w-0">
                                        <p class="text-xs font-medium text-gray-800 truncate">
                                            @if($ecg['recorded_at'])
                                                {{ \Carbon\Carbon::parse($ecg['recorded_at'])->format('d M Y') }}
                                            @else
                                                Unknown
                                            @endif
                                        </p>
                                        <p class="text-[10px] text-gray-400">
                                            @if($ecg['recorded_at'])
                                                {{ \Carbon\Carbon::parse($ecg['recorded_at'])->format('H:i') }}
                                            @endif
                                        </p>
                                    </div>
                                    <div class="flex items-center space-x-1 flex-shrink-0">
                                        @if(($ecg['source'] ?? '') === 'manual')
                                            <span
                                                class="bg-blue-100 text-blue-600 text-[10px] px-1.5 py-0.5 rounded font-medium">M</span>
                                        @endif
                                        @if($ecg['has_pdf'])
                                            <span
                                                class="bg-emerald-100 text-emerald-600 text-[10px] px-1.5 py-0.5 rounded font-medium">PDF</span>
                                        @endif
                                        <button
                                            onclick="event.stopPropagation(); deleteEcg('{{ $ecg['xml_file'] ?? '' }}', '{{ $ecg['pdf_file'] ?? '' }}')"
                                            class="p-0.5 text-gray-300 hover:text-red-500 transition-colors" title="Delete ECG">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Right: ECG Viewer -->
                <div class="flex-1 flex flex-col bg-gray-100">
                    <!-- Viewer Header -->
                    <div class="bg-white border-b border-gray-200 px-4 py-2 flex items-center justify-between">
                        <div class="flex items-center text-gray-700">
                            <svg class="w-5 h-5 mr-2 text-emerald-600" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <span class="font-medium text-sm">ECG Viewer <span class="text-gray-400 mx-2">|</span>
                                {{ $patient->name }} <span class="text-gray-400 mx-2">•</span> <span
                                    class="text-xs text-gray-500">IC: {{ $patient->ic_passport ?? 'N/A' }}</span> <span
                                    class="text-gray-400 text-xs mx-1">•</span> <span class="text-xs text-gray-500">MRN:
                                    {{ $patient->mrn }}</span></span>
                            <span id="currentEcgDate" class="ml-2 text-xs text-gray-400">
                                @if($latestEcg && $latestEcg['recorded_at'])
                                    {{ \Carbon\Carbon::parse($latestEcg['recorded_at'])->format('d M Y H:i') }}
                                @endif
                            </span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button id="exportBtn" onclick="exportEcgPdf()"
                                class="flex items-center px-3 py-1.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-xs font-medium rounded-lg transition-colors {{ $latestEcg && $latestEcg['has_pdf'] ? '' : 'hidden' }}">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                Export PDF
                            </button>
                            <button id="enlargeBtn" onclick="toggleFullscreen()"
                                class="flex items-center px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium rounded-lg transition-colors {{ $latestEcg && $latestEcg['has_pdf'] ? '' : 'hidden' }}">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                                </svg>
                                Enlarge
                            </button>
                        </div>
                    </div>

                    <!-- PDF Container -->
                    <div id="ecgViewer" class="flex-1 bg-gray-50">
                        @if($latestEcg && $latestEcg['has_pdf'])
                            <iframe id="pdfFrame" src="{{ route('ecg.pdf.public', ['file' => $latestEcg['pdf_file']]) }}"
                                class="w-full h-full pdf-viewer" title="ECG PDF Viewer"></iframe>
                        @else
                            <div id="noPdfMessage" class="flex items-center justify-center h-full">
                                <div class="text-center p-6">
                                    <div
                                        class="bg-amber-50 rounded-full w-14 h-14 mx-auto flex items-center justify-center mb-3">
                                        <svg class="w-7 h-7 text-amber-400" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                    </div>
                                    <h3 class="text-sm font-semibold text-gray-700 mb-1">PDF Not Available</h3>
                                    <p class="text-gray-400 text-xs">PDF has not been extracted yet.</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Fullscreen View (hidden by default) -->
            <div id="fullscreenView" class="hidden fullscreen-mode">
                <!-- Fullscreen Header -->
                <div class="bg-gray-800 px-4 py-2.5 flex items-center justify-between">
                    <div class="flex items-center text-white">
                        <svg class="w-5 h-5 mr-2 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 12h4l3-9 4 18 3-9h4" />
                        </svg>
                        <span class="font-medium">ECG - {{ $patient->name }}</span>
                        <span class="ml-3 text-gray-400 text-sm">IC: {{ $patient->ic_passport ?? 'N/A' }}</span>
                        <span class="ml-3 text-gray-400 text-sm">MRN: {{ $patient->mrn }}</span>
                        <span id="fullscreenEcgDate" class="ml-3 text-emerald-400 text-sm"></span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <button onclick="exportEcgPdf()"
                            class="flex items-center px-4 py-1.5 bg-gray-700 hover:bg-gray-600 text-white text-sm font-medium rounded-lg transition-colors">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Export PDF
                        </button>
                        <button onclick="toggleFullscreen()"
                            class="flex items-center px-4 py-1.5 bg-gray-700 hover:bg-gray-600 text-white text-sm font-medium rounded-lg transition-colors">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            Back
                        </button>
                    </div>
                </div>

                <!-- Fullscreen PDF Container -->
                <div id="fullscreenPdfContainer" class="pdf-container bg-gray-900">
                    <!-- PDF will be cloned here -->
                </div>
            </div>
        @endif

        @if($patient)
            <!-- Hidden file input for manual ECG PDF upload -->
            <input type="file" id="ecgFileInput" accept="application/pdf,.pdf" class="hidden"
                onchange="handleEcgFile(this)">
        @endif
    </div>

    <script>
        let currentPdfUrl = '{{ $latestEcg && $latestEcg["has_pdf"] ? route("ecg.pdf.public", ["file" => $latestEcg["pdf_file"]]) : "" }}';
        let currentEcgDate = '{{ $latestEcg && $latestEcg["recorded_at"] ? \Carbon\Carbon::parse($latestEcg["recorded_at"])->format("d M Y H:i") : "" }}';
        let isFullscreen = false;

        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const ecgPatientId = {{ $patient ? $patient->id : 'null' }};
        let ecgUploading = false;

        function triggerEcgUpload() {
            if (ecgUploading) return;
            document.getElementById('ecgFileInput').click();
        }

        function handleEcgFile(input) {
            const file = input.files[0];
            if (!file || !ecgPatientId) return;

            if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
                alert('Please select a PDF file.');
                input.value = '';
                return;
            }

            ecgUploading = true;
            setUploadButtonsState('Uploading...', true);

            const formData = new FormData();
            formData.append('patient_id', ecgPatientId);
            formData.append('pdf', file);

            fetch('{{ route('ecg.upload') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: formData,
            })
                .then(async (response) => {
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Upload failed');
                    }
                    location.reload();
                })
                .catch((error) => {
                    alert('Upload failed: ' + error.message);
                    ecgUploading = false;
                    setUploadButtonsState('Upload PDF', false);
                    input.value = '';
                });
        }

        function setUploadButtonsState(text, disabled) {
            ['sidebarUploadBtn', 'emptyUploadBtn'].forEach((id) => {
                const btn = document.getElementById(id);
                if (btn) {
                    btn.disabled = disabled;
                    btn.classList.toggle('opacity-60', disabled);
                }
            });
        }

        function deleteEcg(xmlFile, pdfFile) {
            if (!confirm('Delete this ECG record? This cannot be undone.')) return;

            const formData = new FormData();
            if (xmlFile) formData.append('xml_file', xmlFile);
            if (pdfFile) formData.append('pdf_file', pdfFile);

            fetch('{{ route('ecg.delete') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: formData,
            })
                .then(async (response) => {
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Delete failed');
                    }
                    location.reload();
                })
                .catch((error) => {
                    alert('Delete failed: ' + error.message);
                });
        }

        function selectEcg(index, pdfFile, hasPdf) {
            // Update active state on list items
            document.querySelectorAll('.ecg-item').forEach((item, i) => {
                if (i === index) {
                    item.classList.add('active');
                } else {
                    item.classList.remove('active');
                }
            });

            const viewer = document.getElementById('ecgViewer');
            const enlargeBtn = document.getElementById('enlargeBtn');
            const exportBtn = document.getElementById('exportBtn');
            const dateSpan = document.getElementById('currentEcgDate');

            // Get the date from the clicked item
            const clickedItem = document.querySelector(`.ecg-item[data-index="${index}"]`);
            const dateText = clickedItem ? clickedItem.querySelector('.text-xs.font-medium').textContent.trim() : '';
            const timeText = clickedItem ? clickedItem.querySelector('.text-\\[10px\\]').textContent.trim() : '';
            currentEcgDate = dateText + ' ' + timeText;
            dateSpan.textContent = currentEcgDate;

            if (hasPdf && pdfFile) {
                currentPdfUrl = '{{ route("ecg.pdf.public") }}?file=' + encodeURIComponent(pdfFile);
                viewer.innerHTML = `<iframe id="pdfFrame" src="${currentPdfUrl}" class="w-full h-full pdf-viewer" title="ECG PDF Viewer"></iframe>`;
                enlargeBtn.classList.remove('hidden');
                if (exportBtn) exportBtn.classList.remove('hidden');
            } else {
                currentPdfUrl = '';
                viewer.innerHTML = `
                    <div class="flex items-center justify-center h-full">
                        <div class="text-center p-6">
                            <div class="bg-amber-50 rounded-full w-14 h-14 mx-auto flex items-center justify-center mb-3">
                                <svg class="w-7 h-7 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                            </div>
                            <h3 class="text-sm font-semibold text-gray-700 mb-1">PDF Not Available</h3>
                            <p class="text-gray-400 text-xs">PDF has not been extracted yet.</p>
                        </div>
                    </div>
                `;
                enlargeBtn.classList.add('hidden');
                if (exportBtn) exportBtn.classList.add('hidden');
            }
        }

        function exportEcgPdf() {
            if (!currentPdfUrl) return;

            // Friendly download name: ECG_{MRN}_{date}.pdf
            const namePart = ('ECG_{{ $patient->mrn ?? '' }}_' + (currentEcgDate || ''))
                .trim()
                .replace(/[^\w\-]+/g, '_')
                .replace(/_+$/, '');
            const sep = currentPdfUrl.includes('?') ? '&' : '?';

            window.location.href = currentPdfUrl + sep + 'download=1&dl_name=' + encodeURIComponent(namePart + '.pdf');
        }

        function toggleFullscreen() {
            const normalView = document.getElementById('normalView');
            const fullscreenView = document.getElementById('fullscreenView');
            const fullscreenContainer = document.getElementById('fullscreenPdfContainer');
            const fullscreenDate = document.getElementById('fullscreenEcgDate');

            isFullscreen = !isFullscreen;

            if (isFullscreen && currentPdfUrl) {
                // Show fullscreen
                normalView.classList.add('hidden');
                fullscreenView.classList.remove('hidden');
                fullscreenDate.textContent = currentEcgDate;
                fullscreenContainer.innerHTML = `<iframe src="${currentPdfUrl}" class="w-full h-full" style="border: none;"></iframe>`;
            } else {
                // Back to normal
                normalView.classList.remove('hidden');
                fullscreenView.classList.add('hidden');
                fullscreenContainer.innerHTML = '';
            }
        }

        // Handle escape key to exit fullscreen
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && isFullscreen) {
                toggleFullscreen();
            }
        });
    </script>
</body>

</html>