import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import {
    ChevronRight,
    Play,
    Info,
    CheckCircle2,
    Lock,
    BookOpen,
    Download,
    X,
    ArrowRight,
} from 'lucide-react';

function formatDurationParts(totalSeconds) {
    const safeSeconds = Math.max(0, Number(totalSeconds || 0));
    return {
        hours: String(Math.floor(safeSeconds / 3600)).padStart(2, '0'),
        minutes: String(Math.floor((safeSeconds % 3600) / 60)).padStart(2, '0'),
        seconds: String(Math.floor(safeSeconds % 60)).padStart(2, '0'),
    };
}

const ONBOARDING_KEY = 'yogafx_onboarding_done';

const SLIDES = [
    {
        title: 'Welcome to YogaFX',
        body: 'A premium learning platform built for focus. Find everything you need in one clean, intuitive interface.',
    },
    {
        title: 'Pick up where you left off',
        body: 'Your active module always appears at the top. Hit Continue and you land directly on your last lesson — no extra steps.',
    },
    {
        title: 'Browse all modules',
        body: 'Scroll through the module rows below. Click any card to preview lessons inside, then jump straight in.',
    },
];

function OnboardingOverlay({ onDone }) {
    const [slide, setSlide] = useState(0);
    const isLast = slide === SLIDES.length - 1;
    const current = SLIDES[slide];

    const finish = () => {
        localStorage.setItem(ONBOARDING_KEY, '1');
        onDone();
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm px-4">
            <div className="relative w-full max-w-md rounded-[28px] border border-white/15 bg-[#1a1210] p-8 shadow-2xl">
                <button
                    onClick={finish}
                    className="absolute right-5 top-5 text-white/40 hover:text-white/70 transition"
                    aria-label="Skip"
                >
                    <X className="size-5" />
                </button>

                <div className="flex gap-2 mb-8">
                    {SLIDES.map((_, i) => (
                        <div
                            key={i}
                            className={[
                                'h-1 rounded-full transition-all duration-300',
                                i === slide ? 'w-8 bg-[#d5462f]' : i < slide ? 'w-4 bg-white/40' : 'w-4 bg-white/15',
                            ].join(' ')}
                        />
                    ))}
                </div>

                <div className="space-y-3 text-center">
                    <h2 className="text-2xl font-semibold text-white tracking-tight">{current.title}</h2>
                    <p className="text-sm leading-7 text-white/55 max-w-sm mx-auto">{current.body}</p>
                </div>

                <div className="mt-8 flex items-center justify-between">
                    <button onClick={finish} className="text-sm text-white/35 hover:text-white/60 transition">
                        Skip
                    </button>
                    <button
                        onClick={() => (isLast ? finish() : setSlide(s => s + 1))}
                        className="flex items-center gap-2 rounded-full bg-[#d5462f] px-6 py-2.5 text-sm font-medium text-white hover:bg-[#e2553d] transition"
                    >
                        {isLast ? 'Get Started' : 'Next'}
                        <ChevronRight className="size-4" />
                    </button>
                </div>
            </div>
        </div>
    );
}

function LessonRow({ lesson }) {
    const isCompleted = lesson.status === 'completed';
    const isLocked = lesson.status === 'locked';

    const icon = isCompleted
        ? <CheckCircle2 className="size-4 text-emerald-400 shrink-0" />
        : isLocked
        ? <Lock className="size-4 text-white/25 shrink-0" />
        : <Play className="size-4 text-[#f15b3a] shrink-0" />;

    const content = (
        <div className="flex items-center gap-3 rounded-2xl border border-white/8 bg-white/[0.03] px-4 py-3 hover:bg-white/[0.06] transition">
            {icon}
            <div className="flex-1 min-w-0">
                <p className={['text-sm truncate', isLocked ? 'text-white/35' : 'text-white'].join(' ')}>
                    {lesson.title}
                </p>
                <p className="text-[11px] text-white/35 mt-0.5 uppercase tracking-[0.18em]">
                    Lesson {lesson.sort_order}
                </p>
            </div>
            {isCompleted && (
                <span className="text-[10px] uppercase tracking-[0.18em] text-emerald-400/70 shrink-0">Done</span>
            )}
            {!isCompleted && !isLocked && lesson.progress_percentage > 0 && (
                <span className="text-[11px] text-white/40 shrink-0">{lesson.progress_percentage}%</span>
            )}
        </div>
    );

    if (isLocked) return <div key={lesson.id}>{content}</div>;

    return (
        <Link key={lesson.id} href={lesson.url}>
            {content}
        </Link>
    );
}

