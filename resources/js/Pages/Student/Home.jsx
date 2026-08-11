import AppStoreBadges from "@/Components/public/AppStoreBadges";
import LockedContentDialog from "@/Components/student/LockedContentDialog";
import StudentStatusBadge from "@/Components/student/StudentStatusBadge";
import WelcomeToYogaFXDialog from "@/Components/student/WelcomeToYogaFXDialog";
import { Button } from "@/Components/ui/button";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, usePage } from "@inertiajs/react";
import { ChevronRight, Play, X } from "lucide-react";
import { useEffect, useRef, useState } from "react";

const FONT_FAMILY = "'Montserrat', sans-serif";
const DASHBOARD_INTRO_IMAGE_URL = "/images/yogafx-student-dashboard-start.png";

function toTitleCase(value) {
    return String(value ?? "")
        .trim()
        .toLowerCase()
        .replace(/\b[a-z]/g, (letter) => letter.toUpperCase());
}

function formatDurationParts(totalSeconds) {
    const safeSeconds = Math.max(0, Number(totalSeconds || 0));
    return {
        hours: String(Math.floor(safeSeconds / 3600)).padStart(2, "0"),
        minutes: String(Math.floor((safeSeconds % 3600) / 60)).padStart(2, "0"),
        seconds: String(Math.floor(safeSeconds % 60)).padStart(2, "0"),
    };
}

function useLiveAccessSeconds(accessTimeSummary) {
    const [liveSeconds, setLiveSeconds] = useState(
        accessTimeSummary?.running_total_access_duration_seconds ?? 0,
    );

    useEffect(() => {
        const runningTotal = Number(
            accessTimeSummary?.running_total_access_duration_seconds ?? 0,
        );

        if (
            !accessTimeSummary?.currently_active ||
            !accessTimeSummary?.active_session_login_at
        ) {
            setLiveSeconds(runningTotal);
            return undefined;
        }

        const loginAt = new Date(
            accessTimeSummary.active_session_login_at,
        ).getTime();

        if (!Number.isFinite(loginAt)) {
            setLiveSeconds(runningTotal);
            return undefined;
        }

        const tick = () => {
            const elapsed = Math.max(
                0,
                Math.floor((Date.now() - loginAt) / 1000),
            );

            setLiveSeconds(runningTotal + elapsed);
        };

        tick();

        const interval = window.setInterval(tick, 1000);

        const handleVisibilityChange = () => {
            if (!document.hidden) {
                tick();
            }
        };

        window.addEventListener("focus", tick);
        document.addEventListener("visibilitychange", handleVisibilityChange);

        return () => {
            window.clearInterval(interval);
            window.removeEventListener("focus", tick);
            document.removeEventListener(
                "visibilitychange",
                handleVisibilityChange,
            );
        };
    }, [
        accessTimeSummary?.active_session_login_at,
        accessTimeSummary?.currently_active,
        accessTimeSummary?.running_total_access_duration_seconds,
    ]);

    return liveSeconds;
}

