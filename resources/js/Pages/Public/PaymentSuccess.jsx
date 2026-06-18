import { Button } from "@/Components/ui/button";
import PublicFlowLayout from "@/Layouts/PublicFlowLayout";
import { Link } from "@inertiajs/react";
import { CheckCircle2 } from "lucide-react";
import { useEffect } from "react";

export default function PaymentSuccess({ onboarding, student }) {
    useEffect(() => {
        const timer = window.setTimeout(() => {
            window.location.assign(onboarding.continue_url);
        }, 2200);

        return () => window.clearTimeout(timer);
    }, [onboarding.continue_url]);

    return (
        <PublicFlowLayout
            title="Payment Success"
            eyebrow="Payment Completed"
            heading="Your payment was successful. We're moving you into enrollment next."
            description="Simulated payment has been recorded as successful, your YogaFX base account has already been created, and the flow will now continue into enrollment."
            aside={
                <div className="space-y-6">
                    {/* Payment result card */}
                    <div className="rounded-[10px] border border-white/10 bg-white/5 p-5">
                        <div className="flex justify-end">
                            <p className="inline-block rounded-full border border-white/30 bg-white/10 px-4 py-1 text-sm font-semibold text-white">
                                Payment result
                            </p>
                        </div>
                        <div className="mt-7 space-y-3 text-sm text-white">
                            <p>Student: {student.name}</p>
                            <p>Email: {student.email}</p>
                            <p>Tier: {onboarding.access_tier.name}</p>
                            <p>Status: success</p>
                        </div>
                    </div>

                    {/* Next step card */}
                    <div className="rounded-[10px] border border-white/10 bg-white/5 p-5">
                        <div className="flex justify-end">
                            <p className="inline-block rounded-full border border-white/30 bg-white/10 px-4 py-1 text-sm font-semibold text-white">
                                Next step
                            </p>
                        </div>
                        <div className="mt-7 space-y-3 text-sm leading-6 text-white/70">
                            <p>
                                Enrollment comes next, then final password
                                creation, then LMS access.
                            </p>
                        </div>
                    </div>
                </div>
            }
        >
            <div className="space-y-6">
                {/* Success message */}
                <div className="flex items-center gap-3">
                    <CheckCircle2
                        className="h-6 w-6 flex-shrink-0 text-green-400"
                        strokeWidth={2.5}
                    />
                    <p className="text-sm font-medium leading-7 text-green-400">
                        Invoice and payment activity have been created, your
                        anti-limbo continuation is active, and we are moving you
                        to enrollment now.
                    </p>
                </div>

                {/* Footer + tombol */}
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <p className="text-sm text-gray-500">
                        Redirecting to enrollment automatically...
                    </p>

                    <Button
                        asChild
                        className="rounded-md bg-[#DB202C] px-6 text-white hover:bg-[#c01a25]"
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
