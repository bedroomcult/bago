<?php
// Set error reporting to catch more issues during development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define the directory where shared files are stored
$shares_directory = 'shares';
$thumbnails_directory = 'shares/thumbnails';
$gd_loaded = extension_loaded('gd');
$warning_message = '';

if (!$gd_loaded) {
    $warning_message = 'PHP GD extension is not enabled. Thumbnail generation is disabled.';
}

// Check for write permissions and create the thumbnails directory if needed
if ($gd_loaded) {
    if (!is_dir($thumbnails_directory)) {
        if (!mkdir($thumbnails_directory, 0777, true)) {
            $warning_message = 'Failed to create the thumbnails directory. Please check permissions for "' . $shares_directory . '".';
            $gd_loaded = false;
        }
    }
}

// Scan the directory and get a list of all files and folders
$files_and_folders = is_dir($shares_directory) ? scandir($shares_directory) : [];

// Filter the list to only include image files (case-insensitive)
$image_files = array_values(array_filter($files_and_folders, function($file) use ($shares_directory) {
    if ($file === '.' || $file === '..') return false;
    $path = "$shares_directory/$file";
    if (is_dir($path)) return false;
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg', 'jpeg', 'png', 'gif']);
}));

// Generate thumbnails if not exist
if ($gd_loaded) {
    foreach ($image_files as $file) {
        $source_path = $shares_directory . '/' . $file;
        $thumb_path = $thumbnails_directory . '/' . $file;

        if (!file_exists($thumb_path) && is_readable($source_path)) {
            $image_info = @getimagesize($source_path);
            if ($image_info) {
                list($original_width, $original_height, $image_type) = $image_info;
                $thumb_width = 300;
                $thumb_height = max(1, intval($original_height / ($original_width / $thumb_width)));

                $new_image = imagecreatetruecolor($thumb_width, $thumb_height);

                $source_image = null;
                switch ($image_type) {
                    case IMAGETYPE_JPEG:
                        $source_image = @imagecreatefromjpeg($source_path);
                        break;
                    case IMAGETYPE_PNG:
                        $source_image = @imagecreatefrompng($source_path);
                        imagealphablending($new_image, false);
                        imagesavealpha($new_image, true);
                        break;
                    case IMAGETYPE_GIF:
                        $source_image = @imagecreatefromgif($source_path);
                        imagealphablending($new_image, false);
                        imagesavealpha($new_image, true);
                        break;
                }

                if ($source_image) {
                    imagecopyresampled($new_image, $source_image, 0, 0, 0, 0, $thumb_width, $thumb_height, $original_width, $original_height);

                    switch ($image_type) {
                        case IMAGETYPE_JPEG:
                            imagejpeg($new_image, $thumb_path, 80);
                            break;
                        case IMAGETYPE_PNG:
                            imagepng($new_image, $thumb_path);
                            break;
                        case IMAGETYPE_GIF:
                            imagegif($new_image, $thumb_path);
                            break;
                    }
                }

                if ($source_image) imagedestroy($source_image);
                imagedestroy($new_image);
            }
        }
    }
}

