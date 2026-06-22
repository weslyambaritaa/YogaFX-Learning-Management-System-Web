import { Check, Eye, Lock } from 'lucide-react';

const statusMap = {
    completed: {
        label: 'Completed',
        icon: Check,
        wrapperClassName:
            'border-emerald-500 bg-emerald-500 text-white shadow-[0_10px_30px_rgba(16,185,129,0.28)]',
        iconClassName: 'bg-white text-emerald-600',
    },
    available: {
        label: 'Available',
        icon: Eye,
        wrapperClassName:
            'border-white/18 bg-white/10 text-white',
        iconClassName: 'bg-white text-black',
    },
    active: {
        label: 'Available',
        icon: Eye,
        wrapperClassName:
            'border-white/18 bg-white/10 text-white',
        iconClassName: 'bg-white text-black',
    },
    current: {
        label: 'Available',
        icon: Eye,
        wrapperClassName:
            'border-white/18 bg-white/10 text-white',
        iconClassName: 'bg-white text-black',
    },
    in_progress: {
        label: 'Available',
        icon: Eye,
        wrapperClassName:
            'border-white/18 bg-white/10 text-white',
        iconClassName: 'bg-white text-black',
    },
    locked: {
        label: 'Locked',
        icon: Lock,
        wrapperClassName:
            'border-[#DB202C] bg-[#DB202C] text-white shadow-[0_10px_30px_rgba(219,32,44,0.28)]',
        iconClassName: 'bg-white text-[#DB202C]',
    },
};

export default function StudentStatusBadge({
    status = 'available',
    label = null,
    className = '',
}) {
    const config = statusMap[status] ?? statusMap.available;
    const Icon = config.icon;

    return (
        <span
            className={[
                'inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.18em]',
                config.wrapperClassName,
                className,
            ].join(' ')}
        >
            <span
                className={[
                    'flex size-5 items-center justify-center rounded-full',
                    config.iconClassName,
                ].join(' ')}
            >
                <Icon className="size-3.5" />
            </span>
            <span>{label ?? config.label}</span>
        </span>
    );
}
