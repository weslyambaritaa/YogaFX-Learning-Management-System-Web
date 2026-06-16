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

// ─── Helpers ──────────────────────────────────────────────────────────────────

function formatDurationParts(totalSeconds) {
    const s = Math.max(0, Number(totalSeconds || 0));
    return {
        hours: String(Math.floor(s / 3600)).padStart(2, '0'),
        minutes: String(Math.floor((s % 3600) / 60)).padStart(2, '0'),
        seconds: String(Math.floor(s % 60)).padStart(2, '0'),
    };
}

// ─── Onboarding Overlay ───────────────────────────────────────────────────────

const ONBOARDING_KEY = 'yogafx_onboarding_done';

const onboardingSlides = [
    {
        icon: '🎬',
        title: 'Selamat datang di YogaFX',
        body: 'Platform belajar yoga yang dirancang seperti layanan streaming premium. Temukan semua materi dalam satu tampilan yang bersih dan intuitif.',
    },
    {
        icon: '▶️',
        title: 'Lanjutkan belajar kapan saja',
        body: 'Modul yang sedang kamu pelajari selalu tampil di bagian atas. Klik "Lanjutkan Belajar" dan kamu langsung masuk ke lesson berikutnya.',
    },
    {
        icon: '📚',
        title: 'Jelajahi semua modul',
        body: 'Geser kartu modul ke kanan untuk melihat seluruh katalog pembelajaran. Klik kartu mana saja untuk melihat detail dan daftar lesson-nya.',
    },
];

function OnboardingOverlay({ onDone }) {
    const [slide, setSlide] = useState(0);
    const isLast = slide === onboardingSlides.length - 1;
    const current = onboardingSlides[slide];

    const finish = () => {
        localStorage.setItem(ONBOARDING_KEY, '1');
        onDone();
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm px-4">
            <div className="relative w-full max-w-md rounded-[28px] border border-white/15 bg-[#1a1210] p-8 shadow-2xl">
                {/* Skip */}
                <button
                    onClick={finish}
                    className="absolute right-5 top-5 text-white/40 hover:text-white/80 transition"
                    aria-label="Skip onboarding"
                >
                    <X className="size-5" />
                </button>

                {/* Slide indicator */}
                <div className="flex gap-2 mb-8">
                    {onboardingSlides.map((_, i) => (
                        <div
                            key={i}
                            className={[
                                'h-1 rounded-full transition-all duration-300',
                                i === slide
                                    ? 'w-8 bg-[#d5462f]'
                                    : i < slide
                                    ? 'w-4 bg-white/40'
                                    : 'w-4 bg-white/15',
                            ].join(' ')}
                        />
                    ))}
                </div>

                {/* Content */}
                <div className="space-y-4 text-center">
                    <div className="text-5xl">{current.icon}</div>
                    <h2 className="text-2xl font-semibold text-white tracking-tight">
                        {current.title}
                    </h2>
                    <p className="text-sm leading-7 text-white/62 max-w-sm mx-auto">
                        {current.body}
                    </p>
                </div>

                {/* Actions */}
                <div className="mt-8 flex items-center justify-between gap-3">
                    <button
                        onClick={finish}
                        className="text-sm text-white/40 hover:text-white/70 transition"
                    >
                        Lewati
                    </button>

                    <button
                        onClick={() => isLast ? finish() : setSlide(s => s + 1)}
                        className="flex items-center gap-2 rounded-full bg-[#d5462f] px-6 py-2.5 text-sm font-medium text-white hover:bg-[#e2553d] transition"
                    >
                        {isLast ? 'Mulai Belajar' : 'Lanjut'}
                        <ChevronRight className="size-4" />
                    </button>
                </div>
            </div>
        </div>
    );
}

// ─── Module Detail Modal ───────────────────────────────────────────────────────

