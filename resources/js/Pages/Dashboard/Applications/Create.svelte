<script>
    import { onMount } from 'svelte';
    import { gsap } from 'gsap';
    import { Link, useForm } from '@inertiajs/svelte';
    import DashboardLayout from '../../../Layouts/DashboardLayout.svelte';

    let cardContainer;
    let uriStatus = null; // 'valid' | 'warning' | 'error' | null
    let uriMessage = '';
    let debounceTimer;

    const form = useForm({
        name: '',
        redirect_uri: ''
    });

    onMount(() => {
        if (cardContainer) {
            gsap.from(cardContainer, {
                opacity: 0,
                y: 15,
                duration: 0.5,
                ease: 'power2.out'
            });
        }
    });

    function validateUri(value) {
        clearTimeout(debounceTimer);
        if (!value) {
            uriStatus = null;
            uriMessage = '';
            return;
        }

        debounceTimer = setTimeout(() => {
            let parsed;
            try {
                parsed = new URL(value);
            } catch {
                uriStatus = 'error';
                uriMessage = 'Invalid URL format (must include a protocol such as https://).';
                return;
            }

            const scheme = parsed.protocol.replace(':', '').toLowerCase();
            const host = parsed.hostname.toLowerCase();

            if (value.includes('#')) {
                uriStatus = 'error';
                uriMessage = 'Redirect URI must not contain a URL fragment (#).';
                return;
            }

            if (['javascript', 'data', 'file'].includes(scheme)) {
                uriStatus = 'error';
                uriMessage = 'That protocol is not allowed.';
                return;
            }

            const isLocalhost = host === 'localhost' || host === '127.0.0.1';
            if (isLocalhost) {
                uriStatus = 'warning';
                uriMessage = 'Using localhost (for testing/development only).';
                return;
            }

            if (scheme === 'http') {
                uriStatus = 'error';
                uriMessage = 'Production URIs must use HTTPS.';
                return;
            }

            if (scheme !== 'https') {
                uriStatus = 'error';
                uriMessage = 'The URI must use HTTPS.';
                return;
            }

            uriStatus = 'valid';
            uriMessage = 'Redirect URI valid.';
        }, 350);
    }

    $: canSubmit = !form.redirect_uri || uriStatus === 'valid' || uriStatus === 'warning';

    function submit() {
        if (!canSubmit) return;
        $form.post('/dashboard/applications');
    }
</script>

<DashboardLayout title="Register Application">
    <div class="max-w-xl mx-auto">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-base-content tracking-tight">New Application</h2>
                <p class="text-xs text-base-content/50 mt-0.5">Register a new client for OAuth2 SSO authentication</p>
            </div>
            <Link href="/dashboard/applications" class="btn btn-ghost btn-sm rounded-xl">
                Cancel
            </Link>
        </div>

        <div bind:this={cardContainer} class="card bg-base-100 border border-base-300 shadow-sm">
            <div class="card-body p-6 sm:p-8">
                <form on:submit|preventDefault={submit} class="space-y-6">
                    {#if $form.hasErrors}
                        <div class="p-3 bg-error/10 border border-error/20 rounded-xl text-error text-xs">
                            Please review and correct the errors below.
                        </div>
                    {/if}

                    <div class="form-control">
                        <label class="label" for="app-name">
                            <span class="label-text font-medium">Application Name</span>
                        </label>
                        <input
                            id="app-name"
                            type="text"
                            bind:value={$form.name}
                            placeholder="e.g. Yado Anime Reader, Frontend Hub"
                            required
                            class="input input-bordered w-full rounded-xl bg-base-100 focus:border-base-content"
                        />
                        {#if $form.errors.name}
                            <p class="text-xs text-error mt-1">{$form.errors.name}</p>
                        {/if}
                    </div>

                    <div class="form-control">
                        <label class="label" for="app-redirect">
                            <span class="label-text font-medium">Redirect URI (Callback URL)</span>
                        </label>
                        <input
                            id="app-redirect"
                            type="url"
                            bind:value={$form.redirect_uri}
                            on:input={(e) => validateUri(e.target.value)}
                            placeholder="https://app.example.com/auth/callback"
                            required
                            class="input input-bordered w-full rounded-xl bg-base-100 font-mono text-sm focus:border-base-content {uriStatus === 'error' ? 'input-error' : uriStatus === 'warning' ? 'input-warning' : uriStatus === 'valid' ? 'input-success' : ''}"
                        />

                        {#if uriMessage}
                            <p class="text-xs mt-1.5 {uriStatus === 'error' ? 'text-error' : uriStatus === 'warning' ? 'text-warning' : 'text-success'}">
                                {uriMessage}
                            </p>
                        {/if}

                        {#if $form.errors.redirect_uri}
                            <p class="text-xs text-error mt-1">{$form.errors.redirect_uri}</p>
                        {/if}
                        <p class="text-[11px] text-base-content/40 mt-1">
                            The URL users are redirected to after approving the OAuth2 login permission.
                        </p>
                    </div>

                    <div class="pt-4 border-t border-base-200 flex items-center justify-end gap-3">
                        <Link href="/dashboard/applications" class="btn btn-ghost btn-sm rounded-xl">
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            class="btn btn-neutral btn-sm rounded-xl px-5"
                            disabled={$form.processing || ($form.redirect_uri && !canSubmit)}
                        >
                            {#if $form.processing}
                                <span class="loading loading-spinner loading-xs"></span>
                            {:else}
                                Create Application
                            {/if}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</DashboardLayout>
