import { Button } from "@/Components/ui/button";
import YogaFXText from "@/Components/YogaFXText";
import PublicFlowLayout from "@/Layouts/PublicFlowLayout";
import { Check } from "lucide-react";
import { Link } from "@inertiajs/react";

export default function PaymentSuccess({ onboarding }) {
    return (
        <PublicFlowLayout
            title={onboarding.title ?? "Payment Success"}
            eyebrow={onboarding.eyebrow ?? "Payment Approved"}
            heading={onboarding.heading ?? "Your payment was received."}
        >
            <div className="flex justify-center pt-0 pb-6">
                <div className="w-full max-w-md text-center">
                    <div className="mx-auto flex h-24 w-24 items-center justify-center rounded-full bg-emerald-500">
                        <Check
                            className="h-14 w-14 text-white"
                            strokeWidth={3.2}
                        />
                    </div>

                    <p className="mt-9 text-base font-semibold leading-relaxed text-white">
                        <YogaFXText
                            text={
                                onboarding.message ??
                                "Continue to enrollment to complete your YogaFX account."
                            }
                        />
                    </p>

                    <Button
                        asChild
                        className="mt-6 w-auto min-w-[220px] max-w-full rounded-md bg-[#DB202C] px-8 py-6 text-sm font-bold text-white hover:bg-[#c01a25]"
                    >
                        <Link
                            href={onboarding.continue_url}
                            className="inline-flex items-center justify-center whitespace-nowrap"
                        >
                            Continue to Enrollment
                        </Link>
                    </Button>
                </div>
            </div>
        </PublicFlowLayout>
    );
}