function ModuleModal({ module, onClose }) {
    useEffect(() => {
        document.body.style.overflow = 'hidden';
        const onKey = (e) => { if (e.key === 'Escape') onClose(); };
        window.addEventListener('keydown', onKey);
        return () => {
            document.body.style.overflow = '';
            window.removeEventListener('keydown', onKey);
        };
    }, [onClose]);

    if (!module) return null;

    const lessons = module.lessons ?? [];
    const progressPct = module.progress_percentage ?? 0;
    const isCompleted = module.status === 'completed';

    return (
        <div
            className="fixed inset-0 z-40 flex items-end sm:items-center justify-center bg-black/75 backdrop-blur-sm px-0 sm:px-4"
            onClick={onClose}
        >
            <div
                className="relative w-full max-w-xl max-h-[90vh] overflow-y-auto rounded-t-[32px] sm:rounded-[32px] border border-white/10 bg-[#141110] shadow-2xl"
                onClick={e => e.stopPropagation()}
            >
                <div className="relative aspect-[16/7] overflow-hidden rounded-t-[32px]">
                    {module.thumbnail_url ? (
                        <img src={module.thumbnail_url} alt={module.title} className="h-full w-full object-cover" />
                    ) : (
                        <div className="h-full w-full bg-[radial-gradient(circle_at_20%_20%,rgba(213,70,47,0.5),transparent_40%),linear-gradient(160deg,#2d1a14,#0d0b0a)]" />
                    )}
                    <div className="absolute inset-0 bg-gradient-to-t from-[#141110] via-[#141110]/40 to-transparent" />

                    <button
                        onClick={onClose}
                        className="absolute right-4 top-4 rounded-full border border-white/15 bg-black/50 p-2 text-white/70 hover:text-white backdrop-blur transition"
                    >
                        <X className="size-4" />
                    </button>

                    <div className="absolute left-4 bottom-4">
                        <span className={[
                            'rounded-full border px-3 py-1 text-[10px] uppercase tracking-[0.2em] backdrop-blur',
                            isCompleted
                                ? 'border-emerald-400/30 bg-emerald-400/15 text-emerald-300'
                                : module.status === 'active'
                                ? 'border-[#d5462f]/40 bg-[#d5462f]/20 text-[#ffcfc7]'
                                : 'border-white/15 bg-black/30 text-white/65',
                        ].join(' ')}>
                            {module.status_label ?? 'Available'}
                        </span>
                    </div>
                </div>

                <div className="p-6 space-y-5">
                    <div className="space-y-1.5">
                        <p className="text-[10px] uppercase tracking-[0.26em] text-[#f2d9c8]">
                            Module {module.sort_order}
                        </p>
                        <h2 className="text-xl font-semibold tracking-tight text-white">{module.title}</h2>
                        {module.description && (
                            <p className="text-sm leading-6 text-white/55">{module.description}</p>
                        )}
                    </div>

                    {module.show_progress && (
                        <div className="space-y-1.5">
                            <div className="flex justify-between text-[11px] uppercase tracking-[0.18em] text-white/40">
                                <span>{module.completed_lessons} of {module.lesson_count} lessons done</span>
                                <span>{progressPct}%</span>
                            </div>
                            <div className="h-1.5 rounded-full bg-white/10 overflow-hidden">
                                <div
                                    className={['h-full rounded-full', isCompleted ? 'bg-emerald-400' : 'bg-[#d5462f]'].join(' ')}
                                    style={{ width: `${progressPct}%` }}
                                />
                            </div>
                        </div>
                    )}

                    <Link
                        href={module.continue_url ?? module.cta_url}
                        className="flex items-center justify-center gap-2 w-full rounded-full bg-[#d5462f] py-3 text-sm font-semibold text-white hover:bg-[#e2553d] transition"
                    >
                        <Play className="size-4 fill-current" />
                        {module.cta_label ?? 'Open Module'}
                    </Link>

                    {lessons.length > 0 && (
                        <div className="space-y-2">
                            <p className="text-[10px] uppercase tracking-[0.22em] text-white/35 pt-1">
                                Lessons in this module
                            </p>
                            <div className="space-y-1.5">
                                {lessons.map(lesson => (
                                    <LessonRow key={lesson.id} lesson={lesson} />
                                ))}
                            </div>
                        </div>
                    )}

                    {lessons.length === 0 && (
                        <p className="text-center text-sm text-white/35 py-4">
                            No lessons available in this module yet.
                        </p>
                    )}
                </div>
            </div>
        </div>
    );
}

