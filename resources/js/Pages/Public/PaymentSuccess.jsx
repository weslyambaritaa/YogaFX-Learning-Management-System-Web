import { Button } from '@/Components/ui/button';
import PublicFlowLayout from '@/Layouts/PublicFlowLayout';
import { Link } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import { useEffect } from 'react';

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
            heading="Berhasil melakukan pembayaran."
            description="Simulated payment has been recorded as successful, your YogaFX base account has already been created, and the flow will now continue into enrollment."
            aside={
                <div className="space-y-5">
                    {/* Payment result card */}
                    <div className="rounded-[24px] border border-gray-200 bg-gray-900 p-5">
                        <p className="text-sm font-semibold text-white">
                            Payment result
                        </p>
                        <div className="mt-4 space-y-2 text-sm text-white">
                            <p>Student: {student.name}</p>
                            <p>Email: {student.email}</p>
                            <p>Tier: {onboarding.access_tier.name}</p>
                            <p>Status: success</p>
                        </div>
                    </div>

                    {/* Next step card */}
                    <div className="rounded-[24px] border border-gray-200 bg-gray-900 p-5">
                        <p className="text-sm font-semibold text-white">
                            Next step
                        </p>
                        <p className="mt-4 text-sm leading-6 text-white/80">
                            Enrollment comes next, then final password creation, then LMS access.
                        </p>
                    </div>
                </div>
            }
        >
            <div className="space-y-5">
                {/* Success info box hijau */}
                <div className="rounded-[16px] border border-emerald-200 bg-emerald-50 px-5 py-5 text-emerald-800">
                    <div className="flex items-start gap-4">
                        <div className="rounded-full border border-emerald-300 bg-emerald-100 p-2">
                            <CheckCircle2 className="size-5 text-emerald-700" />
                        </div>
                        <div>
                            <p className="text-sm font-semibold">
                                Payment success confirmed
                            </p>
                            <p className="mt-2 text-sm leading-6 text-emerald-700">
                                Invoice and payment records have been created, your anti-limbo continuation is active, and we are moving you to enrollment now.
                            </p>
                        </div>
                    </div>
                </div>

                {/* Box abu + tombol */}
                <div className="flex flex-wrap items-center justify-between gap-4 rounded-[15px] border border-gray-300 bg-gray-300 px-5 py-4">
                    <p className="text-sm text-gray-600">
                        Redirecting to enrollment automatically...
                    </p>

                    <Button
                        asChild
                        className="rounded-md bg-red-600 px-6 text-white hover:bg-red-700"
                    >
                        <Link href={onboarding.continue_url}>Continue to Enrollment</Link>
                    </Button>
                </div>
            </div>
        </PublicFlowLayout>
    );
}
