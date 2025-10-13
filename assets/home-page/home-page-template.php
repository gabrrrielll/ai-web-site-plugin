<?php
/**
 * AI Website Builder Home Page Template
 * This template is used by the shortcode to display the home page content
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="ai-website-builder-home">
    <!-- Hero Section -->
    <section class="awb-hero-section">
        <div class="bg-grid-pattern"></div>
        <div class="max-w-7xl mx-auto px-4 sm-px-6 lg-px-8 py-20 lg-py-32">
            <div class="text-center awb-animate-fade-in-up">
                <h1 class="text-4xl sm-text-5xl lg-text-6xl font-bold text-white mb-6">
                    <span class="block">Free AI Website Builder</span>
                    <span class="block text-white">to digitize your business</span>
                </h1>
                <p class="text-xl sm-text-2xl text-white-90 mb-8 max-w-3xl mx-auto leading-relaxed">
                    Join the millions of companies that use AI to create stunning websites in minutes, 
                    embed powerful features, and build a more profitable online presence.
                </p>
                
                <!-- Main CTA Button -->
                <div class="my-16">
                    <a href="<?php echo esc_url($cta_url ?? 'https://editor.ai-web.site/?edit=true'); ?>" 
                       class="awb-cta-button inline-flex items-center px-8 py-4 text-lg font-semibold text-white rounded-lg shadow-lg hover:shadow-xl transition-all duration-300">
                        <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        Start now
                    </a>
                </div>
                
                <!-- Trust Indicators -->
                <div class="flex flex-col sm-flex-row items-center justify-center space-y-4 sm-space-y-0 sm-space-x-8 text-sm text-white-80">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                        No credit card required
                    </div>
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                        Free subdomain included
                    </div>
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                        3 AI generations included
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php if ($show_features !== 'false'): ?>
    <!-- Features Section -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm-px-6 lg-px-8">
            <div class="text-center mb-16 awb-animate-fade-in">
                <h2 class="text-3xl sm-text-4xl font-bold text-gray-900 mb-4">
                    Everything you need to build amazing websites
                </h2>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                    Powerful features designed to make website creation fast, easy, and enjoyable
                </p>
            </div>
            
            <div class="grid grid-cols-1 gap-8">
                <!-- Feature 1: Speed -->
                <div class="awb-feature-card bg-white p-8 rounded-xl border border-gray-200 shadow-sm">
                    <div class="w-12 h-12 bg-primary-10 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-3">Lightning Fast</h3>
                    <p class="text-gray-600 leading-relaxed">
                        Create a complete website in just 5 minutes. Our AI-powered builder generates professional layouts instantly.
                    </p>
                </div>
                
                <!-- Feature 2: AI Assistant -->
                <div class="awb-feature-card bg-white p-8 rounded-xl border border-gray-200 shadow-sm">
                    <div class="w-12 h-12 bg-secondary-10 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-6 h-6 text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-3">AI-Powered</h3>
                    <p class="text-gray-600 leading-relaxed">
                        Get intelligent suggestions and automated content generation. Let AI help you create the perfect website.
                    </p>
                </div>
                
                <!-- Feature 3: Easy Editing -->
                <div class="awb-feature-card bg-white p-8 rounded-xl border border-gray-200 shadow-sm">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-3">Double-Click Editing</h3>
                    <p class="text-gray-600 leading-relaxed">
                        Edit any element on your website with a simple double-click. No technical knowledge required.
                    </p>
                </div>
                
                <!-- Feature 4: Multilingual -->
                <div class="awb-feature-card bg-white p-8 rounded-xl border border-gray-200 shadow-sm">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-3">Multilingual Support</h3>
                    <p class="text-gray-600 leading-relaxed">
                        Generate your website in both Romanian and English. Reach a wider audience effortlessly.
                    </p>
                </div>
                
                <!-- Feature 5: Free Forever -->
                <div class="awb-feature-card bg-white p-8 rounded-xl border border-gray-200 shadow-sm">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-3">Free Forever Plan</h3>
                    <p class="text-gray-600 leading-relaxed">
                        Create one website with a free subdomain. No hidden costs, no time limits. Forever free.
                    </p>
                </div>
                
                <!-- Feature 6: AI Generations -->
                <div class="awb-feature-card bg-white p-8 rounded-xl border border-gray-200 shadow-sm">
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-3">3 AI Generations</h3>
                    <p class="text-gray-600 leading-relaxed">
                        Get 3 complete AI-generated website designs. Find the perfect style for your brand.
                    </p>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($show_pricing !== 'false'): ?>
    <!-- Pricing Section -->
    <section class="py-20 bg-blue-50">
        <div class="max-w-7xl mx-auto px-4 sm-px-6 lg-px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl sm-text-4xl font-bold text-gray-900 mb-4">
                    Simple, transparent pricing
                </h2>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                    Start for free and upgrade when you're ready to grow
                </p>
            </div>
            
            <div class="max-w-lg mx-auto">
                <!-- Free Plan Card -->
                <div class="awb-pricing-card bg-white rounded-2xl shadow-lg border-2 border-primary p-8 relative">
                    <div class="absolute -top-4 left-1/2 transform -translate-x-1/2">
                        <span class="bg-primary text-white px-4 py-2 rounded-full text-sm font-semibold">
                            Most Popular
                        </span>
                    </div>
                    
                    <div class="text-center mb-8">
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">Free Forever</h3>
                        <div class="mb-4">
                            <span class="text-5xl font-bold text-gray-900">$0</span>
                            <span class="text-gray-600 ml-2">forever</span>
                        </div>
                        <p class="text-gray-600">Perfect for getting started</p>
                    </div>
                    
                    <ul class="space-y-4 mb-8">
                        <li class="flex items-center">
                            <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-gray-700">1 Website</span>
                        </li>
                        <li class="flex items-center">
                            <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-gray-700">Free Subdomain Included</span>
                        </li>
                        <li class="flex items-center">
                            <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-gray-700">3 AI Website Generations</span>
                        </li>
                        <li class="flex items-center">
                            <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-gray-700">Double-Click Editing</span>
                        </li>
                        <li class="flex items-center">
                            <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-gray-700">Romanian & English Support</span>
                        </li>
                        <li class="flex items-center">
                            <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-gray-700">24/7 Support</span>
                        </li>
                    </ul>
                    
                    <div class="mt-8">
                        <a href="<?php echo esc_url($cta_url ?? 'https://editor.ai-web.site/?edit=true'); ?>" 
                           class="awb-cta-button w-full inline-flex items-center justify-center px-6 py-3 text-lg font-semibold text-white rounded-lg shadow-lg hover:shadow-xl transition-all duration-300">
                        Get Started Free
                        <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                        </svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($show_how_it_works !== 'false'): ?>
    <!-- How It Works Section -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm-px-6 lg-px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl sm-text-4xl font-bold text-gray-900 mb-4">
                    How it works
                </h2>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                    Get your website online in three simple steps
                </p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Step 1 -->
                <div class="text-center">
                    <div class="w-16 h-16 bg-primary-10 rounded-full flex items-center justify-center mx-auto mb-6">
                        <span class="text-2xl font-bold text-primary">1</span>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-3">Design with AI</h3>
                    <p class="text-gray-600 leading-relaxed">
                        Tell our AI what kind of website you want. Get 3 different designs to choose from in seconds.
                    </p>
                </div>
                
                <!-- Step 2 -->
                <div class="text-center">
                    <div class="w-16 h-16 bg-secondary-10 rounded-full flex items-center justify-center mx-auto mb-6">
                        <span class="text-2xl font-bold text-secondary">2</span>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-3">Customize Easily</h3>
                    <p class="text-gray-600 leading-relaxed">
                        Double-click any element to edit it. Change text, images, colors, and layout with ease.
                    </p>
                </div>
                
                <!-- Step 3 -->
                <div class="text-center">
                    <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <span class="text-2xl font-bold text-purple-600">3</span>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-3">Publish & Share</h3>
                    <p class="text-gray-600 leading-relaxed">
                        Create your account and get a free subdomain. Your website goes live instantly.
                    </p>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Final CTA Section -->
    <section class="py-20 awb-gradient-bg">
        <div class="max-w-4xl mx-auto text-center px-4 sm-px-6 lg-px-8">
            <h2 class="text-3xl sm-text-4xl font-bold text-white mb-6">
                Ready to build your dream website?
            </h2>
                <p class="text-xl text-white-90 mb-12 max-w-2xl mx-auto">
                Join thousands of users who have already created amazing websites with our AI-powered builder.
            </p>
            <div class="mt-8 mb-8">
                <a href="<?php echo esc_url($cta_url ?? 'https://editor.ai-web.site/?edit=true'); ?>" 
                   class="inline-flex items-center px-8 py-4 text-lg font-semibold text-primary bg-white rounded-lg shadow-lg hover:shadow-xl transition-all duration-300 hover:transform hover:scale-105">
                <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
                Start Building for Free
                </a>
            </div>
            <p class="text-white-80 text-sm mt-4">No credit card required • Free forever plan</p>
        </div>
    </section>
</div>
