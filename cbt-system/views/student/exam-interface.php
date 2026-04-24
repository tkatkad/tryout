<!-- views/student/exam-interface.php -->
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ujian - <?= $session['exam_title'] ?? 'CBT BIMBEL' ?></title>
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Custom Config -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#2563eb',
                        secondary: '#1e40af',
                    }
                }
            }
        }
    </script>
    
    <style>
        /* Custom scrollbar untuk stimulus */
        .stimulus-container::-webkit-scrollbar {
            width: 6px;
        }
        .stimulus-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        .stimulus-container::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }
        .stimulus-container::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        /* Animasi untuk timer warning */
        @keyframes pulse-red {
            0%, 100% { background-color: #fee2e2; }
            50% { background-color: #fecaca; }
        }
        .timer-warning {
            animation: pulse-red 2s infinite;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased">

    <!-- Header Sticky -->
    <header class="fixed top-0 w-full bg-blue-600 text-white p-3 z-50 shadow-md">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div>
                <h1 class="font-bold text-lg"><?= APP_NAME ?></h1>
                <p class="text-xs text-blue-100"><?= htmlspecialchars($session['exam_title']) ?></p>
            </div>
            
            <div class="flex items-center space-x-4">
                <!-- Timer -->
                <div id="timer" class="bg-white text-blue-600 px-4 py-2 rounded-lg font-mono font-bold text-xl shadow-inner">
                    <?= gmdate('H:i:s', ($session['duration_minutes'] ?? 75) * 60) ?>
                </div>
                
                <!-- User Info -->
                <div class="hidden md:block text-sm">
                    <p class="font-semibold"><?= htmlspecialchars($session['student_name']) ?></p>
                </div>
                
                <!-- Nav Button -->
                <button onclick="toggleNavModal()" class="bg-blue-700 hover:bg-blue-800 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    📋 Daftar Soal
                </button>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="pt-20 pb-24 px-4 md:px-6 max-w-7xl mx-auto">
        
        <!-- Progress Bar -->
        <div class="mb-4 bg-white rounded-lg shadow p-3">
            <div class="flex justify-between text-sm mb-2">
                <span class="text-gray-600">Progress: <span id="progress-text">0/0</span></span>
                <span class="text-gray-600">Dijawab: <span id="answered-count">0</span></span>
                <span class="text-gray-600">Ragu-ragu: <span id="flagged-count">0</span></span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
                <div id="progress-bar" class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
            </div>
        </div>
        
        <!-- Question Area -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Kolom Kiri: Stimulus (jika ada) -->
            <div id="stimulus-panel" class="lg:col-span-1 bg-white rounded-xl shadow-lg overflow-hidden hidden">
                <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                    <h3 class="font-bold text-gray-700">📖 Stimulus</h3>
                </div>
                <div id="stimulus-content" class="stimulus-container p-4 text-sm text-gray-700 overflow-y-auto max-h-[60vh]">
                    <!-- Konten stimulus akan di-render di sini oleh JS -->
                </div>
            </div>

            <!-- Kolom Kanan: Pertanyaan & Opsi -->
            <div class="lg:col-span-2 bg-white rounded-xl shadow-lg p-6">
                
                <!-- Question Header -->
                <div class="flex justify-between items-start mb-6 pb-4 border-b border-gray-200">
                    <div>
                        <span class="text-gray-500 text-sm">Soal No.</span>
                        <span id="q-number" class="text-2xl font-bold text-gray-800 ml-2">-</span>
                    </div>
                    <div class="flex space-x-2">
                        <span id="q-difficulty" class="text-xs px-3 py-1 rounded-full font-medium">-</span>
                        <span id="q-cognitive" class="text-xs px-3 py-1 rounded-full font-medium bg-purple-100 text-purple-800">-</span>
                    </div>
                </div>
                
                <!-- Question Text -->
                <div id="question-text" class="text-lg text-gray-800 mb-6 leading-relaxed">
                    <!-- Soal akan di-render di sini -->
                </div>
                
                <!-- Options Container -->
                <div id="options-container" class="space-y-3">
                    <!-- Opsi jawaban akan di-render di sini oleh JS -->
                </div>
                
                <!-- Action Buttons for Question Types -->
                <div id="mcma-actions" class="mt-6 hidden">
                    <p class="text-sm text-gray-600 mb-3">
                        💡 <em>Pilihan Ganda Kompleks:</em> Pilih semua jawaban yang benar (bisa lebih dari satu)
                    </p>
                    <button id="btn-save-mcma" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-colors">
                        Simpan Jawaban
                    </button>
                </div>
            </div>
        </div>

    </main>

    <!-- Footer Navigation -->
    <footer class="fixed bottom-0 w-full bg-white border-t border-gray-200 shadow-lg z-40">
        <div class="max-w-7xl mx-auto px-4 py-3 flex justify-between items-center">
            
            <!-- Previous Button -->
            <button id="btn-prev" class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg font-semibold transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                ← Sebelumnya
            </button>
            
            <!-- Center Controls -->
            <div class="flex space-x-3">
                <button id="btn-flag" class="px-6 py-3 bg-yellow-400 hover:bg-yellow-500 text-white rounded-lg font-semibold transition-all flex items-center">
                    🚩 <span class="ml-2">Ragu-ragu</span>
                </button>
                <button id="btn-clear" class="px-6 py-3 bg-red-400 hover:bg-red-500 text-white rounded-lg font-semibold transition-all">
                    🗑️ Clear
                </button>
            </div>
            
            <!-- Next/Finish Button -->
            <button id="btn-next" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold transition-all">
                Selanjutnya →
            </button>
        </div>
    </footer>

    <!-- Modal Daftar Soal -->
    <div id="nav-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex justify-center items-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[80vh] overflow-hidden">
            
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4 flex justify-between items-center">
                <h2 class="text-xl font-bold text-white">📋 Navigasi Soal</h2>
                <button onclick="toggleNavModal()" class="text-white hover:text-gray-200 text-2xl">&times;</button>
            </div>
            
            <!-- Modal Body -->
            <div class="p-6 overflow-y-auto max-h-[60vh]">
                <!-- Legend -->
                <div class="flex flex-wrap gap-4 mb-6 text-sm">
                    <div class="flex items-center">
                        <div class="w-6 h-6 bg-blue-600 rounded mr-2"></div>
                        <span>Sudah Dijawab</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-6 h-6 bg-white border-2 border-gray-300 rounded mr-2"></div>
                        <span>Belum Dijawab</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-6 h-6 bg-yellow-400 rounded mr-2"></div>
                        <span>Ragu-ragu</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-6 h-6 bg-green-500 rounded mr-2"></div>
                        <span>Soal Saat Ini</span>
                    </div>
                </div>
                
                <!-- Grid Nomor Soal -->
                <div id="nav-grid" class="grid grid-cols-5 md:grid-cols-6 gap-3 mb-6">
                    <!-- Grid items akan di-generate oleh JS -->
                </div>
                
                <!-- Summary Stats -->
                <div class="bg-gray-50 rounded-lg p-4 mb-4">
                    <div class="grid grid-cols-3 gap-4 text-center">
                        <div>
                            <p class="text-2xl font-bold text-blue-600" id="nav-total">0</p>
                            <p class="text-xs text-gray-600">Total Soal</p>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-green-600" id="nav-answered">0</p>
                            <p class="text-xs text-gray-600">Dijawab</p>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-yellow-600" id="nav-remaining">0</p>
                            <p class="text-xs text-gray-600">Sisa</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Modal Footer -->
            <div class="border-t border-gray-200 px-6 py-4 bg-gray-50">
                <button onclick="confirmFinishExam()" class="w-full bg-green-600 hover:bg-green-700 text-white py-3 rounded-lg font-bold text-lg transition-all transform hover:scale-[1.02]">
                    ✅ Selesai Ujian
                </button>
            </div>
        </div>
    </div>

    <!-- Confirm Finish Modal -->
    <div id="finish-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex justify-center items-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
            <div class="text-center mb-6">
                <div class="text-5xl mb-4">⚠️</div>
                <h3 class="text-2xl font-bold text-gray-800 mb-2">Selesaikan Ujian?</h3>
                <p class="text-gray-600">
                    Anda telah menjawab <span id="finish-answered" class="font-bold text-blue-600">0</span> 
                    dari <span id="finish-total" class="font-bold">0</span> soal.
                </p>
                <p class="text-sm text-red-600 mt-2">
                    ⚠️ Jawaban tidak dapat diubah setelah submit!
                </p>
            </div>
            
            <div class="flex space-x-3">
                <button onclick="document.getElementById('finish-modal').classList.add('hidden')" 
                    class="flex-1 px-4 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg font-semibold transition-all">
                    Batal
                </button>
                <button onclick="submitExam()" 
                    class="flex-1 px-4 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold transition-all">
                    Ya, Selesai
                </button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <!-- Load config first -->
    <script>
        const EXAM_CONFIG = {
            sessionId: <?= $session['id'] ?? 0 ?>,
            examId: <?= $session['exam_id'] ?? 0 ?>,
            studentId: <?= $session['student_id'] ?? 0 ?>,
            durationSeconds: <?= ($session['duration_minutes'] ?? 75) * 60 ?>,
            baseUrl: '<?= BASE_URL ?>'
        };
    </script>
    
    <!-- Exam Engine -->
    <script src="<?= BASE_URL ?>/assets/js/exam-engine.js"></script>
    
    <!-- Initialize on load -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initExam();
        });
    </script>
</body>
</html>
