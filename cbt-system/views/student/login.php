<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= APP_NAME ?></title>
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Custom Config untuk Tailwind -->
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
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center p-4">

    <!-- Login Card -->
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
        
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-8 py-6 text-center">
            <h1 class="text-2xl font-bold text-white"><?= APP_NAME ?></h1>
            <p class="text-blue-100 text-sm mt-1">Silakan login untuk memulai ujian</p>
        </div>
        
        <!-- Form -->
        <div class="px-8 py-8">
            
            <!-- Flash Message -->
            <?php if (isset($_GET['error'])): ?>
                <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-700">
                    <p class="font-semibold">Login Gagal</p>
                    <p class="text-sm"><?= htmlspecialchars($_GET['error']) ?></p>
                </div>
            <?php endif; ?>
            
            <form action="<?= BASE_URL ?>/auth/login_process.php" method="POST" id="loginForm">
                
                <!-- Username -->
                <div class="mb-6">
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                        Username / NISN
                    </label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        required
                        autocomplete="username"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all outline-none"
                        placeholder="Masukkan username atau NISN"
                    >
                </div>
                
                <!-- Password -->
                <div class="mb-6">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                        Password
                    </label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required
                        autocomplete="current-password"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all outline-none"
                        placeholder="Masukkan password"
                    >
                </div>
                
                <!-- Remember Me -->
                <div class="mb-6 flex items-center">
                    <input 
                        type="checkbox" 
                        id="remember" 
                        name="remember"
                        class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                    >
                    <label for="remember" class="ml-2 text-sm text-gray-600">
                        Ingat saya (tidak disarankan untuk komputer umum)
                    </label>
                </div>
                
                <!-- Submit Button -->
                <button 
                    type="submit" 
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition-all duration-200 transform hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                >
                    🚀 Masuk ke Sistem
                </button>
                
            </form>
            
            <!-- Help Text -->
            <div class="mt-6 text-center text-sm text-gray-500">
                <p>Belum punya akun? Hubungi administrator.</p>
                <p class="mt-1">Butuh bantuan? <a href="#" class="text-blue-600 hover:underline">Kontak Kami</a></p>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="bg-gray-50 px-8 py-4 text-center text-xs text-gray-500">
            <p>&copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved.</p>
            <p class="mt-1">Version <?= APP_VERSION ?></p>
        </div>
    </div>
    
    <!-- JavaScript untuk UX Enhancement -->
    <script>
        // Auto focus ke username field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });
        
        // Prevent form resubmission on refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
        
        // Enter key submits form
        document.getElementById('loginForm').addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && e.target.tagName !== 'BUTTON') {
                e.preventDefault();
                this.submit();
            }
        });
    </script>
</body>
</html>
