<script>
    import { onMount } from 'svelte';
    import { gsap } from 'gsap';
    import { Link, useForm } from '@inertiajs/svelte';
    import DashboardLayout from '../../../Layouts/DashboardLayout.svelte';

    export let roles = [];

    let cardContainer;

    const form = useForm({
        email: '',
        role_id: roles[0]?.id || ''
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

    function submit() {
        $form.post('/dashboard/users/invite');
    }
</script>

<DashboardLayout title="Invite User">
    <div class="max-w-lg mx-auto">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-base-content tracking-tight">Invite Member</h2>
                <p class="text-xs text-base-content/50 mt-0.5">Send an invitation email to add a new member to Yado SSO</p>
            </div>
            <Link href="/dashboard/users" class="btn btn-ghost btn-sm rounded-xl">
                Cancel
            </Link>
        </div>

        <div bind:this={cardContainer} class="card bg-base-100 border border-base-300 shadow-sm rounded-2xl">
            <div class="card-body p-6 sm:p-8">
                <form on:submit|preventDefault={submit} class="space-y-5">
                    {#if $form.hasErrors}
                        <div class="p-3 bg-error/10 border border-error/20 rounded-xl text-error text-xs">
                            Please check the form input below.
                        </div>
                    {/if}

                    <div class="form-control">
                        <label class="label" for="invite-email">
                            <span class="label-text font-medium">Email Address</span>
                        </label>
                        <input
                            id="invite-email"
                            type="email"
                            bind:value={$form.email}
                            placeholder="colleague@example.com"
                            required
                            class="input input-bordered w-full rounded-xl bg-base-100 focus:border-base-content"
                        />
                        {#if $form.errors.email}
                            <p class="text-xs text-error mt-1">{$form.errors.email}</p>
                        {/if}
                    </div>

                    <div class="form-control">
                        <label class="label" for="invite-role">
                            <span class="label-text font-medium">Initial Role</span>
                        </label>
                        <select
                            id="invite-role"
                            bind:value={$form.role_id}
                            class="select select-bordered w-full rounded-xl bg-base-100 focus:border-base-content"
                        >
                            {#each roles as role}
                                <option value={role.id}>{role.name}</option>
                            {/each}
                        </select>
                        {#if $form.errors.role_id}
                            <p class="text-xs text-error mt-1">{$form.errors.role_id}</p>
                        {/if}
                        <p class="text-[11px] text-base-content/40 mt-1.5">
                            The invitation contains a unique link that lets the recipient register an account.
                        </p>
                    </div>

                    <div class="pt-4 border-t border-base-200 flex items-center justify-end gap-3">
                        <Link href="/dashboard/users" class="btn btn-ghost btn-sm rounded-xl">
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            class="btn btn-neutral btn-sm rounded-xl px-5"
                            disabled={$form.processing}
                        >
                            {#if $form.processing}
                                <span class="loading loading-spinner loading-xs"></span>
                            {:else}
                                Send Invitation
                            {/if}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</DashboardLayout>