function AccessTimeCard({ liveSeconds }) {
    const parts = formatDurationParts(liveSeconds);

    return (
        <div
            className="inline-flex items-center gap-5 rounded-[5px] border border-white/10 bg-black/45 px-6 py-4 text-white backdrop-blur"
            style={{ fontFamily: FONT_FAMILY }}
        >
            <div>
                <div className="text-xs text-white/45">Running Total</div>
                <div className="text-xs text-white/45">Login Time</div>
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
            className="space-y-2 rounded-[14px] border border-white/12 bg-white/[0.04] p-3 md:hidden"
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
                        label={
                            module.status === "in_progress" ? "Available" : null
                        }
                    />
                </div>
            </div>

            <div className="space-y-1.5">
                <div className="space-y-1">
                    <div className="text-sm font-semibold text-white/82 sm:text-base">
                        Module {module.sort_order}
                    </div>
                    <div className="line-clamp-2 text-sm font-semibold leading-5 text-white sm:text-base">
                        {module.title}
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

                <div className="inline-flex items-center gap-1.5 text-[11px] font-medium text-white/80">
                    {module.status === "locked"
                        ? "Complete Previous Module"
                        : "Click this to open the module"}
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
                        label={
                            module.status === "in_progress" ? "Available" : null
                        }
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
                        className="inline-flex items-center gap-2 text-white/78"
                        style={{
                            fontFamily: FONT_FAMILY,
                            fontSize: "14px",
                            fontWeight: 500,
                        }}
                    >
                        {module.status === "locked"
                            ? "Complete Previous Module"
                            : "Click this to open the module"}
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

function StudentDashboardIntro({
    studentName,
    accessTierDescription,
    closing,
    onClose,
}) {
    const formattedAccessTierDescription = toTitleCase(accessTierDescription);

    return (
        <div
            className={[
                "grid overflow-hidden will-change-[grid-template-rows,opacity,transform] transition-[grid-template-rows,opacity,transform] duration-[650ms] ease-[cubic-bezier(0.22,1,0.36,1)]",
                closing
                    ? "grid-rows-[0fr] -translate-y-6 opacity-0"
                    : "grid-rows-[1fr] translate-y-0 opacity-100",
            ].join(" ")}
        >
            <div className="min-h-0 overflow-hidden">
                <section
                    className="w-full bg-black text-white"
                    style={{ fontFamily: FONT_FAMILY }}
                >
                    <div className="mx-auto w-full max-w-[1400px] px-4 py-8 sm:px-6 sm:py-10 lg:px-10 lg:py-12">
                        <div className="mx-auto max-w-[1280px]">
                            <h1 className="mb-5 text-[28px] font-bold leading-tight tracking-[-0.03em] text-white sm:mb-6 sm:text-[38px]">
                                Hi {studentName}, Welcome back!
                            </h1>

                            <div className="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-3 sm:gap-4">
                                <div className="min-w-0">
                                    <button
                                        type="button"
                                        onClick={onClose}
                                        className="block w-full cursor-pointer overflow-hidden rounded-[6px] bg-black"
                                        aria-label="Start your YogaFX course"
                                    >
                                        <div className="aspect-video w-full bg-black">
                                            <img
                                                src={DASHBOARD_INTRO_IMAGE_URL}
                                                alt="Bikram Hot 26&2 Yoga Teacher Training Online Course"
                                                loading="eager"
                                                fetchPriority="high"
                                                decoding="async"
                                                className="block h-full w-full object-contain"
                                            />
                                        </div>
                                    </button>

                                    <div className="mt-4 overflow-hidden rounded-[6px] bg-[#DB202C] px-2.5 py-2.5 text-center sm:mt-5 sm:px-4 sm:py-3">
                                        <h2
                                            className="whitespace-nowrap font-bold leading-none text-white"
                                            style={{
                                                fontSize:
                                                    "clamp(6px, 1.55vw, 22px)",
                                            }}
                                        >
                                            Exclusive Access for YogaFX RYT 200{" "}
                                            {formattedAccessTierDescription}
                                        </h2>
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    onClick={onClose}
                                    aria-label="Close welcome section"
                                    className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border-2 border-white bg-black text-white shadow-[0_6px_24px_rgba(0,0,0,0.4)] transition-colors duration-200 hover:bg-white/10 sm:h-12 sm:w-12"
                                >
                                    <X
                                        className="h-6 w-6 stroke-[3.5] sm:h-7 sm:w-7"
                                        aria-hidden="true"
                                    />
                                </button>
                            </div>

                            <div className="mx-auto mt-7 max-w-5xl sm:mt-8">
                                <p className="text-base font-semibold text-white sm:text-lg">
                                    Dear {studentName}
                                </p>

                                <p className="mt-4 text-base leading-7 text-white/85 sm:text-lg sm:leading-8">
                                    We are so happy to have you join us as a
                                    student on our RYT 200{" "}
                                    {formattedAccessTierDescription}
                                </p>

                                <p className="mt-5 text-base leading-7 text-white/75 sm:text-lg sm:leading-8">
                                    This is your online learning dashboard. From
                                    here, you will access all your modules
                                    including posture clinics, dialogue,
                                    lectures, videos, and assessments — our
                                    fully structured, step-by-step platform is
                                    designed to help you deepen your practice,
                                    grow your confidence, and become a certified
                                    Hot Yoga teacher from anywhere in the world.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    );
}

export default function StudentHome({
    studentContext,
    accessTimeSummary,
    continueLearning,
    availableModulesSection,
    assignmentMilestone,
    homeExperience,
    showWelcomePopup,
}) {
    const [selectedModule, setSelectedModule] = useState(null);
    const [lockedModuleOpen, setLockedModuleOpen] = useState(false);
    const [lockedLessonOpen, setLockedLessonOpen] = useState(false);
    const [welcomePopupOpen, setWelcomePopupOpen] = useState(
        Boolean(showWelcomePopup),
    );
    const [dashboardIntroVisible, setDashboardIntroVisible] = useState(false);
    const [dashboardIntroClosing, setDashboardIntroClosing] = useState(false);
    const rawModules = availableModulesSection?.items ?? [];
    const studentName = studentContext?.display_name ?? "Student";
    const { auth, appDownload } = usePage().props;
    const authUser = auth.user;
    const accessTierLabel =
        studentContext?.access_tier?.name ??
        authUser?.access_tier?.name ??
        "Access Tier";
    const accessTierDescription =
        studentContext?.access_tier?.description ??
        authUser?.access_tier?.description ??
        accessTierLabel;
    const liveAccessSeconds = useLiveAccessSeconds(accessTimeSummary);

    const mobileAccessTimeParts = formatDurationParts(liveAccessSeconds);
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
        const image = new Image();

        image.src = DASHBOARD_INTRO_IMAGE_URL;

        if (typeof image.decode === "function") {
            image.decode().catch(() => {
                // The browser can still render the image from cache/network.
            });
        }
    }, []);

    const handleContinueUsingBrowser = () => {
        setWelcomePopupOpen(false);

        if (!showWelcomePopup) {
            return;
        }

        setDashboardIntroClosing(false);
        setDashboardIntroVisible(true);

        window.requestAnimationFrame(() => {
            window.scrollTo({
                top: 0,
                behavior: "smooth",
            });
        });
    };

    const closeDashboardIntro = () => {
        if (dashboardIntroClosing) {
            return;
        }

        setDashboardIntroClosing(true);

        window.setTimeout(() => {
            setDashboardIntroVisible(false);
            setDashboardIntroClosing(false);
        }, 650);
    };

    return (
        <AuthenticatedLayout
            studentVariant="immersive"
            studentContentClassName="pb-0"
        >
            <Head title="Home" />

            <WelcomeToYogaFXDialog
                open={welcomePopupOpen}
                onOpenChange={setWelcomePopupOpen}
                onContinueBrowser={handleContinueUsingBrowser}
                appDownload={appDownload}
                studentName={studentName}
                accessTierLabel={accessTierLabel}
            />
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

            {dashboardIntroVisible ? (
                <StudentDashboardIntro
                    studentName={studentName}
                    accessTierDescription={accessTierDescription}
                    closing={dashboardIntroClosing}
                    onClose={closeDashboardIntro}
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
                </div>
            </section>

            <section className="relative mx-auto hidden w-full max-w-[1400px] overflow-hidden bg-black sm:block">
                {continueLearning?.thumbnail_url ? (
                    <img
                        src={continueLearning.thumbnail_url}
                        alt=""
                        loading="eager"
                        fetchPriority="high"
                        decoding="async"
                        className="block h-auto w-full object-contain"
                    />
                ) : (
                    <div className="aspect-video w-full bg-[radial-gradient(circle_at_18%_28%,rgba(173,76,38,0.55),transparent_36%),linear-gradient(160deg,#1e1210,#0a0908)]" />
                )}

                <div className="pointer-events-none absolute inset-0 bg-[linear-gradient(to_right,rgba(0,0,0,0.88)_0%,rgba(0,0,0,0.42)_52%,rgba(0,0,0,0.08)_100%)]" />

                <div className="absolute inset-0 z-10 flex items-end">
                    <div className="mx-auto flex w-full max-w-[1400px] flex-col gap-6 px-4 pb-6 sm:gap-8 sm:px-6 sm:pb-8 lg:flex-row lg:items-end lg:justify-between lg:px-10">
                        <div className="max-w-2xl space-y-3 text-white sm:space-y-5">
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

                            <h1
                                style={{
                                    fontFamily: FONT_FAMILY,
                                    fontWeight: 700,
                                }}
                                className="text-[36px] leading-[1.04] tracking-[-0.03em] text-white sm:text-[48px] sm:leading-[1.02]"
                            >
                                {continueLearning?.title ??
                                    homeExperience?.hero_title ??
                                    "Start your learning journey"}
                            </h1>

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
                                    liveSeconds={liveAccessSeconds}
                                />
                            </div>
                        ) : null}
                    </div>
                </div>
            </section>

            <div className="relative z-10 mx-auto flex max-w-[1400px] flex-col gap-6 px-4 pt-6 sm:px-6 lg:px-10">
                <section className="space-y-6">
                    <div className="px-3.5 md:px-0">
                        <h1
                            className="text-white"
                            style={{
                                fontFamily: FONT_FAMILY,
                                fontSize: "22px",
                                fontWeight: 500,
                            }}
                        >
                            All Modules
                        </h1>
                    </div>

                    <div>
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
                            style={{
                                fontFamily: FONT_FAMILY,
                            }}
                        >
                            No modules are available for your current access
                            tier.
                        </div>
                    ) : null}
                </section>

                {appDownload?.has_any_link ? (
                    <section className="sm:hidden">
                        <div
                            className=" px-4 py-5 text-center text-white"
                            style={{ fontFamily: FONT_FAMILY }}
                        >
                            <div className="space-y-1.5">
                                <h2 className="text-base font-semibold">
                                    Get it on your mobile!
                                </h2>
                            </div>
                            <div className="mt-4">
                                <AppStoreBadges
                                    googlePlayUrl={appDownload.google_play_url}
                                    appStoreUrl={appDownload.app_store_url}
                                />
                            </div>
                        </div>
                    </section>
                ) : null}

                <footer
                    className="w-full py-5 text-center"
                    style={{ fontFamily: FONT_FAMILY }}
                >
                    <p className="text-sm font-bold text-white/70">
                        © 2026 Yoga
                        <span className="text-[#DB202C]">FX</span>
                    </p>
                </footer>
            </div>
        </AuthenticatedLayout>
    );
}
