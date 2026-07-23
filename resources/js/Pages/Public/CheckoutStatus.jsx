import { Button } from "@/Components/ui/button";
import PublicFlowLayout from "@/Layouts/PublicFlowLayout";
import { formatCurrency } from "@/lib/currency";
import { Head, Link } from "@inertiajs/react";
import { AlertCircle, Clock3 } from "lucide-react";

export default function CheckoutStatus({ statusPage }) {
    const isPending = statusPage.status === "pending";

    return (
        <PublicFlowLayout
            title="Checkout Status"
            eyebrow="Payment Status"
            heading={isPending ? "Your payment is still being processed." : "This payment did not complete."}
            description={statusPage.message}
            aside={
                <div className="space-y-6">
                    <div className="rounded-[18px] border border-white/10 bg-white/5 p-5">
                        <p className="text-sm font-semibold text-white">
                            Transaction snapshot
                        </p>
                        <div className="mt-5 space-y-2 text-sm text-white/78">
                            <p>Invoice: {statusPage.invoice_number}</p>
                            <p>Tier: {statusPage.access_tier.name}</p>
                            <p>
                                Amount:{" "}
                                {formatCurrency(
                                    statusPage.amount,
                                    statusPage.currency_code,
                                )}
                            </p>
                            <p>Status: {statusPage.status}</p>
                        </div>
                    </div>
                </div>
            }
        >
            <Head title="Checkout Status" />

            <div className="space-y-6">
                <div
                    className={`rounded-[20px] border px-5 py-5 text-sm ${
                        isPending
                            ? "border-amber-300/20 bg-amber-400/10 text-amber-100"
                            : "border-rose-400/20 bg-rose-500/10 text-rose-100"
                    }`}
                >
                    <div className="flex items-start gap-3">
                        {isPending ? (
                            <Clock3 className="mt-0.5 h-5 w-5 flex-shrink-0" />
                        ) : (
                            <AlertCircle className="mt-0.5 h-5 w-5 flex-shrink-0" />
                        )}
                        <p>{statusPage.message}</p>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-4">
                    <Button
                        asChild
                        className="rounded-full bg-[#d5462f] px-6 text-white hover:bg-[#e2553d]"
                    >
                        <Link href={isPending ? statusPage.refresh_url : statusPage.retry_url}>
                            {isPending ? "Refresh Status" : "Return to Checkout"}
                        </Link>
                    </Button>

                    {!isPending && (
                        <Button
                            asChild
                            variant="outline"
                            className="rounded-full border-white/15 bg-transparent text-white hover:bg-white/10"
                        >
                            <Link href={statusPage.retry_url}>Try Again</Link>
                        </Button>
                    )}
                </div>
            </div>
        </PublicFlowLayout>
    );
}
