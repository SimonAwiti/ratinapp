<!DOCTYPE html>
<html class="light" lang="en" style="">
<head>
<meta charset="utf-8">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<title>RATIN Admin | Platform Control Center</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&amp;display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
<style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        body {
            font-family: 'Inter', sans-serif;
        }
        /* HERO SECTION - SLIGHTLY TALLER */
        .hero-compact {
            min-height: auto;
            padding: 7rem 0 5rem 0;
            display: flex;
            align-items: center;
        }
        .hero-image-container {
            display: flex;
            justify-content: center;
            align-items: center;
        }
        /* Smooth scroll behavior */
        html {
            scroll-behavior: smooth;
        }
    </style>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "error-container": "#ffdad6",
                        "surface-container-lowest": "#ffffff",
                        "on-tertiary": "#ffffff",
                        "inverse-surface": "#2f3131",
                        "outline": "#717a6d",
                        "tertiary-fixed-dim": "#75d5e2",
                        "surface-bright": "#f9f9f9",
                        "secondary": "#b22c01",
                        "on-primary": "#ffffff",
                        "primary-fixed": "#acf4a4",
                        "on-primary-fixed": "#002203",
                        "on-primary-fixed-variant": "#0c5216",
                        "on-tertiary-container": "#73d4e0",
                        "tertiary-fixed": "#92f1fe",
                        "surface-container-highest": "#e2e2e2",
                        "primary": "#00450d",
                        "on-secondary": "#ffffff",
                        "on-tertiary-fixed": "#001f23",
                        "primary-fixed-dim": "#91d78a",
                        "primary-container": "#1b5e20",
                        "secondary-container": "#ff6338",
                        "error": "#ba1a1a",
                        "inverse-primary": "#91d78a",
                        "on-background": "#1a1c1c",
                        "inverse-on-surface": "#f1f1f1",
                        "surface-variant": "#e2e2e2",
                        "on-tertiary-fixed-variant": "#004f56",
                        "on-secondary-container": "#5d1200",
                        "on-surface-variant": "#41493e",
                        "on-error-container": "#93000a",
                        "surface-container": "#eeeeee",
                        "on-error": "#ffffff",
                        "surface-container-high": "#e8e8e8",
                        "secondary-fixed": "#ffdbd1",
                        "on-secondary-fixed": "#3b0800",
                        "outline-variant": "#c0c9bb",
                        "tertiary": "#004248",
                        "surface-container-low": "#f3f3f3",
                        "on-surface": "#1a1c1c",
                        "surface-tint": "#2a6b2c",
                        "surface": "#f9f9f9",
                        "on-primary-container": "#90d689"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.125rem",
                        "lg": "0.25rem",
                        "xl": "0.5rem",
                        "full": "0.75rem"
                    },
                    "spacing": {
                        "base": "8px",
                        "container-padding": "24px",
                        "gutter": "16px",
                        "card-gap": "20px",
                        "sidebar-width": "260px"
                    },
                    "fontFamily": {
                        "data-tabular": ["Inter"],
                        "headline-lg-mobile": ["Inter"],
                        "body-md": ["Inter"],
                        "label-md": ["Inter"],
                        "headline-lg": ["Inter"],
                        "body-lg": ["Inter"],
                        "headline-md": ["Inter"]
                    },
                    "fontSize": {
                        "data-tabular": ["13px", {"lineHeight": "18px", "fontWeight": "400"}],
                        "headline-lg-mobile": ["24px", {"lineHeight": "32px", "fontWeight": "700"}],
                        "body-md": ["14px", {"lineHeight": "20px", "fontWeight": "400"}],
                        "label-md": ["12px", {"lineHeight": "16px", "letterSpacing": "0.05em", "fontWeight": "600"}],
                        "headline-lg": ["32px", {"lineHeight": "40px", "letterSpacing": "-0.02em", "fontWeight": "700"}],
                        "body-lg": ["16px", {"lineHeight": "24px", "fontWeight": "400"}],
                        "headline-md": ["24px", {"lineHeight": "32px", "letterSpacing": "-0.01em", "fontWeight": "600"}]
                    }
                },
            },
        }
    </script>
</head>
<body class="bg-background text-on-surface">
<nav class="fixed top-0 w-full z-50 flex justify-between items-center px-container-padding h-16 bg-surface dark:bg-inverse-surface shadow-sm">
<div class="flex items-center gap-gutter">
<span class="font-headline-md text-headline-md text-primary dark:text-primary-fixed">RATIN Admin</span>
<div class="hidden md:flex gap-gutter items-center ml-8">
</div>
</div>
<div class="flex items-center gap-gutter">
<!-- Main Portal button links to login.php -->
<a href="login.php" class="bg-secondary text-on-secondary px-6 py-2 rounded-xl font-label-md text-label-md hover:opacity-90 transition-opacity active:scale-95 duration-100 inline-block">
                Main Portal
            </a>
