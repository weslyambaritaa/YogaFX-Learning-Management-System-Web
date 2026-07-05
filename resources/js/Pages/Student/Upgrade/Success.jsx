import { Button } from '@/Components/ui/button';
import PublicFlowLayout from '@/Layouts/PublicFlowLayout';
import { Head, Link } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';

export default function UpgradePaymentSuccess({ upgrade }) {
    return (
        <PublicFlowLayout
            title="Upgrade Success"
            eyebrow="Payment Completed"
            heading="Your upgrade payment was received."
        >
            <Head title="Upgrade Success" />

            <div className="flex justify-center py-4">
                <div className="w-full max-w-md rounded-[5px] border border-white/10 bg-white/5 px-6 py-8 text-center">
                    <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-500/15">
                        <CheckCircle2 className="h-9 w-9 text-emerald-400" />
                    </div>
                    <p className="mt-5 text-sm text-white/75">
                        Your access tier is now {upgrade.target_tier?.name ?? 'active'}.
                    </p>
                    <Button
                        asChild
                        className="mt-6 w-full rounded-md bg-[#DB202C] px-6 text-white hover:bg-[#c01a25]"
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
