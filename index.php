<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Responsive Dashboard</title>
    
    <!-- Link Tailwind Langsung dari Server Unpkg (Alternatif Anti-Gagal) -->
    <script src="https://unpkg.com"></script>
    
    <!-- Font Awesome untuk Ikon -->
    <link rel="stylesheet" href="https://cloudflare.com">
</head>
<body class="bg-gray-100 font-sans antialiased">

    <div class="flex h-screen overflow-hidden">
        
        <!-- SIDEBAR -->
        <div id="sidebar" class="bg-slate-900 text-white w-64 fixed md:relative inset-y-0 left-0 transform -translate-x-full md:translate-x-0 transition-all duration-300 ease-in-out z-50 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-800">
                    <span id="logo-text" class="text-xl font-bold tracking-wider">MYDASH</span>
                    <button id="close-sidebar" class="md:hidden text-gray-400 hover:text-white">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <nav class="mt-6 px-4 space-y-2">
                    <a href="#" class="flex items-center space-x-4 px-4 py-3 rounded-lg bg-blue-600 text-white font-medium transition">
                        <i class="fas fa-th-large w-5 text-center"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                    <a href="#" class="flex items-center space-x-4 px-4 py-3 rounded-lg text-gray-400 hover:bg-slate-800 hover:text-white font-medium transition">
                        <i class="fas fa-chart-bar w-5 text-center"></i>
                        <span class="nav-text">Analytics</span>
                    </a>
                    <a href="#" class="flex items-center space-x-4 px-4 py-3 rounded-lg text-gray-400 hover:bg-slate-800 hover:text-white font-medium transition">
                        <i class="fas fa-users w-5 text-center"></i>
                        <span class="nav-text">Users</span>
                    </a>
                </nav>
            </div>

            <div class="hidden md:block p-4 border-t border-slate-800">
                <button id="collapse-btn" class="w-full flex items-center justify-center space-x-2 bg-slate-800 hover:bg-slate-700 py-2 rounded-lg transition">
                    <i id="collapse-icon" class="fas fa-chevron-left"></i>
                    <span id="collapse-text" class="text-sm">Collapse</span>
                </button>
            </div>
        </div>

        <!-- KONTEN UTAMA -->
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
            <header class="bg-white border-b border-gray-200 h-16 flex items-center justify-between px-6 z-40">
                <div class="flex items-center space-x-4">
                    <button id="menu-btn" class="text-gray-500 hover:text-gray-700 focus:outline-none">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h1 class="text-lg font-semibold text-gray-800">Overview</h1>
                </div>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto p-6">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                        <div class="text-sm text-gray-400 font-medium">Revenue</div>
                        <div class="text-2xl font-bold text-gray-800 mt-2">$45,231</div>
                    </div>
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                        <div class="text-sm text-gray-400 font-medium">Sales</div>
                        <div class="text-2xl font-bold text-gray-800 mt-2">+12,234</div>
                    </div>
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                        <div class="text-sm text-gray-400 font-medium">Active Users</div>
                        <div class="text-2xl font-bold text-gray-800 mt-2">573</div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 h-64 flex items-center justify-center text-gray-400">
                    Konten utama Anda di sini.
                </div>
            </main>
        </div>
    </div>

    <script>
        const sidebar = document.getElementById('sidebar');
        const menuBtn = document.getElementById('menu-btn');
        const closeSidebar = document.getElementById('close-sidebar');
        const collapseBtn = document.getElementById('collapse-btn');
        const collapseIcon = document.getElementById('collapse-icon');
        const collapseText = document.getElementById('collapse-text');
        const logoText = document.getElementById('logo-text');
        const navTexts = document.querySelectorAll('.nav-text');

        menuBtn.addEventListener('click', () => {
            if (window.innerWidth < 768) {
                sidebar.classList.toggle('-translate-x-full');
            } else {
                toggleSidebar();
            }
        });

        closeSidebar.addEventListener('click', () => sidebar.classList.add('-translate-x-full'));
        collapseBtn.addEventListener('click', toggleSidebar);

        function toggleSidebar() {
            if (sidebar.classList.contains('w-64')) {
                sidebar.classList.replace('w-64', 'w-20');
                logoText.style.display = 'none';
                collapseText.style.display = 'none';
                collapseIcon.classList.replace('fa-chevron-left', 'fa-chevron-right');
                navTexts.forEach(t => t.style.display = 'none');
            } else {
                sidebar.classList.replace('w-20', 'w-64');
                logoText.style.display = 'block';
                collapseText.style.display = 'block';
                collapseIcon.classList.replace('fa-chevron-right', 'fa-chevron-left');
                navTexts.forEach(t => t.style.display = 'block');
            }
        }
    </script>
</body>
</html>