</div>
</nav>
<main>
<!-- HERO SECTION - SLIGHTLY TALLER -->
<section class="hero-compact relative flex items-center overflow-hidden bg-surface-container-lowest">
<div class="container mx-auto px-container-padding grid md:grid-cols-2 items-center gap-8 relative z-10">
<div class="max-w-2xl">
<span class="inline-block px-3 py-1 bg-primary-container text-on-primary-container rounded-full font-label-md text-label-md mb-6">
                        Internal Administration
                    </span>
<h1 class="font-headline-lg text-headline-lg text-primary mb-6 leading-tight">
                        Platform Administration Overview
                    </h1>
<p class="font-body-lg text-body-lg text-on-surface-variant mb-10 max-w-lg">
                        Manage users, monitor system performance, and oversee regional data submission workflows across the entire RATIN network from a centralized command interface.
                    </p>
<div class="flex flex-wrap gap-4">
<!-- Access Admin Panel button -->
<a href="login.php" class="bg-secondary text-on-secondary px-8 py-4 rounded-xl font-label-md text-label-md flex items-center gap-2 hover:opacity-90 transition-opacity shadow-lg inline-block">
                            Access Admin Panel
                            <span class="material-symbols-outlined" data-icon="settings">settings</span>
</a>
<!-- RATIN Website link (ratin.net) -->
<a href="https://ratin.net" target="_blank" rel="noopener noreferrer" class="border border-outline text-primary px-8 py-4 rounded-xl font-label-md text-label-md hover:bg-primary-container/5 transition-colors inline-block">
                            RATIN Website
                        </a>
</div>
</div>

<!-- IMAGE - SMALLER AND WELL POSITIONED -->
<div class="hero-image-container relative flex justify-center items-center md:col-span-1">
    <img 
        src="https://lh3.googleusercontent.com/aida-public/AB6AXuATOzOqsDUSPMT3dKvPRuPkU6bXrXvgHB_RGGOCz-aC_Xk2RjRaXKL3kXoJjWpd6io2rkjkWjshzsythmQ16vUtrOlTnlTqxeDRhkm57eJK-Fi9CkY1PdjmF-xhPXTQ9tUiNbFOgoUBNA_y1g5QDmwBCNpcZxrZsbi8pUGTmRgkKBMAj93fE1zogY28ZGsKusmBrA0qE2som69g13EAkD8MvR0V5qdxBvxaRBSWJztOq8kglBmOwsf_mU0V1zfBhiwYOLhHXCL4SUo" 
        alt="RATIN Analytics Grains Farm Illustration" 
        class="w-full max-w-xs md:max-w-sm lg:max-w-md h-auto rounded-2xl shadow-2xl transition-transform duration-700 hover:scale-[1.02]"
    >
</div></div>
</section>

<!-- ADMINISTRATIVE CONTROLS SECTION - CENTERED HEADING + REDUCED TOP PADDING -->
<section class="py-16 bg-surface-container-low">
<div class="container mx-auto px-container-padding">
<!-- Centered heading section with reduced top padding -->
<div class="text-center mb-12">
    <h2 class="font-headline-md text-headline-md text-primary mb-2">Administrative Controls</h2>
    <p class="font-body-md text-body-md text-on-surface-variant">Core management tools for platform integrity and governance.</p>
</div>
<div class="grid grid-cols-1 md:grid-cols-3 gap-card-gap">
<div class="bg-surface-container-lowest p-8 rounded-xl shadow-[0px_4px_12px_rgba(0,0,0,0.05)] hover:translate-y-[-4px] transition-transform border border-surface-container-high">
<div class="w-12 h-12 bg-primary-container text-on-primary-container rounded-full flex items-center justify-center mb-6">
<span class="material-symbols-outlined" data-icon="group">group</span>
</div>
<h3 class="font-headline-md text-[20px] text-primary mb-4">User Management</h3>
<p class="font-body-md text-body-md text-on-surface-variant mb-6">
                            Oversee user accounts, define roles, and manage access permissions for regional coordinators and analysts.
                        </p>
<div class="pt-6 border-t border-outline-variant flex justify-between items-center">
<span class="font-label-md text-label-md text-primary opacity-60">Pending Account Requests</span>
<span class="material-symbols-outlined text-secondary" data-icon="arrow_outward">arrow_outward</span>
</div>
</div>
<div class="bg-surface-container-lowest p-8 rounded-xl shadow-[0px_4px_12px_rgba(0,0,0,0.05)] hover:translate-y-[-4px] transition-transform border border-surface-container-high">
<div class="w-12 h-12 bg-secondary-container text-on-secondary-container rounded-full flex items-center justify-center mb-6">
<span class="material-symbols-outlined" data-icon="speed">speed</span>
</div>
<h3 class="font-headline-md text-[20px] text-primary mb-4">System Health</h3>
<p class="font-body-md text-body-md text-on-surface-variant mb-6">
                            Monitor server load, database performance, and API response times across the distributed infrastructure.
                        </p>
