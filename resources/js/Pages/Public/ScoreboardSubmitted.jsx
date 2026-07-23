import { Button } from "@/Components/ui/button";
import PublicFlowLayout from "@/Layouts/PublicFlowLayout";
import { CheckCircle2 } from "lucide-react";

function formatCurrency(amount) {
    return new Intl.NumberFormat("en-US", {
        style: "currency",
        currency: "USD",
    }).format(Number(amount || 0));
}

export default function ScoreboardSubmitted({ registration }) {
    return (
        <PublicFlowLayout
            title="Registration Saved"
            heading="Your YogaFX checkout link is ready"
            description="We saved your lead registration as a pending record. The next step is the signed checkout flow that keeps the selected tier and amount stable."
            aside={
                <div className="space-y-6">
                    {/* Registration Snapshot */}
                    <div className="rounded-[10px] border border-white/10 bg-white/5 p-5">
                        <div className="flex justify-end">
                            <p className="inline-block rounded-full border border-white/30 bg-white/10 px-4 py-1 text-sm font-semibold text-white">
                                Registration snapshot
                            </p>
                        </div>

                        <div className="mt-7 space-y-3">
                            <p className="text-white">
                                {registration.full_name}
                            </p>

                            <p className="text-white">{registration.email}</p>

                            <p className="text-white">{registration.phone}</p>

                            <p className="text-white">{registration.country}</p>
                        </div>
                    </div>

                    {/* Selected Tier */}
                    <div className="rounded-[10px] border border-white/10 bg-white/5 p-5">
                        <div className="flex justify-end">
                            <p className="inline-block rounded-full border border-white/30 bg-white/10 px-4 py-1 text-sm font-semibold text-white">
                                Selected tier
                            </p>
                        </div>

                        <div className="mt-7 space-y-3">
                            <div className="text-2xl font-semibold text-white">
                                {registration.access_tier.name}
                            </div>

                            <div className="inline-block rounded-full border border-white/20 bg-white/10 px-4 py-2 text-sm text-white">
                                {formatCurrency(registration.amount)}
                            </div>
                        </div>
                    </div>
                </div>
            }
        >
            <div className="space-y-6">
                {/* Success Message */}
                <div className="flex items-center gap-3">
                    <CheckCircle2
                        className="h-6 w-6 flex-shrink-0 text-green-400"
                        strokeWidth={2.5}
                    />

                    <p className="text-sm font-medium leading-7 text-green-400">
                        Your pending registration is now stored in the database,
                        and this same signed checkout link has also been sent to
                        your registered email.
                    </p>
                </div>

                {/* Checkout Section */}
                <div>
                    <p className="text-base leading-7 text-white/80">
                        Continue when you are ready. The signed checkout link
                        preserves the selected tier and keeps this lead inside
                        the correct payment journey.
                    </p>

                    <div className="mt-5">
                        <Button
                            asChild
                            className="rounded-md bg-[#DB202C] px-6 text-white hover:bg-[#c01a25]"
                        >
                            <a
                                href={registration.checkout_url}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Open Signed Checkout
                            </a>
                        </Button>
                    </div>
                </div>
            </div>
        </PublicFlowLayout>
    );
}
