import { Button } from "@/Components/ui/button";
import PublicFlowLayout from "@/Layouts/PublicFlowLayout";
import { Link } from "@inertiajs/react";

export default function PaymentSuccess({ onboarding }) {
    return (
        <PublicFlowLayout
            title="Payment Success"
            eyebrow="Payment Completed"
            heading="Congratulations. Your payment was successful."
        >
            <div className="flex justify-center py-4">
                <div className="w-full max-w-sm">
                    <Button
                        asChild
                        className="w-full rounded-md bg-[#DB202C] px-6 text-white hover:bg-[#c01a25]"
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
