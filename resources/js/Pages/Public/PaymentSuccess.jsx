import { Button } from "@/Components/ui/button";
import PublicFlowLayout from "@/Layouts/PublicFlowLayout";
import { CheckCircle2 } from "lucide-react";
import { Link } from "@inertiajs/react";

export default function PaymentSuccess({ onboarding }) {
    return (
        <PublicFlowLayout
            title="Payment Success"
            eyebrow="Payment Approved"
            heading="Your payment was received."
        >
            <div className="flex justify-center py-4">
                <div className="w-full max-w-md rounded-[5px] border border-white/10 bg-white/5 px-6 py-8 text-center">
                    <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-500/15">
                        <CheckCircle2 className="h-9 w-9 text-emerald-400" />
                    </div>
                    <p className="mt-5 text-sm text-white/75">
                        Continue to enrollment to complete your YogaFX account.
                    </p>
                    <Button
                        asChild
                        className="mt-6 w-full rounded-md bg-[#DB202C] px-6 text-white hover:bg-[#c01a25]"
                    >
                        <Link href={onboarding.continue_url}>
                            Continue to Enrollment
                        </Link>
                    </Button>
                </div>
            </div>
        </PublicFlowLayout>
    );
}
