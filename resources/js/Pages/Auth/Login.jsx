import Checkbox from "@/Components/Checkbox";
import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import TextInput from "@/Components/TextInput";
import { Button } from "@/Components/ui/button";
import { Head, Link, useForm } from "@inertiajs/react";

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: "",
        password: "",
        remember: false,
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
                    <header className="mt-10 flex items-center justify-center">
                        <Link href="/">
                            <img
                                src="https://yogafx.b-cdn.net/content/Logo%20YogAFX.png"
                                alt="YogaFX"
                                className="h-24 w-auto object-contain sm:h-28"
                            />
                        </Link>
                    </header>

                    <main className="flex flex-1 items-center justify-center py-6 lg:py-8">
                        <div className="w-full max-w-xl">
                            <div className="py-8">
                                <form onSubmit={submit} className="space-y-6">
                                    {status && (
                                        <p className="text-sm font-medium leading-6 text-green-400">
                                            {status}
                                        </p>
                                    )}

                                    <div>
                                        <InputLabel
                                            htmlFor="email"
                                            value="Email"
                                            className="text-white/80"
                                        />
                                        <TextInput
                                            id="email"
                                            type="email"
                                            name="email"
                                            value={data.email}
                                            className="mt-2 block w-full border-white/20 bg-white/10 text-white placeholder:text-white/30"
                                            autoComplete="username"
                                            isFocused={true}
                                            onChange={(e) =>
                                                setData("email", e.target.value)
                                            }
                                        />
                                        <InputError
                                            message={errors.email}
                                            className="mt-2 text-red-400"
                                        />
                                    </div>

                                    <div>
                                        <InputLabel
                                            htmlFor="password"
                                            value="Password"
                                            className="text-white/80"
                                        />
                                        <TextInput
                                            id="password"
                                            type="password"
                                            name="password"
                                            value={data.password}
                                            className="mt-2 block w-full border-white/20 bg-white/10 text-white placeholder:text-white/30"
                                            autoComplete="current-password"
                                            onChange={(e) =>
                                                setData(
                                                    "password",
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={errors.password}
                                            className="mt-2 text-red-400"
                                        />
                                    </div>

                                    <div className="flex flex-wrap items-center justify-between gap-4">
                                        <label className="flex items-center gap-2">
                                            <Checkbox
                                                name="remember"
                                                checked={data.remember}
                                                onChange={(e) =>
                                                    setData(
                                                        "remember",
                                                        e.target.checked,
                                                    )
                                                }
                                            />
                                            <span className="text-sm text-white/70">
                                                Remember me
                                            </span>
                                        </label>

                                        {canResetPassword && (
                                            <Link
                                                href={route("password.request")}
                                                className="text-sm text-white/70 underline hover:text-white"
                                            >
                                                Forgot your password?
                                            </Link>
                                        )}
                                    </div>

                                    <div className="flex justify-end">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                            className="rounded-md bg-[#DB202C] px-6 text-white hover:bg-[#c01a25]"
                                        >
                                            {processing
                                                ? "Logging in..."
                                                : "Log In"}
                                        </Button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </main>
                </div>
            </div>
        </>
    );
}
