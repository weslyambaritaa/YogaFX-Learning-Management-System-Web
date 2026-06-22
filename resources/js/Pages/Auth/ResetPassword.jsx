import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import { Button } from '@/Components/ui/button';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { KeyRound, MailCheck } from 'lucide-react';

export default function ResetPassword({ token, email, expires_at }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token: token,
        email: email,
        otp_code: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('password.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Reset Password" />

            <div className="space-y-6">
                <div className="rounded-[14px] border border-white/10 bg-[#15110f] p-6 text-white shadow-[0_20px_80px_rgba(0,0,0,0.35)]">
                    <div className="flex items-start gap-4">
                        <div className="rounded-full border border-white/10 bg-white/5 p-3">
                            <KeyRound className="size-5 text-[#ffd7cf]" />
                        </div>
                        <div className="space-y-2">
                            <p className="text-xs uppercase tracking-[0.24em] text-white/48">
                                Password Reset
                            </p>
                            <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                                Enter the OTP from your email to continue
                            </h1>
                            <p className="max-w-2xl text-sm leading-7 text-white/62">
                                Open the reset email from YogaFX, then enter the OTP code and your new password below.
                            </p>
                        </div>
                    </div>
                </div>

                <div className="rounded-[14px] border border-black/10 bg-white p-6 shadow-sm">
                    <div className="mb-6 flex items-start gap-3">
                        <div className="rounded-full bg-slate-100 p-2">
                            <MailCheck className="size-5 text-slate-700" />
                        </div>
                        <div>
                            <h2 className="text-lg font-semibold text-slate-900">
                                Reset verification
                            </h2>
                            <p className="mt-1 break-all text-sm leading-6 text-slate-600">
                                Email destination: <strong>{email}</strong>
                                {expires_at ? (
                                    <>. This reset request stays active until {new Date(expires_at).toLocaleString()}.</>
                                ) : null}
                            </p>
                        </div>
                    </div>

                    <form onSubmit={submit} className="space-y-5">
                        <div>
                            <InputLabel htmlFor="email" value="Email" />

                            <TextInput
                                id="email"
                                type="email"
                                name="email"
                                value={data.email}
                                className="mt-1 block w-full rounded-[14px] border-[#DB202C] focus:border-[#DB202C] focus:ring-[#DB202C]"
                                autoComplete="username"
                                onChange={(e) => setData('email', e.target.value)}
                            />

                            <InputError message={errors.email} className="mt-2" />
                        </div>

                        <div>
                            <InputLabel htmlFor="otp_code" value="OTP Code" />

                            <TextInput
                                id="otp_code"
                                value={data.otp_code}
                                className="mt-1 block w-full rounded-[14px] border-[#DB202C] focus:border-[#DB202C] focus:ring-[#DB202C]"
                                inputMode="numeric"
                                autoComplete="one-time-code"
                                placeholder="Enter the 6-digit code"
                                isFocused={true}
                                onChange={(e) => setData('otp_code', e.target.value)}
                            />

                            <InputError message={errors.otp_code} className="mt-2" />
                        </div>

                        <div>
                            <InputLabel htmlFor="password" value="Password" />

                            <TextInput
                                id="password"
                                type="password"
                                name="password"
                                value={data.password}
                                className="mt-1 block w-full rounded-[14px] border-[#DB202C] focus:border-[#DB202C] focus:ring-[#DB202C]"
                                autoComplete="new-password"
                                onChange={(e) => setData('password', e.target.value)}
                            />

                            <InputError message={errors.password} className="mt-2" />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="password_confirmation"
                                value="Confirm Password"
                            />

                            <TextInput
                                type="password"
                                id="password_confirmation"
                                name="password_confirmation"
                                value={data.password_confirmation}
                                className="mt-1 block w-full rounded-[14px] border-[#DB202C] focus:border-[#DB202C] focus:ring-[#DB202C]"
                                autoComplete="new-password"
                                onChange={(e) =>
                                    setData('password_confirmation', e.target.value)
                                }
                            />

                            <InputError
                                message={errors.password_confirmation}
                                className="mt-2"
                            />
                        </div>

                        <div className="flex justify-end pt-2">
                            <Button type="submit" disabled={processing} className="w-full sm:w-auto">
                                {processing ? 'Saving...' : 'Save New Password'}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </GuestLayout>
    );
}
