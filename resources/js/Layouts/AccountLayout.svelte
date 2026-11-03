<script>
    import { onMount } from 'svelte';
    import { gsap } from 'gsap';
    import { Link, page } from '@inertiajs/svelte';
    import ThemeToggle from '../Components/ThemeToggle.svelte';

    export let title = 'My Account';

    let contentContainer;

    $: currentUrl = $page.url;
    $: user = $page.props.auth?.user;
    $: flash = $page.props.flash || {};

    const navItems = [
        { name: 'Profile & Password', href: '/account', pattern: /^\/account$/ },
        { name: 'Active Sessions', href: '/account/sessions', pattern: /^\/account\/sessions/ },
        { name: 'Two-Factor Security', href: '/account/two-factor', pattern: /^\/account\/two-factor/ },
    ];

    onMount(() => {
        if (contentContainer) {
            gsap.from(contentContainer, {
                opacity: 0,
                y: 12,
                duration: 0.4,
                ease: 'power3.out'
            });
        }
    });
</script>

<svelte:head>
    <title>{title} - SSO Yado</title>
</svelte:head>

<div class="min-h-screen bg-base-200 text-base-content font-sans flex flex-col">
    <!-- Top Header -->
    <header class="relative z-20 bg-base-100/90 backdrop-blur-md border-b border-base-300 sticky top-0 px-4 sm:px-8 py-3 flex flex-wrap items-center justify-between gap-y-2 gap-x-4">
        <div class="flex items-center gap-4 shrink-0">
            <Link href="/" class="flex items-baseline gap-2 group">
                <span class="font-serif italic text-2xl font-bold tracking-tight text-base-content group-hover:opacity-80 transition-opacity">Yado</span>
                <span class="text-[10px] tracking-widest uppercase font-semibold text-base-content/40 bg-base-200 px-1.5 py-0.5 rounded">SSO</span>
            </Link>
            <span class="text-xs font-semibold text-base-content/40 uppercase tracking-wider hidden sm:inline-block">/</span>
            <span class="text-sm font-semibold text-base-content hidden sm:inline-block">Account Settings</span>
        </div>

        <div class="flex items-center gap-3 shrink-0">
            <ThemeToggle />
            {#if user?.role?.slug === 'superadmin'}
                <Link
                    href="/dashboard"
                    class="btn btn-ghost btn-xs rounded-lg gap-1.5 relative z-10"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                    Back to Dashboard
                </Link>
            {/if}
            <Link href="/logout" method="post" as="button" class="btn btn-ghost btn-xs text-error hover:bg-error/10 rounded-lg relative z-10">
                Sign Out
            </Link>
        </div>
    </header>

    <!-- Main Content with Sidebar -->
    <div class="flex-1 max-w-5xl w-full mx-auto p-4 sm:p-8">
        <div class="flex flex-col md:flex-row gap-8">
            <!-- Navigation -->
            <aside class="w-full md:w-56 shrink-0">
                <div class="card bg-base-100 border border-base-300 shadow-sm p-3 rounded-2xl">
                    <nav class="space-y-1">
                        {#each navItems as item}
                            {@const active = item.pattern.test(currentUrl)}
                            <Link
                                href={item.href}
                                class="flex items-center px-3.5 py-2.5 rounded-xl text-xs sm:text-sm font-medium transition-all {active ? 'bg-neutral text-neutral-content shadow-sm' : 'text-base-content/70 hover:text-base-content hover:bg-base-200'}"
                            >
                                {item.name}
                            </Link>
                        {/each}
                    </nav>
                </div>
            </aside>

            <!-- Content Area -->
            <main class="flex-1 min-w-0" bind:this={contentContainer}>
                {#if flash.success}
                    <div class="alert alert-success alert-soft mb-6 text-xs sm:text-sm shadow-sm rounded-2xl flex items-center gap-3">
                        <svg class="w-5 h-5 shrink-0 text-success" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <span>{flash.success}</span>
                    </div>
                {/if}

                {#if flash.error}
                    <div class="alert alert-error alert-soft mb-6 text-xs sm:text-sm shadow-sm rounded-2xl flex items-center gap-3">
                        <svg class="w-5 h-5 shrink-0 text-error" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                        </svg>
                        <span>{flash.error}</span>
                    </div>
                {/if}

                <slot />
            </main>
        </div>
    </div>
</div>
