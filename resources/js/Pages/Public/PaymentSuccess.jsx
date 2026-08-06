import { Button } from "@/Components/ui/button";
import YogaFXText from "@/Components/YogaFXText";
import PublicFlowLayout from "@/Layouts/PublicFlowLayout";
import { Check } from "lucide-react";

export default function PaymentSuccess({ onboarding }) {
    return (
        <PublicFlowLayout
            title={onboarding.title ?? "Payment Success"}
            eyebrow={
                onboarding.eyebrow ?? "Your Deposit Transfer Was Successful"
            }
            heading={onboarding.heading ?? "Your payment was received."}
        >
            <div className="flex justify-center pb-8 pt-2">
                <div className="w-full max-w-2xl text-center">
                    {/* Success Icon */}
                    <div className="mx-auto flex h-32 w-32 items-center justify-center rounded-full bg-emerald-500 shadow-[0_0_45px_rgba(16,185,129,0.35)]">
                        <Check
                            className="h-20 w-20 text-white"
                            strokeWidth={3}
                        />
                    </div>

                    {/* Congratulations */}
                    <h2 className="mt-10 text-3xl font-bold leading-tight text-white md:text-4xl">
                        Congratulations!
                    </h2>

                    {/* Deposit Confirmation */}
                    <p className="mx-auto mt-6 max-w-xl text-lg font-semibold leading-relaxed text-white">
                        <YogaFXText
                            text={
                                onboarding.message ??
                                "Your deposit has been received, and your installment plan has been successfully set up."
                            }
                            fxClassName="!text-[#DB202C]"
                        />
                    </p>

                    {/* Next Step */}
                    <p className="mx-auto mt-5 max-w-xl text-base font-normal leading-7 text-white/80 md:text-lg">
                        Please continue to your Enrollment Application Form to
                        complete your details and access your dashboard.
                    </p>

                    {/* Continue Button */}
                    <Button
                        asChild
                        className="mt-9 min-h-[64px] w-full max-w-[360px] rounded-[8px] bg-[#DB202C] px-10 py-6 text-base font-bold text-white shadow-[0_12px_35px_rgba(219,32,44,0.3)] transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#c01a25]"
                    >
                        <a
                            href={onboarding.continue_url}
                            className="inline-flex items-center justify-center whitespace-nowrap"
                        >
                            Continue to Enrollment
                        </a>
                    </Button>
                </div>
            </div>
        </PublicFlowLayout>
    );
}