function ModuleCard({ module, onClick }) {
    const isCompleted = module.status === 'completed';

    return (
        <button
            onClick={() => onClick(module)}
            className="group relative shrink-0 w-[240px] sm:w-[260px] rounded-[20px] overflow-hidden border border-white/10 bg-[#120f0e] transition duration-300 hover:-translate-y-1 hover:border-white/25 hover:shadow-[0_20px_60px_rgba(0,0,0,0.55)] text-left"
        >
            <div className="relative aspect-[16/10] overflow-hidden">
                {module.thumbnail_url ? (
                    <img
                        src={module.thumbnail_url}
                        alt={module.title}
                        className="h-full w-full object-cover transition duration-500 group-hover:scale-[1.06]"
                    />
                ) : (
                    <div className="h-full w-full bg-[radial-gradient(circle_at_24%_20%,rgba(213,70,47,0.4),transparent_30%),linear-gradient(160deg,#2b1d16,#120f0e)]" />
                )}
                <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/15 to-transparent" />

                <div className="absolute left-3 top-3">
                    <span className={[
                        'rounded-full border px-2.5 py-0.5 text-[10px] uppercase tracking-[0.18em] backdrop-blur',
                        isCompleted
                            ? 'border-emerald-400/30 bg-emerald-400/15 text-emerald-300'
                            : module.status === 'active'
                            ? 'border-[#d5462f]/40 bg-[#d5462f]/20 text-[#ffcfc7]'
                            : 'border-white/15 bg-black/30 text-white/60',
                    ].join(' ')}>
                        {module.status_label ?? 'Available'}
                    </span>
                </div>

                <div className="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition duration-200">
                    <div className="rounded-full border-2 border-white/80 bg-black/40 p-3 backdrop-blur">
                        <Play className="size-5 fill-white text-white" />
                    </div>
                </div>

                <div className="absolute bottom-2.5 right-3 text-[10px] uppercase tracking-[0.18em] text-white/40">
                    Module {module.sort_order}
                </div>
            </div>

            <div className="p-4 space-y-2">
                <h3 className="text-sm font-semibold text-white leading-snug line-clamp-2">{module.title}</h3>
                <p className="text-xs text-white/40">
                    {module.lesson_count > 0 ? `${module.lesson_count} lesson${module.lesson_count !== 1 ? 's' : ''}` : 'Module resource'}
                </p>
                {module.show_progress && (
                    <div className="h-[3px] rounded-full bg-white/10 overflow-hidden">
                        <div
                            className={['h-full rounded-full', isCompleted ? 'bg-emerald-400' : 'bg-[#d5462f]'].join(' ')}
                            style={{ width: `${module.progress_percentage}%` }}
                        />
                    </div>
                )}
            </div>
        </button>
    );
}

function ModuleRow({ title, modules, onCardClick }) {
    const ref = useRef(null);
    if (!modules?.length) return null;
    const scroll = (d) => ref.current?.scrollBy({ left: d * 290, behavior: 'smooth' });

    return (
        <section className="space-y-4">
            <h2 className="text-base font-semibold text-white px-4 sm:px-6 lg:px-10 tracking-tight">
                {title}
            </h2>
            <div className="relative group/row">
                <button
                    onClick={() => scroll(-1)}
                    className="absolute left-1 top-1/2 -translate-y-1/2 z-10 rounded-full border border-white/15 bg-black/60 p-2 text-white/65 hover:text-white backdrop-blur opacity-0 group-hover/row:opacity-100 transition"
                >
                    <ChevronRight className="size-4 rotate-180" />
                </button>
                <div
                    ref={ref}
                    className="flex gap-4 overflow-x-auto pb-3 px-4 sm:px-6 lg:px-10 scroll-smooth"
                    style={{ scrollbarWidth: 'none', msOverflowStyle: 'none' }}
                >
                    {modules.map(m => <ModuleCard key={m.id} module={m} onClick={onCardClick} />)}
                </div>
                <button
                    onClick={() => scroll(1)}
                    className="absolute right-1 top-1/2 -translate-y-1/2 z-10 rounded-full border border-white/15 bg-black/60 p-2 text-white/65 hover:text-white backdrop-blur opacity-0 group-hover/row:opacity-100 transition"
                >
                    <ChevronRight className="size-4" />
                </button>
            </div>
        </section>
    );
}