$json_image_files = json_encode($image_files);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <title>Local File Server</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* iOS safe area paddings for full-screen modal */
        .safe-top { 
            padding-top: max(1rem, env(safe-area-inset-top)); 
        }
        .safe-bottom { 
            padding-bottom: max(1rem, env(safe-area-inset-bottom)); 
        }
        
        /* Prevent scroll bounce on iOS */
        body {
            -webkit-overflow-scrolling: touch;
            overscroll-behavior: none;
        }
        
        /* Modal specific styles */
        .modal-open {
            overflow: hidden;
            position: fixed;
            width: 100%;
        }
        
        /* Dynamic image scaling */
        .preview-image {
            max-width: calc(100vw - 2rem);
            max-height: calc(100vh - 8rem);
            width: auto;
            height: auto;
            object-fit: contain;
        }
        
        @media (min-width: 640px) {
            .preview-image {
                max-width: calc(90vw - 2rem);
                max-height: calc(90vh - 8rem);
            }
        }
        
        @media (min-width: 1024px) {
            .preview-image {
                max-width: calc(80vw - 2rem);
                max-height: calc(80vh - 8rem);
            }
        }
        
        /* Touch-friendly buttons */
        .touch-btn {
            min-height: 44px;
            min-width: 44px;
        }
        
        /* Smooth transitions */
        .modal-transition {
            transition: opacity 0.3s ease-in-out, transform 0.3s ease-in-out;
        }
        
        /* Loading state */
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }
        
        /* Swipe indicators */
        .swipe-indicator {
            opacity: 0.6;
            transition: opacity 0.2s ease;
        }
        
        /* Grid responsive improvements */
        @media (max-width: 640px) {
            .grid-mobile {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
            }
        }
        
        /* Better hover states for touch devices */
        @media (hover: hover) and (pointer: fine) {
            .hover-effect:hover {
                transform: scale(1.02);
                transition: transform 0.2s ease;
            }
        }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen p-4 sm:p-8">
    <div class="max-w-6xl mx-auto">
        <h1 class="text-3xl sm:text-4xl font-extrabold text-center mb-6 sm:mb-8">
            <span class="text-transparent bg-clip-text bg-gradient-to-r from-teal-400 to-cyan-500">
                Shared Files
            </span>
            <span class="text-2xl ml-2">📁</span>
        </h1>

        <div class="flex justify-end mb-4 gap-2">
            <button id="list-view-btn" class="touch-btn p-2 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition-colors duration-200" aria-label="List view">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 sm:h-7 sm:w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                </svg>
            </button>
            <button id="grid-view-btn" class="touch-btn p-2 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition-colors duration-200" aria-label="Grid view">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 sm:h-7 sm:w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                </svg>
            </button>
        </div>

        <?php if (!empty($warning_message)) : ?>
            <div class="bg-red-500 text-white p-3 sm:p-4 rounded-lg mb-4 text-center">
                <?php echo htmlspecialchars($warning_message, ENT_QUOTES); ?>
            </div>
        <?php endif; ?>

        <!-- Containers -->
        <div id="file-list" class="space-y-3 sm:space-y-4"></div>
        <div id="image-container" class="grid grid-mobile sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 sm:gap-6 hidden"></div>
        <p id="no-files-message" class="text-center text-slate-400 mt-10 hidden">No image files found in the 'shares' directory.</p>
    </div>

    <!-- Preview Modal -->
    <div id="preview-modal"
         class="fixed inset-0 z-50 hidden modal-transition"
         role="dialog"
         aria-modal="true"
         aria-labelledby="preview-title">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black bg-opacity-80 backdrop-blur-sm"></div>

        <!-- Modal Panel - Fully responsive -->
        <div class="relative h-full w-full flex flex-col">
            <!-- Header - Fixed at top -->
            <div class="safe-top flex items-center justify-between gap-2 px-4 py-3 bg-slate-900 bg-opacity-95 backdrop-blur border-b border-slate-800 z-10">
                <h2 id="preview-title" class="text-sm sm:text-base lg:text-lg font-semibold truncate text-slate-100 flex-1 mr-2"></h2>
                <div class="flex items-center gap-2">
                    <span id="image-counter" class="text-xs sm:text-sm text-slate-400 hidden sm:block"></span>
                    <button id="close-modal" class="touch-btn p-2 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition-colors" aria-label="Close">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 sm:h-6 sm:w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Image Area - Flexible center area -->
            <div id="swipe-zone" class="flex-1 flex items-center justify-center p-4 relative overflow-hidden">
                <!-- Loading indicator -->
                <div id="loading-indicator" class="absolute inset-0 flex items-center justify-center bg-slate-800 bg-opacity-50 hidden">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-teal-400"></div>
                </div>
                
                <!-- Navigation arrows for desktop -->
                <button id="prev-arrow" class="hidden lg:flex absolute left-4 top-1/2 transform -translate-y-1/2 touch-btn p-3 rounded-full bg-black bg-opacity-50 text-white hover:bg-opacity-70 transition-all z-10">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <button id="next-arrow" class="hidden lg:flex absolute right-4 top-1/2 transform -translate-y-1/2 touch-btn p-3 rounded-full bg-black bg-opacity-50 text-white hover:bg-opacity-70 transition-all z-10">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>

                <img id="preview-image"
                     src=""
                     alt="Preview"
                     class="preview-image select-none transition-opacity duration-300"
                     draggable="false" />
                     
                <!-- Swipe indicators for mobile -->
                <div class="lg:hidden absolute bottom-4 left-1/2 transform -translate-x-1/2 flex gap-2 text-xs text-slate-400 swipe-indicator">
                    <span>← Swipe →</span>
                </div>
            </div>

            <!-- Footer - Fixed at bottom -->
            <div class="safe-bottom bg-slate-900 bg-opacity-95 backdrop-blur border-t border-slate-800 z-10">
                <!-- Mobile controls -->
                <div class="flex lg:hidden justify-center gap-2 p-3">
                    <button id="prev-btn-mobile" class="flex-1 max-w-24 touch-btn py-3 rounded-xl bg-slate-700 text-slate-100 hover:bg-slate-600 active:scale-95 transition text-sm">
                        ← Prev
                    </button>
                    <button id="next-btn-mobile" class="flex-1 max-w-24 touch-btn py-3 rounded-xl bg-slate-700 text-slate-100 hover:bg-slate-600 active:scale-95 transition text-sm">
                        Next →
                    </button>
                </div>
                
                <!-- Desktop controls -->
                <div class="hidden lg:flex items-center justify-between gap-3 px-4 py-3">
                    <div class="flex justify-start gap-2">
                        <button id="prev-btn" class="touch-btn px-4 py-2 rounded-xl bg-slate-700 text-slate-100 hover:bg-slate-600 active:scale-95 transition">
                            ← Previous
                        </button>
                        <button id="next-btn" class="touch-btn px-4 py-2 rounded-xl bg-slate-700 text-slate-100 hover:bg-slate-600 active:scale-95 transition">
                            Next →
                        </button>
                    </div>
                    <div class="flex justify-end gap-2">
                        <a id="download-btn" href="#" download class="touch-btn px-4 py-2 rounded-xl bg-teal-500 text-white hover:bg-teal-600 active:scale-95 transition text-center">
                            Download
                        </a>
                        <button id="close-btn" class="touch-btn px-4 py-2 rounded-xl bg-slate-700 text-slate-100 hover:bg-slate-600 active:scale-95 transition">
                            Close
                        </button>
                    </div>
                </div>
                
                <!-- Mobile download button -->
                <div class="flex lg:hidden justify-center pb-3">
                    <a id="download-btn-mobile" href="#" download class="touch-btn px-6 py-3 rounded-xl bg-teal-500 text-white hover:bg-teal-600 active:scale-95 transition text-center text-sm font-medium">
                        Download Image
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Pass PHP data to JS
        const imageFiles = <?php echo $json_image_files ?: '[]'; ?>;
        const container = document.getElementById('image-container');
        const fileList = document.getElementById('file-list');
        const noFilesMessage = document.getElementById('no-files-message');
        const listViewBtn = document.getElementById('list-view-btn');
        const gridViewBtn = document.getElementById('grid-view-btn');
        const sharesDirectory = 'shares';
        const thumbnailsDirectory = 'shares/thumbnails';
        const gdLoaded = <?php echo json_encode($gd_loaded); ?>;

        // Modal elements
        const modal = document.getElementById('preview-modal');
        const previewTitle = document.getElementById('preview-title');
        const previewImage = document.getElementById('preview-image');
        const downloadBtn = document.getElementById('download-btn');
        const downloadBtnMobile = document.getElementById('download-btn-mobile');
        const swipeZone = document.getElementById('swipe-zone');
        const loadingIndicator = document.getElementById('loading-indicator');
        const imageCounter = document.getElementById('image-counter');

        let currentIndex = -1;
        let isLoading = false;

        // Utility function to update image counter
        const updateCounter = () => {
            if (imageFiles.length > 0) {
                imageCounter.textContent = `${currentIndex + 1} / ${imageFiles.length}`;
            }
        };

        // Enhanced preloading with loading states
        const preloadAround = (index) => {
            const preload = (i) => {
                if (i >= 0 && i < imageFiles.length) {
                    const img = new Image();
                    img.src = `${sharesDirectory}/${encodeURIComponent(imageFiles[i])}`;
                }
            };
            preload(index + 1);
            preload(index - 1);
        };

        // Show loading state
        const showLoading = () => {
            isLoading = true;
            loadingIndicator.classList.remove('hidden');
            previewImage.classList.add('loading');
        };

        // Hide loading state
        const hideLoading = () => {
            isLoading = false;
            loadingIndicator.classList.add('hidden');
            previewImage.classList.remove('loading');
        };

        const openPreview = (index) => {
            const file = imageFiles[index];
            if (!file || isLoading) return;
            
            showLoading();
            
            const filePath = `${sharesDirectory}/${encodeURIComponent(file)}`;
            previewTitle.textContent = file;
            currentIndex = index;
            
            // Update counter
            updateCounter();
            
            // Set download links
            downloadBtn.href = filePath;
            downloadBtn.download = file;
            downloadBtnMobile.href = filePath;
            downloadBtnMobile.download = file;

            // Lock background scroll and add modal class
            document.body.classList.add('modal-open');
            modal.classList.remove('hidden');

            // Load image with proper error handling
            const img = new Image();
            img.onload = () => {
                previewImage.src = filePath;
                hideLoading();
                preloadAround(index);
            };
            img.onerror = () => {
                previewImage.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjMjkzMDQxIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNiIgZmlsbD0iI2M4ZDBlNyIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPkltYWdlIEVycm9yPC90ZXh0Pjwvc3ZnPg==';
                hideLoading();
            };
            img.src = filePath;

            // Focus management for accessibility
            setTimeout(() => {
                document.getElementById('close-modal').focus({preventScroll: true});
            }, 100);
        };

        const closeModal = () => {
            modal.classList.add('hidden');
            document.body.classList.remove('modal-open');
            currentIndex = -1;
            previewImage.src = '';
            hideLoading();
        };

        const showPrev = () => {
            if (currentIndex > 0 && !isLoading) {
                openPreview(currentIndex - 1);
            }
        };

        const showNext = () => {
            if (currentIndex < imageFiles.length - 1 && !isLoading) {
                openPreview(currentIndex + 1);
            }
        };

        // Event listeners for all navigation buttons
        document.getElementById('close-modal').addEventListener('click', closeModal);
        document.getElementById('close-btn').addEventListener('click', closeModal);
        document.getElementById('prev-btn').addEventListener('click', showPrev);
        document.getElementById('next-btn').addEventListener('click', showNext);
        document.getElementById('prev-btn-mobile').addEventListener('click', showPrev);
        document.getElementById('next-btn-mobile').addEventListener('click', showNext);
        document.getElementById('prev-arrow').addEventListener('click', showPrev);
        document.getElementById('next-arrow').addEventListener('click', showNext);

        // Click on backdrop closes modal
        modal.addEventListener('click', (e) => {
            if (e.target === modal || e.target.classList.contains('bg-black')) {
                closeModal();
            }
        }, true);

        // Enhanced keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (modal.classList.contains('hidden')) return;
            
            switch(e.key) {
                case 'Escape':
                    e.preventDefault();
                    closeModal();
                    break;
                case 'ArrowLeft':
                    e.preventDefault();
                    showPrev();
                    break;
                case 'ArrowRight':
                    e.preventDefault();
                    showNext();
                    break;
                case ' ':
                    e.preventDefault();
                    showNext();
                    break;
            }
        });

        // Enhanced touch handling with better gesture recognition
        let touchStartX = 0, touchStartY = 0, touchEndX = 0, touchEndY = 0;
        let touchStartTime = 0;
        const swipeThreshold = 50;
        const restraint = 100;
        const timeThreshold = 500;

        swipeZone.addEventListener('touchstart', (e) => {
            if (isLoading) return;
            const t = e.changedTouches[0];
            touchStartX = t.pageX;
            touchStartY = t.pageY;
            touchStartTime = Date.now();
            
            // Hide swipe indicator on first touch
            const indicator = document.querySelector('.swipe-indicator');
            if (indicator) {
                indicator.style.opacity = '0';
            }
        }, {passive: true});

        swipeZone.addEventListener('touchend', (e) => {
            if (isLoading) return;
            const t = e.changedTouches[0];
            touchEndX = t.pageX;
            touchEndY = t.pageY;
            
            const dx = touchEndX - touchStartX;
            const dy = touchEndY - touchStartY;
            const dt = Date.now() - touchStartTime;

            // Check if it's a valid swipe gesture
            if (Math.abs(dx) >= swipeThreshold && Math.abs(dy) <= restraint && dt <= timeThreshold) {
                if (dx > 0) {
                    showPrev();
                } else {
                    showNext();
                }
            }
        }, {passive: true});

        // Prevent zoom on double tap in modal
        let lastTouchTime = 0;
        swipeZone.addEventListener('touchend', (e) => {
            const currentTime = new Date().getTime();
            const tapLength = currentTime - lastTouchTime;
            if (tapLength < 500 && tapLength > 0) {
                e.preventDefault();
            }
            lastTouchTime = currentTime;
        });

        // Render list view with enhanced mobile support
        const renderListView = () => {
            fileList.innerHTML = '';
            imageFiles.forEach((file, index) => {
                const filePath = `${sharesDirectory}/${encodeURIComponent(file)}`;
                const listItemHtml = `
                    <div class="flex items-center gap-3 justify-between p-3 sm:p-4 bg-slate-800 rounded-xl shadow-lg hover-effect">
                        <button onclick="openPreview(${index})"
                                class="text-sm sm:text-base font-medium truncate text-left flex-1 hover:text-teal-400 transition touch-btn">
                            ${file}
                        </button>
                        <a href="${filePath}" download="${file}"
                           class="shrink-0 touch-btn p-2 rounded-lg bg-teal-500 text-white hover:bg-teal-600 active:scale-95 transition flex items-center justify-center"
                           aria-label="Download ${file}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 sm:h-5 sm:w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                        </a>
                    </div>
                `;
                fileList.insertAdjacentHTML('beforeend', listItemHtml);
            });
            fileList.classList.remove('hidden');
            container.classList.add('hidden');
        };

        // Render grid view with enhanced mobile support
        const renderGridView = () => {
            container.innerHTML = '';
            imageFiles.forEach((file, index) => {
                const filePath = `${sharesDirectory}/${encodeURIComponent(file)}`;
                const thumbnailPath = gdLoaded ? `${thumbnailsDirectory}/${encodeURIComponent(file)}` : filePath;
                const thumbnailHtml = `
                    <div class="bg-slate-800 rounded-xl shadow-lg overflow-hidden hover-effect">
                        <button onclick="openPreview(${index})" class="block w-full touch-btn">
                            <img
                                src="${thumbnailPath}"
                                alt="${file} thumbnail"
                                class="w-full h-32 sm:h-40 md:h-48 object-cover"
                                loading="lazy"
                                onerror="this.onerror=null;this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjI0MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjMjkzMDQxIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZmlsbD0iI2M4ZDBlNyIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPkltYWdlIEVycm9yPC90ZXh0Pjwvc3ZnPg==';" />
                        </button>
                        <div class="p-2 sm:p-3 md:p-4 flex justify-between items-center gap-2">
                            <span class="text-xs sm:text-sm font-medium truncate">${file}</span>
                            <a href="${filePath}" download="${file}"
                               class="touch-btn p-2 rounded-lg bg-teal-500 text-white hover:bg-teal-600 active:scale-95 transition flex items-center justify-center"
                               aria-label="Download ${file}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 sm:h-5 sm:w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                            </a>
                        </div>
                    </div>
                `;
                container.insertAdjacentHTML('beforeend', thumbnailHtml);
            });
            container.classList.remove('hidden');
            fileList.classList.add('hidden');
        };

        // Initial rendering
        if (!imageFiles || imageFiles.length === 0) {
            noFilesMessage.classList.remove('hidden');
        } else {
            renderGridView(); // default to grid view
        }

        // View toggles with active state management
        const setActiveView = (activeBtn, inactiveBtn) => {
            activeBtn.classList.add('bg-slate-700', 'text-white');
            activeBtn.classList.remove('text-slate-300');
            inactiveBtn.classList.remove('bg-slate-700', 'text-white');
            inactiveBtn.classList.add('text-slate-300');
        };

        listViewBtn.addEventListener('click', () => {
            renderListView();
            setActiveView(listViewBtn, gridViewBtn);
        });

        gridViewBtn.addEventListener('click', () => {
            renderGridView();
            setActiveView(gridViewBtn, listViewBtn);
        });

        // Set initial active state
        setActiveView(gridViewBtn, listViewBtn);

        // Handle orientation changes
        window.addEventListener('orientationchange', () => {
            setTimeout(() => {
                if (!modal.classList.contains('hidden')) {
                    // Recalculate image size after orientation change
                    const img = previewImage;
                    if (img.src) {
                        img.style.maxWidth = '';
                        img.style.maxHeight = '';
                        // Force reflow
                        void img.offsetHeight;
                        img.classList.add('preview-image');
                    }
                }
            }, 100);
        });

        // Prevent context menu on long press for images
        document.addEventListener('contextmenu', (e) => {
            if (e.target.tagName === 'IMG') {
                e.preventDefault();
            }
        });

        // Add pull-to-refresh prevention
        document.addEventListener('touchmove', (e) => {
            if (!modal.classList.contains('hidden')) {
                // Allow scrolling only within the modal content
                const modalContent = e.target.closest('.relative.h-full.w-full.flex.flex-col');
                if (!modalContent) {
                    e.preventDefault();
                }
            }
        }, { passive: false });

        // Performance optimization: Intersection Observer for lazy loading thumbnails
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.removeAttribute('data-src');
                            observer.unobserve(img);
                        }
                    }
                });
            }, {
                rootMargin: '50px'
            });

            // This would be used if we implement lazy loading for thumbnails
            // For now, keeping the existing immediate loading approach
        }

        // Add keyboard shortcuts info (could be shown in a help dialog)
        const shortcuts = {
            'Escape': 'Close modal',
            '←/→': 'Navigate images', 
            'Space': 'Next image'
        };

        // Expose functions globally for onclick handlers
        window.openPreview = openPreview;

        // Debug info for development
        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
            console.log(`🖼️  Loaded ${imageFiles.length} images`);
            console.log('🎮  Keyboard shortcuts:', shortcuts);
            console.log('📱  Mobile optimizations: ✓ Touch gestures, ✓ Responsive scaling, ✓ Safe areas');
        }
    </script>
</body>
</html>