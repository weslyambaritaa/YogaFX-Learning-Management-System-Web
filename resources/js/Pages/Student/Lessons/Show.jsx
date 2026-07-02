import LockedContentDialog from "@/Components/student/LockedContentDialog";
import StudentStatusBadge from "@/Components/student/StudentStatusBadge";
import { Button } from "@/Components/ui/button";
import VideoJsPlayer from "@/Components/VideoJsPlayer";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, router } from "@inertiajs/react";
import { Check, ChevronRight, FileText, Volume2, X } from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";

const CONTENT_COLLAPSED_HEIGHT = 320;

function formatDurationParts(totalSeconds) {
    const safeSeconds = Math.max(0, Number(totalSeconds || 0));
    const hours = Math.floor(safeSeconds / 3600)
        .toString()
        .padStart(2, "0");
    const minutes = Math.floor((safeSeconds % 3600) / 60)
        .toString()
        .padStart(2, "0");
    const seconds = Math.floor(safeSeconds % 60)
        .toString()
        .padStart(2, "0");

    return { hours, minutes, seconds };
}

function workbookStorageKey(lessonId) {
    return `yogafx_workbook_downloaded_${lessonId}`;
}

function navigationBadgeLabel(item) {
    if (item.status === "current") {
        return "Current";
    }

    if (item.status === "completed") {
        return "Completed";
    }

    if (item.status === "locked") {
        return "Locked";
    }

    if (Number(item.progress_percentage ?? 0) > 0) {
        return "In Progress";
    }

    return "Available";
}

function navigationBadgeStatus(item) {
    if (item.status === "completed") {
        return "completed";
    }

    if (item.status === "locked") {
        return "locked";
    }

    if (item.status === "current") {
        return "current";
    }

    return "available";
}

function LessonNavCard({ item, onLockedClick }) {
    const body = (
        <div
            className={[
                "group overflow-hidden rounded-[5px] border p-2.5 transition sm:p-3",
                item.status === "current"
                    ? "border-[#DB202C]/60 bg-[#DB202C]/10 shadow-[0_10px_30px_rgba(219,32,44,0.16)]"
                    : "border-white/10 bg-white/[0.04] hover:border-white/20 hover:bg-white/[0.06]",
            ].join(" ")}
        >
            <div className="space-y-2.5 sm:space-y-3">
                <div className="relative overflow-hidden rounded-[5px] bg-[#161211]">
                    {item.thumbnail_url ? (
                        <img
                            src={item.thumbnail_url}
                            alt={item.title}
                            className="aspect-video h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]"
                        />
                    ) : (
                        <div className="aspect-video bg-[radial-gradient(circle_at_30%_20%,_rgba(227,120,61,0.4),_transparent_28%),linear-gradient(140deg,_rgba(255,255,255,0.09),_rgba(255,255,255,0.02)),linear-gradient(180deg,_#3a2318_0%,_#17110f_100%)]" />
                    )}

                    <div className="absolute inset-0 bg-gradient-to-t from-black/45 via-transparent to-transparent" />

                    <div className="absolute right-2.5 top-2.5">
                        <StudentStatusBadge
                            status={navigationBadgeStatus(item)}
                            label={navigationBadgeLabel(item)}
                            className="scale-[0.72] origin-top-right shadow-none"
                        />
                    </div>

                    {item.status === "current" ? (
                        <div className="absolute inset-x-0 bottom-0 h-1 bg-[#DB202C]" />
                    ) : null}
                </div>

                <div className="min-w-0 space-y-2 sm:space-y-2.5">
                    <div className="space-y-1.5">
                        <p className="font-['Montserrat'] text-[11px] font-medium uppercase tracking-[0.22em] text-white/40">
                            Lesson {item.sort_order}
                        </p>
                        <p className="line-clamp-2 font-['Montserrat'] text-[14px] font-medium leading-5 text-white">
                            {item.title}
                        </p>
                    </div>

                    <div className="space-y-1.5 sm:space-y-2">
                        <div className="flex items-center justify-between gap-3 font-['Montserrat'] text-[12px] font-medium text-white/45">
                            <span>Progress</span>
                            <span className="shrink-0">
                                {item.progress_percentage}%
                            </span>
                        </div>
                        <div className="h-1.5 overflow-hidden rounded-full bg-white/10">
                            <div
                                className={[
                                    "h-full rounded-full transition-all",
                                    item.status === "completed"
                                        ? "bg-emerald-500"
                                        : item.status === "locked"
                                          ? "bg-[#DB202C]"
                                          : item.status === "current"
                                            ? "bg-[#f15b3a]"
                                            : "bg-white",
                                ].join(" ")}
                                style={{
                                    width: `${item.progress_percentage}%`,
                                }}
                            />
                        </div>
                    </div>

                    <div className="inline-flex items-center gap-1.5 font-['Montserrat'] text-[12px] font-medium text-white/58">
                        <span>
                            {item.is_locked
                                ? "Locked for now"
                                : item.status === "current"
                                  ? "Currently playing"
                                  : "Open lesson"}
                        </span>
                        <ChevronRight className="size-3 transition group-hover:translate-x-1" />
                    </div>
                </div>
            </div>
        </div>
    );

    if (item.is_locked || !item.url) {
        return (
            <button
                type="button"
                onClick={() => onLockedClick(item.lock_reason)}
                className="w-full text-left"
            >
                {body}
            </button>
        );
    }

    return <Link href={item.url}>{body}</Link>;
}

