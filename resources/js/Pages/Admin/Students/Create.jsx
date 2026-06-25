import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function CreateStudent({ accessTiers }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        password_confirmation: '',
        access_tier_id: '',
    });

    const submit = (event) => {
        event.preventDefault();

        post(route('admin.students.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            Create Student
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            Create a student account with login access and an assigned
                            access tier.
                        </p>
                    </div>

                    <Link
                        href={route('admin.students.index')}
                        className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                    >
                        Back to Students
                    </Link>
                </div>
            }
        >
            <Head title="Create Student" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                    <div className="rounded-lg bg-white p-6 shadow-sm">
                        <form onSubmit={submit} className="space-y-6">
                            <div className="grid gap-6 md:grid-cols-2">
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
                                        placeholder="student@yogafx.com"
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
                                    <Input
                                        id="password"
                                        type="password"
                                        value={data.password}
                                        onChange={(event) =>
                                            setData('password', event.target.value)
                                        }
                                        className="h-10"
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

                                <div className="space-y-2 md:col-span-2">
                                    <label
                                        htmlFor="access_tier_id"
                                        className="text-sm font-medium text-slate-700"
                                    >
                                        Access Tier
                                    </label>
                                    <select
                                        id="access_tier_id"
                                        value={data.access_tier_id}
                                        onChange={(event) =>
                                            setData('access_tier_id', event.target.value)
                                        }
                                        className="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700"
                                    >
                                        <option value="">Select access tier</option>
                                        {accessTiers.map((accessTier) => (
                                            <option key={accessTier.id} value={accessTier.id}>
                                                {accessTier.name}
                                                {!accessTier.is_active ? ' (Inactive)' : ''}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.access_tier_id && (
                                        <p className="text-sm text-rose-600">
                                            {errors.access_tier_id}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                The student will be created as active and can log in
                                immediately. After login, the existing student profile
                                completion gate will still apply.
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    Create Student
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