function ModuleModal({ module, onClose }) {
    // Lock body scroll when modal is open
    useEffect(() => {
        document.body.style.overflow = 'hidden';
        return () => { document.body.style.overflow = ''; };
    }, []);

    if (!module) return null;

    const lessonStatusIcon = (status) => {
        if (status === 'completed') return <CheckCircle2 className="size-4 text-emerald-400 shrink-0" />;
        if (status === 'locked') return <Lock className="size-4 text-white/30 shrink-0" />;
        return <Play className="size-4 text-[#f15b3a] shrink-0" />;
    };

    return (
        <div
            className="fixed inset-0 z-40 flex items-end sm:items-center justify-center bg-black/75 backdrop-blur-sm px-0 sm:px-4"
            onClick={onClose}
        >
            <div
                className="relative w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-t-[32px] sm:rounded-[32px] border border-white/10 bg-[#141110] shadow-2xl"
                onClick={e => e.stopPropagation()}
            >
                {/* Thumbnail hero */}
                <div className="relative aspect-[16/7] overflow-hidden rounded-t-[32px] sm:rounded-t-[32px]">
                    {module.thumbnail_url ? (
                        <img
                            src={module.thumbnail_url}
                            alt={module.title}
                            className="h-full w-full object-cover"
                        />
                    ) : (
                        <div className="h-full w-full bg-[radial-gradient(circle_at_20%_20%,rgba(213,70,47,0.5),transparent_40%),linear-gradient(160deg,#2d1a14,#0d0b0a)]" />
                    )}
                    <div className="absolute inset-0 bg-gradient-to-t from-[#141110] via-[#141110]/40 to-transparent" />

                    {/* Close button */}
                    <button
                        onClick={onClose}
                        className="absolute right-4 top-4 rounded-full border border-white/15 bg-black/50 p-2 text-white/70 hover:text-white backdrop-blur transition"
                    >
                        <X className="size-4" />
                    </button>

                    {/* Status badge */}
                    <div className="absolute left-5 bottom-5">
                        <span className={[
                            'rounded-full border px-3 py-1 text-xs uppercase tracking-[0.2em] backdrop-blur',
                            module.status === 'completed'
                                ? 'border-emerald-400/30 bg-emerald-400/15 text-emerald-300'
                                : module.status === 'active'
                                ? 'border-[#d5462f]/40 bg-[#d5462f]/20 text-[#ffcfc7]'
                                : 'border-white/15 bg-black/30 text-white/70',
                        ].join(' ')}>
                            {module.status_label ?? 'Available'}
                        </span>
                    </div>
                </div>

                {/* Content */}
                <div className="p-6 space-y-5">
                    <div className="space-y-2">
                        <p className="text-xs uppercase tracking-[0.26em] text-[#f2d9c8]">
                            Modul {module.sort_order}
                        </p>
                        <h2 className="text-2xl font-semibold tracking-tight text-white">
                            {module.title}
                        </h2>
                        {module.description && (
                            <p className="text-sm leading-7 text-white/62">
                                {module.description}
                            </p>
                        )}
                    </div>

                    {/* Progress bar */}
                    {module.show_progress && (
                        <div className="space-y-1.5">
                            <div className="flex items-center justify-between text-xs text-white/45 uppercase tracking-[0.18em]">
                                <span>{module.completed_lessons} dari {module.lesson_count} lesson selesai</span>
                                <span>{module.progress_percentage}%</span>
                            </div>
                            <div className="h-1.5 overflow-hidden rounded-full bg-white/10">
                                <div
                                    className={[
                                        'h-full rounded-full transition-all',
                                        module.status === 'completed' ? 'bg-emerald-400' : 'bg-[#d5462f]',
                                    ].join(' ')}
                                    style={{ width: `${module.progress_percentage}%` }}
                                />
                            </div>
                        </div>
                    )}

                    {/* CTA */}
                    <Link
                        href={module.cta_url ?? route('modules.index')}
                        className="flex items-center justify-center gap-2 w-full rounded-full bg-[#d5462f] py-3 text-sm font-semibold text-white hover:bg-[#e2553d] transition"
                    >
                        <Play className="size-4 fill-current" />
                        {module.cta_label ?? 'Buka Modul'}
                    </Link>

                    {/* Lesson list preview */}
                    {module.lessons?.length > 0 && (
                        <div className="space-y-2">
                            <p className="text-xs uppercase tracking-[0.22em] text-white/40 pt-2">
                                Daftar Lesson
                            </p>
                            <div className="space-y-2">
                                {module.lessons.slice(0, 6).map((lesson) => (
                                    <div
                                        key={lesson.id}
                                        className="flex items-center gap-3 rounded-2xl border border-white/8 bg-white/[0.03] p-3"
                                    >
                                        {lessonStatusIcon(lesson.status)}
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm text-white truncate">{lesson.title}</p>
                                            <p className="text-xs text-white/40 mt-0.5">Lesson {lesson.sort_order}</p>
                                        </div>
                                        {lesson.progress_percentage > 0 && lesson.status !== 'completed' && (
                                            <span className="text-xs text-white/40">{lesson.progress_percentage}%</span>
                                        )}
                                    </div>
                                ))}
                                {module.lessons.length > 6 && (
                                    <Link
                                        href={module.cta_url ?? route('modules.index')}
                                        className="flex items-center justify-center gap-1.5 text-sm text-[#d5462f] hover:text-[#e2553d] transition pt-1"
                                    >
                                        Lihat {module.lessons.length - 6} lesson lainnya
                                        <ArrowRight className="size-3.5" />
                                    </Link>
                                )}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

// ─── Module Card ──────────────────────────────────────────────────────────────

function ModuleCard({ module, onClick }) {
    return (
        <button
            onClick={() => onClick(module)}
            className="group relative shrink-0 w-[260px] sm:w-[280px] rounded-[20px] overflow-hidden border border-white/10 bg-[#120f0e] transition duration-300 hover:-translate-y-1 hover:border-white/25 hover:shadow-[0_20px_60px_rgba(0,0,0,0.5)] text-left"
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
                <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent" />

                {/* Status badge */}
                <div className="absolute left-3 top-3">
                    <span className={[
                        'rounded-full border px-2.5 py-0.5 text-[10px] uppercase tracking-[0.2em] backdrop-blur',
                        module.status === 'completed'
                            ? 'border-emerald-400/30 bg-emerald-400/15 text-emerald-300'
                            : module.status === 'active'
                            ? 'border-[#d5462f]/40 bg-[#d5462f]/20 text-[#ffcfc7]'
                            : 'border-white/15 bg-black/30 text-white/65',
                    ].join(' ')}>
                        {module.status_label ?? 'Available'}
                    </span>
                </div>

                {/* Hover play overlay */}
                <div className="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition duration-200">
                    <div className="rounded-full border-2 border-white/80 bg-black/40 p-3 backdrop-blur">
                        <Play className="size-5 fill-white text-white" />
                    </div>
                </div>

                {/* Module number */}
                <div className="absolute bottom-3 right-3 text-[10px] uppercase tracking-[0.2em] text-white/50">
                    Modul {module.sort_order}
                </div>
            </div>

            {/* Card body */}
            <div className="p-4 space-y-2.5">
                <h3 className="text-sm font-semibold text-white leading-tight line-clamp-2">
                    {module.title}
                </h3>
                <p className="text-xs text-white/45">
                    {module.lesson_count > 0
                        ? `${module.lesson_count} lesson`
                        : 'Resource modul'}
                </p>

                {/* Progress bar */}
                {module.show_progress && (
                    <div className="h-1 overflow-hidden rounded-full bg-white/10">
                        <div
                            className={[
                                'h-full rounded-full',
                                module.status === 'completed' ? 'bg-emerald-400' : 'bg-[#d5462f]',
                            ].join(' ')}
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
    const scrollRef = useRef(null);

    if (!modules?.length) return null;

    const scroll = (dir) => {
        if (!scrollRef.current) return;
        scrollRef.current.scrollBy({ left: dir * 300, behavior: 'smooth' });
    };

    return (
        <section className="space-y-4">
            <h2 className="text-lg font-semibold text-white px-4 sm:px-6 lg:px-10">
                {title}
            </h2>
            <div className="relative group/row">
                {/* Left arrow */}
                <button
                    onClick={() => scroll(-1)}
                    className="absolute left-2 top-1/2 -translate-y-1/2 z-10 rounded-full border border-white/15 bg-black/60 p-2 text-white/70 hover:text-white backdrop-blur opacity-0 group-hover/row:opacity-100 transition"
                >
                    <ChevronRight className="size-4 rotate-180" />
                </button>

                {/* Scroll container */}
                <div
                    ref={scrollRef}
                    className="flex gap-4 overflow-x-auto pb-2 px-4 sm:px-6 lg:px-10 scroll-smooth"
                    style={{ scrollbarWidth: 'none', msOverflowStyle: 'none' }}
                >
                    {modules.map((module) => (
                        <ModuleCard
                            key={module.id}
                            module={module}
                            onClick={onCardClick}
                        />
                    ))}
                </div>

                {/* Right arrow */}
                <button
                    onClick={() => scroll(1)}
                    className="absolute right-2 top-1/2 -translate-y-1/2 z-10 rounded-full border border-white/15 bg-black/60 p-2 text-white/70 hover:text-white backdrop-blur opacity-0 group-hover/row:opacity-100 transition"
                >
                    <ChevronRight className="size-4" />
                </button>
            </div>
        </section>
    );
}

// ─── Hero Section ─────────────────────────────────────────────────────────────

function HeroSection({ homeExperience, continueLearning, studentName, onInfoClick }) {
    const isNewStudent = homeExperience?.state === 'new_student' || homeExperience?.state === 'catalog_empty';
    const thumbnail = continueLearning?.thumbnail_url ?? null;
    const title = homeExperience?.hero_title ?? continueLearning?.title ?? 'Mulai perjalanan belajarmu';
    const description = homeExperience?.hero_description ?? continueLearning?.description ?? 'Temukan modul yoga terbaik dan mulai belajar hari ini.';
    const ctaLabel = homeExperience?.primary_cta_label ?? (isNewStudent ? 'Mulai Belajar' : 'Lanjutkan Belajar');
    const ctaUrl = homeExperience?.primary_cta_url ?? continueLearning?.cta_url ?? route('modules.index');
    const ctaKind = homeExperience?.primary_cta_kind ?? 'link';
    const continueProgress = continueLearning?.progress_percentage ?? 0;

    return (
        <section className="relative min-h-[70vh] sm:min-h-[80vh] flex items-end overflow-hidden">
            {/* Background */}
            {thumbnail ? (
                <img
                    src={thumbnail}
                    alt={title}
                    className="absolute inset-0 h-full w-full object-cover"
                />
            ) : (
                <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_30%,rgba(173,76,38,0.55),transparent_38%),radial-gradient(circle_at_78%_20%,rgba(245,158,11,0.10),transparent_28%),linear-gradient(160deg,#1e1210,#0a0908)]" />
            )}

            {/* Gradient overlay */}
            <div className="absolute inset-0 bg-[linear-gradient(to_right,rgba(0,0,0,0.85)_0%,rgba(0,0,0,0.45)_50%,rgba(0,0,0,0.10)_100%),linear-gradient(to_top,rgba(0,0,0,0.95)_0%,rgba(0,0,0,0.4)_35%,transparent_65%)]" />

            {/* Content */}
            <div className="relative w-full px-4 pb-16 pt-24 sm:px-6 lg:px-10 lg:pb-24 max-w-[1400px] mx-auto">
                <div className="max-w-2xl space-y-5">
                    {/* Eyebrow */}
                    <p className="text-xs font-medium uppercase tracking-[0.28em] text-[#f2d9c8]">
                        {isNewStudent ? `Halo, ${studentName} 👋` : `Selamat datang kembali, ${studentName}`}
                    </p>

                    {/* Title */}
                    <h1 className="text-4xl font-bold tracking-[-0.03em] text-white sm:text-5xl xl:text-6xl leading-[1.08]">
                        {title}
                    </h1>

                    {/* Description */}
                    <p className="text-sm leading-7 text-white/70 sm:text-base max-w-xl">
                        {description}
                    </p>

                    {/* Module + lesson context */}
                    {continueLearning?.module && continueLearning?.lesson && (
                        <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs uppercase tracking-[0.2em] text-white/50">
                            <span>{continueLearning.module.title}</span>
                            <span className="h-1 w-1 rounded-full bg-white/25" />
                            <span>Lesson {continueLearning.lesson.sort_order}</span>
                        </div>
                    )}

                    {/* Progress bar (for returning students) */}
                    {!isNewStudent && continueProgress > 0 && (
                        <div className="flex items-center gap-3 max-w-xs">
                            <div className="flex-1 h-1 overflow-hidden rounded-full bg-white/20">
                                <div
                                    className="h-full rounded-full bg-[#d5462f]"
                                    style={{ width: `${continueProgress}%` }}
                                />
                            </div>
                            <span className="text-xs text-white/50 shrink-0">{continueProgress}%</span>
                        </div>
                    )}

                    {/* CTAs */}
                    <div className="flex flex-wrap items-center gap-3 pt-1">
                        {ctaKind === 'download' ? (
                            <a
                                href={ctaUrl}
                                className="inline-flex items-center gap-2 rounded-full bg-white px-7 py-3 text-sm font-bold text-black hover:bg-white/90 transition shadow-lg"
                            >
                                <Play className="size-4 fill-black" />
                                {ctaLabel}
                            </a>
                        ) : (
                            <Link
                                href={ctaUrl}
                                className="inline-flex items-center gap-2 rounded-full bg-white px-7 py-3 text-sm font-bold text-black hover:bg-white/90 transition shadow-lg"
                            >
                                <Play className="size-4 fill-black" />
                                {ctaLabel}
                            </Link>
                        )}

                        {onInfoClick && continueLearning?.module && (
                            <button
                                onClick={onInfoClick}
                                className="inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/10 px-6 py-3 text-sm font-medium text-white hover:bg-white/20 transition backdrop-blur"
                            >
                                <Info className="size-4" />
                                Info Modul
                            </button>
                        )}
                    </div>
                </div>
            </div>
        </section>
    );
}

// ─── Main Page ────────────────────────────────────────────────────────────────

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
}) {
    const hasReloadedRef = useRef(false);
    const studentName = studentContext?.display_name ?? 'Student';
    const [showOnboarding, setShowOnboarding] = useState(false);
    const [selectedModule, setSelectedModule] = useState(null);

    // Check if onboarding should show
    useEffect(() => {
        const done = localStorage.getItem(ONBOARDING_KEY);
        if (!done) setShowOnboarding(true);
    }, []);

    // Reload fresh data once
    useEffect(() => {
        if (hasReloadedRef.current) return;
        hasReloadedRef.current = true;
        router.reload({
            only: ['availableModulesSection', 'progressSummary', 'nextStep', 'certificateMilestone', 'homeExperience'],
            preserveScroll: true,
            preserveState: true,
        });
    }, []);

    // Split modules into rows
    const allModules = availableModulesSection?.items ?? [];
    const inProgressModules = allModules.filter(m => m.status === 'active');
    const otherModules = allModules.filter(m => m.status !== 'active');

    // The module shown in hero's "Info Modul" button
    const heroModule = continueLearning?.module
        ? allModules.find(m => m.id === continueLearning.module?.id) ?? null
        : null;

    // Ebook row items shaped to look like module cards
    const ebookItems = ebookResourcesSection?.items ?? [];

    return (
        <AuthenticatedLayout studentVariant="immersive" studentContentClassName="pb-20">
            <Head title="Home" />

            {/* Onboarding overlay */}
            {showOnboarding && (
                <OnboardingOverlay onDone={() => setShowOnboarding(false)} />
            )}

            {/* Module detail modal */}
            {selectedModule && (
                <ModuleModal
                    module={selectedModule}
                    onClose={() => setSelectedModule(null)}
                />
            )}

            {/* ── Hero ─────────────────────────────────────────── */}
            <HeroSection
                homeExperience={homeExperience}
                continueLearning={continueLearning}
                studentName={studentName}
                onInfoClick={heroModule ? () => setSelectedModule(heroModule) : null}
            />

            {/* ── Content rows ──────────────────────────────────── */}
            <div className="relative z-10 -mt-10 space-y-10">

                {/* Row 1: In Progress */}
                {inProgressModules.length > 0 && (
                    <ModuleRow
                        title="Sedang Dipelajari"
                        modules={inProgressModules}
                        onCardClick={setSelectedModule}
                    />
                )}

                {/* Row 2: All / Available */}
                <ModuleRow
                    title={inProgressModules.length > 0 ? 'Semua Modul' : 'Mulai Dari Sini'}
                    modules={otherModules.length > 0 ? otherModules : allModules}
                    onCardClick={setSelectedModule}
                />

                {/* Row 3: Ebooks (if any) */}
                {ebookItems.length > 0 && (
                    <section className="space-y-4">
                        <div className="flex items-center justify-between px-4 sm:px-6 lg:px-10">
                            <h2 className="text-lg font-semibold text-white">
                                Sumber Belajar
                            </h2>
                            <Link
                                href={route('ebooks.index')}
                                className="text-xs text-white/45 hover:text-white/80 transition flex items-center gap-1"
                            >
                                Lihat Semua <ChevronRight className="size-3.5" />
                            </Link>
                        </div>
                        <div
                            className="flex gap-4 overflow-x-auto pb-2 px-4 sm:px-6 lg:px-10"
                            style={{ scrollbarWidth: 'none', msOverflowStyle: 'none' }}
                        >
                            {ebookItems.map((ebook) => (
                                <Link
                                    key={ebook.id}
                                    href={ebook.preview_url ?? route('ebooks.index')}
                                    className="group shrink-0 w-[180px] rounded-[20px] overflow-hidden border border-white/10 bg-[#120f0e] transition duration-300 hover:-translate-y-1 hover:border-white/25"
                                >
                                    <div className="relative aspect-[3/4] bg-[radial-gradient(circle_at_20%_18%,rgba(213,70,47,0.45),transparent_30%),linear-gradient(160deg,#2d1e18,#120f0e)] p-4 flex flex-col justify-between">
                                        <div className="flex items-center gap-1.5 text-[10px] uppercase tracking-[0.2em] text-white/55">
                                            <BookOpen className="size-3" />
                                            Ebook
                                        </div>
                                        <h3 className="text-sm font-semibold text-white leading-tight line-clamp-3">
                                            {ebook.title}
                                        </h3>
                                    </div>
                                    <div className="p-3">
                                        <span className="text-xs text-[#d5462f] group-hover:text-[#e2553d] transition flex items-center gap-1">
                                            Buka Preview <ArrowRight className="size-3" />
                                        </span>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </section>
                )}

                {/* Empty state */}
                {allModules.length === 0 && (
                    <div className="px-4 sm:px-6 lg:px-10">
                        <div className="rounded-[28px] border border-white/10 bg-white/[0.03] px-8 py-16 text-center">
                            <p className="text-white/50 text-sm">
                                Modul belum tersedia untuk tier akses kamu saat ini.
                            </p>
                            <p className="mt-2 text-white/30 text-xs">
                                Hubungi admin untuk informasi lebih lanjut.
                            </p>
                        </div>
                    </div>
                )}

                {/* Milestone strip (certificate + assignment) — subtle, not dominant */}
                {(certificateMilestone?.state === 'download_available' || assignmentMilestone?.state === 'approved') && (
                    <div className="px-4 sm:px-6 lg:px-10">
                        <div className="flex flex-wrap gap-3">
                            {certificateMilestone?.state === 'download_available' && (
                                <a
                                    href={certificateMilestone.cta_url ?? '#'}
                                    className="inline-flex items-center gap-2 rounded-full border border-emerald-400/25 bg-emerald-400/10 px-5 py-2.5 text-xs font-medium text-emerald-300 hover:bg-emerald-400/15 transition"
                                >
                                    <Download className="size-3.5" />
                                    {certificateMilestone.cta_label ?? 'Download Sertifikat'}
                                </a>
                            )}
                            {assignmentMilestone?.state === 'approved' && (
                                <div className="inline-flex items-center gap-2 rounded-full border border-emerald-400/25 bg-emerald-400/10 px-5 py-2.5 text-xs font-medium text-emerald-300">
                                    <CheckCircle2 className="size-3.5" />
                                    Assignment disetujui
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}