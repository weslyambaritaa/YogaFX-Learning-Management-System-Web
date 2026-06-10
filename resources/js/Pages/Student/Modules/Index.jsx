import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    CheckCircle2,
    Lock,
    PlayCircle,
} from 'lucide-react';

const statusConfig = {
    completed: {
        icon: CheckCircle2,
        label: 'Completed',
        className: 'text-[#3DDC84]',
    },
    active: {
        icon: PlayCircle,
        label: 'Continue Learning',
        className: 'text-[#f15b3a]',
    },
    available: {
        icon: PlayCircle,
        label: 'Available Now',
        className: 'text-white/75',
    },
    locked: {
        icon: Lock,
        label: 'Locked',
        className: 'text-white/45',
    },
};

export default function StudentModulesIndex({ modules }) {
    return (
        <AuthenticatedLayout
            studentVariant="immersive"
            studentContentClassName="pb-16"
        >
            <Head title="Modules" />

            <div className="mx-auto flex max-w-[1400px] flex-col gap-8 px-4 pt-8 sm:px-6 lg:px-10">
                <section className="relative overflow-hidden rounded-[32px] border border-white/10 bg-[#110f0f] px-6 py-8 shadow-[0_24px_90px_rgba(0,0,0,0.35)] sm:px-8 lg:px-10">
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_15%_20%,_rgba(196,91,49,0.32),_transparent_28%),linear-gradient(180deg,_rgba(255,255,255,0.05)_0%,_rgba(255,255,255,0)_36%),linear-gradient(180deg,_rgba(12,10,10,0.22)_0%,_rgba(12,10,10,0.82)_100%)]" />
                    <div className="relative flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                        <div className="max-w-3xl space-y-4">
                            <p className="text-xs uppercase tracking-[0.28em] text-[#f2d9c8]">
                                YogaFX Learning Catalog
                            </p>
                            <h1 className="text-4xl font-semibold tracking-[-0.03em] text-white sm:text-5xl">
                                Explore your modules like a premium course library.
                            </h1>
                            <p className="max-w-2xl text-sm leading-7 text-white/68 sm:text-base">
                                Every module is presented as part of your learning journey,
                                with progress, availability, and the next content you can
                                open at a glance.
                            </p>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-3">
                            <div className="rounded-2xl border border-white/10 bg-white/5 px-5 py-4 backdrop-blur">
                                <div className="text-xs uppercase tracking-[0.2em] text-white/45">
                                    Modules
                                </div>
                                <div className="mt-2 text-3xl font-semibold text-white">
                                    {modules.length}
                                </div>
                            </div>
                            <div className="rounded-2xl border border-white/10 bg-white/5 px-5 py-4 backdrop-blur">
                                <div className="text-xs uppercase tracking-[0.2em] text-white/45">
                                    Completed
                                </div>
                                <div className="mt-2 text-3xl font-semibold text-white">
                                    {modules.filter((module) => module.status === 'completed').length}
                                </div>
                            </div>
                            <div className="rounded-2xl border border-white/10 bg-white/5 px-5 py-4 backdrop-blur">
                                <div className="text-xs uppercase tracking-[0.2em] text-white/45">
                                    Active
                                </div>
                                <div className="mt-2 text-3xl font-semibold text-white">
                                    {modules.filter((module) => module.status === 'active').length}
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section className="space-y-5">
                    <div className="flex items-center justify-between gap-4">
                        <div>
                            <p className="text-xs uppercase tracking-[0.24em] text-white/40">
                                Module Grid
                            </p>
                            <h2 className="mt-2 text-2xl font-semibold tracking-tight text-white">
                                Browse your available modules
                            </h2>
                        </div>
                        <span className="hidden text-sm text-white/40 md:inline">
                            Premium course browsing
                        </span>
                    </div>

                    <div className="grid gap-5 xl:grid-cols-2">
                        {modules.map((module) => {
                            const status = statusConfig[module.status] ?? statusConfig.available;
                            const StatusIcon = status.icon;

                            return (
                                <Link
                                    key={module.id}
                                    href={route('modules.show', module.url_slug)}
                                    className="group relative overflow-hidden rounded-[30px] border border-white/10 bg-white/[0.04] p-4 transition duration-300 hover:-translate-y-1 hover:border-white/20 hover:bg-white/[0.06]"
                                >
                                    <div className="grid gap-4 md:grid-cols-[42%_1fr]">
                                        <div className="relative overflow-hidden rounded-[24px] bg-[#1a1513]">
                                            {module.thumbnail_url ? (
                                                <img
                                                    src={module.thumbnail_url}
                                                    alt={module.title}
                                                    className="aspect-[4/3] h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]"
                                                />
                                            ) : (
                                                <div className="aspect-[4/3] bg-[radial-gradient(circle_at_24%_20%,_rgba(223,103,57,0.45),_transparent_28%),linear-gradient(160deg,_#2b1d16_0%,_#120f0e_100%)]" />
                                            )}
                                            <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-black/15 to-transparent" />
                                        </div>

                                        <div className="flex flex-col justify-between gap-6 p-2">
                                            <div className="space-y-4">
                                                <div className="flex flex-wrap items-center gap-3">
                                                    <span className="rounded-full border border-white/12 bg-white/5 px-3 py-1 text-[11px] uppercase tracking-[0.22em] text-white/60">
                                                        Module {module.sort_order}
                                                    </span>
                                                    <span className={`inline-flex items-center gap-2 text-sm font-medium ${status.className}`}>
                                                        <StatusIcon className="size-4" />
                                                        {status.label}
                                                    </span>
                                                </div>

                                                <div>
                                                    <h3 className="text-2xl font-semibold tracking-tight text-white">
                                                        {module.title}
                                                    </h3>
                                                    <p className="mt-3 text-sm leading-7 text-white/62">
                                                        {module.completed_lessons} of {module.lesson_count}{' '}
                                                        lessons completed in this module.
                                                    </p>
                                                </div>
                                            </div>

                                            <div className="space-y-4">
                                                <div className="space-y-2">
                                                    <div className="flex items-center justify-between text-xs uppercase tracking-[0.2em] text-white/40">
                                                        <span>Progress</span>
                                                        <span>{module.progress_percentage}%</span>
                                                    </div>
                                                    <div className="h-1.5 overflow-hidden rounded-full bg-white/10">
                                                        <div
                                                            className="h-full rounded-full bg-[#f15b3a]"
                                                            style={{ width: `${module.progress_percentage}%` }}
                                                        />
                                                    </div>
                                                </div>

                                                <div className="inline-flex items-center gap-2 text-sm font-medium text-white">
                                                    Open Module
                                                    <ArrowRight className="size-4 transition group-hover:translate-x-1" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </Link>
                            );
                        })}
                    </div>

                    {modules.length === 0 && (
                        <div className="rounded-[28px] border border-white/10 bg-white/[0.04] px-6 py-10 text-center text-white/62">
                            No modules are available for your current access tier yet.
                        </div>
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
