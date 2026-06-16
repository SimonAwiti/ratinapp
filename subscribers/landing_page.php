<?php
// dashboard.php - User Landing Page after login
include 'user_header_no_sidebar.php';
?>

<!-- Hero Section -->
<section class="relative overflow-hidden bg-surface-container-lowest rounded-2xl mb-12">
    <div class="container mx-auto px-6 py-16 md:py-20">
        <div class="grid md:grid-cols-2 gap-12 items-center">
            <div class="max-w-2xl">
                <span class="inline-block px-4 py-2 bg-primary-container text-on-primary-container rounded-full font-label-md text-label-md mb-6">
                    Welcome to Your Dashboard
                </span>
                <h1 class="font-headline-lg text-headline-lg text-primary mb-6 leading-tight">
                    Agricultural Intelligence<br>at Your Fingertips
                </h1>
                <p class="font-body-lg text-body-lg text-on-surface-variant mb-8">
                    Access real-time market data, expert insights, and comprehensive analytics 
                    to make informed trading decisions in the agricultural commodities market.
                </p>
                <div class="flex flex-wrap gap-4">
                    <a href="marketprices.php" class="bg-secondary text-on-secondary px-8 py-4 rounded-xl font-label-md text-label-md flex items-center gap-2 hover:opacity-90 transition-all hover:scale-105 shadow-lg">
                        Explore Market Data
                        <span class="material-symbols-outlined">trending_up</span>
                    </a>
                    <a href="articles.php" class="border-2 border-outline text-primary px-8 py-4 rounded-xl font-label-md text-label-md hover:bg-primary-container/10 transition-all hover:scale-105">
                        Read Articles
                    </a>
                </div>
            </div>
            <div class="relative flex justify-center">
                <div class="relative">
                    <div class="absolute -top-10 -left-10 w-32 h-32 bg-primary-container/20 rounded-full blur-3xl"></div>
                    <div class="absolute -bottom-10 -right-10 w-40 h-40 bg-secondary-container/20 rounded-full blur-3xl"></div>
                    <img src="https://lh3.googleusercontent.com/aida-public/AB6AXuATOzOqsDUSPMT3dKvPRuPkU6bXrXvgHB_RGGOCz-aC_Xk2RjRaXKL3kXoJjWpd6io2rkjkWjshzsythmQ16vUtrOlTnlTqxeDRhkm57eJK-Fi9CkY1PdjmF-xhPXTQ9tUiNbFOgoUBNA_y1g5QDmwBCNpcZxrZsbi8pUGTmRgkKBMAj93fE1zogY28ZGsKusmBrA0qE2som69g13EAkD8MvR0V5qdxBvxaRBSWJztOq8kglBmOwsf_mU0V1zfBhiwYOLhHXCL4SUo" alt="Agricultural Analytics" class="w-full max-w-md h-auto rounded-2xl shadow-2xl relative z-10">
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Key Features Overview -->
<section class="mb-16">
    <div class="text-center mb-12">
        <h2 class="font-headline-md text-headline-md text-primary mb-3">Platform Capabilities</h2>
        <p class="font-body-lg text-body-lg text-on-surface-variant max-w-2xl mx-auto">
            Comprehensive tools designed for agricultural commodity professionals
        </p>
    </div>
    
    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- Market Price Analytics -->
        <div class="bg-surface-container-lowest p-6 rounded-xl shadow-md hover:shadow-xl transition-all hover:-translate-y-1 border border-surface-container-high group">
            <div class="w-14 h-14 bg-primary-container text-on-primary-container rounded-full flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
                <span class="material-symbols-outlined text-2xl">trending_up</span>
            </div>
            <h3 class="font-headline-md text-xl text-primary mb-3">Market Price Analytics</h3>
            <p class="font-body-md text-body-md text-on-surface-variant mb-4">
                Access real-time and historical price data for grains, oilseeds, and other agricultural commodities. 
                Track price trends, compare markets, and analyze seasonal patterns across multiple trade points.
            </p>
            <div class="flex items-center gap-2 text-secondary font-label-md text-label-md">
                <a href="marketprices.php" class="hover:underline">Explore Prices</a>
                <span class="material-symbols-outlined text-sm">arrow_forward</span>
            </div>
        </div>

        <!-- XBT Volumes -->
        <div class="bg-surface-container-lowest p-6 rounded-xl shadow-md hover:shadow-xl transition-all hover:-translate-y-1 border border-surface-container-high group">
            <div class="w-14 h-14 bg-secondary-container text-on-secondary-container rounded-full flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
                <span class="material-symbols-outlined text-2xl">swap_horiz</span>
            </div>
            <h3 class="font-headline-md text-xl text-primary mb-3">XBT Volumes</h3>
            <p class="font-body-md text-body-md text-on-surface-variant mb-4">
                Monitor cross-border trade volumes and flow patterns. Analyze import/export data to understand 
                market dynamics, supply chain movements, and regional trade balances.
            </p>
            <div class="flex items-center gap-2 text-secondary font-label-md text-label-md">
                <a href="xbt_volume.php" class="hover:underline">View Volumes</a>
                <span class="material-symbols-outlined text-sm">arrow_forward</span>
            </div>
        </div>

        <!-- Miller Prices -->
        <div class="bg-surface-container-lowest p-6 rounded-xl shadow-md hover:shadow-xl transition-all hover:-translate-y-1 border border-surface-container-high group">
            <div class="w-14 h-14 bg-tertiary-container text-on-tertiary-container rounded-full flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
                <span class="material-symbols-outlined text-2xl">factory</span>
            </div>
            <h3 class="font-headline-md text-xl text-primary mb-3">Miller Prices</h3>
            <p class="font-body-md text-body-md text-on-surface-variant mb-4">
                Track processed grain prices from millers across different regions. Monitor flour, feed, 
                and other value-added product pricing to understand downstream market conditions.
            </p>
            <div class="flex items-center gap-2 text-secondary font-label-md text-label-md">
                <a href="miller_prices.php" class="hover:underline">Check Miller Data</a>
                <span class="material-symbols-outlined text-sm">arrow_forward</span>
            </div>
        </div>

        <!-- Insights -->
        <div class="bg-surface-container-lowest p-6 rounded-xl shadow-md hover:shadow-xl transition-all hover:-translate-y-1 border border-surface-container-high group">
            <div class="w-14 h-14 bg-primary-container text-on-primary-container rounded-full flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
                <span class="material-symbols-outlined text-2xl">insights</span>
            </div>
            <h3 class="font-headline-md text-xl text-primary mb-3">Market Insights</h3>
            <p class="font-body-md text-body-md text-on-surface-variant mb-4">
                Access expert analysis, market commentary, and predictive insights. Stay ahead with 
                data-driven recommendations and trend forecasts from our agricultural economics team.
            </p>
            <div class="flex items-center gap-2 text-secondary font-label-md text-label-md">
                <a href="insights.php" class="hover:underline">Read Insights</a>
                <span class="material-symbols-outlined text-sm">arrow_forward</span>
            </div>
        </div>

        <!-- Articles -->
        <div class="bg-surface-container-lowest p-6 rounded-xl shadow-md hover:shadow-xl transition-all hover:-translate-y-1 border border-surface-container-high group">
            <div class="w-14 h-14 bg-secondary-container text-on-secondary-container rounded-full flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
                <span class="material-symbols-outlined text-2xl">article</span>
            </div>
            <h3 class="font-headline-md text-xl text-primary mb-3">Industry Articles</h3>
            <p class="font-body-md text-body-md text-on-surface-variant mb-4">
                Browse curated articles on agricultural policies, market developments, and best practices. 
                Stay informed about regulatory changes and emerging opportunities in global agriculture.
            </p>
            <div class="flex items-center gap-2 text-secondary font-label-md text-label-md">
                <a href="articles.php" class="hover:underline">Browse Articles</a>
                <span class="material-symbols-outlined text-sm">arrow_forward</span>
            </div>
        </div>

        <!-- GrainWatch -->
        <div class="bg-surface-container-lowest p-6 rounded-xl shadow-md hover:shadow-xl transition-all hover:-translate-y-1 border border-surface-container-high group">
            <div class="w-14 h-14 bg-tertiary-container text-on-tertiary-container rounded-full flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
                <span class="material-symbols-outlined text-2xl">monitoring</span>
            </div>
            <h3 class="font-headline-md text-xl text-primary mb-3">GrainWatch</h3>
            <p class="font-body-md text-body-md text-on-surface-variant mb-4">
                Real-time monitoring of grain quality, storage levels, and harvest progress. Track 
                crop conditions, yield estimates, and supply availability across key producing regions.
            </p>
            <div class="flex items-center gap-2 text-secondary font-label-md text-label-md">
                <a href="grainwatch.php" class="hover:underline">Launch GrainWatch</a>
                <span class="material-symbols-outlined text-sm">arrow_forward</span>
            </div>
        </div>
    </div>