function ContentSection({ content }) {
    const contentRef = useRef(null);
    const [isExpanded, setIsExpanded] = useState(false);
    const [isCollapsible, setIsCollapsible] = useState(false);

    useEffect(() => {
        const node = contentRef.current;

        if (!node) {
            return undefined;
        }

        const updateCollapsibleState = () => {
            setIsCollapsible(node.scrollHeight > CONTENT_COLLAPSED_HEIGHT + 24);
        };

        updateCollapsibleState();

        if (typeof ResizeObserver === "undefined") {
            window.addEventListener("resize", updateCollapsibleState);

            return () => {
                window.removeEventListener("resize", updateCollapsibleState);
            };
        }

        const observer = new ResizeObserver(() => {
            updateCollapsibleState();
        });

        observer.observe(node);
        window.addEventListener("resize", updateCollapsibleState);

        return () => {
            observer.disconnect();
            window.removeEventListener("resize", updateCollapsibleState);
        };
    }, [content]);

    useEffect(() => {
        setIsExpanded(false);
    }, [content]);

    if (!content) {
        return (
            <div className="rounded-[5px] border border-white/10 bg-white/[0.04] px-5 py-6 font-['Montserrat'] text-sm leading-7 text-white/60">
                Lesson content will appear here when this learning material
                includes written guidance.
            </div>
        );
    }

    return (
        <div className="rounded-[5px] border border-white/10 bg-white/[0.04] p-5">
            <div className="mb-4">
                <h2 className="font-['Montserrat'] text-[16px] font-semibold text-white">
                    Lesson Notes
                </h2>
                <p className="mt-1 font-['Montserrat'] text-sm text-white/50">
                    Written guidance for this practice.
                </p>
            </div>

            <div className="relative">
                <div
                    ref={contentRef}
                    className={[
                        "prose prose-invert max-w-none overflow-hidden font-['Montserrat'] prose-headings:text-white prose-li:text-white/72 prose-p:text-white/72 prose-strong:text-white transition-[max-height] duration-300",
                        isExpanded ? "max-h-none" : "max-h-[320px]",
                    ].join(" ")}
                    dangerouslySetInnerHTML={{
                        __html: content,
                    }}
                />

                {isCollapsible && !isExpanded ? (
                    <div className="pointer-events-none absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-[#171211] via-[#171211]/85 to-transparent" />
                ) : null}
            </div>

            {isCollapsible ? (
                <div className="mt-4">
                    <button
                        type="button"
                        onClick={() => setIsExpanded((current) => !current)}
                        className="font-['Montserrat'] text-sm font-semibold text-[#f15b3a] transition hover:text-[#ff7a5f]"
                    >
                        {isExpanded ? "Show less" : "Show more"}
                    </button>
                </div>
            ) : null}
        </div>
    );
}

