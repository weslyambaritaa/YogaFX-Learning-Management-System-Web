import { router } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';

export default function StudentBackButton({
    fallbackHref = null,
    label = 'Back',
    className = '',
}) {
    const handleClick = () => {
        if (typeof window !== 'undefined' && window.history.length > 1) {
            window.history.back();

            return;
        }

        if (fallbackHref) {
            router.visit(fallbackHref);
        }
    };

    return (
        <button
            type="button"
            onClick={handleClick}
            className={[
                'inline-flex items-center gap-2 rounded-full border border-white/12 bg-white/6 px-4 py-2 text-sm font-medium text-white transition hover:bg-white/10',
                className,
            ].join(' ')}
        >
            <ChevronLeft className="size-4" />
            <span>{label}</span>
        </button>
    );
}
