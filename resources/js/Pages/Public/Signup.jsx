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
                <div className="space-y-5">
                    {/* Account ready card */}
                    <div className="rounded-[10px] border border-gray-200 bg-gray-900 p-5">
                        <div className="flex justify-end">
                            <p className="inline-block rounded-full border border-white/30 bg-white/10 px-4 py-1 text-sm font-semibold text-white">
                                Account ready
                            </p>
                        </div>
                        <div className="mt-4 space-y-2 text-sm text-white">
                            <p>{student.name}</p>
                            <p>{student.email}</p>
                            <p>Tier access: {onboarding.access_tier.name}</p>
                        </div>
                    </div>

                    {/* Next outcome card */}
                    <div className="rounded-[10px] border border-gray-200 bg-gray-900 p-5">
                        <div className="flex justify-end">
                            <p className="inline-block rounded-full border border-white/30 bg-white/10 px-4 py-1 text-sm font-semibold text-white">
                                Next outcome
                            </p>
                        </div>
                        <div className="mt-4 space-y-3 text-sm leading-6 text-white/80">
                            <p>Your password becomes the final credential for routine login.</p>
                            <p>After submit, YogaFX emails you a verification code before auto-login continues.</p>
                        </div>
                    </div>
                </div>
            }
        >
            <form onSubmit={submit} className="space-y-6">
                <div className="grid gap-5">
                    {/* Name — disabled, style terang */}
                    <div>
                        <InputLabel htmlFor="name" value="Name" className="text-gray-700" />
                        <input
                            id="name"
                            disabled
                            value={student.name}
                            className="mt-2 block w-full rounded-md border border-gray-300 bg-gray-100 px-3 py-2 text-gray-700"
                        />
                    </div>

                    {/* Email — disabled, style terang */}
                    <div>
                        <InputLabel htmlFor="email" value="Email" className="text-gray-700" />
                        <input
                            id="email"
                            disabled
                            value={student.email}
                            className="mt-2 block w-full rounded-md border border-gray-300 bg-gray-100 px-3 py-2 text-gray-700"
                        />
                    </div>

                    {/* Password */}
                    <div>
                        <InputLabel htmlFor="password" value="Create Password" className="text-gray-700" />
                        <TextInput
                            id="password"
                            type="password"
                            value={data.password}
                            className="mt-2 block w-full"
                            onChange={(event) => setData('password', event.target.value)}
                            required
                        />
                        <InputError className="mt-2" message={errors.password} />
                    </div>

                    {/* Confirm Password */}
                    <div>
                        <InputLabel htmlFor="password_confirmation" value="Confirm Password" className="text-gray-700" />
                        <TextInput
                            id="password_confirmation"
                            type="password"
                            value={data.password_confirmation}
                            className="mt-2 block w-full"
                            onChange={(event) => setData('password_confirmation', event.target.value)}
                            required
                        />
                    </div>
                </div>

                {/* Box abu + tombol */}
                <div className="flex flex-wrap items-center justify-between gap-4 rounded-[10px] border border-gray-300 bg-gray-300 px-5 py-4">
                    <p className="text-sm text-gray-600">
                        This is the final credential step before email OTP verification and LMS access.
                    </p>

                    <Button
                        type="submit"
                        disabled={processing}
                        className="rounded-md bg-red-600 px-6 text-white hover:bg-red-700"
                    >
                        Create Password and Send OTP
                    </Button>
                </div>
            </form>
        </PublicFlowLayout>
    );
}