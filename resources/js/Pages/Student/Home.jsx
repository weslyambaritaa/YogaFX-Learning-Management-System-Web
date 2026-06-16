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
    Search, // Ditambahkan karena dipakai di StudentHome
} from 'lucide-react';

// ─── Onboarding ───────────────────────────────────────────────────────────────

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

                {/* Step dots */}
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

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(Number(amount || 0));
}

// ─── Module Detail Modal ───────────────────────────────────────────────────────

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
                {/* Thumbnail */}
                <div className="relative aspect-[16/7] overflow-hidden rounded-t-[32px]">
                    {module.thumbnail_url ? (
                        <img src={module.thumbnail_url} alt={module.title} className="h-full w-full object-cover" />
                    ) : (
                        <div className="h-full w-full bg-[radial-gradient(circle_at_20%_20%,rgba(213,70,47,0.5),transparent_40%),linear-gradient(160deg,#2d1a14,#0d0b0a)]" />
                    )}
                    <div className="absolute inset-0 bg-gradient-to-t from-[#141110] via-[#141110]/40 to-transparent" />

                    {/* Close */}
                    <button
                        onClick={onClose}
                        className="absolute right-4 top-4 rounded-full border border-white/15 bg-black/50 p-2 text-white/70 hover:text-white backdrop-blur transition"
                    >
                        <X className="size-4" />
                    </button>

                    {/* Status badge */}
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

                {/* Body */}
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

                    {/* Progress */}
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

                    {/* CTA — direct to lesson, not module page */}
                    <Link
                        href={module.continue_url ?? module.cta_url}
                        className="flex items-center justify-center gap-2 w-full rounded-full bg-[#d5462f] py-3 text-sm font-semibold text-white hover:bg-[#e2553d] transition"
                    >
                        <Play className="size-4 fill-current" />
                        {module.cta_label ?? 'Open Module'}
                    </Link>

                    {/* Lesson list */}
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

// ─── Module Card ──────────────────────────────────────────────────────────────

