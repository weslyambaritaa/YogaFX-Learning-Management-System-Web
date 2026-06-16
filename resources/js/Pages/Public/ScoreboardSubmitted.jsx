import { Button } from '@/Components/ui/button';
import PublicFlowLayout from '@/Layouts/PublicFlowLayout';

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(Number(amount || 0));
}

export default function ScoreboardSubmitted({ registration }) {
    return (
        <PublicFlowLayout
            title="Registration Saved"
            eyebrow="Pending Registration Created"
            heading="Your YogaFX checkout link is ready."
            description="We saved your lead registration as a pending record. The next step is the signed checkout flow that keeps the selected tier and amount stable."
            aside={
                <div className="space-y-5">
                    <div className="rounded-[24px] border border-white/10 bg-[#161210] p-5">
                        <p className="text-xs uppercase tracking-[0.22em] text-white/46">
                            Registration snapshot
                        </p>
                        <div className="mt-4 space-y-2 text-sm text-white/68">
                            <p>{registration.full_name}</p>
                            <p>{registration.email}</p>
                            <p>{registration.phone}</p>
                            <p>{registration.country}</p>
                        </div>
                    </div>

                    <div className="rounded-[24px] border border-white/10 bg-[#161210] p-5">
                        <p className="text-xs uppercase tracking-[0.22em] text-white/46">
                            Selected tier
                        </p>
                        <div className="mt-4">
                            <p className="text-2xl font-semibold text-white">
                                {registration.access_tier.name}
                            </p>
                            <p className="mt-2 text-sm text-white/60">
                                {formatCurrency(registration.amount)}
                            </p>
                        </div>
                    </div>
                </div>
            }
        >
            <div className="space-y-5">
                <div className="rounded-[24px] border border-emerald-300/15 bg-[linear-gradient(160deg,rgba(16,185,129,0.16),rgba(255,255,255,0.03))] px-5 py-4 text-sm leading-6 text-emerald-50/92">
                    Your pending registration is now stored in the database. In the full business flow, this signed checkout link is also the link that would be emailed to you.
                </div>

                <div className="rounded-[24px] border border-white/10 bg-black/20 p-5">
                    <p className="text-sm leading-7 text-white/60">
                        Continue when you are ready. The signed checkout link preserves the selected tier and keeps this lead inside the correct payment journey.
                    </p>
                    <div className="mt-5 flex flex-wrap gap-3">
                        <Button
                            asChild
                            className="rounded-full bg-[#d5462f] px-6 text-white hover:bg-[#e2553d]"
                        >
                            <a href={registration.checkout_url}>Open Signed Checkout</a>
                        </Button>
                    </div>
                </div>
            </div>
        </PublicFlowLayout>
    );
}
