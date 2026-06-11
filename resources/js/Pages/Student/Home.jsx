import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { ChevronRight, Play, Search } from 'lucide-react';
import { useEffect, useState } from 'react';

function formatDurationParts(totalSeconds) {
    const safeSeconds = Math.max(0, Number(totalSeconds || 0));
    const hours = Math.floor(safeSeconds / 3600)
        .toString()
        .padStart(2, '0');
    const minutes = Math.floor((safeSeconds % 3600) / 60)
        .toString()
        .padStart(2, '0');
    const seconds = Math.floor(safeSeconds % 60)
        .toString()
        .padStart(2, '0');

    return { hours, minutes, seconds };
}

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
        accessTimeSummary?.running_total_access_duration_seconds ?? 0,
    );
    const heroPrimaryKind = homeExperience?.primary_cta_kind ?? 'link';
    const heroSecondaryKind = 'link';
    const continueEngineLabel = homeExperience?.state === 'journey_complete'
        ? 'Next: certificate and resources'
        : homeExperience?.state === 'new_student'
          ? 'Next: keep the first lesson simple'
          : homeExperience?.state === 'catalog_empty'
            ? 'Next: waiting for catalog access'
            : 'Next: assignment milestone';
    const secondaryDiscoveryItems = [
        {
            title: 'Explore the full module path',
            description:
                'Move through the full YogaFX catalog available in your current tier and revisit the modules that shape your learning rhythm.',
            href: route('modules.index'),
            label: 'Browse Modules',
        },
        {
            title: 'Open supporting resources',
            description:
                ebookResourcesSection?.items?.length
                    ? 'Keep your practice deepening with supporting ebooks and preview-first resources that stay close to your learning journey.'
                    : 'Your ebook library will appear here as soon as supporting resources are attached to this tier.',
            href: route('ebooks.index'),
            label: 'Open Ebooks',
        },
        {
            title: 'Review your current milestones',
            description:
                certificateMilestone?.state === 'download_available'
                    ? 'Your latest certificate is already ready, while assignment and certificate milestones stay visible in the same calm Home flow.'
                    : 'Assignment and certificate milestones stay visible here so you can understand where the larger YogaFX journey is heading next.',
            href:
                certificateMilestone?.cta_kind === 'download'
                    ? certificateMilestone?.cta_url
                    : route('modules.index'),
            label:
                certificateMilestone?.cta_kind === 'download'
                    ? 'Download Certificate'
                    : 'Review Journey',
            kind: certificateMilestone?.cta_kind === 'download' ? 'download' : 'link',
        },
    ];

    useEffect(() => {
        if (!accessTimeSummary?.currently_active || !accessTimeSummary?.active_session_login_at) {
            setRunningAccessSeconds(
                accessTimeSummary?.running_total_access_duration_seconds ?? 0,
            );

            return undefined;
        }

        const updateTimer = () => {
            const loginAt = new Date(
                accessTimeSummary.active_session_login_at,
            ).getTime();
            const elapsed = Math.max(
                0,
                Math.floor((Date.now() - loginAt) / 1000),
            );

            setRunningAccessSeconds(
                (accessTimeSummary.total_access_duration_seconds ?? 0) + elapsed,
            );
        };

        updateTimer();

        const interval = window.setInterval(updateTimer, 1000);

        return () => window.clearInterval(interval);
    }, [
        accessTimeSummary?.active_session_login_at,
        accessTimeSummary?.currently_active,
        accessTimeSummary?.running_total_access_duration_seconds,
        accessTimeSummary?.total_access_duration_seconds,
    ]);

    const runningAccessParts = formatDurationParts(runningAccessSeconds);

    return (
        <AuthenticatedLayout
            studentVariant="immersive"
            studentContentClassName="pb-16"
        >
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
                                        <Button
                                            asChild
                                            size="lg"
                                            className="rounded-full bg-[#d5462f] px-6 text-white shadow-[0_18px_50px_rgba(213,70,47,0.3)] hover:bg-[#e2553d]"
                                        >
                                            <a href={homeExperience?.primary_cta_url ?? '#'}>
                                                <Play className="mr-2 size-4 fill-current" />
                                                {homeExperience?.primary_cta_label ??
                                                    'Continue the Course'}
                                            </a>
                                        </Button>
                                    ) : (
                                        <Button
                                            asChild
                                            size="lg"
                                            className="rounded-full bg-[#d5462f] px-6 text-white shadow-[0_18px_50px_rgba(213,70,47,0.3)] hover:bg-[#e2553d]"
                                        >
                                            <Link href={homeExperience?.primary_cta_url ?? route('modules.index')}>
                                                <Play className="mr-2 size-4 fill-current" />
                                                {homeExperience?.primary_cta_label ??
                                                    'Continue the Course'}
                                            </Link>
                                        </Button>
                                    )}

                                    {heroSecondaryKind === 'download' ? (
                                        <Button
                                            asChild
                                            size="lg"
                                            variant="outline"
                                            className="rounded-full border-white/20 bg-white/5 px-6 text-white hover:bg-white/10 hover:text-white"
                                        >
                                            <a href={homeExperience?.secondary_cta_url ?? '#'}>
                                                {homeExperience?.secondary_cta_label ??
                                                    'Explore Modules'}
                                            </a>
                                        </Button>
                                    ) : (
                                        <Button
                                            asChild
                                            size="lg"
                                            variant="outline"
                                            className="rounded-full border-white/20 bg-white/5 px-6 text-white hover:bg-white/10 hover:text-white"
                                        >
                                            <Link href={homeExperience?.secondary_cta_url ?? route('modules.index')}>
                                                {homeExperience?.secondary_cta_label ??
                                                    'Explore Modules'}
                                            </Link>
                                        </Button>
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

                <section className="space-y-4">
                    <div className="flex items-center justify-between gap-4">
                        <div>
                            <p className="text-xs uppercase tracking-[0.24em] text-white/45">
                                {continueLearning?.eyebrow ?? 'Continue Watching'}
                            </p>
                            <h2 className="mt-2 text-2xl font-semibold tracking-tight text-white">
                                {continueLearning?.state === 'resume'
                                    ? 'Pick up where your learning paused'
                                    : continueLearning?.state === 'start'
                                      ? 'Start your first YogaFX lesson'
                                      : 'Continue Learning will appear here'}
                            </h2>
                        </div>
                        <span className="hidden text-sm text-white/45 md:inline">
                            Phase 12 active
                        </span>
                    </div>

                    <div className="grid gap-4 lg:grid-cols-[1.35fr_1fr]">
                        <div className="rounded-[28px] border border-white/10 bg-white/[0.04] p-5 backdrop-blur-sm">
                            <div className="flex h-full flex-col gap-4 rounded-[22px] border border-white/8 bg-[linear-gradient(135deg,rgba(255,255,255,0.08),rgba(255,255,255,0.02))] p-5">
                                <div className="relative aspect-[16/8] overflow-hidden rounded-[20px] bg-[radial-gradient(circle_at_30%_20%,_rgba(227,120,61,0.4),_transparent_28%),linear-gradient(140deg,_rgba(255,255,255,0.09),_rgba(255,255,255,0.02)),linear-gradient(180deg,_#3a2318_0%,_#17110f_100%)]">
                                    {continueLearning?.thumbnail_url && (
                                        <img
                                            src={continueLearning.thumbnail_url}
                                            alt={continueLearning.title}
                                            className="h-full w-full object-cover opacity-70"
                                        />
                                    )}
                                    <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-black/15 to-transparent" />
                                    <div className="absolute left-4 top-4 rounded-full border border-white/12 bg-black/35 px-3 py-1 text-xs uppercase tracking-[0.2em] text-white/70 backdrop-blur">
                                        {continueLearning?.status ?? 'Ready'}
                                    </div>
                                    {!continueLearning?.thumbnail_url && (
                                        <div className="absolute inset-x-4 bottom-4 rounded-2xl border border-white/10 bg-black/30 px-4 py-3 backdrop-blur">
                                            <p className="text-xs uppercase tracking-[0.2em] text-white/50">
                                                Visual placeholder
                                            </p>
                                            <p className="mt-2 text-sm font-medium text-white">
                                                YogaFX lesson artwork is not attached yet.
                                            </p>
                                        </div>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    <h3 className="text-lg font-medium text-white">
                                        {continueLearning?.title ?? 'Continue Learning'}
                                    </h3>
                                    <p className="text-sm leading-6 text-white/60">
                                        {continueLearning?.description ??
                                            'Your current lesson will appear here once Phase 3 is active.'}
                                    </p>
                                    {continueLearning?.module && continueLearning?.lesson && (
                                        <div className="flex flex-wrap items-center gap-x-3 gap-y-2 text-xs uppercase tracking-[0.2em] text-white/45">
                                            <span>{continueLearning.module.title}</span>
                                            <span className="h-1 w-1 rounded-full bg-white/25" />
                                            <span>
                                                Lesson {continueLearning.lesson.sort_order}
                                            </span>
                                        </div>
                                    )}
                                </div>
                                <div className="h-1.5 overflow-hidden rounded-full bg-white/10">
                                    <div
                                        className="h-full rounded-full bg-[#d5462f] transition-all"
                                        style={{ width: `${continueProgress}%` }}
                                    />
                                </div>
                                <div className="flex flex-wrap items-center gap-3">
                                    <Button
                                        asChild
                                        className="rounded-full bg-[#d5462f] px-5 text-white hover:bg-[#e2553d]"
                                    >
                                        <Link href={continueLearning?.cta_url ?? route('modules.index')}>
                                            {continueLearning?.state === 'resume' ? (
                                                <Play className="mr-2 size-4 fill-current" />
                                            ) : (
                                                <ChevronRight className="mr-2 size-4" />
                                            )}
                                            {continueLearning?.cta_label ?? 'Browse Modules'}
                                        </Link>
                                    </Button>

                                    {continueLearning?.module?.url && (
                                        <Button
                                            asChild
                                            variant="outline"
                                            className="rounded-full border-white/15 bg-white/5 text-white hover:bg-white/10 hover:text-white"
                                        >
                                            <Link href={continueLearning.module.url}>
                                                Open Module
                                            </Link>
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </div>

                        <div className="rounded-[28px] border border-white/10 bg-white/[0.04] p-5 backdrop-blur-sm">
                            <div className="flex h-full flex-col justify-between gap-6 rounded-[22px] border border-white/8 bg-black/15 p-5">
                                <div className="space-y-4">
                                    <div className="space-y-2">
                                        <p className="text-xs uppercase tracking-[0.22em] text-white/50">
                                            {sequentialAwareness?.eyebrow ?? 'Learning Sequence'}
                                        </p>
                                        <h3 className="text-xl font-semibold text-white">
                                            {sequentialAwareness?.title ??
                                                'Sequence awareness will appear here'}
                                        </h3>
                                        <p className="text-sm leading-6 text-white/60">
                                            {sequentialAwareness?.description ??
                                                'Home will explain the current lesson order here.'}
                                        </p>
                                    </div>

                                    <div className="flex flex-wrap items-center gap-3 text-xs uppercase tracking-[0.22em] text-white/55">
                                        <span className="rounded-full border border-white/12 bg-black/20 px-3 py-1">
                                            {sequentialAwareness?.status ?? 'Sequence guidance'}
                                        </span>
                                        <span className="rounded-full border border-white/12 bg-black/20 px-3 py-1">
                                            Guidance, not hard locking
                                        </span>
                                    </div>

                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div className="rounded-[20px] border border-white/10 bg-white/5 p-4">
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <p className="text-xs uppercase tracking-[0.2em] text-white/45">
                                                        Current lesson
                                                    </p>
                                                    <p className="mt-2 text-sm font-medium text-white">
                                                        {currentSequenceLesson?.title ??
                                                            'No current lesson yet'}
                                                    </p>
                                                </div>
                                                <span className="rounded-full border border-white/12 bg-black/20 px-3 py-1 text-[11px] uppercase tracking-[0.22em] text-white/65">
                                                    {currentSequenceStatus}
                                                </span>
                                            </div>
                                            <p className="mt-3 text-sm leading-6 text-white/58">
                                                {currentSequenceLesson
                                                    ? `Lesson ${currentSequenceLesson.sort_order} in ${currentSequenceLesson.module_title}`
                                                    : 'The active lesson in the sequence will appear here.'}
                                            </p>
                                        </div>

                                        <div className="rounded-[20px] border border-white/10 bg-white/5 p-4">
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <p className="text-xs uppercase tracking-[0.2em] text-white/45">
                                                        Next lesson
                                                    </p>
                                                    <p className="mt-2 text-sm font-medium text-white">
                                                        {nextSequenceLesson?.title ??
                                                            'No further accessible lesson'}
                                                    </p>
                                                </div>
                                                <span className="rounded-full border border-white/12 bg-black/20 px-3 py-1 text-[11px] uppercase tracking-[0.22em] text-white/65">
                                                    {nextSequenceStatus}
                                                </span>
                                            </div>
                                            <p className="mt-3 text-sm leading-6 text-white/58">
                                                {nextSequenceLesson
                                                    ? `Lesson ${nextSequenceLesson.sort_order} in ${nextSequenceLesson.module_title}`
                                                    : 'When the next lesson in sequence exists, Home will surface it here.'}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="space-y-3">
                                        <div className="rounded-[20px] border border-white/10 bg-white/5 p-4">
                                            <p className="text-xs uppercase tracking-[0.2em] text-white/45">
                                                Sequence rule
                                            </p>
                                            <p className="mt-3 text-sm font-medium text-white">
                                                {sequentialAwareness?.sequence_rule?.label ??
                                                    'Sequence guidance is not available yet.'}
                                            </p>
                                            <p className="mt-2 text-sm leading-6 text-white/58">
                                                {sequentialAwareness?.sequence_rule?.detail ??
                                                    'Home will explain the lesson order and next sequence rule here.'}
                                            </p>
                                        </div>

                                        {(sequentialAwareness?.supporting_rules ?? []).map((rule) => (
                                            <div
                                                key={rule.label}
                                                className="rounded-[20px] border border-white/10 bg-white/5 p-4"
                                            >
                                                <p className="text-sm font-medium text-white">
                                                    {rule.label}
                                                </p>
                                                <p className="mt-2 text-sm leading-6 text-white/58">
                                                    {rule.detail}
                                                </p>
                                            </div>
                                        ))}
                                    </div>
                                </div>

                                <Button
                                    type="button"
                                    variant="ghost"
                                    disabled
                                    className="justify-between rounded-full border border-white/12 bg-white/5 px-5 py-6 text-white/80 opacity-100 hover:bg-white/10 hover:text-white"
                                >
                                    {continueEngineLabel}
                                    <ChevronRight className="size-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </section>

                <section className="space-y-4">
                    <div className="flex items-center justify-between gap-4">
                        <div>
                            <p className="text-xs uppercase tracking-[0.24em] text-white/45">
                                {progressSummary?.eyebrow ?? 'Learning Progress'}
                            </p>
                            <h2 className="mt-2 text-2xl font-semibold tracking-tight text-white">
                                {progressSummary?.title ?? 'Your progress summary will appear here'}
                            </h2>
                        </div>
                        <span className="hidden text-sm text-white/45 md:inline">
                            Lightweight, not admin-style
                        </span>
                    </div>

                    <div className="grid gap-4 xl:grid-cols-[1.2fr_0.8fr]">
                        <div className="rounded-[28px] border border-white/10 bg-white/[0.04] p-5 backdrop-blur-sm">
                            <div className="grid gap-4 md:grid-cols-[1.1fr_repeat(2,minmax(0,1fr))]">
                                <div className="rounded-[24px] border border-white/8 bg-[linear-gradient(145deg,rgba(214,90,52,0.18),rgba(255,255,255,0.03))] p-5">
                                    <p className="text-xs uppercase tracking-[0.22em] text-[#f2d9c8]">
                                        Overall progress
                                    </p>
                                    <div className="mt-6 flex items-end gap-3">
                                        <span className="text-5xl font-semibold tracking-[-0.04em] text-white">
                                            {overallProgress}%
                                        </span>
                                        <span className="pb-2 text-sm text-white/55">
                                            completed
                                        </span>
                                    </div>
                                    <div className="mt-5 h-2 overflow-hidden rounded-full bg-black/30">
                                        <div
                                            className="h-full rounded-full bg-[#d5462f] transition-all"
                                            style={{ width: `${overallProgress}%` }}
                                        />
                                    </div>
                                    <p className="mt-4 text-sm leading-6 text-white/60">
                                        {progressSummary?.status ??
                                            'Progress will update as completed lessons grow.'}
                                    </p>
                                </div>

                                <div className="rounded-[24px] border border-white/8 bg-black/15 p-5">
                                    <p className="text-xs uppercase tracking-[0.22em] text-white/48">
                                        Modules finished
                                    </p>
                                    <div className="mt-8 text-4xl font-semibold tracking-[-0.04em] text-white">
                                        {progressSummary?.modules_completed ?? 0}
                                        <span className="ml-2 text-base font-medium text-white/40">
                                            / {progressSummary?.modules_total ?? 0}
                                        </span>
                                    </div>
                                    <p className="mt-4 text-sm leading-6 text-white/58">
                                        Completed modules reflect accessible lessons that have all
                                        been marked done.
                                    </p>
                                </div>

                                <div className="rounded-[24px] border border-white/8 bg-black/15 p-5">
                                    <p className="text-xs uppercase tracking-[0.22em] text-white/48">
                                        Lessons finished
                                    </p>
                                    <div className="mt-8 text-4xl font-semibold tracking-[-0.04em] text-white">
                                        {progressSummary?.lessons_completed ?? 0}
                                        <span className="ml-2 text-base font-medium text-white/40">
                                            / {progressSummary?.lessons_total ?? 0}
                                        </span>
                                    </div>
                                    <p className="mt-4 text-sm leading-6 text-white/58">
                                        Lesson completion is the main source for the Home progress
                                        summary in this phase.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="rounded-[28px] border border-white/10 bg-white/[0.04] p-5 backdrop-blur-sm">
                            <div className="flex h-full flex-col justify-between gap-6 rounded-[22px] border border-white/8 bg-black/15 p-5">
                                <div className="space-y-2">
                                    <p className="text-xs uppercase tracking-[0.22em] text-white/50">
                                        Momentum summary
                                    </p>
                                    <h3 className="text-xl font-semibold text-white">
                                        {progressSummary?.state === 'ready'
                                            ? 'Your completed lessons now shape the Home overview'
                                            : 'Home is ready to show progress as soon as learning begins'}
                                    </h3>
                                    <p className="text-sm leading-6 text-white/60">
                                        {progressSummary?.description ??
                                            'This area keeps the summary human and calm, so Home stays focused on motivation instead of reporting.'}
                                    </p>
                                </div>

                                <div className="rounded-[22px] border border-white/10 bg-white/5 p-4">
                                    <p className="text-xs uppercase tracking-[0.2em] text-white/45">
                                        Stage 12 scope
                                    </p>
                                    <p className="mt-3 text-sm leading-6 text-white/60">
                                        The final Home order is now aligned to the approved
                                        product priority, sequence guidance is folded into
                                        Continue Learning, and the page is tuned to feel calmer
                                        across mobile, tablet, and desktop.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section className="space-y-4">
                    <div className="flex items-center justify-between gap-4">
                        <div>
                            <p className="text-xs uppercase tracking-[0.24em] text-white/45">
                                {nextStep?.eyebrow ?? 'Recommended Next Step'}
                            </p>
                            <h2 className="mt-2 text-2xl font-semibold tracking-tight text-white">
                                {nextStep?.title ?? 'Your next step will appear here'}
                            </h2>
                        </div>
                        <span className="hidden text-sm text-white/45 md:inline">
                            One clear learning direction
                        </span>
                    </div>

                    <div className="grid gap-4 lg:grid-cols-[1.15fr_0.85fr]">
                        <div className="rounded-[28px] border border-white/10 bg-white/[0.04] p-5 backdrop-blur-sm">
                            <div className="flex h-full flex-col justify-between gap-6 rounded-[22px] border border-white/8 bg-[linear-gradient(145deg,rgba(214,90,52,0.18),rgba(255,255,255,0.03))] p-5">
                                <div className="space-y-4">
                                    <div className="flex flex-wrap items-center gap-3 text-xs uppercase tracking-[0.22em] text-white/55">
                                        <span className="rounded-full border border-white/12 bg-black/20 px-3 py-1">
                                            {nextStep?.status ?? 'Ready now'}
                                        </span>
                                        <span className="rounded-full border border-white/12 bg-black/20 px-3 py-1">
                                            {nextStep?.kind
                                                ? nextStep.kind.replace(/_/g, ' ')
                                                : 'recommended step'}
                                        </span>
                                    </div>

                                    <p className="max-w-2xl text-sm leading-7 text-white/68 sm:text-base">
                                        {nextStep?.description ??
                                            'Home will highlight the strongest next learning action here.'}
                                    </p>

                                    {(nextStep?.module || nextStep?.lesson) && (
                                        <div className="flex flex-wrap items-center gap-x-3 gap-y-2 text-xs uppercase tracking-[0.2em] text-white/45">
                                            {nextStep?.module?.title && (
                                                <span>{nextStep.module.title}</span>
                                            )}
                                            {nextStep?.module?.title && nextStep?.lesson?.sort_order && (
                                                <span className="h-1 w-1 rounded-full bg-white/25" />
                                            )}
                                            {nextStep?.lesson?.sort_order && (
                                                <span>Lesson {nextStep.lesson.sort_order}</span>
                                            )}
                                            {nextStep?.lesson?.title && (
                                                <>
                                                    <span className="h-1 w-1 rounded-full bg-white/25" />
                                                    <span>{nextStep.lesson.title}</span>
                                                </>
                                            )}
                                        </div>
                                    )}
                                </div>

                                <div className="flex flex-wrap items-center gap-3">
                                    <Button
                                        asChild
                                        className="rounded-full bg-[#d5462f] px-5 text-white hover:bg-[#e2553d]"
                                    >
                                        <Link href={nextStep?.cta_url ?? route('modules.index')}>
                                            <ChevronRight className="mr-2 size-4" />
                                            {nextStep?.cta_label ?? 'Browse Modules'}
                                        </Link>
                                    </Button>

                                    {nextStep?.module?.url && (
                                        <Button
                                            asChild
                                            variant="outline"
                                            className="rounded-full border-white/15 bg-white/5 text-white hover:bg-white/10 hover:text-white"
                                        >
                                            <Link href={nextStep.module.url}>Open Module</Link>
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </div>

                        <div className="rounded-[28px] border border-white/10 bg-white/[0.04] p-5 backdrop-blur-sm">
                            <div className="flex h-full flex-col justify-between gap-6 rounded-[22px] border border-white/8 bg-black/15 p-5">
                                <div className="space-y-2">
                                    <p className="text-xs uppercase tracking-[0.22em] text-white/50">
                                        Recommendation engine
                                    </p>
                                    <h3 className="text-xl font-semibold text-white">
                                        {nextStep?.kind === 'continue_lesson'
                                            ? 'Home keeps the student on the current learning track'
                                            : nextStep?.kind === 'next_lesson'
                                              ? 'Home can now point directly to the next unfinished lesson'
                                              : nextStep?.kind === 'start_lesson'
                                                ? 'Home gives new students a safe first step'
                                                : nextStep?.kind === 'explore_modules'
                                                  ? 'Home falls back to discovery when no active lesson is available'
                                                  : 'Home is ready to guide the next action'}
                                    </h3>
                                    <p className="text-sm leading-6 text-white/60">
                                        The current recommendation logic prioritizes paths that
                                        already have safe student-side entry points today:
                                        continue lesson, start lesson, next available lesson, or
                                        browse modules.
                                    </p>
                                </div>

                                <div className="rounded-[22px] border border-white/10 bg-white/5 p-4">
                                    <p className="text-xs uppercase tracking-[0.2em] text-white/45">
                                        Current boundary
                                    </p>
                                    <p className="mt-3 text-sm leading-6 text-white/60">
                                        Assessment recommendation still stays deferred until its
                                        student-side flow becomes active. Assignment and
                                        certificate milestones now appear below as visibility
                                        layers, while this card keeps the main next step focused
                                        on safe learning routes.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section className="space-y-4">
                    <div className="flex items-center justify-between gap-4">
                        <div>
                            <p className="text-xs uppercase tracking-[0.24em] text-white/45">
                                {availableModulesSection?.eyebrow ?? 'Available Modules'}
                            </p>
                            <h2 className="mt-2 text-2xl font-semibold tracking-tight text-white">
                                {availableModulesSection?.title ?? 'Your module catalog will appear here'}
                            </h2>
                        </div>
                        <Button
                            asChild
                            variant="outline"
                            className="hidden rounded-full border-white/15 bg-white/5 text-white hover:bg-white/10 hover:text-white md:inline-flex"
                        >
                            <Link href={route('modules.index')}>See All Modules</Link>
                        </Button>
                    </div>

                    <div className="rounded-[28px] border border-white/10 bg-white/[0.04] p-5 backdrop-blur-sm">
                        <div className="flex flex-col gap-6 rounded-[22px] border border-white/8 bg-black/15 p-5">
                            <div className="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                                <div className="max-w-3xl space-y-2">
                                    <p className="text-sm leading-7 text-white/60">
                                        {availableModulesSection?.description ??
                                            'Home will show the modules available in the current student tier here.'}
                                    </p>
                                </div>

                                <div className="grid gap-3 sm:grid-cols-3">
                                    {[
                                        {
                                            label: availableModulesSection?.summary?.total ?? 0,
                                            eyebrow: 'Modules',
                                        },
                                        {
                                            label: availableModulesSection?.summary?.active ?? 0,
                                            eyebrow: 'In progress',
                                        },
                                        {
                                            label: availableModulesSection?.summary?.completed ?? 0,
                                            eyebrow: 'Completed',
                                        },
                                    ].map((item) => (
                                        <div
                                            key={item.eyebrow}
                                            className="rounded-[20px] border border-white/10 bg-white/5 px-4 py-4"
                                        >
                                            <p className="text-xs uppercase tracking-[0.22em] text-white/45">
                                                {item.eyebrow}
                                            </p>
                                            <div className="mt-3 text-3xl font-semibold tracking-[-0.04em] text-white">
                                                {item.label}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            {availableModulesSection?.items?.length ? (
                                <div className="flex gap-4 overflow-x-auto pb-2 md:grid md:grid-cols-2 md:overflow-visible md:pb-0 xl:grid-cols-3">
                                    {availableModulesSection.items.map((module, index) => (
                                        <div
                                            key={module.id}
                                            className="group min-w-[280px] snap-start rounded-[28px] border border-white/10 bg-[#120f0e] p-4 transition duration-300 hover:-translate-y-1 hover:border-white/15 md:min-w-0"
                                        >
                                            <div className="flex h-full flex-col gap-4 rounded-[22px] border border-white/8 bg-black/20 p-4">
                                                <div className="relative aspect-[16/10] overflow-hidden rounded-[20px] bg-[radial-gradient(circle_at_20%_18%,_rgba(214,90,52,0.4),_transparent_30%),linear-gradient(160deg,_#2d1e18_0%,_#120f0e_100%)]">
                                                    {module.thumbnail_url && (
                                                        <img
                                                            src={module.thumbnail_url}
                                                            alt={module.title}
                                                            className="h-full w-full object-cover transition duration-500 group-hover:scale-[1.04]"
                                                        />
                                                    )}
                                                    {!module.thumbnail_url && (
                                                        <div className="absolute inset-0 flex items-center justify-center px-6 text-center">
                                                            <div>
                                                                <p className="text-xs uppercase tracking-[0.22em] text-white/45">
                                                                    Artwork pending
                                                                </p>
                                                                <p className="mt-3 text-lg font-medium text-white/80">
                                                                    YogaFX module cover will appear here.
                                                                </p>
                                                            </div>
                                                        </div>
                                                    )}
                                                    <div className="absolute inset-0 bg-gradient-to-t from-black/85 via-black/20 to-transparent" />
                                                    <div className="absolute left-4 top-4 flex flex-wrap gap-2">
                                                        <span
                                                            className={[
                                                                'rounded-full border px-3 py-1 text-[11px] uppercase tracking-[0.22em] backdrop-blur',
                                                                module.status === 'completed'
                                                                    ? 'border-emerald-400/30 bg-emerald-400/15 text-emerald-200'
                                                                    : module.status === 'active'
                                                                      ? 'border-[#d5462f]/35 bg-[#d5462f]/20 text-[#ffd7cf]'
                                                                      : 'border-white/15 bg-black/30 text-white/70',
                                                            ].join(' ')}
                                                        >
                                                            {module.status_label}
                                                        </span>
                                                        <span className="rounded-full border border-white/12 bg-black/30 px-3 py-1 text-[11px] uppercase tracking-[0.22em] text-white/70 backdrop-blur">
                                                            Module {module.sort_order}
                                                        </span>
                                                    </div>
                                                    <div className="absolute bottom-4 left-4 right-4">
                                                        <p className="text-xs uppercase tracking-[0.2em] text-white/50">
                                                            {module.lesson_count} lessons
                                                        </p>
                                                        <h3 className="mt-2 text-xl font-semibold leading-tight text-white">
                                                            {module.title}
                                                        </h3>
                                                    </div>
                                                </div>

                                                <div className="space-y-4">
                                                    <div className="flex items-center justify-between gap-3 text-sm text-white/55">
                                                        <span>
                                                            {module.completed_lessons} of {module.lesson_count} lessons completed
                                                        </span>
                                                        <span>{module.progress_percentage}%</span>
                                                    </div>

                                                    <div className="h-1.5 overflow-hidden rounded-full bg-white/10">
                                                        <div
                                                            className={[
                                                                'h-full rounded-full transition-all',
                                                                module.status === 'completed'
                                                                    ? 'bg-emerald-400'
                                                                    : 'bg-[#d5462f]',
                                                            ].join(' ')}
                                                            style={{
                                                                width: `${module.progress_percentage}%`,
                                                            }}
                                                        />
                                                    </div>

                                                    <div className="flex items-center justify-between gap-3">
                                                        <p className="text-sm leading-6 text-white/58">
                                                            {module.status === 'completed'
                                                                ? 'This module is complete and ready to review anytime.'
                                                                : module.status === 'active'
                                                                  ? 'This is your current learning track and is ready to continue.'
                                                                  : 'This module is unlocked in your tier and ready to explore.'}
                                                        </p>
                                                        <span className="hidden text-[11px] uppercase tracking-[0.22em] text-white/30 xl:inline">
                                                            #{index + 1}
                                                        </span>
                                                    </div>
                                                </div>

                                                <div className="flex flex-wrap items-center gap-3 pt-1">
                                                    <Button
                                                        asChild
                                                        className="rounded-full bg-[#d5462f] px-5 text-white hover:bg-[#e2553d]"
                                                    >
                                                        <Link href={module.cta_url}>
                                                            {module.cta_label}
                                                        </Link>
                                                    </Button>

                                                    <Button
                                                        asChild
                                                        variant="outline"
                                                        className="rounded-full border-white/15 bg-white/5 text-white hover:bg-white/10 hover:text-white"
                                                    >
                                                        <Link href={route('modules.index')}>
                                                            Browse Catalog
                                                        </Link>
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="rounded-[24px] border border-dashed border-white/12 bg-black/20 px-5 py-8">
                                    <p className="text-sm leading-7 text-white/60">
                                        {availableModulesSection?.description ??
                                            'No module is available yet for this student tier.'}
                                    </p>
                                    <div className="mt-4">
                                        <Button
                                            asChild
                                            className="rounded-full bg-[#d5462f] px-5 text-white hover:bg-[#e2553d]"
                                        >
                                            <Link href={route('modules.index')}>Open Modules</Link>
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </section>

                <section className="space-y-4">
                    <div className="flex items-center justify-between gap-4">
                        <div>
                            <p className="text-xs uppercase tracking-[0.24em] text-white/45">
                                {assignmentMilestone?.eyebrow ?? 'Assignment Milestone'}
                            </p>
                            <h2 className="mt-2 text-2xl font-semibold tracking-tight text-white">
                                {assignmentMilestone?.title ??
                                    'Assignment milestone will appear here'}
                            </h2>
                        </div>
                        <span className="hidden text-sm text-white/45 md:inline">
                            Milestone visibility first
                        </span>
                    </div>

                    <div className="grid gap-4 xl:grid-cols-[1.15fr_0.85fr]">
                        <div className="rounded-[28px] border border-white/10 bg-white/[0.04] p-5 backdrop-blur-sm">
                            <div className="flex h-full flex-col gap-6 rounded-[22px] border border-white/8 bg-[linear-gradient(145deg,rgba(214,90,52,0.16),rgba(255,255,255,0.03))] p-5">
                                <div className="space-y-4">
                                    <div className="flex flex-wrap items-center gap-3 text-xs uppercase tracking-[0.22em] text-white/55">
                                        <span
                                            className={[
                                                'rounded-full border px-3 py-1',
                                                assignmentMilestone?.state === 'approved'
                                                    ? 'border-emerald-400/30 bg-emerald-400/15 text-emerald-200'
                                                    : assignmentMilestone?.state === 'rejected'
                                                      ? 'border-rose-400/30 bg-rose-400/15 text-rose-200'
                                                      : assignmentMilestone?.state === 'under_review'
                                                        ? 'border-amber-300/30 bg-amber-300/15 text-amber-100'
                                                        : assignmentMilestone?.state === 'not_available'
                                                          ? 'border-white/15 bg-black/25 text-white/65'
                                                          : 'border-[#d5462f]/35 bg-[#d5462f]/18 text-[#ffd7cf]',
                                            ].join(' ')}
                                        >
                                            {assignmentMilestone?.status ?? 'Assignment tracked'}
                                        </span>
                                        <span className="rounded-full border border-white/12 bg-black/20 px-3 py-1">
                                            {assignmentMilestone?.eligibility_label ??
                                                'Tier eligibility pending'}
                                        </span>
                                    </div>

                                    <p className="max-w-2xl text-sm leading-7 text-white/68 sm:text-base">
                                        {assignmentMilestone?.description ??
                                            'Home will explain the assignment milestone here.'}
                                    </p>
                                </div>

                                <div className="grid gap-4 md:grid-cols-3">
                                    {(assignmentMilestone?.checklist ?? []).map((item) => (
                                        <div
                                            key={item.label}
                                            className="rounded-[24px] border border-white/10 bg-black/20 p-5"
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <p className="text-sm font-medium text-white">
                                                    {item.label}
                                                </p>
                                                <span className="rounded-full border border-white/12 bg-white/5 px-3 py-1 text-[11px] uppercase tracking-[0.22em] text-white/65">
                                                    {item.status}
                                                </span>
                                            </div>
                                            <p className="mt-3 text-sm leading-6 text-white/58">
                                                {item.detail}
                                            </p>
                                        </div>
                                    ))}
                                </div>

                                <div className="flex flex-wrap items-center gap-3">
                                    <Button
                                        asChild
                                        className="rounded-full bg-[#d5462f] px-5 text-white hover:bg-[#e2553d]"
                                    >
                                        <Link href={assignmentMilestone?.cta_url ?? route('modules.index')}>
                                            <ChevronRight className="mr-2 size-4" />
                                            {assignmentMilestone?.cta_label ?? 'Browse Modules'}
                                        </Link>
                                    </Button>

                                    <Button
                                        asChild
                                        variant="outline"
                                        className="rounded-full border-white/15 bg-white/5 text-white hover:bg-white/10 hover:text-white"
                                    >
                                        <Link href={route('modules.index')}>Open Learning Catalog</Link>
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <div className="rounded-[28px] border border-white/10 bg-white/[0.04] p-5 backdrop-blur-sm">
                            <div className="flex h-full flex-col justify-between gap-6 rounded-[22px] border border-white/8 bg-black/15 p-5">
                                <div className="space-y-4">
                                    <div className="space-y-2">
                                        <p className="text-xs uppercase tracking-[0.22em] text-white/50">
                                            Assignment context
                                        </p>
                                        <h3 className="text-xl font-semibold text-white">
                                            Home keeps the milestone visible without inventing a dead
                                            end
                                        </h3>
                                    </div>

                                    <div className="rounded-[22px] border border-white/10 bg-white/5 p-4">
                                        <p className="text-xs uppercase tracking-[0.2em] text-white/45">
                                            Latest submission
                                        </p>
                                        <p className="mt-3 text-sm leading-6 text-white/60">
                                            {assignmentMilestone?.latest_submission_at
                                                ? assignmentMilestone.latest_submission_at
                                                : 'No recorded submission timestamp yet.'}
                                        </p>
                                    </div>

                                    <div className="rounded-[22px] border border-white/10 bg-white/5 p-4">
                                        <p className="text-xs uppercase tracking-[0.2em] text-white/45">
                                            Latest feedback
                                        </p>
                                        <p className="mt-3 text-sm font-medium text-white">
                                            {assignmentMilestone?.latest_feedback?.status ??
                                                'No feedback yet'}
                                        </p>
                                        <p className="mt-2 text-sm leading-6 text-white/60">
                                            {assignmentMilestone?.latest_feedback?.message ??
                                                'Feedback from assignment review will appear here when it exists.'}
                                        </p>
                                    </div>
                                </div>

                                <div className="rounded-[22px] border border-white/10 bg-white/5 p-4">
                                    <p className="text-xs uppercase tracking-[0.2em] text-white/45">
                                        Current boundary
                                    </p>
                                    <p className="mt-3 text-sm leading-6 text-white/60">
                                        {assignmentMilestone?.support_note ??
                                            'Home keeps assignment visible as a milestone, but it does not open a student submission flow that is not active yet.'}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section className="space-y-4">
                    <div className="flex items-center justify-between gap-4">
                        <div>
                            <p className="text-xs uppercase tracking-[0.24em] text-white/45">
                                {certificateMilestone?.eyebrow ?? 'Certificate Milestone'}
                            </p>
                            <h2 className="mt-2 text-2xl font-semibold tracking-tight text-white">
                                {certificateMilestone?.title ??
                                    'Certificate milestone will appear here'}
                            </h2>
                        </div>
                        <span className="hidden text-sm text-white/45 md:inline">
                            Major journey milestone
                        </span>
                    </div>

                    <div className="grid gap-4 xl:grid-cols-[1.15fr_0.85fr]">
                        <div className="rounded-[28px] border border-white/10 bg-white/[0.04] p-5 backdrop-blur-sm">
                            <div className="flex h-full flex-col gap-6 rounded-[22px] border border-white/8 bg-[linear-gradient(145deg,rgba(214,90,52,0.16),rgba(255,255,255,0.03))] p-5">
                                <div className="space-y-4">
                                    <div className="flex flex-wrap items-center gap-3 text-xs uppercase tracking-[0.22em] text-white/55">
                                        <span
                                            className={[
                                                'rounded-full border px-3 py-1',
                                                certificateMilestone?.state === 'download_available'
                                                    ? 'border-emerald-400/30 bg-emerald-400/15 text-emerald-200'
                                                    : certificateMilestone?.state === 'ready'
                                                      ? 'border-amber-300/30 bg-amber-300/15 text-amber-100'
                                                      : certificateMilestone?.state === 'not_available'
                                                        ? 'border-white/15 bg-black/25 text-white/65'
                                                        : 'border-[#d5462f]/35 bg-[#d5462f]/18 text-[#ffd7cf]',
                                            ].join(' ')}
                                        >
                                            {certificateMilestone?.status ?? 'Certificate tracked'}
                                        </span>
                                        <span className="rounded-full border border-white/12 bg-black/20 px-3 py-1">
                                            {certificateMilestone?.eligibility_label ??
                                                'Tier eligibility pending'}
                                        </span>
                                    </div>

                                    <p className="max-w-2xl text-sm leading-7 text-white/68 sm:text-base">
                                        {certificateMilestone?.description ??
                                            'Home will explain the certificate milestone here.'}
                                    </p>
                                </div>

                                <div className="grid gap-4 md:grid-cols-3">
                                    {(certificateMilestone?.milestones ?? []).map((item) => (
                                        <div
                                            key={item.label}
                                            className="rounded-[24px] border border-white/10 bg-black/20 p-5"
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <p className="text-sm font-medium text-white">
                                                    {item.label}
                                                </p>
                                                <span className="rounded-full border border-white/12 bg-white/5 px-3 py-1 text-[11px] uppercase tracking-[0.22em] text-white/65">
                                                    {item.status}
                                                </span>
                                            </div>
                                            <p className="mt-3 text-sm leading-6 text-white/58">
                                                {item.detail}
                                            </p>
                                        </div>
                                    ))}
                                </div>

                                <div className="flex flex-wrap items-center gap-3">
                                    {certificateMilestone?.cta_kind === 'download' ? (
                                        <Button
                                            asChild
                                            className="rounded-full bg-[#d5462f] px-5 text-white hover:bg-[#e2553d]"
                                        >
                                            <a href={certificateMilestone?.cta_url ?? '#'}>
                                                <ChevronRight className="mr-2 size-4" />
                                                {certificateMilestone?.cta_label ??
                                                    'Download Latest Certificate'}
                                            </a>
                                        </Button>
                                    ) : (
                                        <Button
                                            asChild
                                            className="rounded-full bg-[#d5462f] px-5 text-white hover:bg-[#e2553d]"
                                        >
                                            <Link href={certificateMilestone?.cta_url ?? route('modules.index')}>
                                                <ChevronRight className="mr-2 size-4" />
                                                {certificateMilestone?.cta_label ?? 'Browse Modules'}
                                            </Link>
                                        </Button>
                                    )}

                                    <Button
                                        asChild
                                        variant="outline"
                                        className="rounded-full border-white/15 bg-white/5 text-white hover:bg-white/10 hover:text-white"
                                    >
                                        <Link href={route('modules.index')}>Open Learning Catalog</Link>
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <div className="rounded-[28px] border border-white/10 bg-white/[0.04] p-5 backdrop-blur-sm">
                            <div className="flex h-full flex-col justify-between gap-6 rounded-[22px] border border-white/8 bg-black/15 p-5">
                                <div className="space-y-4">
                                    <div className="space-y-2">
                                        <p className="text-xs uppercase tracking-[0.22em] text-white/50">
                                            Certificate context
                                        </p>
                                        <h3 className="text-xl font-semibold text-white">
                                            Home makes certificate status visible without needing a
                                            dedicated page
                                        </h3>
                                    </div>

                                    <div className="rounded-[22px] border border-white/10 bg-white/5 p-4">
                                        <p className="text-xs uppercase tracking-[0.2em] text-white/45">
                                            Latest certificate
                                        </p>
                                        <p className="mt-3 text-sm font-medium text-white">
                                            {certificateMilestone?.latest_certificate?.type_label ??
                                                'No generated certificate yet'}
                                        </p>
                                        <p className="mt-2 text-sm leading-6 text-white/60">
                                            {certificateMilestone?.latest_certificate
                                                ? `Version ${certificateMilestone.latest_certificate.version}, generated ${certificateMilestone.latest_certificate.generated_at}`
                                                : 'When YogaFX generates a certificate record, the latest file details will appear here.'}
                                        </p>
                                    </div>
                                </div>

                                <div className="rounded-[22px] border border-white/10 bg-white/5 p-4">
                                    <p className="text-xs uppercase tracking-[0.2em] text-white/45">
                                        Current boundary
                                    </p>
                                    <p className="mt-3 text-sm leading-6 text-white/60">
                                        {certificateMilestone?.support_note ??
                                            'Home surfaces certificate milestone directly in the dashboard while the full student certificate area remains out of scope.'}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section className="space-y-4">
                    <div className="flex items-center justify-between gap-4">
                        <div>
                            <p className="text-xs uppercase tracking-[0.24em] text-white/45">
                                {ebookResourcesSection?.eyebrow ?? 'Ebooks & Resources'}
                            </p>
                            <h2 className="mt-2 text-2xl font-semibold tracking-tight text-white">
                                {ebookResourcesSection?.title ??
                                    'Your supporting resources will appear here'}
                            </h2>
                        </div>
                        <Button
                            asChild
                            variant="outline"
                            className="hidden rounded-full border-white/15 bg-white/5 text-white hover:bg-white/10 hover:text-white md:inline-flex"
                        >
                            <Link href={route('ebooks.index')}>See All Ebooks</Link>
                        </Button>
                    </div>

                    <div className="rounded-[28px] border border-white/10 bg-white/[0.04] p-5 backdrop-blur-sm">
                        <div className="flex flex-col gap-6 rounded-[22px] border border-white/8 bg-black/15 p-5">
                            <div className="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                                <div className="max-w-3xl space-y-2">
                                    <p className="text-sm leading-7 text-white/60">
                                        {ebookResourcesSection?.description ??
                                            'Home will show your supporting ebook resources here.'}
                                    </p>
                                </div>

                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div className="rounded-[20px] border border-white/10 bg-white/5 px-4 py-4">
                                        <p className="text-xs uppercase tracking-[0.22em] text-white/45">
                                            Resources
                                        </p>
                                        <div className="mt-3 text-3xl font-semibold tracking-[-0.04em] text-white">
                                            {ebookResourcesSection?.summary?.total ?? 0}
                                        </div>
                                    </div>

                                    <div className="rounded-[20px] border border-white/10 bg-white/5 px-4 py-4">
                                        <p className="text-xs uppercase tracking-[0.22em] text-white/45">
                                            Tier access
                                        </p>
                                        <div className="mt-3 text-lg font-semibold tracking-[-0.03em] text-white">
                                            {ebookResourcesSection?.summary?.tier_name ??
                                                'Tier pending'}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {ebookResourcesSection?.items?.length ? (
                                <div className="flex gap-4 overflow-x-auto pb-2 md:grid md:grid-cols-2 md:overflow-visible md:pb-0 xl:grid-cols-3">
                                    {ebookResourcesSection.items.map((ebook, index) => (
                                        <div
                                            key={ebook.id}
                                            className="group min-w-[280px] snap-start rounded-[28px] border border-white/10 bg-[#120f0e] p-4 transition duration-300 hover:-translate-y-1 hover:border-white/15 md:min-w-0"
                                        >
                                            <div className="flex h-full flex-col gap-4 rounded-[22px] border border-white/8 bg-black/20 p-4">
                                                <div className="relative aspect-[4/5] overflow-hidden rounded-[22px] bg-[radial-gradient(circle_at_20%_18%,_rgba(214,90,52,0.45),_transparent_30%),linear-gradient(160deg,_#2d1e18_0%,_#120f0e_100%)]">
                                                    <div className="absolute inset-0 bg-[linear-gradient(180deg,rgba(255,255,255,0.12),rgba(255,255,255,0.02))]" />
                                                    <div className="absolute inset-x-4 top-4 flex items-center justify-between gap-3">
                                                        <span className="rounded-full border border-white/12 bg-black/30 px-3 py-1 text-[11px] uppercase tracking-[0.22em] text-white/70 backdrop-blur">
                                                            {ebook.eyebrow}
                                                        </span>
                                                        <span className="rounded-full border border-white/12 bg-black/30 px-3 py-1 text-[11px] uppercase tracking-[0.22em] text-white/70 backdrop-blur">
                                                            {ebook.format_label}
                                                        </span>
                                                    </div>
                                                    <div className="absolute bottom-4 left-4 right-4">
                                                        <p className="text-xs uppercase tracking-[0.2em] text-white/50">
                                                            Resource #{index + 1}
                                                        </p>
                                                        <h3 className="mt-3 text-2xl font-semibold leading-tight text-white">
                                                            {ebook.title}
                                                        </h3>
                                                        <p className="mt-3 text-sm leading-6 text-white/58">
                                                            {ebook.file_name}
                                                        </p>
                                                    </div>
                                                </div>

                                                <div className="space-y-4">
                                                    <p className="text-sm leading-6 text-white/58">
                                                        {ebook.description}
                                                    </p>

                                                    <div className="flex flex-wrap items-center gap-3 pt-1">
                                                        <Button
                                                            asChild
                                                            className="rounded-full bg-[#d5462f] px-5 text-white hover:bg-[#e2553d]"
                                                        >
                                                            <Link href={ebook.preview_url}>Open Preview</Link>
                                                        </Button>

                                                        <Button
                                                            asChild
                                                            variant="outline"
                                                            className="rounded-full border-white/15 bg-white/5 text-white hover:bg-white/10 hover:text-white"
                                                        >
                                                            <a href={ebook.download_url}>Download</a>
                                                        </Button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="rounded-[24px] border border-dashed border-white/12 bg-black/20 px-5 py-8">
                                    <p className="text-sm leading-7 text-white/60">
                                        {ebookResourcesSection?.description ??
                                            'No supporting ebook is available for this student tier yet.'}
                                    </p>
                                    <p className="mt-3 text-sm leading-6 text-white/50">
                                        {ebookResourcesSection?.support_note ??
                                            'Supporting resources stay optional so Home remains focused on the core learning journey.'}
                                    </p>
                                    <div className="mt-4">
                                        <Button
                                            asChild
                                            className="rounded-full bg-[#d5462f] px-5 text-white hover:bg-[#e2553d]"
                                        >
                                            <Link href={route('ebooks.index')}>Open Ebooks</Link>
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </section>

                <section className="space-y-4">
                    <div className="flex items-center justify-between gap-4">
                        <div>
                            <p className="text-xs uppercase tracking-[0.24em] text-white/45">
                                Secondary Discovery
                            </p>
                            <h2 className="mt-2 text-2xl font-semibold tracking-tight text-white">
                                Explore more without losing your current flow
                            </h2>
                        </div>
                        <span className="hidden text-sm text-white/45 md:inline">
                            Streaming-style finish
                        </span>
                    </div>

                    <div className="grid gap-4 lg:grid-cols-3">
                        {secondaryDiscoveryItems.map((item) => (
                            <div
                                key={item.title}
                                className="rounded-[28px] border border-white/10 bg-white/[0.04] p-5 backdrop-blur-sm"
                            >
                                <div className="flex h-full flex-col justify-between gap-6 rounded-[22px] border border-white/8 bg-black/15 p-5">
                                    <div className="space-y-3">
                                        <p className="text-xs uppercase tracking-[0.22em] text-white/45">
                                            Explore more
                                        </p>
                                        <h3 className="text-xl font-semibold text-white">
                                            {item.title}
                                        </h3>
                                        <p className="text-sm leading-6 text-white/60">
                                            {item.description}
                                        </p>
                                    </div>

                                    {item.kind === 'download' ? (
                                        <Button
                                            asChild
                                            className="rounded-full bg-[#d5462f] px-5 text-white hover:bg-[#e2553d]"
                                        >
                                            <a href={item.href ?? '#'}>
                                                <ChevronRight className="mr-2 size-4" />
                                                {item.label}
                                            </a>
                                        </Button>
                                    ) : (
                                        <Button
                                            asChild
                                            className="rounded-full bg-[#d5462f] px-5 text-white hover:bg-[#e2553d]"
                                        >
                                            <Link href={item.href ?? route('modules.index')}>
                                                <ChevronRight className="mr-2 size-4" />
                                                {item.label}
                                            </Link>
                                        </Button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
