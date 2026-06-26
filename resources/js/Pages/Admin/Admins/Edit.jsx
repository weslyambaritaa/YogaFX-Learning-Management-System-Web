import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

function roleLabel(role) {
    return role === 'super_admin' ? 'Super Admin' : 'Admin';
}

export default function EditAdmin({ adminAccount, status }) {
    const { data, setData, patch, processing, errors } = useForm({
        name: adminAccount.name ?? '',
        email: adminAccount.email ?? '',
        role: adminAccount.role ?? 'admin',
        password: '',
        password_confirmation: '',
    });

    const submit = (event) => {
        event.preventDefault();

        patch(route('admin.admins.update', adminAccount.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            Edit Admin
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            Update admin identity, password, and role access within the
                            shared admin domain.
                        </p>
                    </div>

                    <Link
                        href={route('admin.admins.index')}
                        className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                    >
                        Back to Admin
                    </Link>
                </div>
            }
        >
            <Head title="Edit Admin" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {status === 'admin-account-updated' ? (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Admin account has been updated.
                        </div>
                    ) : null}

                    <div className="grid gap-4 md:grid-cols-3">
                        <div className="rounded-lg bg-white p-5 shadow-sm">
                            <div className="text-sm text-slate-500">Admin</div>
                            <div className="mt-1 text-lg font-semibold text-slate-900">
                                {adminAccount.name}
                            </div>
                            <div className="mt-2 text-sm text-slate-600">
                                {adminAccount.email}
                            </div>
                        </div>

                        <div className="rounded-lg bg-white p-5 shadow-sm">
                            <div className="text-sm text-slate-500">Current Role</div>
                            <div className="mt-3">
                                <span className="inline-flex rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-sm font-medium text-slate-700">
                                    {roleLabel(adminAccount.role)}
                                </span>
                            </div>
                        </div>

                        <div className="rounded-lg bg-white p-5 shadow-sm">
                            <div className="text-sm text-slate-500">Created Date</div>
                            <div className="mt-1 text-lg font-semibold text-slate-900">
                                {adminAccount.created_at}
                            </div>
                        </div>
                    </div>

                    <div className="rounded-lg bg-white p-6 shadow-sm">
                        <form onSubmit={submit} className="space-y-6">
                            <div className="grid gap-6 md:grid-cols-2">
                                <div className="space-y-2 md:col-span-2">
                                    <label
                                        htmlFor="name"
                                        className="text-sm font-medium text-slate-700"
                                    >
                                        Name
                                    </label>
                                    <Input
                                        id="name"
                                        value={data.name}
                                        onChange={(event) =>
                                            setData('name', event.target.value)
                                        }
                                        className="h-10"
                                    />
                                    {errors.name ? (
                                        <p className="text-sm text-rose-600">{errors.name}</p>
                                    ) : null}
                                </div>

                                <div className="space-y-2 md:col-span-2">
                                    <label
                                        htmlFor="email"
                                        className="text-sm font-medium text-slate-700"
                                    >
                                        Email
                                    </label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(event) =>
                                            setData('email', event.target.value)
                                        }
                                        className="h-10"
                                    />
                                    {errors.email ? (
                                        <p className="text-sm text-rose-600">{errors.email}</p>
                                    ) : null}
                                </div>

                                <div className="space-y-2 md:col-span-2">
                                    <label
                                        htmlFor="role"
                                        className="text-sm font-medium text-slate-700"
                                    >
                                        Role
                                    </label>
                                    <select
                                        id="role"
                                        value={data.role}
                                        onChange={(event) =>
                                            setData('role', event.target.value)
                                        }
                                        disabled={!adminAccount.can_change_role}
                                        className="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700 disabled:cursor-not-allowed disabled:bg-slate-100"
                                    >
                                        <option value="super_admin">Super Admin</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                    {!adminAccount.can_change_role ? (
                                        <p className="text-sm text-slate-500">
                                            You cannot lower your own role from this screen.
                                        </p>
                                    ) : null}
                                    {errors.role ? (
                                        <p className="text-sm text-rose-600">{errors.role}</p>
                                    ) : null}
                                </div>

                                <div className="space-y-2">
                                    <label
                                        htmlFor="password"
                                        className="text-sm font-medium text-slate-700"
                                    >
                                        New Password
                                    </label>
                                    <Input
                                        id="password"
                                        type="password"
                                        value={data.password}
                                        onChange={(event) =>
                                            setData('password', event.target.value)
                                        }
                                        className="h-10"
                                    />
                                    {errors.password ? (
                                        <p className="text-sm text-rose-600">
                                            {errors.password}
                                        </p>
                                    ) : null}
                                </div>

                                <div className="space-y-2">
                                    <label
                                        htmlFor="password_confirmation"
                                        className="text-sm font-medium text-slate-700"
                                    >
                                        Confirm Password
                                    </label>
                                    <Input
                                        id="password_confirmation"
                                        type="password"
                                        value={data.password_confirmation}
                                        onChange={(event) =>
                                            setData(
                                                'password_confirmation',
                                                event.target.value,
                                            )
                                        }
                                        className="h-10"
                                    />
                                </div>
                            </div>

                            <div className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                Super admin can edit all admin accounts. Self-delete and
                                self-downgrade remain blocked by policy.
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    Save Admin
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
