<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ward Slideshow</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-black text-white overflow-hidden m-0 p-0 h-screen w-screen font-sans">
    
    @if(empty($allSlides))
        <div class="flex items-center justify-center h-full flex-col">
            <svg class="w-24 h-24 text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <h1 class="text-3xl font-bold text-gray-500">No slideshow content available</h1>
            <p class="text-gray-600 mt-2">Please upload images or PDFs from the admin panel.</p>
        </div>
    @else
        <div x-data="slideshow()" x-init="start()" class="relative h-full w-full bg-black"
             @touchstart="handleTouchStart($event)"
             @touchend="handleTouchEnd($event)">
            
            <template x-for="(slide, index) in slides" :key="index">
                <div x-show="currentIndex === index" 
                     x-transition:enter="transition ease-out duration-1000"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="transition ease-in duration-1000"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     class="absolute inset-0"
                     x-cloak>
                     
                    <!-- Image Slide -->
                    <template x-if="slide.type === 'image'">
                        <div class="w-full h-full overflow-auto" :class="slide.scale250 ? 'block' : 'flex items-center justify-center'">
                            <img :src="slide.url" :style="slide.scale250 ? 'zoom: 2.5; transform-origin: top left;' : ''" :class="slide.scale250 ? '' : 'max-w-full max-h-full object-contain m-auto'" class="drop-shadow-2xl" alt="Slide">
                        </div>
                    </template>
                    
                    <!-- PDF Slide -->
                    <template x-if="slide.type === 'pdf'">
                        <object :data="slide.url + '#view=FitH&toolbar=0&navpanes=0&scrollbar=0'" type="application/pdf" class="w-full h-full bg-white">
                            <p>Unable to display PDF. <a :href="slide.url" target="_blank" class="text-blue-400">Download</a> instead.</p>
                        </object>
                    </template>
                </div>
            </template>
            
            <!-- Left/Right Arrow Controls (show on hover) -->
            <div class="absolute inset-0 flex justify-between items-center px-4 opacity-0 hover:opacity-100 transition-opacity duration-300 pointer-events-none">
                <button @click="prev()" class="p-4 bg-black/50 hover:bg-black/80 rounded-full text-white backdrop-blur-sm transition-colors pointer-events-auto">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </button>
                <button @click="next()" class="p-4 bg-black/50 hover:bg-black/80 rounded-full text-white backdrop-blur-sm transition-colors pointer-events-auto">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
            </div>
            
            <!-- Bottom Control Bar -->
            <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/90 to-transparent pt-10 pb-4 px-6 z-50">
                <div class="flex items-center justify-center gap-3">
                    <!-- Prev Button -->
                    <button @click="prev()" class="px-3 py-2 bg-white/20 hover:bg-white/40 rounded-lg text-white text-sm transition-colors">
                        ◀
                    </button>

                    <!-- Slide Selector Dropdown (native HTML select — most reliable) -->
                    <select x-model.number="selectedIndex" 
                            @change="goTo(selectedIndex)"
                            class="px-4 py-2 bg-gray-800 text-white text-sm rounded-lg border border-white/30 focus:border-white/60 focus:outline-none min-w-[250px] cursor-pointer">
                        @foreach($allSlides as $index => $slide)
                            <option value="{{ $index }}">{{ ($index + 1) }}. {{ $slide['name'] }}</option>
                        @endforeach
                    </select>

                    <!-- Next Button -->
                    <button @click="next()" class="px-3 py-2 bg-white/20 hover:bg-white/40 rounded-lg text-white text-sm transition-colors">
                        ▶
                    </button>

                    <!-- Separator -->
                    <span class="text-white/20 mx-1">|</span>

                    <!-- Slide Counter -->
                    <span class="text-white/70 text-sm" x-text="(currentIndex + 1) + ' / ' + slides.length"></span>

                    <!-- Separator -->
                    <span class="text-white/20 mx-1">|</span>

                    <!-- Play/Pause toggle -->
                    <button @click="togglePlay()" class="px-4 py-2 rounded-lg text-sm font-medium transition-all duration-300"
                            :class="playing ? 'bg-green-600 hover:bg-green-700 text-white' : 'bg-orange-500 hover:bg-orange-600 text-white'">
                        <span x-text="playing ? '⏸ Pause' : '▶ Resume'"></span>
                    </button>
                </div>
            </div>
        </div>

        <script>
            function slideshow() {
                return {
                    currentIndex: 0,
                    selectedIndex: 0,
                    playing: true,
                    intervalId: null,
                    delay: 10000, // 10 seconds per slide
                    touchStartX: 0,
                    touchEndX: 0,
                    slides: @json($allSlides),
                    
                    start() {
                        if (this.slides.length > 1) {
                            this.play();
                        }
                    },
                    
                    play() {
                        this.playing = true;
                        this.intervalId = setInterval(() => {
                            this.currentIndex = (this.currentIndex + 1) % this.slides.length;
                            this.selectedIndex = this.currentIndex;
                        }, this.delay);
                    },
                    
                    pause() {
                        this.playing = false;
                        if (this.intervalId) {
                            clearInterval(this.intervalId);
                            this.intervalId = null;
                        }
                    },
                    
                    togglePlay() {
                        if (this.playing) {
                            this.pause();
                        } else {
                            this.play();
                        }
                    },
                    
                    next() {
                        this.currentIndex = (this.currentIndex + 1) % this.slides.length;
                        this.selectedIndex = this.currentIndex;
                        this.pause();
                    },
                    
                    prev() {
                        this.currentIndex = (this.currentIndex - 1 + this.slides.length) % this.slides.length;
                        this.selectedIndex = this.currentIndex;
                        this.pause();
                    },
                    
                    goTo(index) {
                        this.currentIndex = index;
                        this.selectedIndex = index;
                        this.pause();
                    },
                    
                    handleTouchStart(e) {
                        this.touchStartX = e.changedTouches[0].screenX;
                    },

                    handleTouchEnd(e) {
                        this.touchEndX = e.changedTouches[0].screenX;
                        this.handleSwipe();
                    },

                    handleSwipe() {
                        const threshold = 50;
                        if (this.touchEndX < this.touchStartX - threshold) {
                            this.next();
                        } else if (this.touchEndX > this.touchStartX + threshold) {
                            this.prev();
                        }
                    }
                }
            }
        </script>
    @endif
</body>
</html>