function AccessTimeCard({ accessTimeSummary }) {
    const [liveSeconds, setLiveSeconds] = useState(
        accessTimeSummary?.running_total_access_duration_seconds ?? 0,
    );

    useEffect(() => {
        setLiveSeconds(accessTimeSummary?.running_total_access_duration_seconds ?? 0);

        if (!accessTimeSummary?.currently_active || !accessTimeSummary?.active_session_login_at) {
            return;
        }

        const loginAt = new Date(accessTimeSummary.active_session_login_at).getTime();

        const tick = () => {
            const elapsed = Math.max(0, Math.floor((Date.now() - loginAt) / 1000));
            setLiveSeconds(
                (accessTimeSummary.running_total_access_duration_seconds ?? 0) + elapsed,
            );
        };

        tick();
        const interval = setInterval(tick, 1000);
        return () => clearInterval(interval);
    }, [
        accessTimeSummary?.active_session_login_at,
        accessTimeSummary?.currently_active,
        accessTimeSummary?.running_total_access_duration_seconds,
    ]);

    const parts = formatDurationParts(liveSeconds);

    return (
        <div className="inline-flex items-center gap-5 rounded-2xl border border-white/10 bg-black/40 px-7 py-5 backdrop-blur-md">
            <div className="leading-tight">
                <p className="text-xs uppercase tracking-[0.18em] text-white/45">
                    Running Total
                </p>
                <p className="text-xs uppercase tracking-[0.18em] text-white/45">
                    Login Time
                </p>
            </div>
            <div className="text-4xl font-semibold tracking-[0.06em] text-white tabular-nums">
                {parts.hours}:{parts.minutes}:{parts.seconds}
            </div>
        </div>
    );
}

