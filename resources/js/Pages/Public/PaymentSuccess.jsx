import { Button } from "@/Components/ui/button";
import PublicFlowLayout from "@/Layouts/PublicFlowLayout";
import { Link } from "@inertiajs/react";
import { CheckCircle2 } from "lucide-react";

export default function PaymentSuccess({ onboarding, student }) {
    return (
        <PublicFlowLayout
            title="Payment Success"
            eyebrow="Payment Completed"
            heading="Congratulations. Your payment was successful."
            description="Your YogaFX onboarding is ready to continue. Please head to enrollment first, then finish signup in the next step."
            aside={
                <div className="space-y-6">
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

                    <div className="rounded-[10px] border border-white/10 bg-white/5 p-5">
                        <div className="flex justify-end">
                            <p className="inline-block rounded-full border border-white/30 bg-white/10 px-4 py-1 text-sm font-semibold text-white">
                                Next step
                            </p>
                        </div>
                        <div className="mt-7 space-y-3 text-sm leading-6 text-white/70">
                            <p>
                                Enrollment comes next. After that, you will
                                create your password and activate your YogaFX
                                LMS access.
                            </p>
                        </div>
                    </div>
                </div>
            }
        >
            <div className="space-y-6">
                <div className="flex items-center gap-3">
                    <CheckCircle2
                        className="h-6 w-6 flex-shrink-0 text-green-400"
                        strokeWidth={2.5}
                    />
                    <p className="text-sm font-medium leading-7 text-green-400">
                        Congratulations. Your payment has been recorded
                        successfully and your enrollment step is now ready.
                    </p>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-4">
                    <p className="text-sm text-gray-500">
                        Continue to enrollment when you are ready.
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
