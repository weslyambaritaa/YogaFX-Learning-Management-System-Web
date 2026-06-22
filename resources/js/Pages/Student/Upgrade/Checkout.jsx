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

            <div className="mx-auto flex max-w-[980px] flex-col gap-6 px-4 pt-6 sm:px-6 lg:px-8">
                <section className="rounded-[24px] border border-white/10 bg-[#15110f] p-5 shadow-[0_24px_80px_rgba(0,0,0,0.35)] sm:p-7">
                    <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
                        <div className="space-y-6">
                            <div className="space-y-2">
                                <p className="text-xs uppercase tracking-[0.28em] text-[#f2d9c8]">
                                    Tier Upgrade
                                </p>
                                <h1 className="text-3xl font-semibold tracking-[-0.03em] text-white sm:text-4xl">
                                    Upgrade tier
                                </h1>
                                <p className="max-w-2xl text-sm leading-7 text-white/64">
                                    Your billing history stays intact. We only create a new invoice for the remaining amount, then continue through PayPal.
                                </p>
                            </div>

                            <form onSubmit={submit} className="space-y-5 rounded-[20px] border border-white/10 bg-white/[0.04] p-5">
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label className="text-xs uppercase tracking-[0.18em] text-white/62">
                                            Payment Type
                                        </label>
                                        <select
                                            value={data.payment_type}
                                            onChange={(event) => setData('payment_type', event.target.value)}
                                            className="mt-2 block w-full rounded-md border border-white/12 bg-[#171311] text-sm text-white focus:border-[#DB202C] focus:ring-[#DB202C]"
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
                                            className="mt-2 block w-full rounded-md border border-white/12 bg-[#171311] text-sm text-white focus:border-[#DB202C] focus:ring-[#DB202C]"
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

                                <div className="rounded-[16px] border border-white/10 bg-black/20 px-4 py-4 text-sm leading-6 text-white/58">
                                    {mockOptionEnabled
                                        ? 'PayPal redirects you out and back. Mock mode only appears in approved non-production environments.'
                                        : 'PayPal will redirect you out and back to complete this upgrade securely.'}
                                </div>

                                <div className="flex flex-wrap items-center justify-end gap-4">
                                    <p className="text-xs text-white/38">
                                        {mockOptionEnabled
                                            ? 'Non-production flow'
                                            : 'Secure PayPal redirect'}
                                    </p>

                                    <Button
                                        type="submit"
                                        disabled={processing || isSimulating}
                                        className="rounded-full bg-[#DB202C] px-6 text-white hover:bg-[#DB202C]"
                                    >
                                        {isSimulating ? 'Preparing upgrade...' : 'Pay Upgrade Now'}
                                    </Button>
                                </div>
                            </form>
                        </div>

                        <aside className="rounded-[20px] border border-white/10 bg-black/25 p-5 backdrop-blur-md">
                            <div className="space-y-5">
                                <div className="rounded-[18px] border border-white/10 bg-[#161210] p-5">
                                    <p className="text-xs uppercase tracking-[0.22em] text-white/46">
                                        Upgrade path
                                    </p>
                                    <div className="mt-4 space-y-2 text-sm text-white/66">
                                        <p>Current tier: {upgrade.current_tier?.name ?? 'None'}</p>
                                        <p>Target tier: {upgrade.target_tier.name}</p>
                                        <p>Already paid: {formatCurrency(upgrade.total_paid, upgrade.target_tier.currency_code)}</p>
                                    </div>
                                </div>

                                <div className="rounded-[18px] border border-white/10 bg-[#161210] p-5">
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
