import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import TextInput from "@/Components/TextInput";
import { Button } from "@/Components/ui/button";
import PublicFlowLayout from "@/Layouts/PublicFlowLayout";
import { useForm } from "@inertiajs/react";
import { MailCheck } from "lucide-react";

export default function EmailOtpVerify({ token, context, email, expires_at }) {
    const { data, setData, post, processing, errors } = useForm({
        otp_code: "",
    });

    const submit = (event) => {
        event.preventDefault();
        post(route("auth.otp.verify", { token }));
    };

    const heading =
        context === "signup"
            ? "Verify your email code to finish YogaFX sign up"
            : "Verify your email code to finish YogaFX login";
    const description =
        context === "signup"
            ? "Your password is ready. Enter the OTP code that YogaFX sent to your email so the onboarding flow can safely open the LMS."
            : "Your password was correct. Enter the OTP code that YogaFX sent to your email so the login session can continue safely.";

    return (
        <PublicFlowLayout
            title="Email OTP Verification"
            heading={heading}
            description={description}
            aside={
                <div className="space-y-6">
                    {/* Verification details card */}
                    <div className="rounded-[10px] border border-white/10 bg-white/5 p-5">
                        <div className="flex justify-end">
                            <p className="inline-block rounded-full border border-white/30 bg-white/10 px-4 py-1 text-sm font-semibold text-white">
                                Verification details
                            </p>
                        </div>
                        <div className="mt-7 space-y-3 text-sm text-white">
                            <p>{email}</p>
                            {expires_at && (
                                <p className="text-white/70">
                                    Code expires at{" "}
                                    {new Date(expires_at).toLocaleString()}.
                                </p>
                            )}
                        </div>
                    </div>

                    {/* Why this step card */}
                    <div className="rounded-[10px] border border-white/10 bg-white/5 p-5">
                        <div className="flex justify-end">
                            <p className="inline-block rounded-full border border-white/30 bg-white/10 px-4 py-1 text-sm font-semibold text-white">
                                Why this step
                            </p>
                        </div>
                        <div className="mt-7 space-y-3 text-sm leading-6 text-white/70">
                            <p>
                                The OTP code confirms this email address belongs
                                to you before the session continues.
                            </p>
                            <p>
                                Codes are single-use and expire after a short
                                window for security.
                            </p>
                        </div>
                    </div>
                </div>
            }
        >
            <form onSubmit={submit} className="space-y-6">
                <div className="flex items-center gap-3">
                    <MailCheck
                        className="h-6 w-6 flex-shrink-0 text-white/70"
                        strokeWidth={2.5}
                    />
                    <p className="text-sm leading-6 text-white/70">
                        We sent a 6-digit verification code to your email.
                    </p>
                </div>

                <div>
                    <InputLabel
                        htmlFor="otp_code"
                        value="OTP Code"
                        className="text-white/80"
                    />
                    <TextInput
                        id="otp_code"
                        value={data.otp_code}
                        onChange={(event) =>
                            setData("otp_code", event.target.value)
                        }
                        className="mt-2 block w-full border-white/20 bg-white/10 text-white placeholder:text-white/30"
                        inputMode="numeric"
                        autoComplete="one-time-code"
                        placeholder="Enter the 6-digit code from your email"
                    />
                    <InputError
                        message={errors.otp_code}
                        className="mt-2 text-red-400"
                    />
                </div>

                <div className="flex flex-wrap items-center justify-between gap-4">
                    <p className="text-sm text-gray-500">
                        Enter the code exactly as received. Codes expire
                        automatically.
                    </p>

                    <Button
                        type="submit"
                        disabled={processing}
                        className="rounded-md bg-[#DB202C] px-6 text-white hover:bg-[#c01a25]"
                    >
                        {processing ? "Verifying..." : "Verify and Continue"}
                    </Button>
                </div>
            </form>
        </PublicFlowLayout>
    );
}
