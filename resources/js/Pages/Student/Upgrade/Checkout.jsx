import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatCurrency } from '@/lib/currency';
import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function UpgradeCheckout({ upgrade }) {
    const { data, setData, post, processing, errors } = useForm({
        payment_type: 'pay_full',
        payment_method: upgrade.payment_method_options?.[0]?.value ?? 'paypal',
    });
    const [isSimulating, setIsSimulating] = useState(false);
    const mockOptionEnabled = (upgrade.payment_method_options ?? []).some(
        (option) => option.value === 'mock',
    );

    const submit = (event) => {
        event.preventDefault();
        setIsSimulating(true);

        window.setTimeout(() => {
            post(upgrade.submit_url, {
                preserveScroll: true,
                onFinish: () => setIsSimulating(false),
            });
        }, 2300);
    };

    const installmentAmount = Number(upgrade.amount_due || 0) / 4;

    return (
        <AuthenticatedLayout studentVariant="immersive" studentContentClassName="pb-16">
            <Head title="Upgrade Program" />

            <div className="mx-auto flex max-w-[1100px] flex-col gap-8 px-4 pt-6 sm:px-6 lg:px-10">
                <section className="rounded-[32px] border border-white/10 bg-[#15110f] p-6 shadow-[0_30px_100px_rgba(0,0,0,0.4)] sm:p-8 lg:p-10">
                    <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_320px]">
                        <div className="space-y-6">
                            <div className="space-y-3">
                                <p className="text-xs uppercase tracking-[0.28em] text-[#f2d9c8]">
                                    Tier Upgrade
                                </p>
                                <h1 className="text-4xl font-semibold tracking-[-0.04em] text-white sm:text-5xl">
                                    Move into a higher YogaFX tier without losing your billing history.
                                </h1>
                                <p className="max-w-2xl text-sm leading-7 text-white/64 sm:text-base">
                                    This upgrade flow keeps your payment history intact, creates a fresh invoice for the remaining amount due, then continues through full PayPal redirect before your new tier becomes active.
                                </p>
                            </div>

                            <form onSubmit={submit} className="space-y-6 rounded-[28px] border border-white/10 bg-white/[0.04] p-5">
                                <div className="grid gap-5 md:grid-cols-2">
                                    <div>
                                        <label className="text-xs uppercase tracking-[0.18em] text-white/62">
                                            Payment Type
                                        </label>
                                        <select
                                            value={data.payment_type}
                                            onChange={(event) => setData('payment_type', event.target.value)}
                                            className="mt-2 block w-full rounded-md border border-white/12 bg-[#171311] text-white focus:border-[#d5462f] focus:ring-[#d5462f]"
                                        >
                                            <option value="pay_full">
                                                Pay in Full - {formatCurrency(upgrade.amount_due, upgrade.target_tier.currency_code)}
                                            </option>
                                            <option value="installment">
                                                Pay in 4 Installments - {formatCurrency(installmentAmount, upgrade.target_tier.currency_code)} today
                                            </option>
                                        </select>
                                        {errors.payment_type && (
                                            <p className="mt-2 text-sm text-[#ffb4a8]">{errors.payment_type}</p>
                                        )}
                                    </div>

                                    <div>
                                        <label className="text-xs uppercase tracking-[0.18em] text-white/62">
                                            Payment Method
                                        </label>
                                        <select
                                            value={data.payment_method}
                                            onChange={(event) => setData('payment_method', event.target.value)}
                                            className="mt-2 block w-full rounded-md border border-white/12 bg-[#171311] text-white focus:border-[#d5462f] focus:ring-[#d5462f]"
                                        >
                                            {(upgrade.payment_method_options ?? []).map((option) => (
                                                <option key={option.value} value={option.value}>
                                                    {option.label}
                                                </option>
                                            ))}
                                        </select>
                                        {errors.payment_method && (
                                            <p className="mt-2 text-sm text-[#ffb4a8]">{errors.payment_method}</p>
                                        )}
                                    </div>
                                </div>

                                <div className="flex flex-wrap items-center justify-between gap-4">
                                    <p className="text-sm text-white/50">
                                        {mockOptionEnabled
                                            ? 'PayPal will redirect you out and back. Mock mode is only shown in approved non-production environments.'
                                            : 'PayPal will redirect you out and back to finish your upgrade securely.'}
                                    </p>

                                    <Button
                                        type="submit"
                                        disabled={processing || isSimulating}
                                        className="rounded-full bg-[#d5462f] px-6 text-white hover:bg-[#e2553d]"
                                    >
                                        {isSimulating ? 'Preparing upgrade...' : 'Pay Upgrade Now'}
                                    </Button>
                                </div>
                            </form>
                        </div>

                        <aside className="rounded-[28px] border border-white/10 bg-black/25 p-5 backdrop-blur-md">
                            <div className="space-y-5">
                                <div className="rounded-[24px] border border-white/10 bg-[#161210] p-5">
                                    <p className="text-xs uppercase tracking-[0.22em] text-white/46">
                                        Upgrade path
                                    </p>
                                    <div className="mt-4 space-y-2 text-sm text-white/66">
                                        <p>Current tier: {upgrade.current_tier?.name ?? 'None'}</p>
                                        <p>Target tier: {upgrade.target_tier.name}</p>
                                        <p>Already paid: {formatCurrency(upgrade.total_paid, upgrade.target_tier.currency_code)}</p>
                                    </div>
                                </div>

                                <div className="rounded-[24px] border border-white/10 bg-[#161210] p-5">
                                    <p className="text-xs uppercase tracking-[0.22em] text-white/46">
                                        Amount due now
                                    </p>
                                    <div className="mt-4 text-3xl font-semibold text-white">
                                        {formatCurrency(upgrade.amount_due, upgrade.target_tier.currency_code)}
                                    </div>
                                    <p className="mt-3 text-sm leading-6 text-white/60">
                                        Calculated from the target tier price minus the amount already paid on your latest relevant active tier invoice.
                                    </p>
                                </div>
                            </div>
                        </aside>
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
