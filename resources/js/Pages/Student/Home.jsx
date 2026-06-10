import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { ChevronRight, Play, Search } from 'lucide-react';

export default function StudentHome({
    homeStage,
    studentContext,
    continueLearning,
    progressSummary,
    nextStep,
    sequentialAwareness,
    availableModulesSection,
    assignmentMilestone,
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
                                        Your premium YogaFX learning home is now ready to carry
                                        your student identity.
                                    </h1>
                                </div>

                                <p className="max-w-2xl text-sm leading-7 text-white/72 sm:text-base">
                                    You are signed in as {fullName}. Your Home experience is
                                    anchored to {tierLabel.toLowerCase()} access and now has the
                                    core student context needed for the next learning-focused
                                    sections.
                                </p>

                                <div className="flex flex-wrap items-center gap-3 pt-2">
                                    <Button
                                        asChild
                                        size="lg"
                                        className="rounded-full bg-[#d5462f] px-6 text-white shadow-[0_18px_50px_rgba(213,70,47,0.3)] hover:bg-[#e2553d]"
                                    >
                                        <Link href={route('modules.index')}>
                                            <Play className="mr-2 size-4 fill-current" />
                                            Continue the Course
                                        </Link>
                                    </Button>

                                    <Button
                                        asChild
                                        size="lg"
                                        variant="outline"
                                        className="rounded-full border-white/20 bg-white/5 px-6 text-white hover:bg-white/10 hover:text-white"
                                    >
                                        <Link href={route('modules.index')}>
                                            Explore Modules
                                        </Link>
                                    </Button>
                                </div>
                            </div>

                            <div className="flex flex-wrap items-center gap-3 text-sm text-white/70">
                                <div className="rounded-full border border-white/10 bg-black/20 px-4 py-2 backdrop-blur">
                                    {tierLabel}
                                </div>
                                <div className="rounded-full border border-white/10 bg-black/20 px-4 py-2 backdrop-blur">
                                    Sequence awareness is now live
                                </div>
                            </div>
                        </div>

                        <div className="flex items-end justify-start lg:justify-end">
                            <div className="w-full max-w-[280px] rounded-[28px] border border-white/10 bg-black/30 p-5 shadow-2xl backdrop-blur-md">
                                <div className="space-y-6">
                                    <div className="space-y-2">
                                        <p className="text-xs uppercase tracking-[0.24em] text-white/55">
                                            Running Total
                                        </p>
                                        <div className="text-3xl font-semibold tracking-[0.08em] text-white">
                                            132:65:06
                                        </div>
                                        <p className="text-sm text-white/58">
                                            Login Time
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
                            Phase 8 active
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
                                <div className="space-y-2">
                                    <p className="text-xs uppercase tracking-[0.22em] text-white/50">
                                        Continue Learning engine
                                    </p>
                                    <h3 className="text-xl font-semibold text-white">
                                        {continueLearning?.state === 'resume'
                                            ? 'The last active lesson is ready to resume'
                                            : continueLearning?.state === 'start'
                                              ? 'A safe first lesson fallback is now ready'
                                              : 'Home is waiting for lesson data'}
                                    </h3>
                                    <p className="text-sm leading-6 text-white/60">
                                        {continueLearning?.state === 'resume'
                                            ? 'This card is driven by the most recent accessible lesson progress for the signed-in student.'
                                            : continueLearning?.state === 'start'
                                              ? 'No lesson progress was found, so Home safely falls back to the first accessible lesson in the student tier.'
                                              : 'No accessible lesson was found yet, so Continue Learning stays in an empty but stable state.'}
                                    </p>
                                </div>

                                <Button
                                    type="button"
                                    variant="ghost"
                                    disabled
                                    className="justify-between rounded-full border border-white/12 bg-white/5 px-5 py-6 text-white/80 opacity-100 hover:bg-white/10 hover:text-white"
                                >
                                    Next: assignment milestone
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
                                {sequentialAwareness?.eyebrow ?? 'Learning Sequence'}
                            </p>
                            <h2 className="mt-2 text-2xl font-semibold tracking-tight text-white">
                                {sequentialAwareness?.title ??
                                    'Sequence awareness will appear here'}
                            </h2>
                        </div>
                        <span className="hidden text-sm text-white/45 md:inline">
                            Guidance, not hard locking
                        </span>
                    </div>

                    <div className="grid gap-4 xl:grid-cols-[1.15fr_0.85fr]">
                        <div className="rounded-[28px] border border-white/10 bg-white/[0.04] p-5 backdrop-blur-sm">
                            <div className="flex h-full flex-col gap-6 rounded-[22px] border border-white/8 bg-[linear-gradient(145deg,rgba(214,90,52,0.16),rgba(255,255,255,0.03))] p-5">
                                <div className="space-y-3">
                                    <div className="flex flex-wrap items-center gap-3 text-xs uppercase tracking-[0.22em] text-white/55">
                                        <span className="rounded-full border border-white/12 bg-black/20 px-3 py-1">
                                            {sequentialAwareness?.status ?? 'Sequence guidance'}
                                        </span>
                                        {sequentialAwareness?.current_lesson?.module_title && (
                                            <span className="rounded-full border border-white/12 bg-black/20 px-3 py-1">
                                                {sequentialAwareness.current_lesson.module_title}
                                            </span>
                                        )}
                                    </div>

                                    <p className="max-w-2xl text-sm leading-7 text-white/68 sm:text-base">
                                        {sequentialAwareness?.description ??
                                            'Home will explain the current lesson order here.'}
                                    </p>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="rounded-[24px] border border-white/10 bg-black/20 p-5">
                                        <div className="flex flex-wrap items-center justify-between gap-3">
                                            <p className="text-xs uppercase tracking-[0.22em] text-white/45">
                                                Current lesson
                                            </p>
                                            <span className="rounded-full border border-white/12 bg-white/5 px-3 py-1 text-[11px] uppercase tracking-[0.22em] text-white/65">
                                                {currentSequenceStatus}
                                            </span>
                                        </div>
                                        <h3 className="mt-3 text-xl font-semibold text-white">
                                            {sequentialAwareness?.current_lesson?.title ??
                                                'No current lesson yet'}
                                        </h3>
                                        <p className="mt-2 text-sm leading-6 text-white/58">
                                            {sequentialAwareness?.current_lesson
                                                ? `Lesson ${sequentialAwareness.current_lesson.sort_order} in ${sequentialAwareness.current_lesson.module_title}`
                                                : 'The active lesson in the sequence will appear here.'}
                                        </p>
                                        <div className="mt-4 flex flex-wrap items-center gap-3">
                                            {currentSequenceLesson?.module_url && (
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    className="rounded-full border-white/15 bg-white/5 text-white hover:bg-white/10 hover:text-white"
                                                >
                                                    <Link href={currentSequenceLesson.module_url}>
                                                        Open Module
                                                    </Link>
                                                </Button>
                                            )}
                                            {sequentialAwareness?.current_lesson?.url && (
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    className="rounded-full border-white/15 bg-white/5 text-white hover:bg-white/10 hover:text-white"
                                                >
                                                    <Link href={sequentialAwareness.current_lesson.url}>
                                                        Open Current Lesson
                                                    </Link>
                                                </Button>
                                            )}
                                        </div>
                                    </div>

                                    <div className="rounded-[24px] border border-white/10 bg-black/20 p-5">
                                        <div className="flex flex-wrap items-center justify-between gap-3">
                                            <p className="text-xs uppercase tracking-[0.22em] text-white/45">
                                                Next lesson
                                            </p>
                                            <span className="rounded-full border border-white/12 bg-white/5 px-3 py-1 text-[11px] uppercase tracking-[0.22em] text-white/65">
                                                {nextSequenceStatus}
                                            </span>
                                        </div>
                                        <h3 className="mt-3 text-xl font-semibold text-white">
                                            {sequentialAwareness?.next_lesson?.title ??
                                                'No further accessible lesson'}
                                        </h3>
                                        <p className="mt-2 text-sm leading-6 text-white/58">
                                            {sequentialAwareness?.next_lesson
                                                ? `Lesson ${sequentialAwareness.next_lesson.sort_order} in ${sequentialAwareness.next_lesson.module_title}`
                                                : 'When the next lesson in sequence exists, Home will surface it here.'}
                                        </p>
                                        <div className="mt-4 flex flex-wrap items-center gap-3">
                                            {nextSequenceLesson?.module_url && (
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    className="rounded-full border-white/15 bg-white/5 text-white hover:bg-white/10 hover:text-white"
                                                >
                                                    <Link href={nextSequenceLesson.module_url}>
                                                        Open Module
                                                    </Link>
                                                </Button>
                                            )}
                                            {sequentialAwareness?.next_lesson?.url && (
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    className="rounded-full border-white/15 bg-white/5 text-white hover:bg-white/10 hover:text-white"
                                                >
                                                    <Link href={sequentialAwareness.next_lesson.url}>
                                                        View Next Lesson
                                                    </Link>
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                </div>

                                <div className="rounded-[24px] border border-white/10 bg-white/5 p-5">
                                    <p className="text-xs uppercase tracking-[0.22em] text-white/45">
                                        Sequence rule
                                    </p>
                                    <h3 className="mt-3 text-lg font-semibold text-white">
                                        {sequentialAwareness?.sequence_rule?.label ??
                                            'Sequence guidance is not available yet.'}
                                    </h3>
                                    <p className="mt-2 text-sm leading-6 text-white/60">
                                        {sequentialAwareness?.sequence_rule?.detail ??
                                            'Home will explain the lesson order and next sequence rule here.'}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="rounded-[28px] border border-white/10 bg-white/[0.04] p-5 backdrop-blur-sm">
                            <div className="flex h-full flex-col justify-between gap-6 rounded-[22px] border border-white/8 bg-black/15 p-5">
                                <div className="space-y-4">
                                    <div className="space-y-2">
                                        <p className="text-xs uppercase tracking-[0.22em] text-white/50">
                                            Supporting rules
                                        </p>
                                        <h3 className="text-xl font-semibold text-white">
                                            Home now explains why the sequence feels the way it does
                                        </h3>
                                    </div>

                                    <div className="space-y-3">
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

                                <div className="rounded-[22px] border border-white/10 bg-white/5 p-4">
                                    <p className="text-xs uppercase tracking-[0.2em] text-white/45">
                                        Current boundary
                                    </p>
                                    <p className="mt-3 text-sm leading-6 text-white/60">
                                        This stage adds sequence awareness inside Home only. It
                                        does not yet enforce hard locking on lesson routes or
                                        activate assessment player flows.
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
                                        Stage 8 scope
                                    </p>
                                    <p className="mt-3 text-sm leading-6 text-white/60">
                                        Sequence awareness and assignment milestone are now
                                        connected. Certificate and resource sections still stay for
                                        the next phases.
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
                                        Assessment, assignment, and certificate recommendations are
                                        still deferred until their student-side flows are active,
                                        so this card avoids sending students into dead ends.
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
                                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                    {availableModulesSection.items.map((module, index) => (
                                        <div
                                            key={module.id}
                                            className="group rounded-[28px] border border-white/10 bg-[#120f0e] p-4 transition duration-300 hover:-translate-y-1 hover:border-white/15"
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
            </div>
        </AuthenticatedLayout>
    );
}