<div class="pt-6 border-t border-outline-variant flex justify-between items-center">
<span class="font-label-md text-label-md text-primary opacity-60">All Services Up</span>
<span class="material-symbols-outlined text-secondary" data-icon="arrow_outward">arrow_outward</span>
</div>
</div>
<div class="bg-surface-container-lowest p-8 rounded-xl shadow-[0px_4px_12px_rgba(0,0,0,0.05)] hover:translate-y-[-4px] transition-transform border border-surface-container-high">
<div class="w-12 h-12 bg-tertiary-container text-on-tertiary-container rounded-full flex items-center justify-center mb-6">
<span class="material-symbols-outlined" data-icon="history_edu">history_edu</span>
</div>
<h3 class="font-headline-md text-[20px] text-primary mb-4">Data Audits</h3>
<p class="font-body-md text-body-md text-on-surface-variant mb-6">
                            Track data provenance and review validation logs to ensure the accuracy of regional price and volume submissions.
                        </p>
<div class="pt-6 border-t border-outline-variant flex justify-between items-center">
<span class="font-label-md text-label-md text-primary opacity-60">New Reports Ready</span>
<span class="material-symbols-outlined text-secondary" data-icon="arrow_outward">arrow_outward</span>
</div>
</div>
</div>
</div>
</section>

<!-- Manage Platform CTA Section with working link -->
<section class="py-16">
<div class="container mx-auto px-container-padding">
<div class="bg-primary dark:bg-on-primary-fixed rounded-[2rem] p-12 md:p-20 text-center relative overflow-hidden">
<div class="relative z-10">
<h2 class="font-headline-lg text-headline-lg text-white mb-6">Ready to Manage the Platform?</h2>
<p class="text-primary-fixed-dim font-body-lg text-body-lg max-w-2xl mx-auto mb-10 opacity-90">
                            Access the full suite of administrative panels to coordinate regional data hubs and system infrastructure.
                        </p>
<div class="flex justify-center">
<!-- Manage Platform button links to admin dashboard -->
<a href="login.php" class="bg-secondary text-on-secondary px-10 py-5 rounded-full font-label-md text-label-md hover:scale-105 transition-transform shadow-xl inline-block">
                                Manage Platform
                            </a>
</div>
</div>
<div class="absolute inset-0 opacity-10">
<img alt="Background Pattern" class="w-full h-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuAPYaiAKO6B0AuT63GoKpGcvR3Pi4lPcudM62bf9KQrwUvno24QPKasqTMLxLzAzR2TWLvTSMacmLGHdJDNejSRhF8mzPuXyJjQWae6lurMyJ1AZw5AR5_iujr34ChFMfUMRzgsmo88vxufQKkymfIoz8HibfB92_JYem63ENmQJ0RM1zGLZojuOpTA_4FK8yZmKJ4u-NkH9U8hgcG7pyjXq-FkNyu7UnkoAeGJmImEJASV1ti32jtRDkouaNkyiAcQEioRMKHX4Ug">
</div>
</div>
</div>
</section>
</main>
<footer class="w-full py-12 px-container-padding flex flex-col md:flex-row justify-between items-center gap-gutter bg-surface-container-highest dark:bg-inverse-surface border-t border-outline-variant dark:border-outline">
<div class="flex flex-col gap-4">
<span class="font-headline-md text-headline-md text-primary dark:text-primary-fixed">RATIN Admin</span>
<p class="font-body-md text-body-md text-on-surface-variant">© 2026 RATIN Analytics. Admin Portal.</p>
</div>
<div class="flex flex-wrap justify-center gap-gutter">
<a class="font-body-md text-body-md text-on-surface-variant hover:text-secondary transition-colors" href="#">Base Management</a>
<a class="font-body-md text-body-md text-on-surface-variant hover:text-secondary transition-colors" href="#">Data Management</a>
<a class="font-body-md text-body-md text-on-surface-variant hover:text-secondary transition-colors" href="#">Web Management</a>
<a class="font-body-md text-body-md text-on-surface-variant hover:text-secondary transition-colors" href="https://ratin.net" target="_blank" rel="noopener noreferrer">RATIN Website</a>
<a class="font-body-md text-body-md text-on-surface-variant hover:text-secondary transition-colors" href="#">Security Policy</a>
</div>
<div class="flex gap-4">
<button class="w-10 h-10 rounded-full bg-surface flex items-center justify-center hover:text-secondary transition-colors">
<span class="material-symbols-outlined text-[20px]" data-icon="admin_panel_settings">admin_panel_settings</span>
</button>
<button class="w-10 h-10 rounded-full bg-surface flex items-center justify-center hover:text-secondary transition-colors">
<span class="material-symbols-outlined text-[20px]" data-icon="help_center">help_center</span>
</button>
</div>
</footer>

<script>
    (function() {
        const links = document.querySelectorAll('a[href="login.php"], a[href="admin-panel.php"], a[href="manage-platform.php"], a[href="https://ratin.net"]');
        links.forEach(link => {
            link.addEventListener('click', function(e) {
                console.log(`Navigating to: ${this.getAttribute('href')}`);
            });
        });
    })();
</script>
</body>
</html>