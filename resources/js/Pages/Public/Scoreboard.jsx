import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { Button } from '@/Components/ui/button';
import PublicFlowLayout from '@/Layouts/PublicFlowLayout';
import { useForm, usePage } from '@inertiajs/react';

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(Number(amount || 0));
}

export default function Scoreboard({ accessTiers }) {
    const { directory = {} } = usePage().props;
    const countryOptions = directory.countries ?? [];
    const phoneCountryCodeOptions = directory.phone_country_codes ?? [];
    const { data, setData, post, processing, errors } = useForm({
        first_name: '',
        last_name: '',
        email: '',
        phone_country_code: '+62',
        phone_number: '',
        country: '',
        access_tier_id: accessTiers[0]?.id ?? '',
    });

    const selectedTier = accessTiers.find((tier) => String(tier.id) === String(data.access_tier_id)) ?? null;
    const selectedTierHasPrice = Number(selectedTier?.price_amount ?? 0) > 0;

    const submit = (event) => {
        event.preventDefault();
        post(route('lead-registration.store'));
    };

    return (
        <PublicFlowLayout
            title="Scoreboard"
            eyebrow="Scoreboard Entry"
            heading="Start your YogaFX journey with a calm, guided first step."
            description="This scoreboard captures your starting identity and the program you want to join, then hands you into the simulated checkout flow without turning the experience into a school-style registration portal."
            aside={
                <div className="space-y-6">
                    <div>
                        <p className="text-xs uppercase tracking-[0.22em] text-white/46">
                            What happens next
                        </p>
                        <div className="mt-4 space-y-3">
                            {[
                                'We save your pending registration.',
                                'We generate a signed checkout link for the selected tier.',
                                'You continue into simulated payment, then onboarding, then final sign up.',
                            ].map((item) => (
                                <div
                                    key={item}
                                    className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm leading-6 text-white/66"
                                >
                                    {item}
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="rounded-[24px] border border-white/10 bg-[#161210] p-5">
                        <p className="text-xs uppercase tracking-[0.22em] text-white/46">
                            Selected program
                        </p>
                        {selectedTier ? (
                            <div className="mt-4 space-y-3">
                                <div className="text-2xl font-semibold text-white">
                                    {selectedTier.name}
                                </div>
                                <p className="text-sm leading-6 text-white/60">
                                    {selectedTier.description}
                                </p>
                                <div className="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm text-white/72">
                                    {formatCurrency(selectedTier.price_amount)}
                                </div>
                                {!selectedTierHasPrice && (
                                    <div className="rounded-2xl border border-amber-300/15 bg-[linear-gradient(160deg,rgba(217,119,6,0.16),rgba(255,255,255,0.03))] px-4 py-3 text-sm leading-6 text-amber-50/90">
                                        This tier is visible now, but its program price is still empty. Set the price in admin before continuing to checkout.
                                    </div>
                                )}
                            </div>
                        ) : (
                            <p className="mt-4 text-sm leading-6 text-white/60">
                                No paid access tier is configured yet. Set a tier price in admin first before opening this flow.
                            </p>
                        )}
                    </div>
                </div>
            }
        >
            <form onSubmit={submit} className="space-y-6">
                <div className="grid gap-5 md:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="first_name" value="First Name" className="text-white/72" />
                        <TextInput
                            id="first_name"
                            value={data.first_name}
                            className="mt-2 block w-full border-white/12 bg-white/5 text-white placeholder:text-white/30"
                            onChange={(event) => setData('first_name', event.target.value)}
                            required
                        />
                        <InputError className="mt-2 text-[#ffb4a8]" message={errors.first_name} />
                    </div>

                    <div>
                        <InputLabel htmlFor="last_name" value="Last Name" className="text-white/72" />
                        <TextInput
                            id="last_name"
                            value={data.last_name}
                            className="mt-2 block w-full border-white/12 bg-white/5 text-white placeholder:text-white/30"
                            onChange={(event) => setData('last_name', event.target.value)}
                            required
                        />
                        <InputError className="mt-2 text-[#ffb4a8]" message={errors.last_name} />
                    </div>

                    <div>
                        <InputLabel htmlFor="email" value="Email" className="text-white/72" />
                        <TextInput
                            id="email"
                            type="email"
                            value={data.email}
                            className="mt-2 block w-full border-white/12 bg-white/5 text-white placeholder:text-white/30"
                            onChange={(event) => setData('email', event.target.value)}
                            required
                        />
                        <InputError className="mt-2 text-[#ffb4a8]" message={errors.email} />
                    </div>

                    <div>
                        <InputLabel htmlFor="phone_number" value="Mobile Phone" className="text-white/72" />
                        <div className="mt-2 grid gap-3 sm:grid-cols-[180px_minmax(0,1fr)]">
                            <select
                                id="phone_country_code"
                                value={data.phone_country_code}
                                onChange={(event) => setData('phone_country_code', event.target.value)}
                                className="block w-full rounded-md border border-white/12 bg-[#171311] text-white focus:border-[#d5462f] focus:ring-[#d5462f]"
                                required
                            >
                                {phoneCountryCodeOptions.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                            <TextInput
                                id="phone_number"
                                value={data.phone_number}
                                className="block w-full border-white/12 bg-white/5 text-white placeholder:text-white/30"
                                onChange={(event) => setData('phone_number', event.target.value)}
                                placeholder="81234567890"
                                required
                            />
                        </div>
                        <InputError className="mt-2 text-[#ffb4a8]" message={errors.phone_number ?? errors.phone_country_code ?? errors.phone} />
                    </div>

                    <div>
                        <InputLabel htmlFor="country" value="Country" className="text-white/72" />
                        <select
                            id="country"
                            value={data.country}
                            className="mt-2 block w-full rounded-md border border-white/12 bg-[#171311] text-white focus:border-[#d5462f] focus:ring-[#d5462f]"
                            onChange={(event) => {
                                const value = event.target.value;
                                setData('country', value);

                                const matchedDialCode = phoneCountryCodeOptions.find((option) =>
                                    option.label.startsWith(`${value} (`),
                                );

                                if (matchedDialCode && !data.phone_number) {
                                    setData('phone_country_code', matchedDialCode.value);
                                }
                            }}
                            required
                        >
                            <option value="">Select a country</option>
                            {countryOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <InputError className="mt-2 text-[#ffb4a8]" message={errors.country} />
                    </div>

                    <div>
                        <InputLabel htmlFor="access_tier_id" value="Program / Tier" className="text-white/72" />
                        <select
                            id="access_tier_id"
                            value={data.access_tier_id}
                            onChange={(event) => setData('access_tier_id', event.target.value)}
                            className="mt-2 block w-full rounded-md border border-white/12 bg-[#171311] text-white focus:border-[#d5462f] focus:ring-[#d5462f]"
                            required
                        >
                            {accessTiers.map((tier) => (
                                <option key={tier.id} value={tier.id}>
                                    {tier.name} - {Number(tier.price_amount) > 0
                                        ? formatCurrency(tier.price_amount)
                                        : 'Price not set yet'}
                                </option>
                            ))}
                        </select>
                        <InputError className="mt-2 text-[#ffb4a8]" message={errors.access_tier_id} />
                    </div>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-4 rounded-[24px] border border-white/10 bg-black/20 px-5 py-4">
                    <p className="max-w-2xl text-sm leading-6 text-white/56">
                        This step only creates your pending registration. Your LMS account does not exist yet until simulated payment succeeds.
                    </p>

                    <Button
                        type="submit"
                        disabled={processing || accessTiers.length === 0 || !selectedTierHasPrice}
                        className="rounded-full bg-[#d5462f] px-6 text-white hover:bg-[#e2553d]"
                    >
                        {selectedTierHasPrice
                            ? 'Continue to Checkout'
                            : 'Set Tier Price First'}
                    </Button>
                </div>
            </form>
        </PublicFlowLayout>
    );
}
