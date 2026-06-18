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
            eyebrow="Final Sign Up"
            heading="Create your final YogaFX password, then verify the code sent to your email."
            description="Enrollment is complete. This last step prepares the final credential, then YogaFX sends an OTP code to the registered email before the LMS session is opened."
            aside={
                <div className="space-y-5">
                    <div className="rounded-[24px] border border-white/10 bg-[#161210] p-5">
                        <p className="text-xs uppercase tracking-[0.22em] text-white/46">
                            Account ready
                        </p>
                        <div className="mt-4 space-y-2 text-sm text-white/66">
                            <p>{student.name}</p>
                            <p>{student.email}</p>
                            <p>Tier access: {onboarding.access_tier.name}</p>
                        </div>
                    </div>

                    <div className="rounded-[24px] border border-white/10 bg-[#161210] p-5">
                        <p className="text-xs uppercase tracking-[0.22em] text-white/46">
                            Next outcome
                        </p>
                        <div className="mt-4 space-y-3 text-sm leading-6 text-white/62">
                            <p>Your password becomes the final credential for routine login.</p>
                            <p>After submit, YogaFX emails you a verification code before auto-login continues.</p>
                        </div>
                    </div>
                </div>
            }
        >
            <form onSubmit={submit} className="space-y-6">
                <div className="grid gap-5">
                    <div>
                        <InputLabel htmlFor="name" value="Name" className="text-white/72" />
                        <input
                            id="name"
                            disabled
                            value={student.name}
                            className="mt-2 block w-full rounded-md border border-white/12 bg-white/5 px-3 py-2 text-white/72"
                        />
                    </div>

                    <div>
                        <InputLabel htmlFor="email" value="Email" className="text-white/72" />
                        <input
                            id="email"
                            disabled
                            value={student.email}
                            className="mt-2 block w-full rounded-md border border-white/12 bg-white/5 px-3 py-2 text-white/72"
                        />
                    </div>

                    <div>
                        <InputLabel htmlFor="password" value="Create Password" className="text-white/72" />
                        <TextInput
                            id="password"
                            type="password"
                            value={data.password}
                            className="mt-2 block w-full border-white/12 bg-white/5 text-white"
                            onChange={(event) => setData('password', event.target.value)}
                            required
                        />
                        <InputError className="mt-2 text-[#ffb4a8]" message={errors.password} />
                    </div>

                    <div>
                        <InputLabel htmlFor="password_confirmation" value="Confirm Password" className="text-white/72" />
                        <TextInput
                            id="password_confirmation"
                            type="password"
                            value={data.password_confirmation}
                            className="mt-2 block w-full border-white/12 bg-white/5 text-white"
                            onChange={(event) => setData('password_confirmation', event.target.value)}
                            required
                        />
                    </div>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-4">
                    <p className="text-sm text-white/48">
                        This is the final credential step before email OTP verification and LMS access.
                    </p>

                    <Button
                        type="submit"
                        disabled={processing}
                        className="rounded-full bg-[#d5462f] px-6 text-white hover:bg-[#e2553d]"
                    >
                        Create Password and Send OTP
                    </Button>
                </div>
            </form>
        </PublicFlowLayout>
    );
}
