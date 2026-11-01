<script>
    import { onMount } from 'svelte';
    import { gsap } from 'gsap';
    import { Link, page } from '@inertiajs/svelte';

    export let status;

    let container;

    const messages = {
        403: {
            title: 'No Permission',
            body: 'You do not have access to this page.',
        },
        404: {
            title: 'Not Found',
            body: 'The page you are looking for was not found.',
        },
        405: {
            title: 'Method Not Allowed',
            body: 'This request is not supported by the server.',
        },
        419: {
            title: 'Session Expired',
            body: 'Your session has expired, please reload the page.',
        },
        429: {
            title: 'Too Many Requests',
            body: 'Too many requests, please try again shortly.',
        },
        500: {
            title: 'Server Error',
            body: 'A server error occurred. Please try again later.',
        },
        503: {
            title: 'Unavailable',
            body: 'The service is under maintenance. Please try again later.',
        },
    };

    $: message = messages[status] || { title: 'Error', body: 'An unexpected error occurred.' };
    $: user = $page.props.auth?.user;
    $: homeHref = user ? (user.role?.slug === 'superadmin' ? '/dashboard' : '/account') : '/';

    onMount(() => {
        if (container) {
            gsap.from(container.children, {
                y: 20,
                opacity: 0,
                duration: 0.6,
                stagger: 0.1,
                ease: 'power3.out',
            });
        }
    });
</script>

<svelte:head>
    <title>{status} {message.title} - SSO Yado</title>
</svelte:head>

<div class="min-h-screen flex flex-col items-center justify-center bg-base-100 text-base-content p-6">
    <div bind:this={container} class="max-w-md text-center space-y-4">
        <p class="text-sm font-mono tracking-widest uppercase text-base-content/40">Error {status}</p>
        <h1 class="text-4xl font-serif italic tracking-tight text-base-content">{message.title}</h1>
        <p class="text-base-content/60">{message.body}</p>
        <div class="pt-4">
            <Link href={homeHref} class="btn btn-neutral rounded-xl">
                {user ? 'Back' : 'Go to Home'}
            </Link>
        </div>
    </div>
</div>