function ModuleCard({ module, onClick }) {
    const isCompleted = module.status === 'completed';

    return (
        <button
            onClick={() => onClick(module)}
            className="group relative shrink-0 w-[240px] sm:w-[260px] rounded-[20px] overflow-hidden border border-white/10 bg-[#120f0e] transition duration-300 hover:-translate-y-1 hover:border-white/25 hover:shadow-[0_20px_60px_rgba(0,0,0,0.55)] text-left"
        >
            {/* Thumbnail */}
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

                {/* Status badge */}
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

                {/* Play overlay on hover */}
                <div className="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition duration-200">
                    <div className="rounded-full border-2 border-white/80 bg-black/40 p-3 backdrop-blur">
                        <Play className="size-5 fill-white text-white" />
                    </div>
                </div>

                <div className="absolute bottom-2.5 right-3 text-[10px] uppercase tracking-[0.18em] text-white/40">
                    Module {module.sort_order}
                </div>
            </div>

            {/* Card body */}
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

// ─── Horizontal Row ───────────────────────────────────────────────────────────

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
                >
                    {modules.map(module => (
                        <ModuleCard key={module.id} module={module} onClick={onCardClick} />
                    ))}
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

// ─── Student Home Component ───────────────────────────────────────────────────

export default function StudentHome({
    homeStage,
    studentContext,
    accessTimeSummary,
    continueLearning,
    progressSummary,
    nextStep,
    sequentialAwareness,
    availableModulesSection,
    assignmentMilestone,
    certificateMilestone,
    ebookResourcesSection,
    homeExperience,
    upgradeOptions = [],
    status,
    heroPrimaryKind,     // Ditambahkan dari destrukturisasi kode konflik
    heroSecondaryKind,   // Ditambahkan dari destrukturisasi kode konflik
    runningAccessParts = { hours: '00', minutes: '00', seconds: '00' } // Fallback default agar tidak crash
}) {
    const hasReloadedRef = useRef(false);
    const studentName = studentContext?.display_name ?? 'Student';
    const fullName = studentContext?.full_name ?? studentName;
    const accessTier = studentContext?.access_tier ?? null;
    const tierLabel = accessTier?.name ?? 'Tier assignment pending';
    const tierStatusLabel = accessTier
        ? accessTier.is_active
            ? 'Active access tier'
            : 'Inactive access tier'
        : 'No access tier assigned yet';
    const continueProgress = continueLearning?.progress_percentage ?? 0;
    const overallProgress = progressSummary?.overall_progress_percentage ?? 0;
    const currentSequenceLesson = sequentialAwareness?.current_lesson ?? null;
    const nextSequenceLesson = sequentialAwareness?.next_lesson ?? null;
    
    const currentSequenceStatus = currentSequenceLesson
        ? currentSequenceLesson.is_done
            ? 'Completed'
            : currentSequenceLesson.watch_progress > 0
              ? `${currentSequenceLesson.watch_progress}% watched`
              : 'Not started yet'
        : 'No current lesson';
        
    const nextSequenceStatus = nextSequenceLesson
        ? currentSequenceLesson?.is_done
            ? 'Ready next'
            : 'Waiting in sequence'
        : 'No next lesson';
        
    const heroBadges = homeExperience?.hero_badges ?? [
        tierLabel,
        'Learning momentum is active',
    ];
    
    const [runningAccessSeconds, setRunningAccessSeconds] = useState(
        accessTimeSummary?.running_total_access_duration_seconds ?? 0
    );

    // Placeholder untuk state modal jika dibutuhkan di component utama ini
    const [selectedModule, setSelectedModule] = useState(null);

    return (
        <AuthenticatedLayout>
            <Head title="Home" />

            <div className="mx-auto flex max-w-[1400px] flex-col gap-10 px-4 pt-6 sm:px-6 lg:px-10">
                <section className="relative overflow-hidden rounded-[32px] border border-white/10 bg-[#15110f] shadow-[0_30px_120px_rgba(0,0,0,0.45)]">
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,_rgba(173,76,38,0.45),_transparent_30%),radial-gradient(circle_at_82%_18%,_rgba(245,158,11,0.12),_transparent_26%),linear-gradient(120deg,_rgba(255,255,255,0.05)_0%,_rgba(255,255,255,0)_40%),linear-gradient(180deg,_rgba(0,0,0,0.02)_0%,_rgba(0,0,0,0.58)_78%,_rgba(0,0,0,0.82)_100%)]" />
                    <div className="absolute right-0 top-0 h-full w-[48%] bg-[radial-gradient(circle_at_center,_rgba(249,115,22,0.22),_transparent_42%),linear-gradient(180deg,_rgba(255,255,255,0.08),_rgba(255,255,255,0.01))]" />

                    <div className="relative grid min-h-[540px] gap-10 px-6 py-8 sm:px-8 lg:grid-cols-[minmax(0,1fr)_300px] lg:px-12 lg:py-12">
                        <div className="flex flex-col justify-between gap-8">
                            <div className="flex flex-wrap items-center gap-3 text-xs uppercase tracking-[0.24em] text-white/65">
                                <span>YogaFX Home</span>
                                <span className="h-1 w-1 rounded-full bg-white/30" />
                                <span>Phase {homeStage}</span>
                                <span className="h-1 w-1 rounded-full bg-white/30" />
                                <span>Premium Streaming Shell</span>
                            </div>

                            <div className="max-w-3xl space-y-6">
                                <div className="space-y-3">
                                    <p className="text-xs font-medium uppercase tracking-[0.28em] text-[#f2d9c8]">
                                        Hi {studentName}, welcome back
                                    </p>
                                    <h1 className="max-w-2xl text-4xl font-semibold tracking-[-0.03em] text-white sm:text-5xl xl:text-6xl">
                                        {homeExperience?.hero_title ??
                                            'Your premium YogaFX learning home is now ready to carry your student identity.'}
                                    </h1>
                                </div>

                                <p className="max-w-2xl text-sm leading-7 text-white/72 sm:text-base">
                                    You are signed in as {fullName}.{' '}
                                    {homeExperience?.hero_description ??
                                        `Your Home experience is anchored to ${tierLabel.toLowerCase()} access and now has the core student context needed for the next learning-focused sections.`}
                                </p>

                                <div className="flex flex-wrap items-center gap-3 pt-2">
                                    {heroPrimaryKind === 'download' ? (
                                        <button
                                            size="lg"
                                            className="rounded-full bg-[#d5462f] px-6 py-3 text-sm font-medium text-white shadow-[0_18px_50px_rgba(213,70,47,0.3)] hover:bg-[#e2553d] transition flex items-center"
                                        >
                                            <a href={homeExperience?.primary_cta_url ?? '#'} className="flex items-center">
                                                <Play className="mr-2 size-4 fill-current" />
                                                {homeExperience?.primary_cta_label ?? 'Continue the Course'}
                                            </a>
                                        </button>
                                    ) : (
                                        <Link 
                                            href={homeExperience?.primary_cta_url ?? route('modules.index')}
                                            className="rounded-full bg-[#d5462f] px-6 py-3 text-sm font-medium text-white shadow-[0_18px_50px_rgba(213,70,47,0.3)] hover:bg-[#e2553d] transition flex items-center"
                                        >
                                            <Play className="mr-2 size-4 fill-current" />
                                            {homeExperience?.primary_cta_label ?? 'Continue the Course'}
                                        </Link>
                                    )}

                                    {heroSecondaryKind === 'download' ? (
                                        <a 
                                            href={homeExperience?.secondary_cta_url ?? '#'}
                                            className="rounded-full border border-white/20 bg-white/5 px-6 py-3 text-sm font-medium text-white hover:bg-white/10 transition flex items-center"
                                        >
                                            {homeExperience?.secondary_cta_label ?? 'Explore Modules'}
                                        </a>
                                    ) : (
                                        <Link 
                                            href={homeExperience?.secondary_cta_url ?? route('modules.index')}
                                            className="rounded-full border border-white/20 bg-white/5 px-6 py-3 text-sm font-medium text-white hover:bg-white/10 transition flex items-center"
                                        >
                                            {homeExperience?.secondary_cta_label ?? 'Explore Modules'}
                                        </Link>
                                    )}
                                </div>
                            </div>

                            <div className="flex flex-wrap items-center gap-3 text-sm text-white/70">
                                {heroBadges.map((badge) => (
                                    <div
                                        key={badge}
                                        className="rounded-full border border-white/10 bg-black/20 px-4 py-2 backdrop-blur"
                                    >
                                        {badge}
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="flex items-end justify-start lg:justify-end">
                            <div className="w-full max-w-[280px] rounded-[28px] border border-white/10 bg-black/30 p-5 shadow-2xl backdrop-blur-md">
                                <div className="space-y-6">
                                    <div className="space-y-2">
                                        <p className="text-xs uppercase tracking-[0.24em] text-white/55">
                                            Total access time
                                        </p>
                                        <div className="text-3xl font-semibold tracking-[0.08em] text-white">
                                            {`${runningAccessParts.hours}:${runningAccessParts.minutes}:${runningAccessParts.seconds}`}
                                        </div>
                                        <p className="text-sm text-white/58">
                                            Cumulative student access time
                                        </p>
                                    </div>

                                    <div className="rounded-2xl border border-white/10 bg-white/5 p-4">
                                        <div className="flex items-center justify-between gap-3">
                                            <div>
                                                <p className="text-xs uppercase tracking-[0.2em] text-white/55">
                                                    Student Context
                                                </p>
                                                <p className="mt-1 text-sm font-medium text-white">
                                                    {tierStatusLabel}
                                                </p>
                                            </div>
                                            <Search className="size-4 text-white/60" />
                                        </div>
                                        <p className="mt-3 text-sm leading-6 text-white/60">
                                            {accessTier
                                                ? `${tierLabel} is attached to this student profile and ready to be used by the next Home sections.`
                                                : 'This student can open Home safely, but content sections should keep using a no-tier fallback until access tier is assigned.'}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {status === 'upgrade-payment-success' && (
                    <div className="rounded-[24px] border border-emerald-300/15 bg-[linear-gradient(160deg,rgba(16,185,129,0.16),rgba(255,255,255,0.03))] px-5 py-4 text-sm text-emerald-50/90">
                        Your simulated upgrade payment succeeded and your student tier has been updated.
                    </div>
                )}

                {upgradeOptions.length > 0 && (
                    <section className="space-y-4">
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <p className="text-xs uppercase tracking-[0.24em] text-white/45">
                                    Upgrade Path
                                </p>
                                <h2 className="mt-2 text-2xl font-semibold tracking-tight text-white">
                                    Step into a higher YogaFX tier when you are ready
                                </h2>
                            </div>
                            <span className="hidden text-sm text-white/45 md:inline">
                                Simulated billing active
                            </span>
                        </div>

                        <div className="grid gap-4 lg:grid-cols-3">
                            {upgradeOptions.map((option) => (
                                <div
                                    key={option.id}
                                    className="rounded-[28px] border border-white/10 bg-white/[0.04] p-5 backdrop-blur-sm"
                                >
                                    <div className="flex h-full flex-col justify-between gap-6 rounded-[22px] border border-white/8 bg-black/15 p-5">
                                        <div className="space-y-3">
                                            <p className="text-xs uppercase tracking-[0.22em] text-white/45">
                                                Upgrade target
                                            </p>
                                            <h3 className="text-2xl font-semibold text-white">
                                                {option.name}
                                            </h3>
                                            <p className="text-sm leading-6 text-white/60">
                                                Total tier price {formatCurrency(option.price_amount)}.
                                                Your current journey reduces the amount due to {formatCurrency(option.amount_due)}.
                                            </p>
                                        </div>

                                        <Link 
                                            href={option.checkout_url}
                                            className="rounded-full bg-[#d5462f] px-5 py-2.5 text-sm font-medium text-white hover:bg-[#e2553d] transition flex items-center justify-center"
                                        >
                                            <ChevronRight className="mr-2 size-4" />
                                            Upgrade to {option.name}
                                        </Link>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>
                )}

                {/* Bagian bawah halaman Home (Kategori baris modul dll) bisa ditaruh di sini */}
                <ModuleRow 
                    title="Available Modules" 
                    modules={availableModulesSection?.modules} 
                    onCardClick={(mod) => setSelectedModule(mod)} 
                />

                {/* Render Modal jika ada module yang dipilih */}
                {selectedModule && (
                    <ModuleModal module={selectedModule} onClose={() => setSelectedModule(null)} />
                )}
            </div>
        </AuthenticatedLayout>
    );
}