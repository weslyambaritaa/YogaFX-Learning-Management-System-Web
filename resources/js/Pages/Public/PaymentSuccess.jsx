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
                    <div className="rounded-[24px] border border-white/10 bg-[#161210] p-5">
                        <p className="text-xs uppercase tracking-[0.22em] text-white/46">
                            Payment result
                        </p>
                        <div className="mt-4 space-y-2 text-sm text-white/66">
                            <p>Student: {student.name}</p>
                            <p>Email: {student.email}</p>
                            <p>Tier: {onboarding.access_tier.name}</p>
                            <p>Status: success</p>
                        </div>
                    </div>

                    <div className="rounded-[24px] border border-white/10 bg-[#161210] p-5">
                        <p className="text-xs uppercase tracking-[0.22em] text-white/46">
                            Next step
                        </p>
                        <p className="mt-4 text-sm leading-6 text-white/62">
                            Enrollment comes next, then final password creation, then LMS access.
                        </p>
                    </div>
                </div>
            }
        >
            <div className="space-y-5">
                <div className="rounded-[28px] border border-emerald-300/15 bg-[linear-gradient(160deg,rgba(16,185,129,0.16),rgba(255,255,255,0.03))] px-5 py-5 text-emerald-50/92">
                    <div className="flex items-start gap-4">
                        <div className="rounded-full border border-white/10 bg-white/10 p-2">
                            <CheckCircle2 className="size-6" />
                        </div>
                        <div>
                            <p className="text-lg font-semibold">
                                Payment success confirmed
                            </p>
                            <p className="mt-2 text-sm leading-6">
                                Invoice and payment activity have been created, your anti-limbo continuation is active, and we are moving you to enrollment now.
                            </p>
                        </div>
                    </div>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-4 rounded-[24px] border border-white/10 bg-black/20 px-5 py-4">
                    <p className="text-sm text-white/54">
                        Redirecting to enrollment automatically...
                    </p>

                    <Button
                        asChild
                        className="rounded-full bg-[#d5462f] px-6 text-white hover:bg-[#e2553d]"
                    >
                        <Link href={onboarding.continue_url}>Continue to Enrollment</Link>
                    </Button>
                </div>
            </div>
        </PublicFlowLayout>
    );
}
