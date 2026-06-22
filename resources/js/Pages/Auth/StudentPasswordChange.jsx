import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { Button } from '@/Components/ui/button';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { KeyRound, MailCheck } from 'lucide-react';

export default function StudentPasswordChange({ token, email, expires_at, status }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token,
        email,
        otp_code: '',
        new_password: '',
        new_password_confirmation: '',
    });

    const submit = (event) => {
        event.preventDefault();

        post(route('profile.password.change.update'), {
            onFinish: () => reset('new_password', 'new_password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Student Password Change" />

            <div className="space-y-6">
                <div className="rounded-[14px] border border-white/10 bg-[#15110f] p-6 text-white shadow-[0_20px_80px_rgba(0,0,0,0.35)]">
                    <div className="flex items-start gap-4">
                        <div className="rounded-full border border-white/10 bg-white/5 p-3">
                            <KeyRound className="size-5 text-[#ffd7cf]" />
                        </div>
                        <div className="space-y-2">
                            <p className="text-xs uppercase tracking-[0.24em] text-white/48">
                                Student Password Flow
                            </p>
                            <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                                Verify your email code and set a new password
                            </h1>
                            <p className="max-w-2xl text-sm leading-7 text-white/62">
                                Enter the OTP from your email and set a new password.
                            </p>
                        </div>
                    </div>
                </div>

                {status === 'student-password-change-email-sent' && (
                    <div className="rounded-[12px] border border-emerald-300/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
                        Password change email sent. Check your inbox.
                    </div>
                )}

                <div className="rounded-[14px] border border-black/10 bg-white p-6 shadow-sm">
                    <div className="mb-6 flex items-start gap-3">
                        <div className="rounded-full bg-slate-100 p-2">
                            <MailCheck className="size-5 text-slate-700" />
                        </div>
                        <div>
                            <h2 className="text-lg font-semibold text-slate-900">
                                Password verification
                            </h2>
                            <p className="mt-1 break-all text-sm leading-6 text-slate-600">
                                Email destination: <strong>{email}</strong>
                                {expires_at ? (
                                    <>. This request stays active until {new Date(expires_at).toLocaleString()}.</>
                                ) : null}
                            </p>
                        </div>
                    </div>

                    <form onSubmit={submit} className="space-y-5">
                        <div>
                            <InputLabel htmlFor="otp_code" value="OTP Code" />
                            <TextInput
                                id="otp_code"
                                value={data.otp_code}
                                onChange={(event) => setData('otp_code', event.target.value)}
                                className="mt-1 block w-full rounded-[14px] border-[#DB202C] focus:border-[#DB202C] focus:ring-[#DB202C]"
                                inputMode="numeric"
                                autoComplete="one-time-code"
                                placeholder="Enter the 6-digit code"
                            />
                            <InputError message={errors.otp_code} className="mt-2" />
                        </div>

                        <div>
                            <InputLabel htmlFor="new_password" value="New Password" />
                            <TextInput
                                id="new_password"
                                type="password"
                                value={data.new_password}
                                onChange={(event) => setData('new_password', event.target.value)}
                                className="mt-1 block w-full rounded-[14px] border-[#DB202C] focus:border-[#DB202C] focus:ring-[#DB202C]"
                                autoComplete="new-password"
                            />
                            <InputError message={errors.new_password} className="mt-2" />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="new_password_confirmation"
                                value="Confirm New Password"
                            />
                            <TextInput
                                id="new_password_confirmation"
                                type="password"
                                value={data.new_password_confirmation}
                                onChange={(event) =>
                                    setData('new_password_confirmation', event.target.value)
                                }
                                className="mt-1 block w-full rounded-[14px] border-[#DB202C] focus:border-[#DB202C] focus:ring-[#DB202C]"
                                autoComplete="new-password"
                            />
                            <InputError
                                message={errors.new_password_confirmation}
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
