import TextInput from '@/Components/TextInput';
import { Search } from 'lucide-react';

export default function AdminSearchInput({
    value,
    onChange,
    placeholder = 'Search...',
    resultLabel = null,
    className = '',
}) {
    return (
        <div className={['flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between', className].join(' ')}>
            <div className="relative w-full sm:max-w-md">
                <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                <TextInput
                    type="search"
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    placeholder={placeholder}
                    className="w-full pl-9"
                />
            </div>

            {resultLabel ? (
                <div className="text-sm text-gray-500">
                    {resultLabel}
                </div>
            ) : null}
        </div>
    );
}
