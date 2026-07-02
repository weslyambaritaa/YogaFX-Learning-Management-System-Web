import LockedContentDialog from "@/Components/student/LockedContentDialog";
import StudentStatusBadge from "@/Components/student/StudentStatusBadge";
import { Button } from "@/Components/ui/button";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, usePage } from "@inertiajs/react";
import { ChevronRight, Play, X } from "lucide-react";
import { useEffect, useRef, useState } from "react";

const ONBOARDING_KEY = "yogafx_onboarding_done";
const FONT_FAMILY = "'Montserrat', sans-serif";

const SLIDES = [
    {
        title: "Welcome to YogaFX",
        body: "A premium learning platform built for focus with a cleaner module flow across desktop and mobile.",
    },
    {
        title: "Keep moving forward",
        body: "Continue from your latest lesson, track what is completed, and see what is still locked before opening it.",
    },
    {
        title: "Everything stays guided",
        body: "Your next step, module access, and supporting resources stay visible without turning the experience into a school portal.",
    },
];

function formatDurationParts(totalSeconds) {
    const safeSeconds = Math.max(0, Number(totalSeconds || 0));
    return {
        hours: String(Math.floor(safeSeconds / 3600)).padStart(2, "0"),
        minutes: String(Math.floor((safeSeconds % 3600) / 60)).padStart(2, "0"),
        seconds: String(Math.floor(safeSeconds % 60)).padStart(2, "0"),
    };
}

function OnboardingOverlay({ onDone }) {
    const [slide, setSlide] = useState(0);
    const current = SLIDES[slide];
    const isLast = slide === SLIDES.length - 1;

    const finish = () => {
        localStorage.setItem(ONBOARDING_KEY, "1");
        onDone();
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 px-4 backdrop-blur-sm">
            <div
                className="relative w-full max-w-md rounded-[5px] border border-white/10 bg-[#141110] p-8 text-white"
                style={{ fontFamily: FONT_FAMILY }}
            >
                <button
                    type="button"
                    onClick={finish}
                    className="absolute right-5 top-5 text-white/45 transition hover:text-white"
                >
                    <X className="size-4" />
                </button>
                <div className="mb-8 flex gap-2">
                    {SLIDES.map((_, index) => (
                        <div
                            key={index}
                            className={[
                                "h-1 rounded-full transition-all",
                                index === slide
                                    ? "w-10 bg-[#DB202C]"
                                    : "w-4 bg-white/15",
                            ].join(" ")}
                        />
                    ))}
                </div>
                <div className="space-y-3 text-center">
                    <h2 className="text-2xl font-semibold">{current.title}</h2>
                    <p className="text-sm leading-7 text-white/60">
                        {current.body}
                    </p>
                </div>
                <div className="mt-8 flex items-center justify-between">
                    <button
                        type="button"
                        onClick={finish}
                        className="text-sm text-white/40 transition hover:text-white/70"
                    >
                        Skip
                    </button>
                    <Button
                        type="button"
                        onClick={() =>
                            isLast ? finish() : setSlide((value) => value + 1)
                        }
                        className="rounded-[5px] bg-[#DB202C] text-white hover:bg-[#c31c28]"
                    >
                        {isLast ? "Get Started" : "Next"}
                    </Button>
                </div>
            </div>
        </div>
    );
}

