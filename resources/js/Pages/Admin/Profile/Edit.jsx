import InputError from '@/Components/InputError';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';

export default function AdminProfileEdit({ status }) {
    const { auth } = usePage().props;
    const user = auth.user;

    const form = useForm({
        first_name: user.first_name ?? '',
        last_name: user.last_name ?? '',
        email: user.email ?? '',
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (event) => {
        event.preventDefault();

        form.patch(route('admin.profile.update'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('current_password', 'password', 'password_confirmation');
            },
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="min-w-0">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Profile
                    </h2>
                    <p className="mt-1 text-sm text-slate-500">
                        Update the admin account details shown across the console.
                    </p>
                </div>
            }
        >
            <Head title="Admin Profile" />

            <div className="py-12">
                <div className="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {status === 'admin-profile-updated' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Admin profile has been updated.
                        </div>
                    )}

                    <form
                        onSubmit={submit}
                        className="space-y-8 rounded-2xl bg-white p-6 shadow-sm sm:p-8"
                    >
                        <section className="space-y-5">
                            <div>
                                <h3 className="text-lg font-semibold text-slate-900">
                                    Account Details
                                </h3>
                                <p className="mt-1 text-sm text-slate-500">
                                    These details are used for the admin identity in the topbar and account records.
                                </p>
                            </div>

                            <div className="grid gap-5 md:grid-cols-2">
                                <div className="space-y-2">
                                    <label className="text-sm font-medium text-slate-700">
                                        First name
                                    </label>
                                    <Input
                                        value={form.data.first_name}
                                        onChange={(event) => form.setData('first_name', event.target.value)}
                                        autoComplete="given-name"
                                    />
                                    <InputError message={form.errors.first_name} />
                                </div>

                                <div className="space-y-2">
                                    <label className="text-sm font-medium text-slate-700">
                                        Last name
                                    </label>
                                    <Input
                                        value={form.data.last_name}
                                        onChange={(event) => form.setData('last_name', event.target.value)}
                                        autoComplete="family-name"
                                    />
                                    <InputError message={form.errors.last_name} />
                                </div>
                            </div>

                            <div className="space-y-2">
                                <label className="text-sm font-medium text-slate-700">
                                    Email
                                </label>
                                <Input
                                    type="email"
                                    value={form.data.email}
                                    onChange={(event) => form.setData('email', event.target.value)}
                                    autoComplete="email"
                                />
                                <InputError message={form.errors.email} />
                            </div>
                        </section>

                        <section className="space-y-5 rounded-2xl border border-slate-200 p-5">
                            <div>
                                <h3 className="text-lg font-semibold text-slate-900">
                                    Change Password
                                </h3>
                                <p className="mt-1 text-sm text-slate-500">
                                    Leave these fields empty if you do not want to change the current password.
                                </p>
                            </div>

                            <div className="grid gap-5 md:grid-cols-2">
                                <div className="space-y-2 md:col-span-2">
                                    <label className="text-sm font-medium text-slate-700">
                                        Current password
                                    </label>
                                    <Input
                                        type="password"
                                        value={form.data.current_password}
                                        onChange={(event) => form.setData('current_password', event.target.value)}
                                        autoComplete="current-password"
                                    />
                                    <InputError message={form.errors.current_password} />
                                </div>

                                <div className="space-y-2">
                                    <label className="text-sm font-medium text-slate-700">
                                        New password
                                    </label>
                                    <Input
                                        type="password"
                                        value={form.data.password}
                                        onChange={(event) => form.setData('password', event.target.value)}
                                        autoComplete="new-password"
                                    />
                                    <InputError message={form.errors.password} />
                                </div>

                                <div className="space-y-2">
                                    <label className="text-sm font-medium text-slate-700">
                                        Confirm new password
                                    </label>
                                    <Input
                                        type="password"
                                        value={form.data.password_confirmation}
                                        onChange={(event) =>
                                            form.setData('password_confirmation', event.target.value)
                                        }
                                        autoComplete="new-password"
                                    />
                                </div>
                            </div>
                        </section>

                        <div className="flex justify-end">
                            <Button
                                type="submit"
                                size="lg"
                                disabled={form.processing}
                                className="min-w-36 bg-slate-950 text-white shadow-lg shadow-slate-950/20 hover:bg-slate-800"
                            >
                                Save Changes
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
