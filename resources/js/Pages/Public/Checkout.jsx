import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import { Button } from '@/Components/ui/button';
import PublicFlowLayout from '@/Layouts/PublicFlowLayout';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(Number(amount || 0));
}

export default function Checkout({ checkout }) {
    const { data, setData, post, processing, errors } = useForm({
        payment_type: 'pay_in_full',
        payment_method: 'paypal_credit_card',
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
            eyebrow="Signed Checkout"
            heading="Review your details, choose how to pay, and continue into YogaFX."
            description="This checkout still looks and behaves like a real payment step, but the actual payment result for this phase is simulated internally so the business flow can be built without a live gateway."
            aside={
                <div className="space-y-5">
                    <div className="rounded-[24px] border border-white/10 bg-[#161210] p-5">
                        <p className="text-xs uppercase tracking-[0.22em] text-white/46">
                            Program
                        </p>
                        <div className="mt-4">
                            <p className="text-2xl font-semibold text-white">
                                {checkout.access_tier.name}
                            </p>
                            <p className="mt-2 text-sm text-white/60">
                                {formatCurrency(checkout.amount)}
                            </p>
                        </div>
                    </div>

                    <div className="rounded-[24px] border border-white/10 bg-[#161210] p-5">
                        <p className="text-xs uppercase tracking-[0.22em] text-white/46">
                            Payment behavior
                        </p>
                        <div className="mt-4 space-y-3 text-sm leading-6 text-white/62">
                            <p>Invoice and payment activity are created when you click Pay Now.</p>
                            <p>The payment result is simulated as success after a short loading state.</p>
                            <p>Successful payment immediately opens anti-limbo onboarding continuation.</p>
                        </div>
                    </div>
                </div>
            }
        >
            <form onSubmit={payNow} className="space-y-6">
                <div className="grid gap-5 md:grid-cols-2">
                    {[
                        ['First Name', checkout.first_name],
                        ['Last Name', checkout.last_name],
                        ['Email', checkout.email],
                        ['Mobile Phone', checkout.phone],
                        ['Country', checkout.country],
                        ['Amount', formatCurrency(checkout.amount)],
                    ].map(([label, value]) => (
                        <div key={label}>
                            <InputLabel value={label} className="text-white/72" />
                            <input
                                value={value}
                                disabled
                                className="mt-2 block w-full rounded-md border border-white/12 bg-white/5 px-3 py-2 text-white/72"
                            />
                        </div>
                    ))}
                </div>

                <div className="grid gap-5 md:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="payment_type" value="Payment Type" className="text-white/72" />
                        <select
                            id="payment_type"
                            value={data.payment_type}
                            onChange={(event) => setData('payment_type', event.target.value)}
                            className="mt-2 block w-full rounded-md border border-white/12 bg-[#171311] text-white focus:border-[#d5462f] focus:ring-[#d5462f]"
                        >
                            <option value="pay_in_full">
                                Pay in Full - {formatCurrency(checkout.amount)}
                            </option>
                            <option value="pay_in_4_installments">
                                Pay in 4 Installments - {formatCurrency(installmentAmount)} today
                            </option>
                        </select>
                        <InputError className="mt-2 text-[#ffb4a8]" message={errors.payment_type} />
                    </div>

                    <div>
                        <InputLabel htmlFor="payment_method" value="Payment Method" className="text-white/72" />
                        <select
                            id="payment_method"
                            value={data.payment_method}
                            onChange={(event) => setData('payment_method', event.target.value)}
                            className="mt-2 block w-full rounded-md border border-white/12 bg-[#171311] text-white focus:border-[#d5462f] focus:ring-[#d5462f]"
                        >
                            <option value="paypal_credit_card">PayPal / Credit Card</option>
                            <option value="bank_transfer">Bank Transfer</option>
                        </select>
                        <InputError className="mt-2 text-[#ffb4a8]" message={errors.payment_method} />
                    </div>
                </div>

                <div className="rounded-[24px] border border-white/10 bg-black/20 px-5 py-4">
                    <p className="text-sm leading-6 text-white/58">
                        {data.payment_type === 'pay_in_4_installments'
                            ? `The first simulated payment records ${formatCurrency(installmentAmount)} now and leaves the remaining balance on the invoice as installment.`
                            : 'The simulated payment records the full amount and closes the invoice as paid in full.'}
                    </p>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-4">
                    <p className="text-sm text-white/48">
                        Signed route validated. Amount and tier are locked to this pending registration.
                    </p>

                    <Button
                        type="submit"
                        disabled={processing || isSimulating}
                        className="rounded-full bg-[#d5462f] px-6 text-white hover:bg-[#e2553d]"
                    >
                        {isSimulating ? 'Processing payment...' : 'Pay Now'}
                    </Button>
                </div>
            </form>
        </PublicFlowLayout>
    );
}