function HeroSection({ homeExperience, continueLearning, studentName, onInfoClick, accessTimeSummary }) {
    const isNew = homeExperience?.state === 'new_student' || homeExperience?.state === 'catalog_empty';
    const thumbnail = continueLearning?.thumbnail_url ?? null;
    const title = continueLearning?.title ?? homeExperience?.hero_title ?? 'Start your learning journey';
    const description = continueLearning?.description ?? homeExperience?.hero_description ?? '';

    const ctaLabel = continueLearning?.cta_label ?? homeExperience?.primary_cta_label ?? (isNew ? 'Start First Lesson' : 'Continue Learning');
    const ctaUrl = continueLearning?.cta_url ?? homeExperience?.primary_cta_url ?? route('modules.index');

    const progressPct = continueLearning?.progress_percentage ?? 0;
    const moduleCtx = continueLearning?.module ?? null;
    const lessonCtx = continueLearning?.lesson ?? null;

    return (
        <section className="relative min-h-[72vh] sm:min-h-[82vh] flex items-end overflow-hidden">
            {thumbnail ? (
                <img src={thumbnail} alt="" className="absolute inset-0 h-full w-full object-cover" />
            ) : (
                <div className="absolute inset-0 bg-[radial-gradient(circle_at_18%_28%,rgba(173,76,38,0.55),transparent_36%),radial-gradient(circle_at_78%_18%,rgba(245,158,11,0.10),transparent_26%),linear-gradient(160deg,#1e1210,#0a0908)]" />
            )}
            <div className="absolute inset-0 bg-[linear-gradient(to_right,rgba(0,0,0,0.88)_0%,rgba(0,0,0,0.42)_52%,rgba(0,0,0,0.08)_100%),linear-gradient(to_top,rgba(0,0,0,0.96)_0%,rgba(0,0,0,0.38)_32%,transparent_62%)]" />

            {accessTimeSummary && (
                <div className="absolute right-4 bottom-24 z-10 sm:right-6 sm:bottom-28 lg:right-10 lg:bottom-32">
                    <AccessTimeCard accessTimeSummary={accessTimeSummary} />
                </div>
            )}

            <div className="relative w-full px-4 pb-16 pt-24 sm:px-6 lg:px-10 lg:pb-24 max-w-[1400px] mx-auto">
                <div className="max-w-2xl space-y-5">
                    <p className="text-[11px] font-medium uppercase tracking-[0.28em] text-[#f2d9c8]">
                        {isNew ? `Hello, ${studentName}` : `Welcome back, ${studentName}`}
                    </p>

                    <h1 className="text-4xl font-bold tracking-[-0.03em] text-white sm:text-5xl xl:text-6xl leading-[1.08]">
                        {title}
                    </h1>

                    {description && (
                        <p className="text-sm leading-7 text-white/65 sm:text-[15px] max-w-xl">{description}</p>
                    )}

                    {moduleCtx && lessonCtx && (
                        <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] uppercase tracking-[0.2em] text-white/45">
                            <span>{moduleCtx.title}</span>
                            <span className="h-1 w-1 rounded-full bg-white/25" />
                            <span>Lesson {lessonCtx.sort_order}</span>
                        </div>
                    )}

                    {!isNew && progressPct > 0 && (
                        <div className="flex items-center gap-3 max-w-xs">
                            <div className="flex-1 h-[3px] overflow-hidden rounded-full bg-white/20">
                                <div className="h-full rounded-full bg-[#d5462f]" style={{ width: `${progressPct}%` }} />
                            </div>
                            <span className="text-[11px] text-white/45 shrink-0">{progressPct}%</span>
                        </div>
                    )}

                    <div className="flex flex-wrap items-center gap-3 pt-1">
                        <Link
                            href={ctaUrl}
                            className="inline-flex items-center gap-2 rounded-full bg-[#d5462f] px-7 py-3 text-sm font-bold text-white hover:bg-[#e2553d] transition shadow-lg"
                        >
                            <Play className="size-4 fill-white" />
                            {ctaLabel}
                        </Link>

                        {onInfoClick && moduleCtx && (
                            <button
                                onClick={onInfoClick}
                                className="inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/10 px-6 py-3 text-sm font-medium text-white hover:bg-white/18 transition backdrop-blur"
                            >
                                <Info className="size-4" />
                                More Info
                            </button>
                        )}
                    </div>
                </div>
            </div>
        </section>
    );
}

