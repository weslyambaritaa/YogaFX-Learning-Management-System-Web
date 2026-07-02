import LockedContentDialog from '@/Components/student/LockedContentDialog';
import StudentBackButton from '@/Components/student/StudentBackButton';
import StudentStatusBadge from '@/Components/student/StudentStatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

function ModuleCard({ module, onLockedClick }) {
    const content = (
        <div className="group relative overflow-hidden rounded-[5px] border border-white/10 bg-white/[0.04] transition duration-300 hover:-translate-y-1 hover:border-white/20 hover:bg-white/[0.06]">
            <div className="relative aspect-video overflow-hidden bg-[#1a1513]">
                {module.thumbnail_url ? (
                    <img
                        src={module.thumbnail_url}
                        alt={module.title}
                        className="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]"
                    />
                ) : (
                    <div className="h-full w-full bg-[radial-gradient(circle_at_24%_20%,_rgba(223,103,57,0.45),_transparent_28%),linear-gradient(160deg,_#2b1d16_0%,_#120f0e_100%)]" />
                )}
                <div className="absolute inset-0 bg-gradient-to-t from-black/75 via-black/15 to-transparent" />
                <div className="absolute right-4 top-4">
                    <StudentStatusBadge status={module.status} />
                </div>
            </div>

            <div className="space-y-3 p-4 sm:space-y-4 sm:p-5">
                <div className="space-y-1.5 sm:space-y-2">
                    <p className="text-xl font-semibold tracking-tight text-white">
                        Module {module.sort_order}
                    </p>
                    <h3 className="text-[22px] font-medium tracking-tight text-white">
                        {module.title}
                    </h3>
                </div>

                <div className="flex items-center justify-between gap-4 text-sm text-white/62">
                    <span>
                        {module.lesson_count} lesson{module.lesson_count === 1 ? '' : 's'}
                    </span>
                    <span>{module.progress_percentage}%</span>
                </div>

                <div className="h-2 overflow-hidden rounded-full bg-white/10">
                    <div
                        className={[
                            'h-full rounded-full',
                            module.status === 'completed' ? 'bg-emerald-500' : module.status === 'locked' ? 'bg-[#DB202C]' : 'bg-white',
                        ].join(' ')}
                        style={{ width: `${module.progress_percentage}%` }}
                    />
                </div>

                <div className="inline-flex items-center gap-2 text-[14px] font-medium text-white">
                    {module.status === 'locked' ? 'Complete previous module' : 'Open Module'}
                    <ArrowRight className="size-4 transition group-hover:translate-x-1" />
                </div>
            </div>
        </div>
    );

    if (module.status === 'locked' || !module.url) {
        return (
            <button type="button" onClick={onLockedClick} className="text-left">
                {content}
            </button>
        );
    }

    return (
        <Link href={module.url}>
            {content}
        </Link>
    );
}

export default function StudentModulesIndex({ modules }) {
    const hasReloadedRef = useRef(false);
    const [lockedDialogOpen, setLockedDialogOpen] = useState(false);

    useEffect(() => {
        if (hasReloadedRef.current) {
            return;
        }

        hasReloadedRef.current = true;

        router.reload({
            only: ['modules'],
            preserveScroll: true,
            preserveState: true,
        });
    }, []);

    return (
        <AuthenticatedLayout studentVariant="immersive" studentContentClassName="pb-16">
            <Head title="Modules" />

            <LockedContentDialog
                open={lockedDialogOpen}
                onOpenChange={setLockedDialogOpen}
                kind="module"
            />

            <div className="mx-auto flex max-w-[1400px] flex-col gap-6 px-4 pt-8 sm:gap-8 sm:px-6 lg:px-10">
                <StudentBackButton fallbackHref={route('student.dashboard')} />

                <section className="space-y-4 sm:space-y-5">
                    <div className="grid gap-4 sm:gap-5 xl:grid-cols-2">
                        {modules.map((module) => (
                            <ModuleCard
                                key={module.id}
                                module={module}
                                onLockedClick={() => setLockedDialogOpen(true)}
                            />
                        ))}
                    </div>

                    {modules.length === 0 && (
                        <div className="rounded-[5px] border border-white/10 bg-white/[0.04] px-6 py-10 text-center text-white/62">
                            No modules are available for your current access tier yet.
                        </div>
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
