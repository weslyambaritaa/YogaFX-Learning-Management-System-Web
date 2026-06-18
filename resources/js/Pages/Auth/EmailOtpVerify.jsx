import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { Button } from '@/Components/ui/button';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { MailCheck } from 'lucide-react';

export default function EmailOtpVerify({ token, context, email, expires_at }) {
    const { data, setData, post, processing, errors } = useForm({
        otp_code: '',
    });

    const submit = (event) => {
        event.preventDefault();
        post(route('auth.otp.verify', { token }));
    };

    const heading = context === 'signup'
        ? 'Verify your email code to finish YogaFX sign up'
        : 'Verify your email code to finish YogaFX login';
    const description = context === 'signup'
        ? 'Your password is ready. Enter the OTP code that YogaFX sent to your email so the onboarding flow can safely open the LMS.'
        : 'Your password was correct. Enter the OTP code that YogaFX sent to your email so the login session can continue safely.';

    return (
        <GuestLayout>
            <Head title="Email OTP Verification" />

            <div className="space-y-6">
                {/* Header box — hitam, tanpa shadow besar */}
                <div className="rounded-[16px] border border-gray-200 bg-gray-900 p-6 text-white">
                    <div className="flex items-start gap-4">
                        <div className="rounded-full border border-white/20 bg-white/10 p-3">
                            <MailCheck className="size-5 text-white" />
                        </div>
                        <div className="space-y-2">
                            <p className="text-sm font-semibold text-white/70">
                                Email Verification
                            </p>
                            <h1 className="text-base font-semibold text-white">
                                {heading}
                            </h1>
                            <p className="text-sm leading-6 text-white/70">
                                {description}
                            </p>
                        </div>
                    </div>
                </div>

                {/* Form box */}
                <div className="rounded-[16px] border border-gray-200 bg-white p-6">
                    <div className="mb-6">
                        <h2 className="text-base font-semibold text-gray-900">
                            Verification details
                        </h2>
                        <p className="mt-1 text-sm leading-6 text-gray-600">
                            Email destination: <strong>{email}</strong>
                            {expires_at ? <>. This code expires at {new Date(expires_at).toLocaleString()}.</> : null}
                        </p>
                    </div>

                    <form onSubmit={submit} className="space-y-5">
                        <div>
                            <InputLabel htmlFor="otp_code" value="OTP Code" />
                            <TextInput
                                id="otp_code"
                                value={data.otp_code}
                                onChange={(event) => setData('otp_code', event.target.value)}
                                className="mt-1 block w-full"
                                inputMode="numeric"
                                autoComplete="one-time-code"
                                placeholder="Enter the 6-digit code from your email"
                            />
                            <InputError message={errors.otp_code} className="mt-2" />
                        </div>

                        <div className="flex justify-end pt-2">
                            <Button
                                type="submit"
                                disabled={processing}
                                className="rounded-md bg-red-600 px-6 text-white hover:bg-red-700"
                            >
                                {processing ? 'Verifying...' : 'Verify and Continue'}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </GuestLayout>
    );
}