export default function StudentHome({
    studentContext,
    accessTimeSummary,
    continueLearning,
    progressSummary,
    availableModulesSection,
    assignmentMilestone,
    certificateMilestone,
    ebookResourcesSection,
    homeExperience,
    sequentialAwareness,
}) {
    const hasReloadedRef = useRef(false);
    const studentName = studentContext?.display_name ?? 'Student';
    const [showOnboarding, setShowOnboarding] = useState(false);
    const [selectedModule, setSelectedModule] = useState(null);

    useEffect(() => {
        if (!localStorage.getItem(ONBOARDING_KEY)) setShowOnboarding(true);
    }, []);

    useEffect(() => {
        if (hasReloadedRef.current) return;
        hasReloadedRef.current = true;
        router.reload({
            only: ['availableModulesSection', 'progressSummary', 'homeExperience'],
            preserveScroll: true,
            preserveState: true,
        });
    }, []);

    const rawModules = availableModulesSection?.items ?? [];
    const inProgress = rawModules.filter(m => m.status === 'active');
    const others = rawModules.filter(m => m.status !== 'active');

    const activeModuleSlug = continueLearning?.module?.url_slug ?? null;
    const heroModule = activeModuleSlug
        ? rawModules.find(m => m.url_slug === activeModuleSlug) ?? null
        : null;

    const enrichModule = (m) => {
        const isActive = m.url_slug === activeModuleSlug;
        return {
            ...m,
            continue_url: isActive
                ? (continueLearning?.cta_url ?? m.cta_url)
                : m.cta_url,
            lessons: m.lessons ?? [],
        };
    };

    const ebookItems = ebookResourcesSection?.items ?? [];

    return (
        <AuthenticatedLayout studentVariant="immersive" studentContentClassName="pb-20">
            <Head title="Home" />

            {showOnboarding && <OnboardingOverlay onDone={() => setShowOnboarding(false)} />}

            {selectedModule && (
                <ModuleModal
                    module={enrichModule(selectedModule)}
                    onClose={() => setSelectedModule(null)}
                />
            )}

            <HeroSection
                homeExperience={homeExperience}
                continueLearning={continueLearning}
                studentName={studentName}
                onInfoClick={heroModule ? () => setSelectedModule(heroModule) : null}
                accessTimeSummary={accessTimeSummary}
            />

            <div className="relative z-10 -mt-10 space-y-10">

                {inProgress.length > 0 && (
                    <ModuleRow
                        title="In Progress"
                        modules={inProgress}
                        onCardClick={setSelectedModule}
                    />
                )}

                <ModuleRow
                    title={inProgress.length > 0 ? 'All Modules' : 'Start Here'}
                    modules={others.length > 0 ? others : rawModules}
                    onCardClick={setSelectedModule}
                />

                {ebookItems.length > 0 && (
                    <section className="space-y-4">
                        <div className="flex items-center justify-between px-4 sm:px-6 lg:px-10">
                            <h2 className="text-base font-semibold text-white tracking-tight">Learning Resources</h2>
                            <Link
                                href={route('ebooks.index')}
                                className="text-xs text-white/40 hover:text-white/70 transition flex items-center gap-1"
                            >
                                View All <ChevronRight className="size-3.5" />
                            </Link>
                        </div>
                        <div
                            className="flex gap-4 overflow-x-auto pb-3 px-4 sm:px-6 lg:px-10"
                            style={{ scrollbarWidth: 'none', msOverflowStyle: 'none' }}
                        >
                            {ebookItems.map(ebook => (
                                <Link
                                    key={ebook.id}
                                    href={ebook.preview_url ?? route('ebooks.index')}
                                    className="group shrink-0 w-[170px] rounded-[18px] overflow-hidden border border-white/10 bg-[#120f0e] transition duration-300 hover:-translate-y-1 hover:border-white/22"
                                >
                                    <div className="relative aspect-[3/4] bg-[radial-gradient(circle_at_20%_18%,rgba(213,70,47,0.45),transparent_30%),linear-gradient(160deg,#2d1e18,#120f0e)] p-4 flex flex-col justify-between">
                                        <div className="flex items-center gap-1.5 text-[10px] uppercase tracking-[0.2em] text-white/50">
                                            <BookOpen className="size-3" />
                                            Ebook
                                        </div>
                                        <h3 className="text-sm font-semibold text-white leading-tight line-clamp-3">{ebook.title}</h3>
                                    </div>
                                    <div className="p-3">
                                        <span className="text-xs text-[#d5462f] group-hover:text-[#e2553d] transition flex items-center gap-1">
                                            Open Preview <ArrowRight className="size-3" />
                                        </span>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </section>
                )}

                {rawModules.length === 0 && (
                    <div className="px-4 sm:px-6 lg:px-10">
                        <div className="rounded-[28px] border border-white/8 bg-white/[0.02] px-8 py-16 text-center">
                            <p className="text-sm text-white/45">No modules are available for your current access tier.</p>
                            <p className="mt-2 text-xs text-white/25">Contact your administrator for more information.</p>
                        </div>
                    </div>
                )}

                {(certificateMilestone?.state === 'download_available' || assignmentMilestone?.state === 'approved') && (
                    <div className="px-4 sm:px-6 lg:px-10 pb-4">
                        <div className="flex flex-wrap gap-3">
                            {certificateMilestone?.state === 'download_available' && (
                                <a
                                    href={certificateMilestone.cta_url ?? '#'}
                                    className="inline-flex items-center gap-2 rounded-full border border-emerald-400/25 bg-emerald-400/10 px-5 py-2.5 text-xs font-medium text-emerald-300 hover:bg-emerald-400/15 transition"
                                >
                                    <Download className="size-3.5" />
                                    {certificateMilestone.cta_label ?? 'Download Certificate'}
                                </a>
                            )}
                            {assignmentMilestone?.state === 'approved' && (
                                <div className="inline-flex items-center gap-2 rounded-full border border-emerald-400/25 bg-emerald-400/10 px-5 py-2.5 text-xs font-medium text-emerald-300">
                                    <CheckCircle2 className="size-3.5" />
                                    Assignment approved
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}