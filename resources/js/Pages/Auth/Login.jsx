import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import PasswordField from "@/Components/PasswordField";
import TextInput from "@/Components/TextInput";
import { Button } from "@/Components/ui/button";
import { Head, Link, useForm } from "@inertiajs/react";
import { Mail } from "lucide-react";

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

                        <div className="mb-8 text-center">
                            <h1 className="font-['Montserrat'] text-3xl font-semibold tracking-tight text-white sm:text-4xl">
                                Sign in to Your Account
                            </h1>
                        </div>

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
                                    <div className="relative mt-2">
                                        <Mail className="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-white/45" />
                                        <TextInput
                                            id="email"
                                            type="email"
                                            name="email"
                                            value={data.email}
                                            className="block w-full rounded-[5px] border-white/20 bg-white/10 py-[8px] pl-[10px] pr-10 font-['Montserrat'] text-[14px] font-medium text-white placeholder:text-white/30"
                                            autoComplete="username"
                                            isFocused={true}
                                            onChange={(e) =>
                                                setData("email", e.target.value)
                                            }
                                        />
                                    </div>
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
                                    <PasswordField
                                        id="password"
                                        name="password"
                                        value={data.password}
                                        className="mt-2"
                                        inputClassName="block w-full rounded-[5px] border-white/20 bg-white/10 px-[10px] py-[8px] font-['Montserrat'] text-[14px] font-medium text-white placeholder:text-white/30"
                                        buttonClassName="text-white/45 hover:text-white"
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
                                        {processing
                                            ? "Logging in..."
                                            : "Log in"}
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
