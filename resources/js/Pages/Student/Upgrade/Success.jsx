import { Button } from '@/Components/ui/button';
import PublicFlowLayout from '@/Layouts/PublicFlowLayout';
import { formatCurrency } from '@/lib/currency';
import { Head, Link } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';

export default function UpgradePaymentSuccess({ upgrade }) {
    return (
        <PublicFlowLayout
            title="Upgrade Success"
            eyebrow="Payment Completed"
            heading="Your upgrade payment was successful."
            description="Your YogaFX upgrade has been finalized, your new tier access is ready, and you can continue back into your learning dashboard whenever you're ready."
            aside={
                <div className="space-y-6">
                    <div className="rounded-[10px] border border-white/10 bg-white/5 p-5">
                        <div className="flex justify-end">
                            <p className="inline-block rounded-full border border-white/30 bg-white/10 px-4 py-1 text-sm font-semibold text-white">
                                Upgrade result
                            </p>
                        </div>
                        <div className="mt-7 space-y-3 text-sm text-white">
                            <p>Student: {upgrade.student?.name ?? 'Student'}</p>
                            <p>Email: {upgrade.student?.email ?? '-'}</p>
                            <p>Tier: {upgrade.target_tier?.name ?? '-'}</p>
                            <p>Invoice: {upgrade.invoice_number}</p>
                            <p>Amount received: {formatCurrency(upgrade.amount_paid, upgrade.currency_code)}</p>
                        </div>
                    </div>

                    <div className="rounded-[10px] border border-white/10 bg-white/5 p-5">
                        <div className="flex justify-end">
                            <p className="inline-block rounded-full border border-white/30 bg-white/10 px-4 py-1 text-sm font-semibold text-white">
                                Next step
                            </p>
                        </div>
                        <div className="mt-7 space-y-3 text-sm leading-6 text-white/70">
                            <p>Your account has already been moved into the upgraded access tier.</p>
                            <p>Return to the dashboard to continue with the new YogaFX content available in this path.</p>
                        </div>
                    </div>
                </div>
            }
        >
            <Head title="Upgrade Success" />

            <div className="space-y-6">
                <div className="flex items-center gap-3">
                    <CheckCircle2
                        className="h-6 w-6 flex-shrink-0 text-green-400"
                        strokeWidth={2.5}
                    />
                    <p className="text-sm font-medium leading-7 text-green-400">
                        Your upgrade payment has been finalized and your new tier is now active.
                    </p>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-4">
                    <p className="text-sm text-gray-500">
                        Continue back into your upgraded learning dashboard when you're ready.
                    </p>

                    <Button
                        asChild
                        className="rounded-md bg-[#DB202C] px-6 text-white hover:bg-[#DB202C]"
                    >
                        <Link href={upgrade.continue_url}>
                            {upgrade.cta_label ?? 'Return to Dashboard'}
                        </Link>
                    </Button>
                </div>
            </div>
        </PublicFlowLayout>
    );
}