</section>


<!-- Getting Started Guide -->
<section class="mb-12">
    <div class="text-center mb-10">
        <h2 class="font-headline-md text-headline-md text-primary mb-3">Getting Started</h2>
        <p class="font-body-lg text-body-lg text-on-surface-variant">Your journey to data-driven decisions begins here</p>
    </div>
    <div class="grid md:grid-cols-3 gap-8">
        <div class="text-center">
            <div class="w-16 h-16 bg-primary-container text-on-primary-container rounded-full flex items-center justify-center mx-auto mb-4 text-2xl font-bold">1</div>
            <h3 class="font-headline-md text-lg text-primary mb-2">Explore Market Data</h3>
            <p class="font-body-md text-body-md text-on-surface-variant">Start with Market Prices to understand current commodity values</p>
        </div>
        <div class="text-center">
            <div class="w-16 h-16 bg-primary-container text-on-primary-container rounded-full flex items-center justify-center mx-auto mb-4 text-2xl font-bold">2</div>
            <h3 class="font-headline-md text-lg text-primary mb-2">Analyze Trends</h3>
            <p class="font-body-md text-body-md text-on-surface-variant">Use Insights and GrainWatch for deeper market analysis</p>
        </div>
        <div class="text-center">
            <div class="w-16 h-16 bg-primary-container text-on-primary-container rounded-full flex items-center justify-center mx-auto mb-4 text-2xl font-bold">3</div>
            <h3 class="font-headline-md text-lg text-primary mb-2">Make Informed Decisions</h3>
            <p class="font-body-md text-body-md text-on-surface-variant">Combine all tools to optimize your trading strategy</p>
        </div>
    </div>
</section>

<!-- Call to Action -->
<section class="py-8">
    <div class="bg-surface-container-high rounded-2xl p-10 text-center">
        <h2 class="font-headline-md text-headline-md text-primary mb-4">Need Assistance?</h2>
        <p class="font-body-lg text-body-lg text-on-surface-variant max-w-2xl mx-auto mb-6">
            Our support team is ready to help you make the most of your subscription
        </p>
        <div class="flex flex-wrap gap-4 justify-center">
            <a href="user_settings.php" class="bg-primary text-white px-6 py-3 rounded-lg font-label-md text-label-md hover:bg-primary/90 transition-all inline-flex items-center gap-2">
                <span class="material-symbols-outlined">support_agent</span>
                Contact Support
            </a>
            <a href="#" class="border-2 border-primary text-primary px-6 py-3 rounded-lg font-label-md text-label-md hover:bg-primary-container/10 transition-all inline-flex items-center gap-2">
                <span class="material-symbols-outlined">description</span>
                View Documentation
            </a>
        </div>
    </div>
</section>
