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
            heading="Your YogaFX checkout link is ready"
            description="We saved your lead registration as a pending record. The next step is the signed checkout flow that keeps the selected tier and amount stable."
            aside={
                <div className="space-y-5">
                    {/* Registration Snapshot — card gelap, teks putih */}
                    <div className="rounded-[10px] border border-gray-200 bg-gray-900 p-5">
                        <div className="flex justify-end">
                            <p className="inline-block rounded-full border border-white/30 bg-white/10 px-4 py-1 text-sm font-semibold text-white">
                                Registration snapshot
                            </p>
                        </div>
                        <div className="mt-7 space-y-2 text-sm text-white">
                            <p>{registration.full_name}</p>
                            <p>{registration.email}</p>
                            <p>{registration.phone}</p>
                            <p>{registration.country}</p>
                        </div>
                    </div>

                    {/* Selected Tier — card gelap, teks putih */}
                    <div className="rounded-[10px] border border-gray-200 bg-gray-900 p-5">
                        <div className="flex justify-end">
                            <p className="inline-block rounded-full border border-white/30 bg-white/10 px-4 py-1 text-sm font-semibold text-white">
                                Selected tier
                            </p>
                        </div>
                        <div className="mt-7">
                            <p className="text-2xl font-semibold text-white">
                                {registration.access_tier.name}
                            </p>
                            <p className="mt-2 text-sm text-white">
                                {formatCurrency(registration.amount)}
                            </p>
                        </div>
                    </div>
                </div>
            }
        >
            <div className="space-y-5">
                {/* Info box hijau */}
                <div className="rounded-[24px] border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm leading-6 text-emerald-800">
                    Your pending registration is now stored in the database, and this same signed checkout link has also been sent to your registered email.
                </div>

                {/* Box abu + tombol */}
                <div className="rounded-[10px] border border-gray-300 bg-gray-300 p-5">
                    <p className="text-sm leading-7 text-gray-600">
                        Continue when you are ready. The signed checkout link preserves the selected tier and keeps this lead inside the correct payment journey.
                    </p>
                    <div className="mt-5 flex flex-wrap gap-3">
                        <Button
                            asChild
                            className="rounded-md bg-red-600 px-6 text-white hover:bg-red-700"
                        >
                            <a href={registration.checkout_url}>Open Signed Checkout</a>
                        </Button>
                    </div>
                </div>
            </div>
        </PublicFlowLayout>
    );
}