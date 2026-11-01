<script>
    import { onMount } from 'svelte';
    import { gsap } from 'gsap';
    import { Link, router, useForm } from '@inertiajs/svelte';
    import DashboardLayout from '../../../Layouts/DashboardLayout.svelte';

    export let users = { data: [], links: [] };
    export let roles = [];

    let tableContainer;
    let passwordDialog;
    let activePasswordUser = null;

    const passwordForm = useForm({
        new_password: '',
        new_password_confirmation: ''
    });

    onMount(() => {
        if (tableContainer) {
            gsap.from(tableContainer, {
                opacity: 0,
                y: 15,
                duration: 0.5,
                ease: 'power2.out'
            });
        }
    });

    function toggleActive(user) {
        const action = user.is_active ? 'deactivate' : 'activate';
        if (confirm(`Are you sure you want to ${action} user "${user.name}"?`)) {
            router.patch(`/dashboard/users/${user.id}/toggle-active`, {}, {
                onError: (errors) => alert(Object.values(errors)[0] || 'Failed to change the user status.')
            });
        }
    }

    function changeRole(user, event) {
        const roleId = event.target.value;
        router.patch(`/dashboard/users/${user.id}/role`, {
            role_id: roleId
        }, {
            onError: (errors) => alert(Object.values(errors)[0] || 'Failed to change the user role.')
        });
    }

    function openPasswordModal(user) {
        activePasswordUser = user;
        $passwordForm.reset();
        $passwordForm.clearErrors();
        passwordDialog?.showModal();
    }

    function closePasswordModal() {
        passwordDialog?.close();
        activePasswordUser = null;
    }

    function submitPassword() {
        if (!activePasswordUser) return;
        $passwordForm.patch(`/dashboard/users/${activePasswordUser.id}/password`, {
            onSuccess: () => {
                $passwordForm.reset();
                closePasswordModal();
            }
        });
    }

    function formatDate(dateStr) {
        if (!dateStr) return '-';
        return new Date(dateStr).toLocaleDateString('en-GB', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
    }
</script>

<DashboardLayout title="User Management">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-base-content">Users & Permissions</h2>
            <p class="text-xs text-base-content/50 mt-0.5">Manage registered accounts, roles, and administrative access</p>
        </div>

        <Link href="/dashboard/users/invite" class="btn btn-neutral btn-sm gap-2 rounded-xl">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z" />
            </svg>
            Invite User
        </Link>
    </div>

    <div bind:this={tableContainer} class="card bg-base-100 border border-base-300 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table w-full">
                <thead class="bg-base-200/50 text-base-content/60 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="py-3 px-6">User</th>
                        <th class="py-3 px-4 hidden sm:table-cell">Role</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 hidden md:table-cell">Joined</th>
                        <th class="py-3 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-base-200">
                    {#if !users.data || users.data.length === 0}
                        <tr>
                            <td colspan="5" class="text-center py-16 text-base-content/50 text-sm">
                                No users found.
                            </td>
                        </tr>
                    {:else}
                        {#each users.data as user}
                            <tr class="hover:bg-base-200/40 transition-colors">
                                <td class="py-4 px-6">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-neutral text-neutral-content flex items-center justify-center font-bold text-xs shrink-0">
                                            {user.name ? user.name.charAt(0).toUpperCase() : 'U'}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-base-content truncate">{user.name}</p>
                                            <p class="text-xs text-base-content/50 font-mono truncate">{user.email}</p>
                                        </div>
                                    </div>
                                </td>

                                <td class="py-4 px-4 hidden sm:table-cell">
                                    <select
                                        class="select select-bordered select-xs w-32 rounded-lg bg-base-100 text-xs font-medium"
                                        value={user.role_id}
                                        on:change={(e) => changeRole(user, e)}
                                    >
                                        {#each roles as role}
                                            <option value={role.id}>{role.name}</option>
                                        {/each}
                                    </select>
                                </td>

                                <td class="py-4 px-4">
                                    {#if user.is_active}
                                        <span class="badge badge-success badge-soft badge-xs font-medium px-2 py-1">Active</span>
                                    {:else}
                                        <span class="badge badge-error badge-soft badge-xs font-medium px-2 py-1">Inactive</span>
                                    {/if}
                                </td>

                                <td class="py-4 px-4 text-xs text-base-content/60 hidden md:table-cell whitespace-nowrap">
                                    {formatDate(user.created_at)}
                                </td>

                                <td class="py-4 px-6 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-2">
                                        <!-- Toggle Active Button -->
                                        <button
                                            type="button"
                                            class="btn btn-xs rounded-lg {user.is_active ? 'btn-ghost text-error hover:bg-error/10' : 'btn-outline btn-success'}"
                                            on:click={() => toggleActive(user)}
                                        >
                                            {user.is_active ? 'Deactivate' : 'Activate'}
                                        </button>

                                        <!-- Password Reset -->
                                        <button
                                            type="button"
                                            class="btn btn-outline btn-xs rounded-lg font-medium"
                                            on:click={() => openPasswordModal(user)}
                                        >
                                            Password
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        {/each}
                    {/if}
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        {#if users.links && users.links.length > 3}
            <div class="p-4 border-t border-base-200 flex items-center justify-between">
                <p class="text-xs text-base-content/50">
                    Showing {users.data.length} of {users.total || users.data.length} users
                </p>
                <div class="join">
                    {#each users.links as link}
                        {#if link.url}
                            <Link
                                href={link.url}
                                class="join-item btn btn-xs {link.active ? 'btn-neutral' : 'btn-ghost'}"
                            >
                                {@html link.label}
                            </Link>
                        {:else}
                            <span class="join-item btn btn-xs btn-disabled opacity-40">
                                {@html link.label}
                            </span>
                        {/if}
                    {/each}
                </div>
            </div>
        {/if}
    </div>

    <!-- Change Password Modal -->
    <dialog bind:this={passwordDialog} class="modal" on:close={() => activePasswordUser = null}>
        <div class="modal-box max-w-sm rounded-2xl">
            <div class="flex items-center justify-between mb-4 pb-3 border-b border-base-200">
                <div>
                    <h4 class="text-sm font-bold text-base-content">Change Password</h4>
                    {#if activePasswordUser}
                        <p class="text-xs text-base-content/50 mt-0.5">{activePasswordUser.name}</p>
                    {/if}
                </div>
                <button type="button" class="btn btn-ghost btn-xs btn-circle" on:click={closePasswordModal} aria-label="Close">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form on:submit|preventDefault={submitPassword} class="space-y-4">
                <div class="form-control">
                    <label class="label" for="user-new-pass">
                        <span class="label-text text-sm font-medium">New Password</span>
                    </label>
                    <input
                        id="user-new-pass"
                        type="password"
                        bind:value={$passwordForm.new_password}
                        placeholder="Min 8 characters"
                        required
                        class="input input-bordered w-full rounded-xl"
                    />
                    {#if $passwordForm.errors.new_password}
                        <p class="text-xs text-error mt-1">{$passwordForm.errors.new_password}</p>
                    {/if}
                </div>

                <div class="form-control">
                    <label class="label" for="user-new-pass-confirm">
                        <span class="label-text text-sm font-medium">Confirm Password</span>
                    </label>
                    <input
                        id="user-new-pass-confirm"
                        type="password"
                        bind:value={$passwordForm.new_password_confirmation}
                        placeholder="Repeat password"
                        required
                        class="input input-bordered w-full rounded-xl"
                    />
                </div>

                <div class="pt-2 flex justify-end gap-2">
                    <button type="button" class="btn btn-ghost btn-sm rounded-xl" on:click={closePasswordModal}>
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-neutral btn-sm rounded-xl px-4" disabled={$passwordForm.processing}>
                        {#if $passwordForm.processing}
                            <span class="loading loading-spinner loading-xs"></span>
                        {:else}
                            Update
                        {/if}
                    </button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button on:click={closePasswordModal}>close</button>
        </form>
    </dialog>
</DashboardLayout>
