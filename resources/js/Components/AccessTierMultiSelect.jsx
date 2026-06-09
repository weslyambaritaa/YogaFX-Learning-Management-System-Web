import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { cn } from '@/lib/utils';

export default function AccessTierMultiSelect({
    id = 'access_tier_ids',
    label = 'Access Tiers',
    description = 'Choose one or more tiers that can access this content.',
    value = [],
    onChange,
    accessTiers = [],
    error,
}) {
    const normalizedValue = Array.isArray(value)
        ? value.map((item) => Number(item))
        : [];

    const toggleTier = (tierId) => {
        const normalizedTierId = Number(tierId);
        const nextValue = normalizedValue.includes(normalizedTierId)
            ? normalizedValue.filter((item) => item !== normalizedTierId)
            : [...normalizedValue, normalizedTierId];

        onChange(nextValue);
    };

    return (
        <div className="space-y-3">
            <div className="space-y-1">
                <InputLabel htmlFor={id} value={label} />
                <p className="text-sm text-gray-500">{description}</p>
            </div>

            <div
                id={id}
                className="grid gap-3 md:grid-cols-2"
                role="group"
                aria-label={label}
            >
                {accessTiers.map((accessTier) => {
                    const isSelected = normalizedValue.includes(accessTier.id);

                    return (
                        <Card
                            key={accessTier.id}
                            className={cn(
                                'border transition-colors',
                                isSelected
                                    ? 'border-indigo-500 ring-2 ring-indigo-200'
                                    : 'border-gray-200',
                                !accessTier.is_active && 'opacity-75',
                            )}
                        >
                            <CardContent className="p-0">
                                <Button
                                    type="button"
                                    variant={isSelected ? 'default' : 'outline'}
                                    className="h-auto w-full justify-start rounded-xl px-4 py-4 text-left"
                                    onClick={() => toggleTier(accessTier.id)}
                                    aria-pressed={isSelected}
                                >
                                    <div className="space-y-1">
                                        <div className="font-medium">
                                            {accessTier.name}
                                        </div>
                                        <div className="text-xs opacity-80">
                                            {accessTier.is_active
                                                ? 'Active tier'
                                                : 'Inactive tier'}
                                        </div>
                                    </div>
                                </Button>
                            </CardContent>
                        </Card>
                    );
                })}
            </div>

            <InputError className="mt-2" message={error} />
        </div>
    );
}
