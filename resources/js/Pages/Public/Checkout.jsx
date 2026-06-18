import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import { Button } from "@/Components/ui/button";
import PublicFlowLayout from "@/Layouts/PublicFlowLayout";
import { formatCurrency } from "@/lib/currency";
import { useForm } from "@inertiajs/react";
import { useState } from "react";

export default function Checkout({ checkout }) {
    const { data, setData, post, processing, errors } = useForm({
        payment_type: "pay_full",
        payment_method: "paypal",
    });
    const [isSimulating, setIsSimulating] = useState(false);

    const payNow = (event) => {
        event.preventDefault();

        setIsSimulating(true);

        window.setTimeout(() => {
            post(checkout.pay_url, {
                preserveScroll: true,
                onFinish: () => setIsSimulating(false),
            });
        }, 2300);
    };

    const installmentAmount = Number(checkout.amount || 0) / 4;

    return (
        <PublicFlowLayout
            title="Checkout"
            heading="Review your details, choose how to pay, and continue into YogaFX."
            description="This checkout still looks and behaves like a real payment step, but the actual payment result for this phase is simulated internally so the business flow can be built without a live gateway."
            aside={
                <div className="space-y-6">
                    {/* Program card */}
                    <div className="rounded-[10px] border border-white/10 bg-white/5 p-5">
                        <div className="flex justify-end">
                            <p className="inline-block rounded-full border border-white/30 bg-white/10 px-4 py-1 text-sm font-semibold text-white">
                                Program
                            </p>
                        </div>
                        <div className="mt-7 space-y-3">
                            <div className="text-2xl font-semibold text-white">
                                {checkout.access_tier.name}
                            </div>
                            <div className="inline-block rounded-full border border-white/20 bg-white/10 px-4 py-2 text-sm text-white">
                                {formatCurrency(checkout.amount)}
                            </div>
                        </div>
                    </div>

                    {/* Payment behavior card */}
                    <div className="rounded-[10px] border border-white/10 bg-white/5 p-5">
                        <div className="flex justify-end">
                            <p className="inline-block rounded-full border border-white/30 bg-white/10 px-4 py-1 text-sm font-semibold text-white">
                                Payment behavior
                            </p>
                        </div>
                        <div className="mt-7 space-y-3 text-sm leading-6 text-white/70">
                            <p>
                                Invoice and payment activity are created when
                                you click Pay Now.
                            </p>
                            <p>
                                The payment result is simulated as success after
                                a short loading state.
                            </p>
                            <p>
                                Successful payment immediately opens anti-limbo
                                onboarding continuation.
                            </p>
                        </div>
                    </div>
                </div>
            }
        >
            <form onSubmit={payNow} className="space-y-6">
                {/* Input fields — style gelap */}
                <div className="grid gap-5 md:grid-cols-2">
                    {[
                        ["First Name", checkout.first_name],
                        ["Last Name", checkout.last_name],
                        ["Email", checkout.email],
                        ["Mobile Phone", checkout.phone],
                        ["Country", checkout.country],
                        [
                            "Amount",
                            formatCurrency(
                                checkout.amount,
                                checkout.currency_code,
                            ),
                        ],
                    ].map(([label, value]) => (
                        <div key={label}>
                            <InputLabel
                                value={label}
                                className="text-gray-700"
                            />
                            <input
                                value={value}
                                disabled
                                className="mt-2 block w-full rounded-md border border-gray-700 bg-black px-3 py-2 text-white"
                            />
                        </div>
                    ))}
                </div>

                {/* Select dropdowns — style gelap */}
                <div className="grid gap-5 md:grid-cols-2">
                    <div>
                        <InputLabel
                            htmlFor="payment_type"
                            value="Payment Type"
                            className="text-gray-700"
                        />
                        <select
                            id="payment_type"
                            value={data.payment_type}
                            onChange={(event) =>
                                setData("payment_type", event.target.value)
                            }
                            className="mt-2 block w-full rounded-md border border-gray-700 bg-black text-white focus:border-white focus:ring-white"
                        >
                            <option value="pay_full">
                                Pay in Full -{" "}
                                {formatCurrency(
                                    checkout.amount,
                                    checkout.currency_code,
                                )}
                            </option>
                            <option value="installment">
                                Pay in 4 Installments -{" "}
                                {formatCurrency(
                                    installmentAmount,
                                    checkout.currency_code,
                                )}{" "}
                                today
                            </option>
                        </select>
                        <InputError
                            className="mt-2"
                            message={errors.payment_type}
                        />
                    </div>

                    <div>
                        <InputLabel
                            htmlFor="payment_method"
                            value="Payment Method"
                            className="text-gray-700"
                        />
                        <select
                            id="payment_method"
                            value={data.payment_method}
                            onChange={(event) =>
                                setData("payment_method", event.target.value)
                            }
                            className="mt-2 block w-full rounded-md border border-gray-700 bg-black text-white focus:border-white focus:ring-white"
                        >
                            <option value="paypal">PayPal</option>
                            <option value="bank_transfer">Bank Transfer</option>
                        </select>
                        <InputError
                            className="mt-2"
                            message={errors.payment_method}
                        />
                    </div>
                </div>

                {/* Info box abu */}
                <div className="rounded-[7px] border border-gray-300 bg-gray-300 px-5 py-4">
                    <p className="text-sm leading-6 text-gray-700">
                        {data.payment_type === "installment"
                            ? `The first simulated payment records ${formatCurrency(installmentAmount, checkout.currency_code)} now and leaves the remaining balance on the invoice as installment.`
                            : "The simulated payment records the full amount and closes the invoice as paid in full."}
                    </p>
                </div>

                {/* Footer + tombol */}
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <p className="text-sm text-gray-500">
                        Signed route validated. Amount and tier are locked to
                        this pending registration.
                    </p>

                    <Button
                        type="submit"
                        disabled={processing || isSimulating}
                        className="rounded-md bg-red-600 px-6 text-white hover:bg-red-700"
                    >
                        {isSimulating ? "Processing payment..." : "Pay Now"}
                    </Button>
                </div>
            </form>
        </PublicFlowLayout>
    );
}
