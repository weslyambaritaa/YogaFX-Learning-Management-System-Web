import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import PasswordField from '@/Components/PasswordField';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function CreateAdmin() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (event) => {
        event.preventDefault();

        post(route('admin.admins.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            Create Admin
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            Add another admin account with the same platform access level
                            as the current admin role.
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
            <Head title="Create Admin" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
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
                                        placeholder="Admin name"
                                        className="h-10"
                                    />
                                    {errors.name && (
                                        <p className="text-sm text-rose-600">{errors.name}</p>
                                    )}
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
                                        placeholder="admin@yogafx.com"
                                        className="h-10"
                                    />
                                    {errors.email && (
                                        <p className="text-sm text-rose-600">{errors.email}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <label
                                        htmlFor="password"
                                        className="text-sm font-medium text-slate-700"
                                    >
                                        Password
                                    </label>
                                    <PasswordField
                                        id="password"
                                        value={data.password}
                                        onChange={(event) =>
                                            setData('password', event.target.value)
                                        }
                                        inputClassName="border-slate-300 bg-white text-slate-900"
                                        autoComplete="new-password"
                                    />
                                    {errors.password && (
                                        <p className="text-sm text-rose-600">
                                            {errors.password}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <label
                                        htmlFor="password_confirmation"
                                        className="text-sm font-medium text-slate-700"
                                    >
                                        Confirm Password
                                    </label>
                                    <PasswordField
                                        id="password_confirmation"
                                        value={data.password_confirmation}
                                        onChange={(event) =>
                                            setData(
                                                'password_confirmation',
                                                event.target.value,
                                            )
                                        }
                                        inputClassName="border-slate-300 bg-white text-slate-900"
                                        autoComplete="new-password"
                                    />
                                </div>
                            </div>

                            <div className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                Only super admin can add admin accounts from this screen.
                                New accounts are created as regular admin users, while password
                                resets should continue through the existing forgot password flow.
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    Create Admin
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
