import LockedContentDialog from '@/Components/student/LockedContentDialog';
import StudentStatusBadge from '@/Components/student/StudentStatusBadge';
import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { Check, ChevronRight, Download, Play, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

const ONBOARDING_KEY = 'yogafx_onboarding_done';

const SLIDES = [
    {
        title: 'Welcome to YogaFX',
        body: 'A premium learning platform built for focus with a cleaner module flow across desktop and mobile.',
    },
    {
        title: 'Keep moving forward',
        body: 'Continue from your latest lesson, track what is completed, and see what is still locked before opening it.',
    },
    {
        title: 'Everything stays guided',
        body: 'Your next step, module access, and supporting resources stay visible without turning the experience into a school portal.',
    },
];

function formatDurationParts(totalSeconds) {
    const safeSeconds = Math.max(0, Number(totalSeconds || 0));
    return {
        hours: String(Math.floor(safeSeconds / 3600)).padStart(2, '0'),
        minutes: String(Math.floor((safeSeconds % 3600) / 60)).padStart(2, '0'),
        seconds: String(Math.floor(safeSeconds % 60)).padStart(2, '0'),
    };
}

function OnboardingOverlay({ onDone }) {
    const [slide, setSlide] = useState(0);
    const current = SLIDES[slide];
    const isLast = slide === SLIDES.length - 1;

    const finish = () => {
        localStorage.setItem(ONBOARDING_KEY, '1');
        onDone();
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 px-4 backdrop-blur-sm">
            <div className="relative w-full max-w-md rounded-[18px] border border-white/10 bg-[#141110] p-8 text-white">
                <button type="button" onClick={finish} className="absolute right-5 top-5 text-white/45 transition hover:text-white">
                    <X className="size-4" />
                </button>
                <div className="mb-8 flex gap-2">
                    {SLIDES.map((_, index) => (
                        <div
                            key={index}
                            className={[
                                'h-1 rounded-full transition-all',
                                index === slide ? 'w-10 bg-[#DB202C]' : 'w-4 bg-white/15',
                            ].join(' ')}
                        />
                    ))}
                </div>
                <div className="space-y-3 text-center">
                    <h2 className="text-2xl font-semibold">{current.title}</h2>
                    <p className="text-sm leading-7 text-white/60">{current.body}</p>
                </div>
                <div className="mt-8 flex items-center justify-between">
                    <button type="button" onClick={finish} className="text-sm text-white/40 transition hover:text-white/70">
                        Skip
                    </button>
                    <Button type="button" onClick={() => (isLast ? finish() : setSlide((value) => value + 1))} className="rounded-[12px] bg-[#DB202C] text-white hover:bg-[#c31c28]">
                        {isLast ? 'Get Started' : 'Next'}
                    </Button>
                </div>
            </div>
        </div>
    );
}

function AccessTimeCard({ accessTimeSummary }) {
    const [liveSeconds, setLiveSeconds] = useState(accessTimeSummary?.running_total_access_duration_seconds ?? 0);

    useEffect(() => {
        if (!accessTimeSummary?.currently_active || !accessTimeSummary?.active_session_login_at) {
            setLiveSeconds(accessTimeSummary?.running_total_access_duration_seconds ?? 0);
            return undefined;
        }

        const loginAt = new Date(accessTimeSummary.active_session_login_at).getTime();
        const tick = () => {
            const elapsed = Math.max(0, Math.floor((Date.now() - loginAt) / 1000));
            setLiveSeconds((accessTimeSummary.running_total_access_duration_seconds ?? 0) + elapsed);
        };

        tick();
        const interval = window.setInterval(tick, 1000);
        return () => window.clearInterval(interval);
    }, [
        accessTimeSummary?.active_session_login_at,
        accessTimeSummary?.currently_active,
        accessTimeSummary?.running_total_access_duration_seconds,
    ]);

    const parts = formatDurationParts(liveSeconds);

    return (
        <div className="inline-flex items-center gap-5 rounded-[16px] border border-white/10 bg-black/45 px-6 py-4 text-white backdrop-blur">
            <div>
                <div className="text-xs uppercase tracking-[0.2em] text-white/45">Running Total</div>
                <div className="text-xs uppercase tracking-[0.2em] text-white/45">Login Time</div>
            </div>
            <div className="text-3xl font-semibold tracking-[0.08em]">
                {parts.hours}:{parts.minutes}:{parts.seconds}
            </div>
        </div>
    );
}

function LessonRow({ lesson, onLockedClick }) {
    const row = (
        <div className="flex items-center justify-between gap-4 rounded-[14px] border border-white/10 bg-white/[0.04] px-4 py-3 transition hover:bg-white/[0.06]">
            <div className="min-w-0">
                <div className="text-sm font-medium text-white">{lesson.title}</div>
                <div className="text-xs uppercase tracking-[0.18em] text-white/45">Lesson {lesson.sort_order}</div>
            </div>
            <div className="flex items-center gap-3">
                <StudentStatusBadge status={lesson.status === 'in_progress' ? 'available' : lesson.status} label={lesson.status === 'in_progress' ? 'Available' : null} />
                <div className="text-xs text-white/55">{lesson.progress_percentage}%</div>
            </div>
        </div>
    );

    if (!lesson.url || lesson.status === 'locked') {
        return (
            <button type="button" onClick={onLockedClick} className="w-full text-left">
                {row}
            </button>
        );
    }

    return <Link href={lesson.url}>{row}</Link>;
}

function ModuleModal({ module, onClose, onLockedLessonClick }) {
    useEffect(() => {
        document.body.style.overflow = 'hidden';
        return () => {
            document.body.style.overflow = '';
        };
    }, []);

    return (
        <div className="fixed inset-0 z-40 flex items-end justify-center bg-black/75 px-0 backdrop-blur-sm sm:items-center sm:px-4" onClick={onClose}>
            <div className="relative w-full max-w-3xl rounded-t-[18px] border border-white/10 bg-[#141110] sm:rounded-[18px]" onClick={(event) => event.stopPropagation()}>
                <button type="button" onClick={onClose} className="absolute right-4 top-4 z-10 rounded-full border border-white/15 bg-black/45 p-2 text-white/70 transition hover:text-white">
                    <X className="size-4" />
                </button>

                <div className="relative aspect-video overflow-hidden rounded-t-[18px] sm:rounded-t-[18px]">
                    {module.thumbnail_url ? (
                        <img src={module.thumbnail_url} alt={module.title} className="h-full w-full object-cover" />
                    ) : (
                        <div className="h-full w-full bg-[radial-gradient(circle_at_24%_20%,rgba(223,103,57,0.45),transparent_28%),linear-gradient(160deg,#2b1d16_0%,#120f0e_100%)]" />
                    )}
                    <div className="absolute inset-0 bg-gradient-to-t from-black/85 via-black/20 to-transparent" />
                    <div className="absolute bottom-5 left-5 space-y-3">
                        <StudentStatusBadge status={module.status === 'in_progress' ? 'available' : module.status} label={module.status_label} />
                        <div className="text-3xl font-semibold text-white">{module.title}</div>
                        <div className="text-3xl font-semibold text-white">Module {module.sort_order}</div>
                    </div>
                </div>

                <div className="space-y-6 p-6">
                    {module.description ? (
                        <p className="text-sm leading-7 text-white/65">{module.description}</p>
                    ) : null}

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="rounded-[14px] border border-white/10 bg-white/[0.04] px-5 py-4 text-white">
                            <div className="text-xs uppercase tracking-[0.18em] text-white/45">Lessons</div>
                            <div className="mt-2 text-3xl font-semibold">{module.lesson_count}</div>
                        </div>
                        <div className="rounded-[14px] border border-white/10 bg-white/[0.04] px-5 py-4 text-white">
                            <div className="text-xs uppercase tracking-[0.18em] text-white/45">Progress</div>
                            <div className="mt-2 text-3xl font-semibold">{module.progress_percentage}%</div>
                        </div>
                    </div>

                    {module.continue_url ? (
                        <Button asChild className="w-full rounded-[14px] bg-[#DB202C] text-white hover:bg-[#c31c28]">
                            <Link href={module.continue_url}>{module.cta_label ?? 'Open Module'}</Link>
                        </Button>
                    ) : null}

                    <div className="space-y-3">
                        <div className="text-xs uppercase tracking-[0.2em] text-white/45">Lessons in this module</div>
                        <div className="space-y-2">
                            {(module.lessons ?? []).map((lesson) => (
                                <LessonRow key={lesson.id} lesson={lesson} onLockedClick={onLockedLessonClick} />
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

function ModuleCard({ module, onLockedClick }) {
    const card = (
        <div className="group h-full overflow-hidden rounded-[14px] border border-white/10 bg-white/[0.04] text-left transition hover:-translate-y-1 hover:border-white/20 hover:bg-white/[0.06]">
            <div className="relative aspect-video overflow-hidden">
                {module.thumbnail_url ? (
                    <img src={module.thumbnail_url} alt={module.title} className="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]" />
                ) : (
                    <div className="h-full w-full bg-[radial-gradient(circle_at_24%_20%,rgba(223,103,57,0.45),transparent_28%),linear-gradient(160deg,#2b1d16_0%,#120f0e_100%)]" />
                )}
                <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/15 to-transparent" />
                <div className="absolute left-4 top-4">
                    <StudentStatusBadge status={module.status === 'in_progress' ? 'available' : module.status} label={module.status_label} />
                </div>
            </div>

            <div className="space-y-2 p-3.5">
                <div className="space-y-1">
                    <div className="line-clamp-2 text-sm font-semibold leading-5 text-white sm:text-base">{module.title}</div>
                    <div className="text-sm font-semibold text-white/82 sm:text-base">Module {module.sort_order}</div>
                </div>

                <div className="flex items-center justify-between text-[11px] text-white/62 sm:text-xs">
                    <span>{module.lesson_count} lessons</span>
                    <span>{module.progress_percentage}%</span>
                </div>

                <div className="h-1.5 overflow-hidden rounded-full bg-white/10">
                    <div
                        className={[
                            'h-full rounded-full',
                            module.status === 'completed' ? 'bg-emerald-500' : module.status === 'locked' ? 'bg-[#DB202C]' : 'bg-white',
                        ].join(' ')}
                        style={{ width: `${module.progress_percentage}%` }}
                    />
                </div>

                <div className="inline-flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-[0.16em] text-white/80">
                    {module.status === 'locked' ? 'Complete Previous Module' : null}
                    <ChevronRight className="size-3.5 transition group-hover:translate-x-1" />
                </div>
            </div>
        </div>
    );

    if (module.status === 'locked' || !module.cta_url) {
        return (
            <button type="button" onClick={onLockedClick} className="w-full text-left">
                {card}
            </button>
        );
    }

    return (
        <Link href={module.cta_url} className="block w-full text-left">
            {card}
        </Link>
    );
}

export default function StudentHome({
    studentContext,
    accessTimeSummary,
    continueLearning,
    availableModulesSection,
    assignmentMilestone,
    certificateMilestone,
    homeExperience,
}) {
    const [showOnboarding, setShowOnboarding] = useState(false);
    const [selectedModule, setSelectedModule] = useState(null);
    const [lockedModuleOpen, setLockedModuleOpen] = useState(false);
    const [lockedLessonOpen, setLockedLessonOpen] = useState(false);
    const bootedRef = useRef(false);
    const rawModules = availableModulesSection?.items ?? [];
    const inProgressModules = useMemo(
        () => rawModules.filter((module) => module.status === 'in_progress'),
        [rawModules],
    );
    const studentName = studentContext?.display_name ?? 'Student';

    useEffect(() => {
        if (!bootedRef.current && !localStorage.getItem(ONBOARDING_KEY)) {
            setShowOnboarding(true);
        }
        bootedRef.current = true;
    }, []);

    return (
        <AuthenticatedLayout studentVariant="immersive" studentContentClassName="pb-16">
            <Head title="Home" />

            {showOnboarding ? <OnboardingOverlay onDone={() => setShowOnboarding(false)} /> : null}
            <LockedContentDialog open={lockedModuleOpen} onOpenChange={setLockedModuleOpen} kind="module" />
            <LockedContentDialog open={lockedLessonOpen} onOpenChange={setLockedLessonOpen} kind="lesson" />
            {selectedModule ? (
                <ModuleModal
                    module={selectedModule}
                    onClose={() => setSelectedModule(null)}
                    onLockedLessonClick={() => setLockedLessonOpen(true)}
                />
            ) : null}

            <section className="relative overflow-hidden">
                <div className="absolute inset-0">
                    {continueLearning?.thumbnail_url ? (
                        <img src={continueLearning.thumbnail_url} alt="" className="h-full w-full object-cover" />
                    ) : (
                        <div className="h-full w-full bg-[radial-gradient(circle_at_18%_28%,rgba(173,76,38,0.55),transparent_36%),linear-gradient(160deg,#1e1210,#0a0908)]" />
                    )}
                    <div className="absolute inset-0 bg-[linear-gradient(to_right,rgba(0,0,0,0.88)_0%,rgba(0,0,0,0.42)_52%,rgba(0,0,0,0.08)_100%),linear-gradient(to_top,rgba(0,0,0,0.96)_0%,rgba(0,0,0,0.38)_32%,transparent_62%)]" />
                </div>

                <div className="relative mx-auto flex min-h-screen max-w-[1400px] flex-col justify-end gap-8 px-4 pb-20 pt-24 sm:px-6 lg:px-10 lg:pb-28">
                    <div className="max-w-2xl space-y-5 text-white">
                        <div className="text-xs uppercase tracking-[0.28em] text-[#f2d9c8]">
                            {homeExperience?.state === 'new_student' ? `Hello, ${studentName}` : `Welcome back, ${studentName}`}
                        </div>
                        <h1 className="text-4xl font-bold tracking-[-0.03em] sm:text-5xl xl:text-6xl">
                            {continueLearning?.title ?? homeExperience?.hero_title ?? 'Start your learning journey'}
                        </h1>
                        <p className="text-sm leading-7 text-white/68 sm:text-base">
                            {continueLearning?.description ?? homeExperience?.hero_description}
                        </p>
                        <div className="flex flex-wrap gap-3">
                            <Button asChild className="rounded-[14px] bg-[#DB202C] px-7 text-white hover:bg-[#c31c28]">
                                <Link href={continueLearning?.cta_url ?? route('modules.index')}>
                                    <Play className="mr-2 size-4 fill-white" />
                                    {continueLearning?.cta_label ?? homeExperience?.primary_cta_label ?? 'Continue Learning'}
                                </Link>
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    const activeModuleSlug = continueLearning?.module?.url_slug ?? null;
                                    const targetModule = activeModuleSlug
                                        ? rawModules.find((module) => module.url_slug === activeModuleSlug)
                                        : rawModules[0] ?? null;
                                    if (targetModule?.status === 'locked') {
                                        setLockedModuleOpen(true);
                                        return;
                                    }
                                    setSelectedModule(targetModule ?? null);
                                }}
                                className="rounded-[14px] border-white/25 bg-white/10 text-white hover:bg-white/15 hover:text-white"
                            >
                                More Info
                            </Button>
                        </div>
                    </div>

                    {accessTimeSummary ? (
                        <div className="flex justify-start lg:justify-end">
                            <AccessTimeCard accessTimeSummary={accessTimeSummary} />
                        </div>
                    ) : null}
                </div>
            </section>

            <div className="mx-auto flex max-w-[1400px] flex-col gap-10 px-4 pt-10 sm:px-6 lg:px-10">
                {inProgressModules.length ? (
                    <section className="space-y-4">
                        <div>
                            <p className="text-[11px] uppercase tracking-[0.22em] text-white/40">On Progress</p>
                            <h2 className="mt-1 text-lg font-semibold text-white sm:text-xl">Continue where you left off</h2>
                        </div>
                        <div className="-mx-4 overflow-x-auto px-4 pb-2 no-scrollbar sm:-mx-6 sm:px-6 lg:-mx-10 lg:px-10">
                            <div className="flex min-w-max gap-4">
                            {inProgressModules.map((module) => (
                                <div key={module.id} className="w-[240px] shrink-0 sm:w-[260px]">
                                    <ModuleCard
                                        module={module}
                                        onLockedClick={() => setLockedModuleOpen(true)}
                                    />
                                </div>
                            ))}
                            </div>
                        </div>
                    </section>
                ) : null}

                <section className="space-y-4">
                    <div>
                        <p className="text-[11px] uppercase tracking-[0.22em] text-white/40">All Modules</p>
                        <h2 className="mt-1 text-lg font-semibold text-white sm:text-xl">Browse your learning path</h2>
                    </div>
                    <div className="-mx-4 overflow-x-auto px-4 pb-2 no-scrollbar sm:-mx-6 sm:px-6 lg:-mx-10 lg:px-10">
                        <div className="flex min-w-max gap-4">
                        {rawModules.map((module) => (
                            <div key={module.id} className="w-[240px] shrink-0 sm:w-[260px]">
                                <ModuleCard
                                    module={module}
                                    onLockedClick={() => setLockedModuleOpen(true)}
                                />
                            </div>
                        ))}
                        </div>
                    </div>
                    {!rawModules.length ? (
                        <div className="rounded-[16px] border border-white/10 bg-white/[0.04] px-6 py-12 text-center text-white/60">
                            No modules are available for your current access tier.
                        </div>
                    ) : null}
                </section>

                {(certificateMilestone?.state === 'download_available' || assignmentMilestone?.state === 'approved') ? (
                    <section className="flex flex-wrap gap-3 pb-4">
                        {certificateMilestone?.state === 'download_available' ? (
                            <a
                                href={certificateMilestone.cta_url ?? '#'}
                                className="inline-flex items-center gap-2 rounded-[14px] border border-emerald-500 bg-emerald-500 px-5 py-3 text-sm font-semibold text-white"
                            >
                                <Download className="size-4" />
                                {certificateMilestone.cta_label ?? 'Download Certificate'}
                            </a>
                        ) : null}
                        {assignmentMilestone?.state === 'approved' ? (
                            <div className="inline-flex items-center gap-2 rounded-[14px] border border-emerald-500 bg-emerald-500 px-5 py-3 text-sm font-semibold text-white">
                                <Check className="size-4" />
                                Assignment Approved
                            </div>
                        ) : null}
                    </section>
                ) : null}
            </div>
        </AuthenticatedLayout>
    );
}
