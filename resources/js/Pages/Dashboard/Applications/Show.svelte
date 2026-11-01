<script>
    import { onMount } from 'svelte';
    import { gsap } from 'gsap';
    import { Link, useForm, page } from '@inertiajs/svelte';
    import DashboardLayout from '../../../Layouts/DashboardLayout.svelte';

    export let client = {};

    let cardContainer;
    let copiedKey = null;
    let revealedSecret = null;
    let revealing = false;
    let revealError = null;

    async function toggleSecret() {
        revealError = null;
        if (revealedSecret) {
            revealedSecret = null;
            return;
        }
        revealing = true;
        try {
            const res = await fetch(`/dashboard/applications/${client.id}/secret`, {
                headers: { 'Accept': 'application/json' }
            });
            if (!res.ok) throw new Error('Failed to fetch the secret.');
            const data = await res.json();
            revealedSecret = data.secret;
        } catch (err) {
            revealError = err.message;
        } finally {
            revealing = false;
        }
    }

    $: newSecret = $page.props.flash?.new_secret || null;

    const form = useForm({
        name: client.name || '',
        redirect_uri: client.redirect || ''
    });

    onMount(() => {
        if (cardContainer) {
            gsap.from(cardContainer.children, {
                opacity: 0,
                y: 15,
                duration: 0.5,
                stagger: 0.1,
                ease: 'power2.out'
            });
        }
    });

    async function copyToClipboard(key, text) {
        try {
            await navigator.clipboard.writeText(text);
            copiedKey = key;
            setTimeout(() => copiedKey = null, 2000);
        } catch (err) {
            console.error('Failed to copy', err);
        }
    }

    $: baseUrl = typeof window !== 'undefined' ? window.location.origin : '';
    $: envSnippet = `SSO_CLIENT_ID=${client.id}\nSSO_CLIENT_SECRET=${newSecret || 'YOUR_CLIENT_SECRET'}\nSSO_BASE_URL=${baseUrl}\nSSO_REDIRECT_URI=${client.redirect || ''}`;
    $: authUrl = `${baseUrl}/oauth/authorize?client_id=${client.id}&redirect_uri=${encodeURIComponent(client.redirect || '')}&response_type=code&scope=profile:read&code_challenge=PKCE_CHALLENGE_HERE&code_challenge_method=S256`;

    function updateApp() {
        $form.patch(`/dashboard/applications/${client.id}`);
    }
</script>