function AccessTimeCard({ accessTimeSummary }) {
    const [liveSeconds, setLiveSeconds] = useState(
        accessTimeSummary?.running_total_access_duration_seconds ?? 0,
    );

    useEffect(() => {
        if (
            !accessTimeSummary?.currently_active ||
            !accessTimeSummary?.active_session_login_at
        ) {
            setLiveSeconds(
                accessTimeSummary?.running_total_access_duration_seconds ?? 0,
            );
            return undefined;
        }

        const loginAt = new Date(
            accessTimeSummary.active_session_login_at,
        ).getTime();
        const tick = () => {
            const elapsed = Math.max(
                0,
                Math.floor((Date.now() - loginAt) / 1000),
            );
            setLiveSeconds(
                (accessTimeSummary.running_total_access_duration_seconds ?? 0) +
                    elapsed,
            );
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
        <div
            className="inline-flex items-center gap-5 rounded-[5px] border border-white/10 bg-black/45 px-6 py-4 text-white backdrop-blur"
            style={{ fontFamily: FONT_FAMILY }}
        >
            <div>
                {/* Hapus uppercase dan tracking */}
                <div className="text-xs text-white/45">
                    Running Total
                </div>
                <div className="text-xs text-white/45">
                    Login Time
                </div>
            </div>
            <div className="text-3xl font-semibold tracking-[0.08em]">
                {parts.hours}:{parts.minutes}:{parts.seconds}
            </div>
        </div>
    );
}

function LessonRow({ lesson, onLockedClick }) {
    const row = (
        <div
            className="flex items-center justify-between gap-4 rounded-[5px] border border-white/10 bg-white/[0.04] px-4 py-3 transition hover:bg-white/[0.06]"
            style={{ fontFamily: FONT_FAMILY }}
        >
            <div className="min-w-0">
                <div className="text-sm font-medium text-white">
                    {lesson.title}
                </div>
                <div className="text-xs uppercase tracking-[0.18em] text-white/45">
                    Lesson {lesson.sort_order}
                </div>
            </div>
            <div className="flex items-center gap-3">
                <StudentStatusBadge
                    status={
                        lesson.status === "in_progress"
                            ? "available"
                            : lesson.status
                    }
                    label={lesson.status === "in_progress" ? "Available" : null}
                />
                <div className="text-xs text-white/55">
                    {lesson.progress_percentage}%
                </div>
            </div>
        </div>
    );

    if (!lesson.url || lesson.status === "locked") {
        return (
            <button
                type="button"
                onClick={onLockedClick}
                className="w-full text-left"
            >
                {row}
            </button>
        );
    }

    return <Link href={lesson.url}>{row}</Link>;
}

function ModuleModal({ module, onClose, onLockedLessonClick }) {
    useEffect(() => {
        document.body.style.overflow = "hidden";
        return () => {
            document.body.style.overflow = "";
        };
    }, []);

    return (
        <div
            className="fixed inset-0 z-40 flex items-end justify-center bg-black/75 px-0 backdrop-blur-sm sm:items-center sm:px-4"
            onClick={onClose}
        >
            <div
                className="relative w-full max-w-3xl rounded-t-[5px] border border-white/10 bg-[#141110] sm:rounded-[5px]"
                onClick={(event) => event.stopPropagation()}
                style={{ fontFamily: FONT_FAMILY }}
            >
                <button
                    type="button"
                    onClick={onClose}
                    className="absolute right-4 top-4 z-10 rounded-full border border-white/15 bg-black/45 p-2 text-white/70 transition hover:text-white"
                >
                    <X className="size-4" />
                </button>

                <div className="relative aspect-video overflow-hidden rounded-t-[5px]">
                    {module.thumbnail_url ? (
                        <img
                            src={module.thumbnail_url}
                            alt={module.title}
                            className="h-full w-full object-cover"
                        />
                    ) : (
                        <div className="h-full w-full bg-[radial-gradient(circle_at_24%_20%,rgba(223,103,57,0.45),transparent_28%),linear-gradient(160deg,#2b1d16_0%,#120f0e_100%)]" />
                    )}
                    <div className="absolute inset-0 bg-gradient-to-t from-black/85 via-black/20 to-transparent" />
                    <div className="absolute bottom-5 left-5 space-y-3">
                        <StudentStatusBadge
                            status={
                                module.status === "in_progress"
                                    ? "available"
                                    : module.status
                            }
                            label={module.status_label}
                        />
                        <div
                            style={{
                                fontFamily: FONT_FAMILY,
                                fontSize: "14px",
                                fontWeight: 500,
                            }}
                            className="text-white"
                        >
                            Module {module.sort_order}
                        </div>
                        <div
                            style={{
                                fontFamily: FONT_FAMILY,
                                fontSize: "22px",
                                fontWeight: 500,
                            }}
                            className="text-white"
                        >
                            {module.title}
                        </div>
                    </div>
                </div>

                <div className="space-y-6 p-6">
                    {module.description ? (
                        <p className="text-sm leading-7 text-white/65">
                            {module.description}
                        </p>
                    ) : null}

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="rounded-[5px] border border-white/10 bg-white/[0.04] px-5 py-4 text-white">
                            <div className="text-xs uppercase tracking-[0.18em] text-white/45">
                                Lessons
                            </div>
                            <div
                                style={{
                                    fontFamily: FONT_FAMILY,
                                    fontSize: "14px",
                                    fontWeight: 400,
                                }}
                                className="mt-2"
                            >
                                {module.lesson_count}
                            </div>
                        </div>
                        <div className="rounded-[5px] border border-white/10 bg-white/[0.04] px-5 py-4 text-white">
                            <div className="text-xs uppercase tracking-[0.18em] text-white/45">
                                Progress
                            </div>
                            <div
                                style={{
                                    fontFamily: FONT_FAMILY,
                                    fontSize: "14px",
                                    fontWeight: 400,
                                }}
                                className="mt-2"
                            >
                                {module.progress_percentage}%
                            </div>
                        </div>
                    </div>

                    {module.continue_url ? (
                        <Button
                            asChild
                            className="w-full rounded-[5px] bg-[#DB202C] text-white hover:bg-[#c31c28]"
                            style={{
                                fontFamily: FONT_FAMILY,
                                fontSize: "14px",
                                fontWeight: 500,
                            }}
                        >
                            <Link href={module.continue_url}>
                                {module.cta_label ?? "Open Module"}
                            </Link>
                        </Button>
                    ) : null}

                    <div className="space-y-3">
                        <div className="text-xs uppercase tracking-[0.2em] text-white/45">
                            Lessons in this module
                        </div>
                        <div className="space-y-2">
                            {(module.lessons ?? []).map((lesson) => (
                                <LessonRow
                                    key={lesson.id}
                                    lesson={lesson}
                                    onLockedClick={onLockedLessonClick}
                                />
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

function ModuleCard({ module, onLockedClick }) {
    const [isHoverOpen, setIsHoverOpen] = useState(false);
    const hoverTimeoutRef = useRef(null);

    const openHover = () => {
        if (hoverTimeoutRef.current) {
            window.clearTimeout(hoverTimeoutRef.current);
        }
        hoverTimeoutRef.current = window.setTimeout(() => {
            setIsHoverOpen(true);
        }, 200);
    };

    const closeHover = () => {
        if (hoverTimeoutRef.current) {
            window.clearTimeout(hoverTimeoutRef.current);
            hoverTimeoutRef.current = null;
        }
        setIsHoverOpen(false);
    };

    useEffect(
        () => () => {
            if (hoverTimeoutRef.current) {
                window.clearTimeout(hoverTimeoutRef.current);
            }
        },
        [],
    );

    const mobileCard = (
        <div
            className="space-y-2 p-3 md:hidden"
            style={{ fontFamily: FONT_FAMILY }}
        >
            <div className="relative aspect-video overflow-hidden rounded-[5px]">
                {module.thumbnail_url ? (
                    <img
                        src={module.thumbnail_url}
                        alt={module.title}
                        className="h-full w-full object-cover"
                    />
                ) : (
                    <div className="h-full w-full bg-[radial-gradient(circle_at_24%_20%,rgba(223,103,57,0.45),transparent_28%),linear-gradient(160deg,#2b1d16_0%,#120f0e_100%)]" />
                )}
                <div className="absolute inset-0 bg-gradient-to-t from-black/72 via-black/10 to-transparent" />
                <div className="absolute right-3 top-3">
                    <StudentStatusBadge
                        status={
                            module.status === "in_progress"
                                ? "available"
                                : module.status
                        }
                        label={module.status === "in_progress" ? "Available" : null}
                    />
                </div>
            </div>

                <div className="space-y-1.5">
                    <div className="space-y-1">
                        <div className="line-clamp-2 text-sm font-semibold leading-5 text-white sm:text-base">
                            {module.title}
                    </div>
                    <div className="text-sm font-semibold text-white/82 sm:text-base">
                        Module {module.sort_order}
                    </div>
                </div>

                <div className="flex items-center justify-between text-[11px] text-white/62 sm:text-xs">
                    <span>{module.lesson_count} lessons</span>
                    <span>{module.progress_percentage}%</span>
                </div>

                <div className="h-1.5 overflow-hidden rounded-full bg-white/10">
                    <div
                        className={[
                            "h-full rounded-full",
                            module.status === "completed"
                                ? "bg-emerald-500"
                                : module.status === "locked"
                                  ? "bg-[#DB202C]"
                                  : "bg-white",
                        ].join(" ")}
                        style={{ width: `${module.progress_percentage}%` }}
                    />
                </div>

                <div className="inline-flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-[0.16em] text-white/80">
                    {module.status === "locked"
                        ? "Complete Previous Module"
                        : null}
                    <ChevronRight className="size-3.5" />
                </div>
            </div>
        </div>
    );

    const desktopCard = (
        <div
            className={`relative hidden md:block ${isHoverOpen ? "z-50" : "z-10"}`}
            onMouseEnter={openHover}
            onMouseLeave={closeHover}
            style={{ fontFamily: FONT_FAMILY }}
        >
            <div className="aspect-video overflow-hidden rounded-[5px] border border-white/10 bg-white/[0.04] shadow-[0_16px_40px_rgba(0,0,0,0.22)] transition duration-200">
                {module.thumbnail_url ? (
                    <img
                        src={module.thumbnail_url}
                        alt={module.title}
                        className="h-full w-full object-cover transition duration-200"
                    />
                ) : (
                    <div className="h-full w-full bg-[radial-gradient(circle_at_24%_20%,rgba(223,103,57,0.45),transparent_28%),linear-gradient(160deg,#2b1d16_0%,#120f0e_100%)]" />
                )}
                <div className="absolute inset-0 bg-gradient-to-t from-black/58 via-transparent to-transparent" />
                <div className="absolute right-4 top-4">
                    <StudentStatusBadge
                        status={
                            module.status === "in_progress"
                                ? "available"
                                : module.status
                        }
                        label={module.status === "in_progress" ? "Available" : null}
                    />
                </div>
            </div>

            <div
                className={[
                    "pointer-events-none absolute left-1/2 top-1/2 w-[112%] min-w-[320px] max-w-[380px] -translate-x-1/2 rounded-[22px] border border-white/12 bg-[#141110] shadow-[0_34px_90px_rgba(0,0,0,0.55)] transition-all duration-200",
                    isHoverOpen
                        ? "-translate-y-[52%] scale-100 opacity-100"
                        : "-translate-y-1/2 scale-95 opacity-0",
                ].join(" ")}
            >
                <div className="overflow-hidden rounded-t-[22px]">
                    <div className="relative aspect-video">
                        {module.thumbnail_url ? (
                            <img
                                src={module.thumbnail_url}
                                alt={module.title}
                                className="h-full w-full object-cover"
                            />
                        ) : (
                            <div className="h-full w-full bg-[radial-gradient(circle_at_24%_20%,rgba(223,103,57,0.45),transparent_28%),linear-gradient(160deg,#2b1d16_0%,#120f0e_100%)]" />
                        )}
                        <div className="absolute inset-0 bg-gradient-to-t from-black/90 via-black/20 to-transparent" />
                    </div>
                </div>

                <div className="space-y-4 p-5">
                    <div className="space-y-1.5">
                        <div
                            className="uppercase tracking-[0.18em] text-white/48"
                            style={{
                                fontFamily: FONT_FAMILY,
                                fontSize: "14px",
                                fontWeight: 500,
                            }}
                        >
                            Module {module.sort_order}
                        </div>
                        <div
                            className="leading-7 text-white"
                            style={{
                                fontFamily: FONT_FAMILY,
                                fontSize: "22px",
                                fontWeight: 500,
                            }}
                        >
                            {module.title}
                        </div>
                    </div>

                    <div
                        className="flex items-center justify-between text-white/65"
                        style={{
                            fontFamily: FONT_FAMILY,
                            fontSize: "14px",
                            fontWeight: 400,
                        }}
                    >
                        <span>{module.lesson_count} lessons</span>
                        <span>{module.progress_percentage}%</span>
                    </div>

                    <div className="h-1.5 overflow-hidden rounded-full bg-white/10">
                        <div
                            className={[
                                "h-full rounded-full",
                                module.status === "completed"
                                    ? "bg-emerald-500"
                                    : module.status === "locked"
                                      ? "bg-[#DB202C]"
                                      : "bg-white",
                            ].join(" ")}
                            style={{ width: `${module.progress_percentage}%` }}
                        />
                    </div>

                    <div
                        className="inline-flex items-center gap-2 uppercase tracking-[0.16em] text-white/78"
                        style={{
                            fontFamily: FONT_FAMILY,
                            fontSize: "14px",
                            fontWeight: 500,
                        }}
                    >
                        {module.status === "locked"
                            ? "Complete Previous Module"
                            : "Open Module"}
                        <ChevronRight className="size-4" />
                    </div>
                </div>
            </div>
        </div>
    );

    const card = (
        <>
            {desktopCard}
            {mobileCard}
        </>
    );

    if (module.status === "locked" || !module.url) {
        return (
            <button
                type="button"
                onClick={onLockedClick}
                className="w-full text-left"
            >
                {card}
            </button>
        );
    }

    return (
        <Link href={module.url} className="block w-full text-left">
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
    homeExperience,
}) {
    const [showOnboarding, setShowOnboarding] = useState(false);
    const [selectedModule, setSelectedModule] = useState(null);
    const [lockedModuleOpen, setLockedModuleOpen] = useState(false);
    const [lockedLessonOpen, setLockedLessonOpen] = useState(false);
    const bootedRef = useRef(false);
    const rawModules = availableModulesSection?.items ?? [];
    const studentName = studentContext?.display_name ?? "Student";
    const authUser = usePage().props.auth.user;
    const accessTierLabel =
        studentContext?.access_tier?.name ??
        authUser?.access_tier?.name ??
        "Access Tier";
    const mobileAccessTimeParts = formatDurationParts(
        accessTimeSummary?.running_total_access_duration_seconds ?? 0,
    );
    const mobileModuleLessonLabel = [
        continueLearning?.module?.sort_order
            ? `MODULE ${continueLearning.module.sort_order}`
            : null,
        continueLearning?.lesson?.sort_order
            ? `LESSON ${continueLearning.lesson.sort_order}`
            : null,
    ]
        .filter(Boolean)
        .join(" - ");

    useEffect(() => {
        if (!bootedRef.current && !localStorage.getItem(ONBOARDING_KEY)) {
            setShowOnboarding(true);
        }
        bootedRef.current = true;
    }, []);

    return (
        <AuthenticatedLayout
            studentVariant="immersive"
            studentContentClassName="pb-16"
        >
            <Head title="Home" />

            {showOnboarding ? (
                <OnboardingOverlay onDone={() => setShowOnboarding(false)} />
            ) : null}
            <LockedContentDialog
                open={lockedModuleOpen}
                onOpenChange={setLockedModuleOpen}
                kind="module"
            />
            <LockedContentDialog
                open={lockedLessonOpen}
                onOpenChange={setLockedLessonOpen}
                kind="lesson"
            />
            {selectedModule ? (
                <ModuleModal
                    module={selectedModule}
                    onClose={() => setSelectedModule(null)}
                    onLockedLessonClick={() => setLockedLessonOpen(true)}
                />
            ) : null}

            <section className="sm:hidden">
                <div className="mx-auto max-w-[1400px] px-4 pt-6">
                    <div className="mb-3 flex items-center justify-between gap-3">
                        <div className="rounded-[4px] border border-[#a12626] bg-[#3d1414] px-3 py-1 text-[11px] font-medium uppercase tracking-[0.22em] text-[#ff6f61]">
                            {accessTierLabel}
                        </div>
                        <div className="flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.04] px-3 py-1.5 text-[11px] text-white/70">
                            <span>Running time</span>
                            <span className="font-semibold tracking-[0.08em] text-white">
                                {mobileAccessTimeParts.hours}:
                                {mobileAccessTimeParts.minutes}:
                                {mobileAccessTimeParts.seconds}
                            </span>
                        </div>
                    </div>

                    <div className="mb-4 text-[26px] font-semibold leading-none text-white">
                        Hi {studentName}, Welcome Back!
                    </div>

                    <div
                        className="overflow-hidden rounded-[14px] border border-white/35 bg-[#120f0f] shadow-[0_24px_90px_rgba(0,0,0,0.35)]"
                        style={{ fontFamily: FONT_FAMILY }}
                    >
                        <div className="relative aspect-[0.8]">
                            {continueLearning?.thumbnail_url ? (
                                <img
                                    src={continueLearning.thumbnail_url}
                                    alt={
                                        continueLearning?.title ??
                                        "Continue learning"
                                    }
                                    className="h-full w-full object-cover"
                                />
                            ) : (
                                <div className="h-full w-full bg-[radial-gradient(circle_at_18%_28%,rgba(173,76,38,0.55),transparent_36%),linear-gradient(160deg,#1e1210,#0a0908)]" />
                            )}
                            <div className="absolute inset-0 bg-[linear-gradient(180deg,rgba(0,0,0,0.24)_0%,rgba(0,0,0,0.14)_24%,rgba(0,0,0,0.72)_68%,rgba(0,0,0,0.96)_100%)]" />

                            <div className="absolute inset-x-0 bottom-0 p-4">
                                <div className="space-y-3 text-white">
                                    <div className="space-y-1.5">
                                        <h1
                                            className="text-[22px] leading-[1.04] tracking-[-0.03em] text-white"
                                            style={{
                                                fontFamily: FONT_FAMILY,
                                                fontWeight: 700,
                                            }}
                                        >
                                            {continueLearning?.title ??
                                                homeExperience?.hero_title ??
                                                "Start your learning journey"}
                                        </h1>
                                        <div className="text-[11px] font-medium uppercase tracking-[0.18em] text-white/78">
                                            {mobileModuleLessonLabel ||
                                                (continueLearning?.module_label ??
                                                    "Continue Learning")}
                                        </div>
                                    </div>

                                    <Button
                                        asChild
                                        className="h-auto w-full rounded-[5px] bg-white px-5 py-3 text-black transition-colors hover:bg-white/85"
                                        style={{
                                            fontFamily: FONT_FAMILY,
                                            fontSize: "14px",
                                            fontWeight: 600,
                                        }}
                                    >
                                        <Link
                                            href={
                                                continueLearning?.cta_url ??
                                                route("modules.index")
                                            }
                                            className="flex items-center justify-center"
                                        >
                                            <Play className="mr-2 size-5 fill-black text-black" />
                                            {continueLearning?.cta_label ??
                                                homeExperience?.primary_cta_label ??
                                                "Continue Learning"}
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {accessTimeSummary ? (
                        <div className="mt-4 flex justify-start">
                            <AccessTimeCard
                                accessTimeSummary={accessTimeSummary}
                            />
                        </div>
                    ) : null}
                </div>
            </section>

            <section className="relative hidden overflow-hidden sm:block">
                <div className="absolute inset-0">
                    {continueLearning?.thumbnail_url ? (
                        <img
                            src={continueLearning.thumbnail_url}
                            alt=""
                            className="h-full w-full object-cover"
                        />
                    ) : (
                        <div className="h-full w-full bg-[radial-gradient(circle_at_18%_28%,rgba(173,76,38,0.55),transparent_36%),linear-gradient(160deg,#1e1210,#0a0908)]" />
                    )}
                    <div className="absolute inset-0 bg-[linear-gradient(to_right,rgba(0,0,0,0.88)_0%,rgba(0,0,0,0.42)_52%,rgba(0,0,0,0.08)_100%),linear-gradient(to_top,rgba(0,0,0,0.96)_0%,rgba(0,0,0,0.38)_32%,transparent_62%)]" />
                </div>

                <div className="relative mx-auto flex min-h-screen max-w-[1400px] flex-col justify-end gap-6 px-4 pb-20 pt-24 sm:gap-8 sm:px-6 lg:px-10 lg:pb-28">
                    <div className="max-w-2xl space-y-3 text-white sm:space-y-5">
                        {/* "Welcome back, Rahel" → medium 14px, tanpa uppercase */}
                        <div
                            style={{
                                fontFamily: FONT_FAMILY,
                                fontSize: "14px",
                                fontWeight: 600,
                            }}
                            className="text-white"
                        >
                            {homeExperience?.state === "new_student"
                                ? `Hello, ${studentName}`
                                : `Welcome back, ${studentName}`}
                        </div>

                        {/* Judul hero → bold 48px */}
                        <h1
                            style={{
                                fontFamily: FONT_FAMILY,
                                fontSize: "48px",
                                fontWeight: 700,
                            }}
                            className="text-[36px] leading-[1.04] tracking-[-0.03em] text-white sm:text-[48px] sm:leading-[1.02]"
                        >
                            {continueLearning?.title ??
                                homeExperience?.hero_title ??
                                "Start your learning journey"}
                        </h1>

                        {/* Deskripsi → regular 12px */}
                        <p
                            style={{
                                fontFamily: FONT_FAMILY,
                                fontSize: "14px",
                                fontWeight: 400,
                            }}
                            className="leading-6 text-white sm:leading-7"
                        >
                            {continueLearning?.description ??
                                homeExperience?.hero_description}
                        </p>

                        <div className="flex flex-wrap gap-3 pt-2">
                            <Button
                                asChild
                                className="h-auto rounded-md bg-white px-7 py-3 text-black transition-colors hover:bg-white/80"
                                style={{
                                    fontFamily: FONT_FAMILY,
                                    fontSize: "14px",
                                    fontWeight: 500,
                                }}
                            >
                                <Link
                                    href={
                                        continueLearning?.cta_url ??
                                        route("modules.index")
                                    }
                                    className="flex items-center"
                                >
                                    <Play className="mr-2.5 size-6 fill-black text-black" />
                                    {continueLearning?.cta_label ??
                                        homeExperience?.primary_cta_label ??
                                        "Continue Learning"}
                                </Link>
                            </Button>
                        </div>
                    </div>

                    {accessTimeSummary ? (
                        <div className="flex justify-start lg:justify-end">
                            <AccessTimeCard
                                accessTimeSummary={accessTimeSummary}
                            />
                        </div>
                    ) : null}
                </div>
            </section>

            <div className="mx-auto flex max-w-[1400px] flex-col gap-8 px-4 pt-8 sm:gap-10 sm:px-6 sm:pt-10 lg:px-10">
                <section className="space-y-4">
                    <div className="px-3.5 md:px-0">
                        <h1
                            className="mt-1 text-white"
                            style={{
                                fontFamily: FONT_FAMILY,
                                fontSize: "22px",
                                fontWeight: 500,
                            }}
                        >
                            All Modules
                        </h1>
                    </div>
                    <div className="py-4 sm:py-6">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-6 lg:grid-cols-3 xl:grid-cols-4">
                            {rawModules.map((module) => (
                                <div key={module.id} className="w-full">
                                    <ModuleCard
                                        module={module}
                                        onLockedClick={() =>
                                            setLockedModuleOpen(true)
                                        }
                                    />
                                </div>
                            ))}
                        </div>
                    </div>
                    {!rawModules.length ? (
                        <div
                            className="rounded-[5px] border border-white/10 bg-white/[0.04] px-6 py-10 text-center text-white/60 sm:py-12"
                            style={{ fontFamily: FONT_FAMILY }}
                        >
                            No modules are available for your current access
                            tier.
                        </div>
                    ) : null}
                </section>

            </div>
        </AuthenticatedLayout>
    );
}
