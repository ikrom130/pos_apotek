<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>INFINITY | Premium Dashboard</title>
    
    <!-- CSS Hasil Compile Tailwind v4 -->
    <link rel="stylesheet" href="assets/css/output.css"> 
    
    <!-- Library Eksternal -->
    <script src="https://code.iconify.design/iconify-icon/1.0.7/iconify-icon.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://iconify.design"></script>
    <script src="https://jsdelivr.net"></script>
    <link rel="preconnect" href="https://googleapis.com">
    <link rel="preconnect" href="https://gstatic.com" crossorigin="">
    <link href="https://googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>

<body class="bg-gray-50 text-slate-800 antialiased transition-colors duration-300 dark:bg-dark-bg dark:text-slate-300 selection:bg-brand-900 selection:text-white">

    <!-- Mobile Overlay -->
    <div id="sidebar-overlay" onclick="toggleSidebar()" class="fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-sm hidden lg:hidden transition-opacity opacity-0"></div>

    <!-- Layout Container -->
    <div class="flex h-screen overflow-hidden">

    <?php include 'sidebar.php'; ?>