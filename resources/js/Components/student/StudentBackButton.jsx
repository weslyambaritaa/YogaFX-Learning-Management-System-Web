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
                'inline-flex w-fit shrink-0 items-center gap-2 self-start rounded-full border border-white/12 bg-white/[0.04] px-4 py-2 text-sm font-medium text-white shadow-[0_10px_30px_rgba(0,0,0,0.18)] transition-colors hover:border-white/20 hover:bg-white/[0.08] focus:outline-none focus:ring-2 focus:ring-white/20',
                className,
            ].join(' ')}
        >
            <ChevronLeft className="size-4" />
            <span>{label}</span>
        </button>
    );
}
