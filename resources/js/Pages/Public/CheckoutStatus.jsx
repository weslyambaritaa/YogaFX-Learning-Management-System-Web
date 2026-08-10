import { Button } from "@/Components/ui/button";
import PublicFlowLayout from "@/Layouts/PublicFlowLayout";
import { formatCurrency } from "@/lib/currency";
import { Link } from "@inertiajs/react";
import { AlertCircle, Clock3 } from "lucide-react";

const FONT_FAMILY = "'Montserrat', sans-serif";

export default function CheckoutStatus({ statusPage }) {
    const isPending = statusPage.status === "pending";

    const heading = isPending
        ? "Your Payment Is Still Being Processed."
        : "This Payment Did Not Complete.";

    return (
        <PublicFlowLayout
            title="Checkout Status"
            eyebrow={null}
            heading={null}
            description={null}
        >
            <div
                className="flex justify-center pb-8 pt-2"
                style={{ fontFamily: FONT_FAMILY }}
            >
                <div className="w-full max-w-2xl text-center">
                    <div
                        className={[
                            "mx-auto flex h-32 w-32 items-center justify-center rounded-full",
                            isPending
                                ? "bg-amber-500 shadow-[0_0_45px_rgba(245,158,11,0.35)]"
                                : "bg-[#DB202C] shadow-[0_0_45px_rgba(219,32,44,0.35)]",
                        ].join(" ")}
                    >
                        {isPending ? (
                            <Clock3
                                className="h-20 w-20 text-white"
                                strokeWidth={3.5}
                                aria-hidden="true"
                            />
                        ) : (
                            <AlertCircle
                                className="h-20 w-20 text-white"
                                strokeWidth={3.5}
                                aria-hidden="true"
                            />
                        )}
                    </div>

                    <p
                        className={[
                            "mt-8 text-sm font-bold uppercase tracking-[0.2em]",
                            isPending ? "text-amber-300" : "text-[#ffb8bf]",
                        ].join(" ")}
                    >
                        Payment Status
                    </p>

                    <h1 className="mx-auto mt-3 max-w-xl text-3xl font-bold leading-tight text-white sm:text-4xl">
                        {heading}
                    </h1>

                    <p className="mx-auto mt-5 max-w-xl text-base font-medium leading-7 text-white/80 sm:text-lg">
                        {statusPage.message}
                    </p>

                    <div className="mx-auto mt-8 w-full max-w-xl rounded-[18px] border border-white/15 bg-[#111111] px-6 py-6 text-left shadow-[0_18px_55px_rgba(0,0,0,0.28)] sm:px-8">
                        <h2 className="text-center text-lg font-bold text-white">
                            Transaction Snapshot
                        </h2>

                        <div className="mt-6 divide-y divide-white/10">
                            <div className="flex items-center justify-between gap-6 py-3">
                                <span className="text-sm font-medium text-white/60">
                                    Invoice
                                </span>
                                <span className="text-right text-sm font-bold text-white">
                                    {statusPage.invoice_number}
                                </span>
                            </div>

                            <div className="flex items-center justify-between gap-6 py-3">
                                <span className="text-sm font-medium text-white/60">
                                    Tier
                                </span>
                                <span className="text-right text-sm font-bold text-white">
                                    {statusPage.access_tier?.name ?? "-"}
                                </span>
                            </div>

                            <div className="flex items-center justify-between gap-6 py-3">
                                <span className="text-sm font-medium text-white/60">
                                    Amount
                                </span>
                                <span className="text-right text-sm font-bold text-white">
                                    {formatCurrency(
                                        statusPage.amount,
                                        statusPage.currency_code,
                                    )}
                                </span>
                            </div>

                            <div className="flex items-center justify-between gap-6 py-3">
                                <span className="text-sm font-medium text-white/60">
                                    Status
                                </span>
                                <span
                                    className={[
                                        "text-right text-sm font-bold capitalize",
                                        isPending
                                            ? "text-amber-300"
                                            : "text-[#ff6b75]",
                                    ].join(" ")}
                                >
                                    {statusPage.status}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="mt-8 flex justify-center px-4">
                        <Button
                            asChild
                            className="min-h-[64px] w-full max-w-[360px] rounded-[8px] bg-[#DB202C] px-8 py-5 text-base font-bold italic text-white shadow-[0_12px_35px_rgba(219,32,44,0.3)] transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#c01a25] focus:outline-none focus:ring-4 focus:ring-[#DB202C]/35 sm:text-xl"
                        >
                            <Link
                                href={
                                    isPending
                                        ? statusPage.refresh_url
                                        : statusPage.retry_url
                                }
                                className="inline-flex items-center justify-center whitespace-nowrap"
                            >
                                {isPending
                                    ? "Refresh Payment Status"
                                    : "Return To Checkout"}
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>
        </PublicFlowLayout>
    );
}