<DashboardLayout title="Application: {client.name}">
    <div class="max-w-3xl mx-auto">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-base-content tracking-tight">{client.name}</h2>
                <p class="text-xs text-base-content/50 mt-0.5">OAuth2 client credentials and configuration</p>
            </div>
            <Link href="/dashboard/applications" class="btn btn-ghost btn-sm rounded-xl">
                ? All Applications
            </Link>
        </div>

        <div bind:this={cardContainer} class="space-y-6">
            <!-- Flash Notification: New Secret Warning (Shown once after creation) -->
            {#if newSecret}
                <div class="card bg-warning/10 border border-warning/30 shadow-sm p-6 rounded-2xl">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="badge badge-warning text-xs font-semibold">Save Now</span>
                        <p class="text-xs font-semibold text-warning">This Client Secret is only shown once!</p>
                    </div>

                    <!-- Client ID -->
                    <div class="mb-4">
                        <p class="text-xs font-medium text-base-content/70 mb-1">Client ID</p>
                        <div class="flex items-center gap-2">
                            <code class="flex-1 font-mono text-xs text-base-content bg-base-100 border border-warning/30 rounded-xl px-3 py-2 break-all">{client.id}</code>
                            <button
                                type="button"
                                on:click={() => copyToClipboard('id', client.id)}
                                class="btn btn-outline btn-warning btn-xs rounded-lg"
                            >
                                {copiedKey === 'id' ? 'Copied!' : 'Copy'}
                            </button>
                        </div>
                    </div>

                    <!-- Client Secret -->
                    <div class="mb-4">
                        <p class="text-xs font-medium text-base-content/70 mb-1">Client Secret</p>
                        <div class="flex items-center gap-2">
                            <code class="flex-1 font-mono text-xs text-base-content bg-base-100 border border-warning/30 rounded-xl px-3 py-2 break-all font-bold">{newSecret}</code>
                            <button
                                type="button"
                                on:click={() => copyToClipboard('secret', newSecret)}
                                class="btn btn-outline btn-warning btn-xs rounded-lg"
                            >
                                {copiedKey === 'secret' ? 'Copied!' : 'Copy'}
                            </button>
                        </div>
                    </div>

                    <!-- .env Snippet -->
                    <div class="mb-4">
                        <p class="text-xs font-medium text-base-content/70 mb-1">Format .env Snippet</p>
                        <div class="relative">
                            <pre class="font-mono text-xs text-base-content bg-base-100 border border-warning/30 rounded-xl p-3 overflow-x-auto whitespace-pre">{envSnippet}</pre>
                            <button
                                type="button"
                                on:click={() => copyToClipboard('env', envSnippet)}
                                class="btn btn-outline btn-warning btn-xs absolute top-2 right-2 rounded-lg"
                            >
                                {copiedKey === 'env' ? 'Copied!' : 'Copy .env'}
                            </button>
                        </div>
                    </div>

                    <!-- Auth URL -->
                    <div>
                        <p class="text-xs font-medium text-base-content/70 mb-1">Contoh Authorization URL</p>
                        <div class="flex items-center gap-2">
                            <code class="flex-1 font-mono text-xs text-base-content bg-base-100 border border-warning/30 rounded-xl px-3 py-2 break-all">{authUrl}</code>
                            <button
                                type="button"
                                on:click={() => copyToClipboard('authUrl', authUrl)}
                                class="btn btn-outline btn-warning btn-xs rounded-lg"
                            >
                                {copiedKey === 'authUrl' ? 'Copied!' : 'Copy URL'}
                            </button>
                        </div>
                    </div>
                </div>
            {/if}

            <!-- Credentials Card -->
            <div class="card bg-base-100 border border-base-300 shadow-sm rounded-2xl">
                <div class="card-body p-6 sm:p-8">
                    <h3 class="text-sm font-semibold text-base-content mb-4 pb-2 border-b border-base-200">
                        Credentials & Settings
                    </h3>

                    <div class="space-y-4">
                        <div>
                            <p class="text-xs font-medium text-base-content/50 uppercase tracking-wider mb-1">Client ID</p>
                            <div class="flex items-center gap-2">
                                <code class="font-mono text-xs bg-base-200 px-3 py-2 rounded-xl text-base-content flex-1 break-all">{client.id}</code>
                                <button
                                    type="button"
                                    on:click={() => copyToClipboard('id-main', client.id)}
                                    class="btn btn-outline btn-xs rounded-lg"
                                >
                                    {copiedKey === 'id-main' ? 'Copied!' : 'Copy'}
                                </button>
                            </div>
                        </div>

                        <div>
                            <p class="text-xs font-medium text-base-content/50 uppercase tracking-wider mb-1">Client Secret</p>
                            <div class="flex items-center gap-2">
                                <code class="font-mono text-xs bg-base-200 px-3 py-2 rounded-xl text-base-content flex-1 break-all">
                                    {revealedSecret ?? '•'.repeat(32)}
                                </code>
                                <button
                                    type="button"
                                    on:click={toggleSecret}
                                    disabled={revealing}
                                    class="btn btn-outline btn-xs rounded-lg"
                                    aria-label={revealedSecret ? 'Hide secret' : 'Show secret'}
                                >
                                    {#if revealing}
                                        <span class="loading loading-spinner loading-xs"></span>
                                    {:else if revealedSecret}
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                        </svg>
                                    {:else}
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        </svg>
                                    {/if}
                                </button>
                                {#if revealedSecret}
                                    <button
                                        type="button"
                                        on:click={() => copyToClipboard('secret-main', revealedSecret)}
                                        class="btn btn-outline btn-xs rounded-lg"
                                    >
                                        {copiedKey === 'secret-main' ? 'Copied!' : 'Copy'}
                                    </button>
                                {/if}
                            </div>
                            {#if revealError}
                                <p class="text-xs text-error mt-1">{revealError}</p>
                            {/if}
                        </div>

                        <div>
                            <p class="text-xs font-medium text-base-content/50 uppercase tracking-wider mb-1">Redirect URI</p>
                            <code class="font-mono text-xs bg-base-200 px-3 py-2 rounded-xl text-base-content block break-all">{client.redirect || '-'}</code>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Form Card -->
            <div class="card bg-base-100 border border-base-300 shadow-sm rounded-2xl">
                <div class="card-body p-6 sm:p-8">
                    <h3 class="text-sm font-semibold text-base-content mb-4 pb-2 border-b border-base-200">
                        Update Configuration
                    </h3>

                    <form on:submit|preventDefault={updateApp} class="space-y-5">
                        <div class="form-control">
                            <label class="label" for="edit-name">
                                <span class="label-text font-medium">Application Name</span>
                            </label>
                            <input
                                id="edit-name"
                                type="text"
                                bind:value={$form.name}
                                required
                                class="input input-bordered w-full rounded-xl bg-base-100 focus:border-base-content"
                            />
                            {#if $form.errors.name}
                                <p class="text-xs text-error mt-1">{$form.errors.name}</p>
                            {/if}
                        </div>

                        <div class="form-control">
                            <label class="label" for="edit-redirect">
                                <span class="label-text font-medium">Redirect URI</span>
                            </label>
                            <input
                                id="edit-redirect"
                                type="url"
                                bind:value={$form.redirect_uri}
                                required
                                class="input input-bordered w-full rounded-xl bg-base-100 font-mono text-sm focus:border-base-content"
                            />
                            {#if $form.errors.redirect_uri}
                                <p class="text-xs text-error mt-1">{$form.errors.redirect_uri}</p>
                            {/if}
                        </div>

                        <div class="pt-2 flex justify-end">
                            <button
                                type="submit"
                                class="btn btn-neutral btn-sm rounded-xl px-5"
                                disabled={$form.processing}
                            >
                                {#if $form.processing}
                                    <span class="loading loading-spinner loading-xs"></span>
                                {:else}
                                    Save Changes
                                {/if}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</DashboardLayout>
