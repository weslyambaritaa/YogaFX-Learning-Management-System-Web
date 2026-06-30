import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import TextInput from "@/Components/TextInput";
import { Button } from "@/Components/ui/button";
import { Head, Link, useForm } from "@inertiajs/react";

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: "",
        password: "",
    });

    const submit = (e) => {
        e.preventDefault();

        post(route("login"), {
            onFinish: () => reset("password"),
        });
    };

    return (
        <>
            <Head title="Login" />

            <div className="min-h-screen bg-black text-white">
                <div className="relative mx-auto flex min-h-screen max-w-[1280px] flex-col px-4 py-6 sm:px-6 lg:px-10">

                    {/* Logo + Form dibungkus satu blok di tengah layar */}
                    <div className="flex flex-1 flex-col items-center justify-center pb-12 pt-6 lg:pt-8">

                        {/* Logo */}
                        <header className="mb-8 flex items-center justify-center">
                            <Link href="/">
                                <img
                                    src="https://yogafx.b-cdn.net/content/Logo%20YogAFX.png"
                                    alt="YogaFX"
                                    className="h-16 w-auto object-contain"
                                />
                            </Link>
                        </header>

                        {/* Form */}
                        <div className="w-full max-w-xl">
                            <form onSubmit={submit} className="space-y-6">
                                {status && (
                                    <p className="font-['Montserrat'] text-[14px] font-medium leading-6 text-green-400">
                                        {status}
                                    </p>
                                )}

                                <div>
                                    <InputLabel
                                        htmlFor="email"
                                        value="Email"
                                        className="font-['Montserrat'] text-[14px] font-medium text-white/80"
                                    />
                                    <TextInput
                                        id="email"
                                        type="email"
                                        name="email"
                                        value={data.email}
                                        className="mt-2 block w-full rounded-[5px] border-white/20 bg-white/10 px-[10px] py-[8px] font-['Montserrat'] text-[14px] font-medium text-white placeholder:text-white/30"
                                        autoComplete="username"
                                        isFocused={true}
                                        onChange={(e) =>
                                            setData("email", e.target.value)
                                        }
                                    />
                                    <InputError
                                        message={errors.email}
                                        className="mt-2 font-['Montserrat'] text-[14px] font-medium text-red-400"
                                    />
                                </div>

                                <div>
                                    <InputLabel
                                        htmlFor="password"
                                        value="Password"
                                        className="font-['Montserrat'] text-[14px] font-medium text-white/80"
                                    />
                                    <TextInput
                                        id="password"
                                        type="password"
                                        name="password"
                                        value={data.password}
                                        className="mt-2 block w-full rounded-[5px] border-white/20 bg-white/10 px-[10px] py-[8px] font-['Montserrat'] text-[14px] font-medium text-white placeholder:text-white/30"
                                        autoComplete="current-password"
                                        onChange={(e) =>
                                            setData("password", e.target.value)
                                        }
                                    />
                                    <InputError
                                        message={errors.password}
                                        className="mt-2 font-['Montserrat'] text-[14px] font-medium text-red-400"
                                    />
                                </div>

                                <div className="flex flex-wrap items-center justify-end gap-4">
                                    {canResetPassword && (
                                        <Link
                                            href={route("password.request")}
                                            className="font-['Montserrat'] text-[14px] font-medium text-white/70 underline hover:text-white"
                                        >
                                            Forgot your password?
                                        </Link>
                                    )}
                                </div>

                                <div className="flex justify-end">
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="rounded-[5px] px-[10px] py-[8px] font-['Montserrat'] text-[14px] font-medium bg-[#DB202C] text-white hover:bg-[#c01a25]"
                                    >
                                        {processing ? "Logging in..." : "Log in"}
                                    </Button>
                                </div>
                            </form>
                        </div>

                    </div>
                </div>
            </div>
        </>
    );
}
