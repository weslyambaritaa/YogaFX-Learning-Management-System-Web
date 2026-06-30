import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PasswordField from '@/Components/PasswordField';
import PasswordRequirementsCard from '@/Components/PasswordRequirementsCard';
import { Button } from '@/Components/ui/button';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { MailCheck } from 'lucide-react';

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
                            <PasswordField
                                id="otp_code"
                                value={data.otp_code}
                                onChange={(event) => setData('otp_code', event.target.value)}
                                className="mt-1 block w-full"
                                inputClassName="rounded-[14px] border-[#DB202C] focus:border-[#DB202C] focus:ring-[#DB202C]"
                                inputMode="numeric"
                                autoComplete="one-time-code"
                                placeholder="Enter the 6-digit code"
                            />
                            <InputError message={errors.otp_code} className="mt-2" />
                        </div>

                        <div>
                            <InputLabel htmlFor="new_password" value="New Password" />
                            <PasswordField
                                id="new_password"
                                value={data.new_password}
                                onChange={(event) => setData('new_password', event.target.value)}
                                className="mt-1 block w-full"
                                inputClassName="rounded-[14px] border-[#DB202C] focus:border-[#DB202C] focus:ring-[#DB202C]"
                                autoComplete="new-password"
                            />
                            <InputError message={errors.new_password} className="mt-2" />
                        </div>

                        <PasswordRequirementsCard password={data.new_password} />

                        <div>
                            <InputLabel
                                htmlFor="new_password_confirmation"
                                value="Confirm New Password"
                            />
                            <PasswordField
                                id="new_password_confirmation"
                                value={data.new_password_confirmation}
                                onChange={(event) =>
                                    setData('new_password_confirmation', event.target.value)
                                }
                                className="mt-1 block w-full"
                                inputClassName="rounded-[14px] border-[#DB202C] focus:border-[#DB202C] focus:ring-[#DB202C]"
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
