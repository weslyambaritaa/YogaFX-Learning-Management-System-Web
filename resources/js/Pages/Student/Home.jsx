import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { ChevronRight, Play, Search } from 'lucide-react';

export default function StudentHome({ homeStage, studentContext, continueLearning }) {
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
                                        <Link href={route('profile.edit')}>
                                            More Information
                                        </Link>
                                    </Button>
                                </div>
                            </div>

                            <div className="flex flex-wrap items-center gap-3 text-sm text-white/70">
                                <div className="rounded-full border border-white/10 bg-black/20 px-4 py-2 backdrop-blur">
                                    {tierLabel}
                                </div>
                                <div className="rounded-full border border-white/10 bg-black/20 px-4 py-2 backdrop-blur">
                                    Continue Learning comes next
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
                            Phase 3 active
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
                                    Next: progress summary
                                    <ChevronRight className="size-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </section>

                <section className="space-y-4">
                    <div>
                        <p className="text-xs uppercase tracking-[0.24em] text-white/45">
                            Module
                        </p>
                        <h2 className="mt-2 text-2xl font-semibold tracking-tight text-white">
                            Large visual cards will live here
                        </h2>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {[
                            {
                                label: tierLabel,
                                eyebrow: 'Access tier',
                            },
                            {
                                label: fullName,
                                eyebrow: 'Student',
                            },
                            {
                                label: 'Phase 3',
                                eyebrow: 'Next build target',
                            },
                        ].map((item, index) => (
                            <div
                                key={item.label}
                                className="group relative overflow-hidden rounded-[28px] border border-white/10 bg-[#171311] p-5"
                            >
                                <div
                                    className={[
                                        'absolute inset-0 opacity-90 transition duration-300 group-hover:scale-[1.02]',
                                        index === 0
                                            ? 'bg-[radial-gradient(circle_at_20%_18%,_rgba(211,101,52,0.45),_transparent_30%),linear-gradient(160deg,_#2f1d16_0%,_#120f0e_100%)]'
                                            : index === 1
                                              ? 'bg-[radial-gradient(circle_at_78%_16%,_rgba(255,255,255,0.12),_transparent_26%),linear-gradient(160deg,_#251a15_0%,_#100d0c_100%)]'
                                              : 'bg-[radial-gradient(circle_at_50%_12%,_rgba(197,96,46,0.35),_transparent_24%),linear-gradient(160deg,_#221712_0%,_#0f0d0c_100%)]',
                                    ].join(' ')}
                                />
                                <div className="relative flex aspect-[1.35/1] items-end rounded-[22px] border border-white/8 p-6">
                                    <div>
                                        <p className="text-xs uppercase tracking-[0.22em] text-white/50">
                                            {item.eyebrow}
                                        </p>
                                        <h3 className="mt-2 text-4xl font-semibold tracking-[-0.03em] text-white">
                                            {item.label}
                                        </h3>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                    </section>
            </div>
        </AuthenticatedLayout>
    );
}
