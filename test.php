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

        <!-- Sidebar -->
        <aside id="sidebar" class="sidebar-transition fixed inset-y-0 left-0 z-50 w-64 -translate-x-full border-r border-slate-200 bg-white lg:static lg:translate-x-0 dark:border-dark-border dark:bg-dark-card flex flex-col justify-between">
            <div class="">
                <!-- Logo -->
                <div class="flex h-16 items-center px-6 border-b border-slate-100 dark:border-dark-border">
                    <div class="flex items-center gap-2">
                        <div class="h-6 w-6 rounded bg-brand-900 flex items-center justify-center text-white">
                            <iconify-icon icon="solar:infinity-linear" width="16"></iconify-icon>
                        </div>
                        <span class="dark:text-white text-lg font-extrabold text-slate-900 tracking-tight font-poppins">INFINITY</span>
                    </div>
                </div>

                <!-- Nav -->
                <nav class="space-y-1 px-3 py-6">
                    <a href="#" class="group flex items-center gap-3 dark:bg-brand-900/20 dark:text-brand-100 text-sm font-medium text-brand-900 bg-brand-50 rounded-lg pt-2.5 pr-3 pb-2.5 pl-3">
                        <iconify-icon icon="solar:widget-5-linear" width="20" stroke-width="1.5"></iconify-icon>
                        Dashboard
                    </a>
                    <a href="#" class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-white transition-all">
                        <iconify-icon icon="solar:chart-2-linear" width="20" stroke-width="1.5" class=""></iconify-icon>
                        Analytics
                    </a>
                    <a href="#" class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-white transition-all">
                        <iconify-icon icon="solar:users-group-rounded-linear" width="20" stroke-width="1.5"></iconify-icon>
                        Customers
                    </a>
                    <a href="#" class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-white transition-all">
                        <iconify-icon icon="solar:wallet-money-linear" width="20" stroke-width="1.5"></iconify-icon>
                        Finance
                    </a>
                    <a href="#" class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-white transition-all">
                        <iconify-icon icon="solar:folder-with-files-linear" width="20" stroke-width="1.5"></iconify-icon>
                        Documents
                    </a>
                </nav>
            </div>

            <!-- Bottom Settings -->
            <div class="border-t border-slate-100 p-3 dark:border-dark-border">
                <a href="#" class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-white transition-all">
                    <iconify-icon icon="solar:settings-linear" width="20" stroke-width="1.5"></iconify-icon>
                    Settings
                </a>
                <div class="mt-4 flex items-center gap-3 px-3">
                    <div class="relative h-8 w-8 overflow-hidden rounded-full bg-slate-200">
                        <img src="https://ui-avatars.com/api/?name=Alex+Morgan&background=010694&color=fff" alt="User" class="h-full w-full object-cover">
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs font-semibold text-slate-900 dark:text-white">Alex Morgan</span>
                        <span class="text-xxs text-slate-500">Admin Workspace</span>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 flex flex-col h-screen overflow-hidden relative">
            
            <!-- Top Navigation -->
            <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-slate-200/60 bg-white/80 px-4 glass-effect lg:px-8 dark:border-dark-border dark:bg-dark-bg/80">
                <div class="flex items-center gap-4">
                    <button onclick="toggleSidebar()" class="flex items-center justify-center rounded-xl p-2 text-slate-500 hover:bg-slate-100 lg:hidden dark:text-slate-400 dark:hover:bg-white/5 border">
                        <iconify-icon icon="solar:hamburger-menu-linear" width="24" stroke-width="1.5"></iconify-icon>
                    </button>
                    <!-- Search -->
                    <div class="hidden md:flex items-center gap-2 rounded-lg bg-slate-50 px-3 py-1.5 ring-1 ring-slate-200 focus-within:ring-brand-500 dark:bg-dark-card dark:ring-dark-border">
                        <iconify-icon icon="solar:magnifer-linear" class="text-slate-400"></iconify-icon>
                        <input type="text" placeholder="Search analytics..." class="w-64 bg-transparent text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none dark:text-white">
                        <div class="flex items-center gap-1 rounded border border-slate-200 px-1.5 py-0.5 dark:border-dark-border">
                            <span class="text-xs text-slate-400">⌘K</span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <!-- Theme Toggle -->
                    <button id="theme-toggle" class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 text-slate-600 transition hover:bg-slate-50 dark:border-dark-border dark:text-slate-400 dark:hover:bg-white/5">
                        <iconify-icon id="theme-icon" icon="solar:sun-2-linear" width="20"></iconify-icon>
                    </button>
                    <!-- Notification -->
                    <button class="relative flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 text-slate-600 transition hover:bg-slate-50 dark:border-dark-border dark:text-slate-400 dark:hover:bg-white/5">
                        <iconify-icon icon="solar:bell-linear" width="20"></iconify-icon>
                        <span class="absolute right-0 top-0 mr-0.5 mt-0.5 h-2 w-2 rounded-full bg-red-500 ring-2 ring-white dark:ring-dark-bg"></span>
                    </button>
                </div>
            </header>

            <!-- Scrollable Content -->
            <div class="flex-1 overflow-y-auto lg:p-8 dark:bg-dark-bg bg-slate-50/50 pt-4 pr-4 pb-4 pl-4">
                
                <!-- Page Header -->
                <div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div class="">
                        <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Overview</h1>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Welcome back, here's what's happening today.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 items-center rounded-lg border border-slate-200 bg-white px-3 shadow-sm dark:border-dark-border dark:bg-dark-card">
                            <span class="text-sm font-medium text-slate-600 dark:text-slate-300">Oct 24, 2023</span>
                        </div>
                        <button class="flex h-9 items-center gap-2 rounded-lg bg-brand-900 px-4 text-sm font-medium text-white shadow-lg shadow-brand-900/20 hover:bg-brand-800 transition-colors">
                            <iconify-icon icon="solar:download-linear"></iconify-icon>
                            Export
                        </button>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <!-- Card 1 -->
                    <div class="group relative overflow-hidden rounded-xl border border-slate-200 bg-white p-5 shadow-[0_2px_10px_-3px_rgba(6,81,237,0.1)] transition-all hover:-translate-y-1 hover:shadow-lg dark:border-dark-border dark:bg-dark-card dark:shadow-none">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">TOTAL REVENUE</p>
                                <h3 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">$124,500</h3>
                            </div>
                            <div class="dark:bg-brand-900/20 dark:text-brand-300 text-brand-900 bg-brand-50 rounded-lg pt-2 pr-2 pb-1 pl-2">
                                <iconify-icon icon="solar:dollar-linear" width="20" class="" height="20" style="color: rgb(1, 6, 148);"></iconify-icon>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center gap-1 text-xs">
                            <span class="flex items-center font-medium text-emerald-600 dark:text-emerald-400">
                                <iconify-icon icon="solar:arrow-right-up-linear" class="mr-0.5"></iconify-icon>
                                +12.5%
                            </span>
                            <span class="text-slate-400">from last month</span>
                        </div>
                    </div>

                    <!-- Card 2 -->
                    <div class="group relative overflow-hidden rounded-xl border border-slate-200 bg-white p-5 shadow-[0_2px_10px_-3px_rgba(6,81,237,0.1)] transition-all hover:-translate-y-1 hover:shadow-lg dark:border-dark-border dark:bg-dark-card dark:shadow-none">
                        <div class="flex items-start justify-between">
                            <div class="">
                                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">ACTIVE USERS</p>
                                <h3 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">8,234</h3>
                            </div>
                            <div class="dark:bg-purple-900/20 dark:text-purple-300 text-purple-600 bg-purple-50 rounded-lg pt-2 pr-2 pb-0 pl-2">
                                <iconify-icon icon="solar:users-group-two-rounded-linear" width="20" class=""></iconify-icon>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center gap-1 text-xs">
                            <span class="flex items-center font-medium text-emerald-600 dark:text-emerald-400">
                                <iconify-icon icon="solar:arrow-right-up-linear" class="mr-0.5"></iconify-icon>
                                +4.2%
                            </span>
                            <span class="text-slate-400">from last month</span>
                        </div>
                    </div>

                    <!-- Card 3 -->
                    <div class="group overflow-hidden transition-all hover:-translate-y-1 hover:shadow-lg dark:border-dark-border dark:bg-dark-card dark:shadow-none bg-white border-slate-200 border rounded-xl pt-5 pr-5 pb-5 pl-5 relative shadow-[0_2px_10px_-3px_rgba(6,81,237,0.1)]">
                        <div class="flex items-start justify-between">
                            <div class="">
                                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">BOUNCE RATE</p>
                                <h3 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">42.3%</h3>
                            </div>
                            <div class="dark:bg-orange-900/20 dark:text-orange-300 text-orange-600 bg-orange-50 rounded-lg pt-2 pr-2 pb-0 pl-2">
                                <iconify-icon icon="solar:graph-down-linear" width="20" class=""></iconify-icon>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center gap-1 text-xs">
                            <span class="flex items-center font-medium text-red-500">
                                <iconify-icon icon="solar:arrow-right-down-linear" class="mr-0.5"></iconify-icon>
                                -2.1%
                            </span>
                            <span class="text-slate-400">from last month</span>
                        </div>
                    </div>

                    <!-- Card 4 -->
                    <div class="group relative overflow-hidden rounded-xl border border-slate-200 bg-white p-5 shadow-[0_2px_10px_-3px_rgba(6,81,237,0.1)] transition-all hover:-translate-y-1 hover:shadow-lg dark:border-dark-border dark:bg-dark-card dark:shadow-none">
                        <div class="flex items-start justify-between">
                            <div class="">
                                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">AVG. SESSION</p>
                                <h3 class="dark:text-white text-2xl font-semibold text-slate-900 tracking-tight mt-2">4m 32s</h3>
                            </div>
                            <div class="dark:bg-teal-900/20 dark:text-teal-300 text-teal-600 bg-teal-50 rounded-lg pt-2 pr-2 pb-0 pl-2">
                                <iconify-icon icon="solar:clock-circle-linear" width="20"></iconify-icon>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center gap-1 text-xs">
                            <span class="flex items-center font-medium text-emerald-600 dark:text-emerald-400">
                                <iconify-icon icon="solar:arrow-right-up-linear" class="mr-0.5"></iconify-icon>
                                +8.1%
                            </span>
                            <span class="text-slate-400">from last month</span>
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <!-- Main Chart -->
                    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-dark-border dark:bg-dark-card lg:col-span-2">
                        <div class="mb-6 flex items-center justify-between">
                            <h3 class="text-base font-semibold text-slate-900 dark:text-white">Revenue Growth</h3>
                            <select class="rounded-lg border border-slate-200 bg-transparent px-2 py-1 text-xs text-slate-600 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-dark-border dark:text-slate-400">
                                <option>This Year</option>
                                <option>Last Year</option>
                            </select>
                        </div>
                        <div class="relative h-64 w-full">
                            <canvas id="revenueChart" style="display: block; box-sizing: border-box; height: 256px; width: 745.6px;" width="932" height="320" class=""></canvas>
                        </div>
                    </div>

                    <!-- Side Chart -->
                    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-dark-border dark:bg-dark-card">
                        <div class="mb-6 flex items-center justify-between">
                            <h3 class="text-base font-semibold text-slate-900 dark:text-white">Traffic Source</h3>
                            <button class="text-slate-400 hover:text-brand-900 dark:hover:text-white">
                                <iconify-icon icon="solar:menu-dots-linear"></iconify-icon>
                            </button>
                        </div>
                        <div class="relative h-48 w-full flex items-center justify-center">
                            <canvas id="trafficChart" style="display: block; box-sizing: border-box; height: 192px; width: 336px;" width="420" height="240" class=""></canvas>
                        </div>
                        <div class="mt-6 space-y-3">
                            <div class="flex items-center justify-between text-sm">
                                <div class="flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full bg-brand-900"></span>
                                    <span class="text-slate-600 dark:text-slate-400">Direct</span>
                                </div>
                                <span class="font-medium text-slate-900 dark:text-white">45%</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <div class="flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full bg-brand-400"></span>
                                    <span class="text-slate-600 dark:text-slate-400">Social</span>
                                </div>
                                <span class="font-medium text-slate-900 dark:text-white">32%</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <div class="flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full bg-slate-200 dark:bg-slate-600"></span>
                                    <span class="text-slate-600 dark:text-slate-400">Referral</span>
                                </div>
                                <span class="font-medium text-slate-900 dark:text-white">23%</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Data Table -->
                <div class="mt-6 rounded-xl border border-slate-200 bg-white shadow-sm dark:border-dark-border dark:bg-dark-card">
                    <div class="flex flex-col gap-4 border-b border-slate-200 px-6 py-5 sm:flex-row sm:items-center sm:justify-between dark:border-dark-border">
                        <h3 class="text-base font-semibold text-slate-900 dark:text-white">Recent Transactions</h3>
                        <div class="flex items-center gap-2">
                            <div class="relative">
                                <iconify-icon icon="solar:magnifer-linear" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></iconify-icon>
                                <input type="text" placeholder="Filter..." class="h-9 w-40 rounded-lg border border-slate-200 bg-transparent pl-9 pr-3 text-sm text-slate-600 focus:border-brand-500 focus:outline-none dark:border-dark-border dark:text-slate-300">
                            </div>
                            <button class="flex h-9 items-center gap-2 rounded-lg border border-slate-200 px-3 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-dark-border dark:text-slate-300 dark:hover:bg-white/5">
                                <iconify-icon icon="solar:filter-linear"></iconify-icon>
                                Filter
                            </button>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="">
                                <tr class="border-b border-slate-200 bg-slate-50/50 text-xs font-medium uppercase tracking-wide text-slate-500 dark:border-dark-border dark:bg-white/5 dark:text-slate-400">
                                    <th class="p-4 w-4">
                                        <input type="checkbox" class="custom-checkbox h-4 w-4 rounded border-slate-300 text-brand-900 focus:ring-0 dark:border-slate-600 dark:bg-dark-bg">
                                    </th>
                                    <th class="p-4">Customer</th>
                                    <th class="p-4">Status</th>
                                    <th class="p-4">Date</th>
                                    <th class="p-4">Amount</th>
                                    <th class="p-4 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-dark-border text-sm">
                                <tr class="group hover:bg-slate-50 dark:hover:bg-white/5 transition-colors">
                                    <td class="p-4">
                                        <input type="checkbox" class="custom-checkbox h-4 w-4 rounded border-slate-300 text-brand-900 focus:ring-0 dark:border-slate-600 dark:bg-dark-bg">
                                    </td>
                                    <td class="p-4">
                                        <div class="flex items-center gap-3">
                                            <div class="h-8 w-8 rounded-full bg-gradient-to-tr from-brand-100 to-purple-100 dark:from-brand-900/40 dark:to-purple-900/40 flex items-center justify-center text-xs font-bold text-brand-900 dark:text-brand-200">
                                                JD
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="font-medium text-slate-900 dark:text-white">John Doe</span>
                                                <span class="text-xs text-slate-500">john@example.com</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-4">
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400">
                                            Paid
                                        </span>
                                    </td>
                                    <td class="p-4 text-slate-500 dark:text-slate-400">Oct 24, 2023</td>
                                    <td class="p-4 font-medium text-slate-900 dark:text-white">$350.00</td>
                                    <td class="p-4 text-right">
                                        <button class="text-slate-400 hover:text-brand-900 dark:hover:text-white transition-colors">
                                            <iconify-icon icon="solar:menu-dots-linear" width="20"></iconify-icon>
                                        </button>
                                    </td>
                                </tr>

                                <tr class="group hover:bg-slate-50 dark:hover:bg-white/5 transition-colors">
                                    <td class="p-4">
                                        <input type="checkbox" class="custom-checkbox h-4 w-4 rounded border-slate-300 text-brand-900 focus:ring-0 dark:border-slate-600 dark:bg-dark-bg">
                                    </td>
                                    <td class="p-4">
                                        <div class="flex items-center gap-3">
                                            <div class="h-8 w-8 rounded-full bg-gradient-to-tr from-orange-100 to-amber-100 dark:from-orange-900/40 dark:to-amber-900/40 flex items-center justify-center text-xs font-bold text-orange-800 dark:text-orange-200">
                                                SM
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="font-medium text-slate-900 dark:text-white">Sarah Miller</span>
                                                <span class="text-xs text-slate-500">sarah@studio.io</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-4">
                                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/20 dark:text-amber-400">
                                            Pending
                                        </span>
                                    </td>
                                    <td class="p-4 text-slate-500 dark:text-slate-400">Oct 23, 2023</td>
                                    <td class="p-4 font-medium text-slate-900 dark:text-white">$1,200.00</td>
                                    <td class="p-4 text-right">
                                        <button class="text-slate-400 hover:text-brand-900 dark:hover:text-white transition-colors">
                                            <iconify-icon icon="solar:menu-dots-linear" width="20"></iconify-icon>
                                        </button>
                                    </td>
                                </tr>

                                <tr class="group hover:bg-slate-50 dark:hover:bg-white/5 transition-colors">
                                    <td class="p-4">
                                        <input type="checkbox" class="custom-checkbox h-4 w-4 rounded border-slate-300 text-brand-900 focus:ring-0 dark:border-slate-600 dark:bg-dark-bg">
                                    </td>
                                    <td class="p-4">
                                        <div class="flex items-center gap-3">
                                            <div class="h-8 w-8 rounded-full bg-gradient-to-tr from-blue-100 to-cyan-100 dark:from-blue-900/40 dark:to-cyan-900/40 flex items-center justify-center text-xs font-bold text-blue-800 dark:text-blue-200">
                                                MK
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="font-medium text-slate-900 dark:text-white">Mike K.</span>
                                                <span class="text-xs text-slate-500">mike@tech.co</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-4">
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400">
                                            Paid
                                        </span>
                                    </td>
                                    <td class="p-4 text-slate-500 dark:text-slate-400">Oct 21, 2023</td>
                                    <td class="p-4 font-medium text-slate-900 dark:text-white">$850.00</td>
                                    <td class="p-4 text-right">
                                        <button class="text-slate-400 hover:text-brand-900 dark:hover:text-white transition-colors">
                                            <iconify-icon icon="solar:menu-dots-linear" width="20"></iconify-icon>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="flex items-center justify-between border-t border-slate-200 px-6 py-4 dark:border-dark-border">
                        <span class="text-xs text-slate-500 dark:text-slate-400">Showing 1-3 of 24 results</span>
                        <div class="flex items-center gap-2">
                            <button class="flex h-8 w-8 items-center justify-center rounded border border-slate-200 text-slate-500 hover:bg-slate-50 disabled:opacity-50 dark:border-dark-border dark:text-slate-400 dark:hover:bg-white/5">
                                <iconify-icon icon="solar:alt-arrow-left-linear"></iconify-icon>
                            </button>
                            <button class="flex h-8 w-8 items-center justify-center rounded border border-slate-200 bg-brand-900 text-white shadow-sm dark:border-brand-900">1</button>
                            <button class="flex h-8 w-8 items-center justify-center rounded border border-slate-200 text-slate-500 hover:bg-slate-50 dark:border-dark-border dark:text-slate-400 dark:hover:bg-white/5">2</button>
                            <button class="flex h-8 w-8 items-center justify-center rounded border border-slate-200 text-slate-500 hover:bg-slate-50 dark:border-dark-border dark:text-slate-400 dark:hover:bg-white/5">
                                <iconify-icon icon="solar:alt-arrow-right-linear"></iconify-icon>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mt-8 text-center">
                    <p class="text-xs text-slate-400">© 2023 Infinity Inc. All rights reserved.</p>
                </div>

            </div>
        </main>
    </div>
    
    <script src="assets/js/dashboard.js"></script>
</body>
</html>