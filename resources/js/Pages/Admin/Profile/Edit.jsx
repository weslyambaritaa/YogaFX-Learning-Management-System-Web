import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import PasswordField from "@/Components/PasswordField";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Head, useForm, usePage } from "@inertiajs/react";

export default function Edit({ status }) {
    const user = usePage().props.auth.user;

    const { data, setData, patch, processing, errors, reset } = useForm({
        first_name: user.first_name ?? "",
        last_name: user.last_name ?? "",
        email: user.email ?? "",
        current_password: "",
        password: "",
        password_confirmation: "",
    });

    const submit = (event) => {
        event.preventDefault();

        patch(route("admin.profile.update"), {
            onSuccess: () =>
                reset("current_password", "password", "password_confirmation"),
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Admin Profile" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {status === "admin-profile-updated" && (
                        <div className="rounded-[5px] border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Profile updated.
                        </div>
                    )}

                    <div className="rounded-[5px] bg-white p-6 shadow-sm">
                        <h3 className="text-lg font-semibold text-slate-900">
                            Personal Information
                        </h3>
                        <p className="mt-1 text-sm text-slate-500">
                            Your name and email are used to identify your admin
                            account.
                        </p>

                        <form onSubmit={submit} className="mt-6 space-y-6">
                            <div className="grid gap-6 md:grid-cols-2">
                                <div className="space-y-2">
                                    <label
                                        htmlFor="first_name"
                                        className="text-sm font-medium text-slate-700"
                                    >
                                        First Name
                                    </label>
                                    <Input
                                        id="first_name"
                                        value={data.first_name}
                                        onChange={(event) =>
                                            setData(
                                                "first_name",
                                                event.target.value,
                                            )
                                        }
                                        className="h-10 rounded-[5px]"
                                    />
                                    {errors.first_name ? (
                                        <p className="text-sm text-rose-600">
                                            {errors.first_name}
                                        </p>
                                    ) : null}
                                </div>

                                <div className="space-y-2">
                                    <label
                                        htmlFor="last_name"
                                        className="text-sm font-medium text-slate-700"
                                    >
                                        Last Name
                                    </label>
                                    <Input
                                        id="last_name"
                                        value={data.last_name}
                                        onChange={(event) =>
                                            setData(
                                                "last_name",
                                                event.target.value,
                                            )
                                        }
                                        className="h-10 rounded-[5px]"
                                    />
                                    {errors.last_name ? (
                                        <p className="text-sm text-rose-600">
                                            {errors.last_name}
                                        </p>
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
                                            setData("email", event.target.value)
                                        }
                                        className="h-10 rounded-[5px]"
                                    />
                                    {errors.email ? (
                                        <p className="text-sm text-rose-600">
                                            {errors.email}
                                        </p>
                                    ) : null}
                                </div>
                            </div>

                            <div className="border-t border-slate-200 pt-6">
                                <h3 className="text-base font-semibold text-slate-900">
                                    Change Password
                                </h3>
                                <p className="mt-1 text-sm text-slate-500">
                                    Leave the password fields empty if you do not
                                    want to change it.
                                </p>

                                <div className="mt-4 grid gap-6 md:grid-cols-2">
                                    <div className="space-y-2 md:col-span-2">
                                        <label
                                            htmlFor="current_password"
                                            className="text-sm font-medium text-slate-700"
                                        >
                                            Current Password
                                        </label>
                                        <PasswordField
                                            id="current_password"
                                            value={data.current_password}
                                            onChange={(event) =>
                                                setData(
                                                    "current_password",
                                                    event.target.value,
                                                )
                                            }
                                            inputClassName="border-slate-300 bg-white text-slate-900"
                                            autoComplete="current-password"
                                        />
                                        {errors.current_password ? (
                                            <p className="text-sm text-rose-600">
                                                {errors.current_password}
                                            </p>
                                        ) : null}
                                    </div>

                                    <div className="space-y-2">
                                        <label
                                            htmlFor="password"
                                            className="text-sm font-medium text-slate-700"
                                        >
                                            New Password
                                        </label>
                                        <PasswordField
                                            id="password"
                                            value={data.password}
                                            onChange={(event) =>
                                                setData(
                                                    "password",
                                                    event.target.value,
                                                )
                                            }
                                            inputClassName="border-slate-300 bg-white text-slate-900"
                                            autoComplete="new-password"
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
                                            Confirm New Password
                                        </label>
                                        <PasswordField
                                            id="password_confirmation"
                                            value={data.password_confirmation}
                                            onChange={(event) =>
                                                setData(
                                                    "password_confirmation",
                                                    event.target.value,
                                                )
                                            }
                                            inputClassName="border-slate-300 bg-white text-slate-900"
                                            autoComplete="new-password"
                                        />
                                        {errors.password_confirmation ? (
                                            <p className="text-sm text-rose-600">
                                                {errors.password_confirmation}
                                            </p>
                                        ) : null}
                                    </div>
                                </div>
                            </div>

                            <div className="flex justify-end">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="rounded-[5px] bg-[#DB202C] px-6 text-white hover:bg-[#c31c28]"
                                >
                                    Save Profile
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
