import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { Button } from '@/Components/ui/button';
import PublicFlowLayout from '@/Layouts/PublicFlowLayout';
import { useForm } from '@inertiajs/react';

export default function Signup({ onboarding, student }) {
    const { data, setData, post, processing, errors } = useForm({
        password: '',
        password_confirmation: '',
    });

    const submit = (event) => {
        event.preventDefault();
        post(onboarding.submit_url, {
            onFinish: () => setData('password', ''),
        });
    };

    return (
        <PublicFlowLayout
            title="Create Password"
            heading="Create your final YogaFX password, then verify the code sent to your email"
            description="Enrollment is complete. This last step prepares the final credential, then YogaFX sends an OTP code to the registered email before the LMS session is opened."
            aside={
                <div className="space-y-6">
                    {/* Account ready card */}
                    <div className="rounded-[10px] border border-white/10 bg-white/5 p-5">
                        <div className="flex justify-end">
                            <p className="inline-block rounded-full border border-white/30 bg-white/10 px-4 py-1 text-sm font-semibold text-white">
                                Account ready
                            </p>
                        </div>
                        <div className="mt-7 space-y-3 text-sm text-white">
                            <p>{student.name}</p>
                            <p>{student.email}</p>
                            <p>Tier access: {onboarding.access_tier.name}</p>
                        </div>
                    </div>

                    {/* Next outcome card */}
                    <div className="rounded-[10px] border border-white/10 bg-white/5 p-5">
                        <div className="flex justify-end">
                            <p className="inline-block rounded-full border border-white/30 bg-white/10 px-4 py-1 text-sm font-semibold text-white">
                                Next outcome
                            </p>
                        </div>
                        <div className="mt-7 space-y-3 text-sm leading-6 text-white/70">
                            <p>Your password becomes the final credential for routine login.</p>
                            <p>After submit, YogaFX emails you a verification code before auto-login continues.</p>
                        </div>
                    </div>
                </div>
            }
        >
            <form onSubmit={submit} className="space-y-6">
                <div className="grid gap-5">
                    {/* Name — disabled, style sama dengan Checkout */}
                    <div>
                        <InputLabel htmlFor="name" value="Name" className="text-gray-700" />
                        <input
                            id="name"
                            disabled
                            value={student.name}
                            className="mt-2 block w-full rounded-md border border-gray-700 bg-black px-3 py-2 text-white"
                        />
                    </div>

                    {/* Email — disabled, style sama dengan Checkout */}
                    <div>
                        <InputLabel htmlFor="email" value="Email" className="text-gray-700" />
                        <input
                            id="email"
                            disabled
                            value={student.email}
                            className="mt-2 block w-full rounded-md border border-gray-700 bg-black px-3 py-2 text-white"
                        />
                    </div>

                    {/* Password */}
                    <div>
                        <InputLabel htmlFor="password" value="Create Password" className="text-white/80" />
                        <TextInput
                            id="password"
                            type="password"
                            value={data.password}
                            className="mt-2 block w-full border-white/20 bg-white/10 text-white placeholder:text-white/30"
                            onChange={(event) => setData('password', event.target.value)}
                            required
                        />
                        <InputError className="mt-2 text-red-400" message={errors.password} />
                    </div>

                    {/* Confirm Password */}
                    <div>
                        <InputLabel htmlFor="password_confirmation" value="Confirm Password" className="text-white/80" />
                        <TextInput
                            id="password_confirmation"
                            type="password"
                            value={data.password_confirmation}
                            className="mt-2 block w-full border-white/20 bg-white/10 text-white placeholder:text-white/30"
                            onChange={(event) => setData('password_confirmation', event.target.value)}
                            required
                        />
                        <InputError className="mt-2 text-red-400" message={errors.password_confirmation} />
                    </div>
                </div>

                {/* Footer + tombol */}
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <p className="text-sm text-gray-500">
                        This is the final credential step before email OTP verification and LMS access.
                    </p>

                    <Button
                        type="submit"
                        disabled={processing}
                        className="rounded-md bg-[#DB202C] px-6 text-white hover:bg-[#c01a25]"
                    >
                        Create Password and Send OTP
                    </Button>
                </div>
            </form>
        </PublicFlowLayout>
    );
}