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
            : null;
    const description =
        context === "signup"
            ? "Your password is ready. Enter the OTP code that YogaFX sent to your email so the onboarding flow can safely open the LMS."
            : null;

    return (
        <PublicFlowLayout
            title="Email OTP Verification"
            heading={heading}
            description={description}
        >
            <form onSubmit={submit} className="mx-auto flex max-w-xl flex-col items-center space-y-6 text-center">
                <div className="flex items-center justify-center gap-3">
                    <MailCheck
                        className="h-6 w-6 flex-shrink-0 text-white/70"
                        strokeWidth={2.5}
                    />
                    <p className="text-sm leading-6 text-white/70">
                        We sent a 6-digit verification code to your email.
                    </p>
                </div>

                <div className="w-full text-left">
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

                <div className="space-y-3 text-sm text-white">
                    <p className="font-semibold text-white/80">
                        Verification details
                    </p>
                    <p>{email}</p>
                    {expires_at && (
                        <p className="text-white/70">
                            Code expires at{" "}
                            {new Date(expires_at).toLocaleString()}.
                        </p>
                    )}
                </div>

                <div className="flex justify-center">
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