export default function StudentLessonShow({ lesson, accessTimeSummary }) {
    const hasWorkbook = Boolean(lesson.workbook_download_url);
    const initialWorkbookDownloaded =
        Boolean(lesson.progress?.is_workbook_downloaded) ||
        (typeof window !== "undefined" &&
            window.localStorage.getItem(workbookStorageKey(lesson.id)) === "1");
    const [playerWarning, setPlayerWarning] = useState(null);
    const [watchProgress, setWatchProgress] = useState(
        lesson.progress?.watch_progress ?? 0,
    );
    const [isLessonDone, setIsLessonDone] = useState(
        Boolean(lesson.progress?.is_done),
    );
    const [assessmentState, setAssessmentState] = useState(lesson.assessment);
    const [moduleState, setModuleState] = useState(lesson.module);
    const [navigationItems, setNavigationItems] = useState(
        lesson.navigation ?? [],
    );
    const [nextLesson, setNextLesson] = useState(lesson.next_lesson);
    const [autoNextCountdown, setAutoNextCountdown] = useState(null);
    const [workbookDownloaded, setWorkbookDownloaded] = useState(
        initialWorkbookDownloaded,
    );
    const [isTriggeringWorkbook, setIsTriggeringWorkbook] = useState(false);
    const [downloadNotice, setDownloadNotice] = useState(null);
    const [showLockedDialog, setShowLockedDialog] = useState(false);
    const [lockedReason, setLockedReason] = useState(null);
    const [totalAccessSeconds, setTotalAccessSeconds] = useState(
        accessTimeSummary?.running_total_access_duration_seconds ?? 0,
    );
    const progressRequestRef = useRef({
        inFlight: false,
        latestSent: Number(lesson.progress?.watch_progress ?? 0),
        pending: null,
    });
    const autoNextStartedRef = useRef(false);
    const workbookTriggerAttemptedRef = useRef(false);
    const lessonVideoUrl = lesson.video?.hls_url ?? null;
    const playbackErrorMessage =
        typeof playerWarning === "string"
            ? playerWarning
            : (playerWarning?.message ?? null);
    const canAutoAdvance = Boolean(
        lesson.lesson_video_id && !assessmentState && nextLesson?.id,
    );
    const totalAccessParts = formatDurationParts(totalAccessSeconds);
    const canOpenNextLesson = Boolean(
        nextLesson?.is_unlocked && nextLesson?.url,
    );
    const autoNextProgress = useMemo(() => {
        if (autoNextCountdown === null) {
            return 0;
        }

        return ((10 - Math.max(0, autoNextCountdown)) / 10) * 100;
    }, [autoNextCountdown]);

    const currentNavigationItem = useMemo(
        () => navigationItems.find((item) => item.id === lesson.id) ?? null,
        [lesson.id, navigationItems],
    );

    const currentStatusLabel = currentNavigationItem
        ? navigationBadgeLabel(currentNavigationItem)
        : isLessonDone
          ? "Completed"
          : watchProgress > 0
            ? "In Progress"
            : "Current";

    const moduleLabel = lesson.module?.sort_order
        ? `Module ${lesson.module.sort_order}`
        : "Lesson";

    useEffect(() => {
        const persistedWorkbookDownloaded =
            typeof window !== "undefined" &&
            window.localStorage.getItem(workbookStorageKey(lesson.id)) === "1";

        setWatchProgress(lesson.progress?.watch_progress ?? 0);
        setIsLessonDone(Boolean(lesson.progress?.is_done));
        setAssessmentState(lesson.assessment);
        setModuleState(lesson.module);
        setNavigationItems(lesson.navigation ?? []);
        setNextLesson(lesson.next_lesson);
        setAutoNextCountdown(null);
        setDownloadNotice(null);
        setIsTriggeringWorkbook(false);
        setLockedReason(null);
        setWorkbookDownloaded(
            Boolean(lesson.progress?.is_workbook_downloaded) ||
                persistedWorkbookDownloaded,
        );
        autoNextStartedRef.current = false;
        workbookTriggerAttemptedRef.current = false;
        progressRequestRef.current = {
            inFlight: false,
            latestSent: Number(lesson.progress?.watch_progress ?? 0),
            pending: null,
        };
    }, [lesson]);

    useEffect(() => {
        if (typeof window === "undefined") {
            return;
        }

        const storageKey = workbookStorageKey(lesson.id);

        if (workbookDownloaded) {
            window.localStorage.setItem(storageKey, "1");
            return;
        }

        window.localStorage.removeItem(storageKey);
    }, [lesson.id, workbookDownloaded]);

    useEffect(() => {
        if (autoNextCountdown === null || !nextLesson?.url) {
            return undefined;
        }

        if (autoNextCountdown <= 0) {
            router.visit(
                route("lessons.show", { lesson: nextLesson.id, autoplay: 1 }),
            );

            return undefined;
        }

        const timeout = window.setTimeout(() => {
            setAutoNextCountdown((current) =>
                current === null ? null : Math.max(0, current - 1),
            );
        }, 1000);

        return () => window.clearTimeout(timeout);
    }, [autoNextCountdown, nextLesson]);

    useEffect(() => {
        if (
            !accessTimeSummary?.currently_active ||
            !accessTimeSummary?.active_session_login_at
        ) {
            setTotalAccessSeconds(
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

            setTotalAccessSeconds(
                (accessTimeSummary.total_access_duration_seconds ?? 0) +
                    elapsed,
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

    useEffect(() => {
        const refreshLessonState = () => {
            router.reload({
                only: ["lesson", "accessTimeSummary"],
                preserveScroll: true,
                preserveState: true,
            });
        };

        const handlePageShow = (event) => {
            if (event.persisted) {
                refreshLessonState();
            }
        };

        const handleVisibilityChange = () => {
            if (document.visibilityState === "visible") {
                refreshLessonState();
            }
        };

        window.addEventListener("pageshow", handlePageShow);
        document.addEventListener("visibilitychange", handleVisibilityChange);

        return () => {
            window.removeEventListener("pageshow", handlePageShow);
            document.removeEventListener(
                "visibilitychange",
                handleVisibilityChange,
            );
        };
    }, []);

    const triggerBrowserDownload = (downloadUrl) => {
        if (typeof window === "undefined" || !downloadUrl) {
            return;
        }

        const iframe = document.createElement("iframe");
        iframe.style.display = "none";
        iframe.src = downloadUrl;
        document.body.appendChild(iframe);

        window.setTimeout(() => {
            iframe.remove();
        }, 60000);
    };

    const readXsrfToken = () => {
        const xsrfCookie = document.cookie
            .split("; ")
            .find((item) => item.startsWith("XSRF-TOKEN="));

        return xsrfCookie
            ? decodeURIComponent(xsrfCookie.split("=").slice(1).join("="))
            : "";
    };

    useEffect(() => {
        if (
            typeof window === "undefined" ||
            !hasWorkbook ||
            workbookDownloaded ||
            isTriggeringWorkbook ||
            !lesson.workbook_trigger_url ||
            workbookTriggerAttemptedRef.current
        ) {
            return;
        }

        workbookTriggerAttemptedRef.current = true;
        setIsTriggeringWorkbook(true);

        const triggerWorkbookDelivery = async () => {
            try {
                const response = await fetch(lesson.workbook_trigger_url, {
                    method: "POST",
                    headers: {
                        Accept: "application/json",
                        "Content-Type": "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                        "X-XSRF-TOKEN": readXsrfToken(),
                    },
                    credentials: "same-origin",
                    body: JSON.stringify({}),
                });

                if (!response.ok) {
                    throw new Error(
                        `Failed to trigger workbook delivery (${response.status}).`,
                    );
                }

                const result = await response.json();
                const downloadUrl =
                    result?.download_url ?? lesson.workbook_download_url;

                setWorkbookDownloaded(
                    Boolean(result?.is_workbook_downloaded ?? true),
                );

                if (downloadUrl) {
                    triggerBrowserDownload(downloadUrl);
                }

                setDownloadNotice({
                    tone: "success",
                    title: result?.was_first_trigger
                        ? "Workbook download started"
                        : "Workbook ready",
                    message: result?.was_first_trigger
                        ? "Your workbook is being downloaded. We also sent it to your email as an attachment."
                        : "This workbook was already delivered before. You can download it again manually anytime.",
                });
            } catch (error) {
                console.error("Failed to trigger workbook delivery.", error);
                workbookTriggerAttemptedRef.current = false;
                setDownloadNotice({
                    tone: "warning",
                    title: "Workbook download needs manual fallback",
                    message:
                        "Your browser or device may have blocked the automatic download. Use the manual download button below.",
                });
            } finally {
                setIsTriggeringWorkbook(false);
            }
        };

        void triggerWorkbookDelivery();
    }, [
        hasWorkbook,
        isTriggeringWorkbook,
        lesson.workbook_download_url,
        lesson.workbook_trigger_url,
        workbookDownloaded,
    ]);

    const flushProgressUpdate = async () => {
        if (progressRequestRef.current.inFlight) {
            return;
        }

        const pendingProgress = progressRequestRef.current.pending;

        if (
            pendingProgress === null ||
            pendingProgress <= progressRequestRef.current.latestSent
        ) {
            return;
        }

        progressRequestRef.current.inFlight = true;
        progressRequestRef.current.pending = null;

        try {
            const response = await fetch(
                route("lessons.progress.update", lesson.id),
                {
                    method: "POST",
                    headers: {
                        Accept: "application/json",
                        "Content-Type": "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                        "X-XSRF-TOKEN": readXsrfToken(),
                    },
                    credentials: "same-origin",
                    body: JSON.stringify({
                        watch_progress: pendingProgress,
                    }),
                },
            );

            if (!response.ok) {
                throw new Error(
                    `Failed to persist lesson progress (${response.status}).`,
                );
            }

            const result = await response.json();
            const persistedProgress = Number(
                result?.watch_progress ?? pendingProgress,
            );
            const completedNow = Boolean(result?.is_done);

            progressRequestRef.current.latestSent = persistedProgress;
            setWatchProgress(persistedProgress);
            setIsLessonDone(completedNow);
            setAssessmentState((current) =>
                current
                    ? {
                          ...current,
                          is_unlocked:
                              current.is_unlocked ||
                              Boolean(result?.assessment_unlocked),
                      }
                    : current,
            );
            setNavigationItems((current) =>
                current.map((item) =>
                    item.id === lesson.id
                        ? {
                              ...item,
                              progress_percentage: persistedProgress,
                              status: completedNow ? "completed" : "current",
                          }
                        : item,
                ),
            );

            if (completedNow) {
                setModuleState((current) => {
                    if (!current || isLessonDone) {
                        return current;
                    }

                    const completedLessons = Math.min(
                        Number(current.completed_lessons ?? 0) + 1,
                        Number(current.lesson_count ?? 0),
                    );

                    return {
                        ...current,
                        completed_lessons: completedLessons,
                        progress_percentage:
                            Number(current.lesson_count ?? 0) > 0
                                ? Math.round(
                                      (completedLessons /
                                          Number(current.lesson_count)) *
                                          100,
                                  )
                                : 0,
                    };
                });

                setNavigationItems((current) =>
                    current.map((item) =>
                        item.id === nextLesson?.id
                            ? {
                                  ...item,
                                  is_locked: false,
                                  lock_reason: null,
                                  status:
                                      item.status === "locked"
                                          ? "available"
                                          : item.status,
                                  url:
                                      nextLesson?.url ??
                                      route("lessons.show", nextLesson.id),
                              }
                            : item,
                    ),
                );
                setNextLesson((current) =>
                    current
                        ? {
                              ...current,
                              is_unlocked: true,
                              lock_reason: null,
                              url:
                                  current.url ??
                                  route("lessons.show", current.id),
                          }
                        : current,
                );
            }
        } catch (error) {
            console.error("Failed to persist lesson watch progress.", error);
            progressRequestRef.current.pending = Math.max(
                pendingProgress,
                progressRequestRef.current.pending ?? 0,
            );
        } finally {
            progressRequestRef.current.inFlight = false;

            if (
                progressRequestRef.current.pending !== null &&
                progressRequestRef.current.pending >
                    progressRequestRef.current.latestSent
            ) {
                void flushProgressUpdate();
            }
        }
    };

    const handleProgressUpdate = (nextProgress) => {
        const normalizedProgress = Math.max(
            0,
            Math.min(100, Math.round(Number(nextProgress) || 0)),
        );

        if (normalizedProgress <= watchProgress) {
            return;
        }

        setWatchProgress(normalizedProgress);
        progressRequestRef.current.pending = Math.max(
            normalizedProgress,
            progressRequestRef.current.pending ?? 0,
        );
        void flushProgressUpdate();
    };

    const handlePlayerTimeUpdate = ({ remainingSeconds, isEnded }) => {
        if (!canAutoAdvance) {
            return;
        }

        if (isEnded) {
            if (!autoNextStartedRef.current) {
                autoNextStartedRef.current = true;
                setAutoNextCountdown(0);
            }

            return;
        }

        if (remainingSeconds <= 10 && remainingSeconds > 0) {
            autoNextStartedRef.current = true;
            setAutoNextCountdown(Math.ceil(remainingSeconds));

            return;
        }

        if (remainingSeconds > 10 && autoNextStartedRef.current) {
            autoNextStartedRef.current = false;
            setAutoNextCountdown(null);
        }
    };

    const openLockedDialog = (reason = null) => {
        setLockedReason(reason);
        setShowLockedDialog(true);
    };

    return (
        <AuthenticatedLayout
            studentVariant="immersive"
            studentContentClassName="pb-16"
        >
            <Head title={lesson.title} />

            <LockedContentDialog
                open={showLockedDialog}
                onOpenChange={setShowLockedDialog}
                kind="lesson"
                reason={lockedReason}
            />

            <div className="mx-auto flex max-w-[1400px] flex-col gap-5 pt-0 sm:gap-6 sm:px-6 sm:pt-4 lg:px-10">
                <section className="grid gap-4 sm:gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(340px,0.42fr)] pb-[50vh]">
                    <div className="min-w-0 space-y-0 sm:space-y-6 h-full">
                        <div className="sticky top-0 z-40 overflow-hidden bg-black shadow-[0_24px_90px_rgba(0,0,0,0.35)] sm:rounded-[5px] sm:border sm:border-white/10 lg:top-24">
                            {lessonVideoUrl ? (
                                <div className="aspect-video w-full">
                                    <VideoJsPlayer
                                        src={lessonVideoUrl}
                                        poster={lesson.thumbnail_url}
                                        className="h-full w-full overflow-hidden"
                                        autoplay={Boolean(lesson.autoplay)}
                                        hideProgressHandle={
                                            autoNextCountdown !== null
                                        }
                                        onPlaybackError={setPlayerWarning}
                                        onProgressUpdate={handleProgressUpdate}
                                        onTimeUpdate={handlePlayerTimeUpdate}
                                    />
                                </div>
                            ) : lesson.thumbnail_url ? (
                                <div className="aspect-video w-full overflow-hidden">
                                    <img
                                        src={lesson.thumbnail_url}
                                        alt={lesson.title}
                                        className="h-full w-full object-cover opacity-70"
                                    />
                                </div>
                            ) : (
                                <div className="aspect-video w-full bg-[radial-gradient(circle_at_30%_20%,_rgba(227,120,61,0.4),_transparent_28%),linear-gradient(140deg,_rgba(255,255,255,0.09),_rgba(255,255,255,0.02)),linear-gradient(180deg,_#3a2318_0%,_#17110f_100%)]" />
                            )}

                            <div className="absolute right-5 top-5">
                                <StudentStatusBadge
                                    status={
                                        currentNavigationItem
                                            ? navigationBadgeStatus(
                                                  currentNavigationItem,
                                              )
                                            : isLessonDone
                                              ? "completed"
                                              : "current"
                                    }
                                    label={currentStatusLabel}
                                />
                            </div>

                            {autoNextCountdown !== null && nextLesson?.title ? (
                                <div className="absolute inset-x-4 bottom-4 rounded-[5px] border border-white/15 bg-black/60 px-3 py-2.5 backdrop-blur sm:inset-x-5 sm:bottom-5 sm:px-5 sm:py-4">
                                    <div className="flex items-center justify-between gap-3 sm:gap-4">
                                        <div className="space-y-1 sm:space-y-2">
                                            <div className="font-['Montserrat'] text-[11px] font-semibold uppercase tracking-[0.16em] text-white/55 sm:text-sm sm:tracking-[0.18em]">
                                                Next Lesson
                                            </div>
                                            <div className="font-['Montserrat'] text-sm font-semibold text-white sm:text-lg">
                                                {nextLesson.title}
                                            </div>
                                            <div className="font-['Montserrat'] text-[11px] text-white/70 sm:text-sm">
                                                Continue in {autoNextCountdown}{" "}
                                                seconds
                                            </div>
                                        </div>
                                        {nextLesson.url ? (
                                            <Button
                                                asChild
                                                className="h-auto rounded-[5px] bg-[#DB202C] px-[9px] py-[7px] font-['Montserrat'] text-[11px] font-medium text-white hover:bg-[#c31c28] sm:px-[10px] sm:py-[8px] sm:text-[14px]"
                                            >
                                                <Link href={nextLesson.url}>
                                                    Next Lesson
                                                </Link>
                                            </Button>
                                        ) : null}
                                    </div>
                                    <div className="mt-3 h-1.5 overflow-hidden rounded-full bg-white/10 sm:mt-4 sm:h-2">
                                        <div
                                            className="h-full rounded-full bg-[#DB202C]"
                                            style={{
                                                width: `${autoNextProgress}%`,
                                            }}
                                        />
                                    </div>
                                </div>
                            ) : null}
                        </div>
                        <div className="bg-[#110f0f] shadow-[0_24px_90px_rgba(0,0,0,0.35)] sm:rounded-[5px] sm:border sm:border-white/10">
                            <div className="border-t border-white/10 bg-white/[0.04] px-4 py-5 sm:border-t-0 sm:p-6 lg:p-8">
                                <div className="space-y-4 sm:space-y-5">
                                    <div className="space-y-3">
                                        <p className="font-['Montserrat'] text-[12px] font-medium uppercase tracking-[0.22em] text-white/45">
                                            {moduleLabel}
                                        </p>
                                        <h1 className="font-['Montserrat'] text-[26px] font-semibold tracking-[-0.03em] text-white sm:text-[32px]">
                                            {lesson.title}
                                        </h1>
                                        <div className="flex flex-wrap items-center gap-3">
                                            <StudentStatusBadge
                                                status={
                                                    currentNavigationItem
                                                        ? navigationBadgeStatus(
                                                              currentNavigationItem,
                                                          )
                                                        : isLessonDone
                                                          ? "completed"
                                                          : "current"
                                                }
                                                label={currentStatusLabel}
                                            />
                                            <div className="rounded-full border border-white/10 bg-white/5 px-4 py-2 font-['Montserrat'] text-sm text-white/65">
                                                Watch progress {watchProgress}%
                                            </div>
                                        </div>
                                    </div>

                                    <div className="grid gap-3 sm:gap-4 xl:grid-cols-2">
                                        <div className="rounded-[5px] border border-white/10 bg-black/20 p-5">
                                            <h2 className="font-['Montserrat'] text-[14px] font-medium tracking-tight text-white">
                                                {moduleState?.completed_lessons ??
                                                    0}{" "}
                                                of{" "}
                                                {moduleState?.lesson_count ?? 0}{" "}
                                                lessons completed
                                            </h2>
                                            <div className="mt-4 h-2 overflow-hidden rounded-full bg-white/10">
                                                <div
                                                    className="h-full rounded-full bg-emerald-500"
                                                    style={{
                                                        width: `${moduleState?.progress_percentage ?? 0}%`,
                                                    }}
                                                />
                                            </div>
                                        </div>

                                        <div className="rounded-[5px] border border-white/10 bg-black/20 p-5">
                                            <h2 className="font-['Montserrat'] text-[14px] font-medium tracking-tight text-white">
                                                Total Access Time
                                            </h2>
                                            <div className="mt-3 font-['Montserrat'] text-3xl font-semibold tracking-[0.08em] text-white">
                                                {`${totalAccessParts.hours}:${totalAccessParts.minutes}:${totalAccessParts.seconds}`}
                                            </div>
                                        </div>
                                    </div>

                                    {playbackErrorMessage ? (
                                        <div className="rounded-[5px] border border-amber-400/25 bg-amber-500/10 px-5 py-4 font-['Montserrat'] text-sm leading-7 text-amber-100">
                                            {playbackErrorMessage}
                                        </div>
                                    ) : null}

                                    {downloadNotice ? (
                                        <div
                                            className={[
                                                "flex items-start justify-between gap-4 rounded-[5px] border px-5 py-4 font-['Montserrat'] text-sm leading-7",
                                                downloadNotice.tone ===
                                                "warning"
                                                    ? "border-amber-400/30 bg-amber-500/10 text-amber-100"
                                                    : "border-emerald-400/25 bg-emerald-500/10 text-emerald-100",
                                            ].join(" ")}
                                        >
                                            <div className="space-y-1">
                                                <p className="font-['Montserrat'] text-sm font-semibold text-white">
                                                    {downloadNotice.title}
                                                </p>
                                                <p>{downloadNotice.message}</p>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setDownloadNotice(null)
                                                }
                                                className="rounded-full border border-white/10 p-2 text-white/70 transition hover:bg-white/10 hover:text-white"
                                                aria-label="Dismiss workbook notice"
                                            >
                                                <X className="size-4" />
                                            </button>
                                        </div>
                                    ) : null}

                                    {lesson.assessment ? (
                                        <div className="rounded-[5px] border border-white/10 bg-black/20 px-5 py-5 text-white">
                                            <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                                <div>
                                                    <div className="font-['Montserrat'] text-[14px] font-semibold text-white">
                                                        {assessmentState.title}
                                                    </div>
                                                    <p className="mt-1 font-['Montserrat'] text-[14px] font-medium text-white">
                                                        {assessmentState.is_completed
                                                            ? "This assessment has already been completed."
                                                            : assessmentState.is_unlocked
                                                              ? "This assessment is ready to start."
                                                              : "Assessment unlocks after your lesson watch progress reaches 95%."}
                                                    </p>
                                                </div>

                                                {assessmentState.is_unlocked ? (
                                                    <Button
                                                        asChild
                                                        className="h-auto rounded-[5px] bg-[#DB202C] px-[10px] py-[8px] font-['Montserrat'] text-[14px] font-medium text-white hover:bg-[#c31c28]"
                                                    >
                                                        <Link
                                                            href={route(
                                                                "assessments.intro",
                                                                lesson.id,
                                                            )}
                                                        >
                                                            {assessmentState.current_attempt_id
                                                                ? "Resume Assessment"
                                                                : assessmentState.is_completed
                                                                  ? "View Assessment Result"
                                                                  : "Open Assessment"}
                                                        </Link>
                                                    </Button>
                                                ) : (
                                                    <StudentStatusBadge
                                                        status="locked"
                                                        label="Locked"
                                                    />
                                                )}
                                            </div>
                                        </div>
                                    ) : null}

                                    <div className="grid gap-3 sm:gap-4 xl:grid-cols-2">
                                        {lesson.workbook_download_url ? (
                                            <div className="rounded-[5px] border border-white/10 bg-black/20 p-5">
                                                <div className="mb-4">
                                                    <h2 className="font-['Montserrat'] text-[16px] font-semibold text-white">
                                                        Workbook
                                                    </h2>
                                                    <p className="mt-1 font-['Montserrat'] text-sm text-white/50">
                                                        Download the practice
                                                        workbook for this
                                                        lesson.
                                                    </p>
                                                </div>

                                                <div className="flex flex-wrap gap-3">
                                                    <Button
                                                        asChild
                                                        className="h-auto rounded-[5px] bg-[#DB202C] px-[10px] py-[8px] font-['Montserrat'] text-[14px] font-medium text-white hover:bg-[#c31c28]"
                                                    >
                                                        <a
                                                            href={
                                                                lesson.workbook_download_url
                                                            }
                                                            onClick={() => {
                                                                setWorkbookDownloaded(
                                                                    true,
                                                                );
                                                                setDownloadNotice(
                                                                    {
                                                                        tone: "success",
                                                                        title: "Manual workbook download",
                                                                        message:
                                                                            "If the automatic download did not start on your device, this manual download will open the workbook now.",
                                                                    },
                                                                );
                                                            }}
                                                        >
                                                            {workbookDownloaded ? (
                                                                <Check className="mr-2 size-4 rounded-full bg-emerald-500 p-0.5 text-white" />
                                                            ) : (
                                                                <FileText className="mr-2 size-4" />
                                                            )}
                                                            {workbookDownloaded
                                                                ? "Workbook Downloaded"
                                                                : "Download Workbook"}
                                                        </a>
                                                    </Button>

                                                    {hasWorkbook &&
                                                    isTriggeringWorkbook ? (
                                                        <div className="rounded-full border border-white/10 bg-white/5 px-4 py-2 font-['Montserrat'] text-sm text-white/70">
                                                            Starting workbook
                                                            download...
                                                        </div>
                                                    ) : null}
                                                </div>
                                            </div>
                                        ) : null}

                                        {lesson.audio_url ? (
                                            <div className="rounded-[5px] border border-white/10 bg-black/20 p-5">
                                                <div className="mb-3 flex items-center gap-2 font-['Montserrat'] text-sm font-medium text-white">
                                                    <Volume2 className="size-4 text-[#f15b3a]" />
                                                    Audio Companion
                                                </div>
                                                <audio
                                                    controls
                                                    src={lesson.audio_url}
                                                    className="w-full"
                                                    onPlay={() => {
                                                        window.dispatchEvent(
                                                            new CustomEvent(
                                                                "yogafx:audio-play",
                                                            ),
                                                        );
                                                    }}
                                                >
                                                    Your browser does not
                                                    support the audio element.
                                                </audio>
                                            </div>
                                        ) : null}
                                    </div>

                                    <ContentSection content={lesson.content} />

                                    <div className="flex flex-wrap items-center gap-3">
                                        <Button
                                            asChild
                                            variant="outline"
                                            className="h-auto rounded-[5px] border-white/15 bg-white/5 px-[10px] py-[8px] font-['Montserrat'] text-[14px] font-medium text-white hover:bg-white/10 hover:text-white"
                                        >
                                            <Link
                                                href={route(
                                                    "modules.show",
                                                    lesson.module.url_slug,
                                                )}
                                            >
                                                Back to module
                                            </Link>
                                        </Button>
                                        {nextLesson ? (
                                            canOpenNextLesson ? (
                                                <Button
                                                    asChild
                                                    className="h-auto rounded-[5px] bg-[#DB202C] px-[10px] py-[8px] font-['Montserrat'] text-[14px] font-medium text-white hover:bg-[#c31c28]"
                                                >
                                                    <Link href={nextLesson.url}>
                                                        Next Lesson
                                                    </Link>
                                                </Button>
                                            ) : (
                                                <Button
                                                    type="button"
                                                    onClick={() =>
                                                        openLockedDialog(
                                                            nextLesson?.lock_reason,
                                                        )
                                                    }
                                                    className="h-auto rounded-[5px] bg-[#DB202C] px-[10px] py-[8px] font-['Montserrat'] text-[14px] font-medium text-white hover:bg-[#c31c28]"
                                                >
                                                    Next Lesson
                                                </Button>
                                            )
                                        ) : null}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <aside className="min-w-0">
                        <div className="lg:sticky lg:top-24">
                            <div className="overflow-hidden rounded-[5px] border border-white/10 bg-[#110f0f] shadow-[0_24px_90px_rgba(0,0,0,0.28)]">
                                <div className="border-b border-white/10 px-5 py-5">
                                    <p className="font-['Montserrat'] text-[12px] font-medium uppercase tracking-[0.22em] text-white/40">
                                        Same Module
                                    </p>
                                    <h2 className="mt-2 font-['Montserrat'] text-[22px] font-medium tracking-tight text-white">
                                        {moduleState?.title ??
                                            "More lessons in this module"}
                                    </h2>
                                    <p className="mt-2 font-['Montserrat'] text-sm leading-6 text-white/55">
                                        {moduleState?.completed_lessons ?? 0} of{" "}
                                        {moduleState?.lesson_count ?? 0} lessons
                                        completed
                                    </p>
                                </div>

                                <div
                                    className="lg:h-[calc(100vh-7rem)] lg:overflow-y-auto [&::-webkit-scrollbar]:hidden"
                                    style={{
                                        scrollbarWidth: "none",
                                        msOverflowStyle: "none",
                                    }}
                                >
                                    <div className="space-y-2.5 p-3 sm:space-y-3 sm:p-4">
                                        {navigationItems?.map((item) => (
                                            <LessonNavCard
                                                key={item.id}
                                                item={item}
                                                onLockedClick={openLockedDialog}
                                            />
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </aside>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
