import LockedContentDialog from '@/Components/student/LockedContentDialog';
import StudentBackButton from '@/Components/student/StudentBackButton';
import StudentStatusBadge from '@/Components/student/StudentStatusBadge';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import VideoJsPlayer from '@/Components/VideoJsPlayer';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Check, ChevronRight, FileText, Volume2 } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

function formatDurationParts(totalSeconds) {
    const safeSeconds = Math.max(0, Number(totalSeconds || 0));
    const hours = Math.floor(safeSeconds / 3600).toString().padStart(2, '0');
    const minutes = Math.floor((safeSeconds % 3600) / 60).toString().padStart(2, '0');
    const seconds = Math.floor(safeSeconds % 60).toString().padStart(2, '0');

    return { hours, minutes, seconds };
}

function workbookStorageKey(lessonId) {
    return `yogafx_workbook_downloaded_${lessonId}`;
}

function LessonNavCard({ item, onLockedClick }) {
    const body = (
        <div className="group h-full rounded-[14px] border border-white/10 bg-white/[0.04] p-3.5 transition hover:-translate-y-1 hover:border-white/20 hover:bg-white/[0.06]">
            <div className="space-y-3">
                <div className="relative overflow-hidden rounded-[12px] bg-[#161211]">
                    {item.thumbnail_url ? (
                        <img
                            src={item.thumbnail_url}
                            alt={item.title}
                            className="aspect-video h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]"
                        />
                    ) : (
                        <div className="aspect-video bg-[radial-gradient(circle_at_30%_20%,_rgba(227,120,61,0.4),_transparent_28%),linear-gradient(140deg,_rgba(255,255,255,0.09),_rgba(255,255,255,0.02)),linear-gradient(180deg,_#3a2318_0%,_#17110f_100%)]" />
                    )}
                </div>

                <div className="space-y-3">
                    <div className="flex items-start justify-between gap-3">
                        <div className="space-y-1.5">
                            <p className="text-base font-semibold tracking-tight text-white/82">
                                Lesson {item.sort_order}
                            </p>
                            <p className="line-clamp-2 text-base font-semibold leading-6 text-white">
                                {item.title}
                            </p>
                        </div>
                        <StudentStatusBadge status={item.status} className="scale-[0.92] origin-right" />
                    </div>
                </div>

                <div className="space-y-2">
                    <div className="flex items-center justify-between text-[11px] uppercase tracking-[0.18em] text-white/40">
                        <span>Progress</span>
                        <span>{item.progress_percentage}%</span>
                    </div>
                    <div className="h-1.5 overflow-hidden rounded-full bg-white/10">
                        <div
                            className={[
                                'h-full rounded-full',
                                item.status === 'completed' ? 'bg-emerald-500' : item.status === 'locked' ? 'bg-[#DB202C]' : 'bg-white',
                            ].join(' ')}
                            style={{ width: `${item.progress_percentage}%` }}
                        />
                    </div>
                </div>

                <div className="inline-flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-[0.16em] text-white/80">
                    {item.is_locked ? 'Complete previous lesson' : null}
                    <ChevronRight className="size-3.5 transition group-hover:translate-x-1" />
                </div>
            </div>
        </div>
    );

    if (item.is_locked || !item.url) {
        return (
            <button type="button" onClick={onLockedClick} className="text-left">
                {body}
            </button>
        );
    }

    return <Link href={item.url}>{body}</Link>;
}

export default function StudentLessonShow({ lesson, accessTimeSummary }) {
    const initialWorkbookDownloaded = Boolean(lesson.progress?.is_workbook_downloaded)
        || (typeof window !== 'undefined'
            && window.localStorage.getItem(workbookStorageKey(lesson.id)) === '1');
    const [playerWarning, setPlayerWarning] = useState(null);
    const [watchProgress, setWatchProgress] = useState(lesson.progress?.watch_progress ?? 0);
    const [isLessonDone, setIsLessonDone] = useState(Boolean(lesson.progress?.is_done));
    const [assessmentState, setAssessmentState] = useState(lesson.assessment);
    const [moduleState, setModuleState] = useState(lesson.module);
    const [navigationItems, setNavigationItems] = useState(lesson.navigation ?? []);
    const [nextLesson, setNextLesson] = useState(lesson.next_lesson);
    const [autoNextCountdown, setAutoNextCountdown] = useState(null);
    const [workbookDownloaded, setWorkbookDownloaded] = useState(initialWorkbookDownloaded);
    const [showWorkbookDialog, setShowWorkbookDialog] = useState(false);
    const [showLockedDialog, setShowLockedDialog] = useState(false);
    const [totalAccessSeconds, setTotalAccessSeconds] = useState(
        accessTimeSummary?.running_total_access_duration_seconds ?? 0,
    );
    const progressRequestRef = useRef({
        inFlight: false,
        latestSent: Number(lesson.progress?.watch_progress ?? 0),
        pending: null,
    });
    const autoNextStartedRef = useRef(false);
    const lessonVideoUrl = lesson.video?.hls_url ?? null;
    const playbackErrorMessage =
        typeof playerWarning === 'string'
            ? playerWarning
            : playerWarning?.message ?? null;
    const canAutoAdvance = Boolean(
        lesson.lesson_video_id && !assessmentState && nextLesson?.id,
    );
    const totalAccessParts = formatDurationParts(totalAccessSeconds);
    const workbookBlocksVideo = Boolean(
        lesson.progress?.requires_workbook_download
        && lesson.progress?.is_video_locked_until_workbook_downloaded
        && !workbookDownloaded,
    );
    const canOpenNextLesson = Boolean(nextLesson?.is_unlocked && nextLesson?.url);
    const autoNextProgress = useMemo(() => {
        if (autoNextCountdown === null) {
            return 0;
        }

        return ((10 - Math.max(0, autoNextCountdown)) / 10) * 100;
    }, [autoNextCountdown]);

    useEffect(() => {
        const persistedWorkbookDownloaded = typeof window !== 'undefined'
            && window.localStorage.getItem(workbookStorageKey(lesson.id)) === '1';

        setWatchProgress(lesson.progress?.watch_progress ?? 0);
        setIsLessonDone(Boolean(lesson.progress?.is_done));
        setAssessmentState(lesson.assessment);
        setModuleState(lesson.module);
        setNavigationItems(lesson.navigation ?? []);
        setNextLesson(lesson.next_lesson);
        setAutoNextCountdown(null);
        setWorkbookDownloaded(Boolean(lesson.progress?.is_workbook_downloaded) || persistedWorkbookDownloaded);
        autoNextStartedRef.current = false;
        progressRequestRef.current = {
            inFlight: false,
            latestSent: Number(lesson.progress?.watch_progress ?? 0),
            pending: null,
        };
    }, [lesson]);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        const storageKey = workbookStorageKey(lesson.id);

        if (workbookDownloaded) {
            window.localStorage.setItem(storageKey, '1');
            return;
        }

        window.localStorage.removeItem(storageKey);
    }, [lesson.id, workbookDownloaded]);

    useEffect(() => {
        if (autoNextCountdown === null || !nextLesson?.url) {
            return undefined;
        }

        if (autoNextCountdown <= 0) {
            router.visit(route('lessons.show', { lesson: nextLesson.id, autoplay: 1 }));

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
        if (!accessTimeSummary?.currently_active || !accessTimeSummary?.active_session_login_at) {
            setTotalAccessSeconds(
                accessTimeSummary?.running_total_access_duration_seconds ?? 0,
            );

            return undefined;
        }

        const updateTimer = () => {
            const loginAt = new Date(
                accessTimeSummary.active_session_login_at,
            ).getTime();
            const elapsed = Math.max(0, Math.floor((Date.now() - loginAt) / 1000));

            setTotalAccessSeconds(
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

    useEffect(() => {
        const refreshLessonState = () => {
            router.reload({
                only: ['lesson', 'accessTimeSummary'],
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
            if (document.visibilityState === 'visible') {
                refreshLessonState();
            }
        };

        window.addEventListener('pageshow', handlePageShow);
        document.addEventListener('visibilitychange', handleVisibilityChange);

        return () => {
            window.removeEventListener('pageshow', handlePageShow);
            document.removeEventListener('visibilitychange', handleVisibilityChange);
        };
    }, []);

    const readXsrfToken = () => {
        const xsrfCookie = document.cookie
            .split('; ')
            .find((item) => item.startsWith('XSRF-TOKEN='));

        return xsrfCookie ? decodeURIComponent(xsrfCookie.split('=').slice(1).join('=')) : '';
    };

    const flushProgressUpdate = async () => {
        if (progressRequestRef.current.inFlight) {
            return;
        }

        const pendingProgress = progressRequestRef.current.pending;

        if (pendingProgress === null || pendingProgress <= progressRequestRef.current.latestSent) {
            return;
        }

        progressRequestRef.current.inFlight = true;
        progressRequestRef.current.pending = null;

        try {
            const response = await fetch(route('lessons.progress.update', lesson.id), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': readXsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    watch_progress: pendingProgress,
                }),
            });

            if (!response.ok) {
                throw new Error(`Failed to persist lesson progress (${response.status}).`);
            }

            const result = await response.json();
            const persistedProgress = Number(result?.watch_progress ?? pendingProgress);
            const completedNow = Boolean(result?.is_done);

            progressRequestRef.current.latestSent = persistedProgress;
            setWatchProgress(persistedProgress);
            setIsLessonDone(completedNow);
            setAssessmentState((current) =>
                current
                    ? {
                          ...current,
                          is_unlocked: current.is_unlocked || Boolean(result?.assessment_unlocked),
                      }
                    : current,
            );
            setNavigationItems((current) =>
                current.map((item) =>
                    item.id === lesson.id
                        ? {
                              ...item,
                              progress_percentage: persistedProgress,
                              status: completedNow ? 'completed' : 'current',
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
                                ? Math.round((completedLessons / Number(current.lesson_count)) * 100)
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
                                  status: item.status === 'locked' ? 'available' : item.status,
                                  url: nextLesson?.url ?? route('lessons.show', nextLesson.id),
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
                              url: current.url ?? route('lessons.show', current.id),
                          }
                        : current,
                );
            }
        } catch (error) {
            console.error('Failed to persist lesson watch progress.', error);
            progressRequestRef.current.pending = Math.max(
                pendingProgress,
                progressRequestRef.current.pending ?? 0,
            );
        } finally {
            progressRequestRef.current.inFlight = false;

            if (
                progressRequestRef.current.pending !== null
                && progressRequestRef.current.pending > progressRequestRef.current.latestSent
            ) {
                void flushProgressUpdate();
            }
        }
    };

    const handleProgressUpdate = (nextProgress) => {
        const normalizedProgress = Math.max(0, Math.min(100, Math.round(Number(nextProgress) || 0)));

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
        if (!canAutoAdvance || workbookBlocksVideo) {
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

    const moduleLabel = lesson.module?.sort_order
        ? `Module ${lesson.module.sort_order} - ${lesson.module.title}`
        : lesson.module?.title ?? 'Lesson';

    return (
        <AuthenticatedLayout studentVariant="immersive" studentContentClassName="pb-16">
            <Head title={lesson.title} />

            <LockedContentDialog
                open={showLockedDialog}
                onOpenChange={setShowLockedDialog}
                kind="lesson"
            />

            <Dialog open={showWorkbookDialog} onOpenChange={setShowWorkbookDialog}>
                <DialogContent
                    className="max-w-md border-white/10 bg-[#141110] p-0 text-white ring-white/10"
                    overlayClassName="bg-black/65 backdrop-blur-sm"
                >
                    <div className="p-6">
                        <DialogHeader className="space-y-4">
                            <DialogTitle className="text-xl font-semibold text-white">
                                Download Workbook First
                            </DialogTitle>
                            <DialogDescription className="text-sm leading-7 text-white/65">
                                Download the workbook before watching this lesson video.
                            </DialogDescription>
                        </DialogHeader>
                    </div>
                    <DialogFooter className="border-white/10 bg-white/[0.03]">
                        {lesson.workbook_url ? (
                            <Button asChild className="bg-[#DB202C] text-white hover:bg-[#c31c28]">
                                <a
                                    href={lesson.workbook_url}
                                    onClick={() => {
                                        setWorkbookDownloaded(true);
                                        setShowWorkbookDialog(false);
                                    }}
                                >
                                    Download Workbook
                                </a>
                            </Button>
                        ) : null}
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <div className="mx-auto flex max-w-[1400px] flex-col gap-8 px-4 pt-8 sm:px-6 lg:px-10">
                <StudentBackButton fallbackHref={route('modules.show', lesson.module?.url_slug)} />

                <section className="space-y-6">
                    <div className="space-y-4">
                        <p className="text-2xl font-semibold tracking-tight text-white">
                            {moduleLabel}
                        </p>
                        <h1 className="text-4xl font-semibold tracking-[-0.03em] text-white sm:text-5xl">
                            {lesson.title}
                        </h1>
                    </div>

                    <div className="overflow-hidden rounded-[16px] border border-white/10 bg-[#110f0f] shadow-[0_24px_90px_rgba(0,0,0,0.35)]">
                        <div className="relative">
                            {lessonVideoUrl ? (
                                <div className="border-b border-white/10 bg-black/20 p-4 sm:p-6">
                                    {workbookBlocksVideo ? (
                                        <button
                                            type="button"
                                            onClick={() => setShowWorkbookDialog(true)}
                                            className="flex aspect-video w-full items-center justify-center rounded-[14px] border border-[#DB202C]/30 bg-[#DB202C]/10 px-6 text-center text-white"
                                        >
                                            Download the workbook first before watching this video.
                                        </button>
                                    ) : (
                                        <VideoJsPlayer
                                            src={lessonVideoUrl}
                                            poster={lesson.thumbnail_url}
                                            className="overflow-hidden rounded-[14px]"
                                            autoplay={Boolean(lesson.autoplay)}
                                            onPlaybackError={setPlayerWarning}
                                            onProgressUpdate={handleProgressUpdate}
                                            onTimeUpdate={handlePlayerTimeUpdate}
                                        />
                                    )}
                                </div>
                            ) : lesson.thumbnail_url ? (
                                <img
                                    src={lesson.thumbnail_url}
                                    alt={lesson.title}
                                    className="aspect-video h-full w-full object-cover opacity-70"
                                />
                            ) : (
                                <div className="aspect-video bg-[radial-gradient(circle_at_30%_20%,_rgba(227,120,61,0.4),_transparent_28%),linear-gradient(140deg,_rgba(255,255,255,0.09),_rgba(255,255,255,0.02)),linear-gradient(180deg,_#3a2318_0%,_#17110f_100%)]" />
                            )}

                            <div className="absolute left-5 top-5">
                                <StudentStatusBadge status={isLessonDone ? 'completed' : 'available'} />
                            </div>

                            {autoNextCountdown !== null && nextLesson?.title ? (
                                <div className="absolute inset-x-5 bottom-5 rounded-[14px] border border-white/15 bg-black/60 px-5 py-4 backdrop-blur">
                                    <div className="flex items-center justify-between gap-4">
                                        <div className="space-y-2">
                                            <div className="text-sm font-semibold uppercase tracking-[0.18em] text-white/55">
                                                Next Lesson
                                            </div>
                                            <div className="text-lg font-semibold text-white">
                                                {nextLesson.title}
                                            </div>
                                            <div className="text-sm text-white/70">
                                                Continue in {autoNextCountdown} seconds
                                            </div>
                                        </div>
                                        {nextLesson.url ? (
                                            <Button asChild className="rounded-full bg-[#DB202C] text-white hover:bg-[#c31c28]">
                                                <Link href={nextLesson.url}>Next Lesson</Link>
                                            </Button>
                                        ) : null}
                                    </div>
                                    <div className="mt-4 h-2 overflow-hidden rounded-full bg-white/10">
                                        <div
                                            className="h-full rounded-full bg-[#DB202C]"
                                            style={{ width: `${autoNextProgress}%` }}
                                        />
                                    </div>
                                </div>
                            ) : null}
                        </div>

                        <div className="space-y-6 p-6 sm:p-8">
                            {playbackErrorMessage && (
                                <div className="rounded-[12px] border border-amber-400/25 bg-amber-500/10 px-5 py-4 text-sm leading-7 text-amber-100">
                                    {playbackErrorMessage}
                                </div>
                            )}

                            <div className="flex flex-wrap gap-3">
                                {lesson.workbook_url ? (
                                    <Button
                                        asChild
                                        className="rounded-full bg-[#DB202C] text-white hover:bg-[#c31c28]"
                                    >
                                        <a
                                            href={lesson.workbook_url}
                                            onClick={() => setWorkbookDownloaded(true)}
                                        >
                                            {workbookDownloaded ? (
                                                <Check className="mr-2 size-4 rounded-full bg-emerald-500 p-0.5 text-white" />
                                            ) : (
                                                <FileText className="mr-2 size-4" />
                                            )}
                                            {workbookDownloaded ? 'Workbook Downloaded' : 'Download Workbook'}
                                        </a>
                                    </Button>
                                ) : null}
                            </div>

                            {lesson.audio_url && (
                                <div className="rounded-[12px] border border-white/10 bg-white/[0.04] p-5">
                                    <div className="mb-3 flex items-center gap-2 text-sm font-medium text-white">
                                        <Volume2 className="size-4 text-[#f15b3a]" />
                                        Audio Companion
                                    </div>
                                    <audio controls src={lesson.audio_url} className="w-full">
                                        Your browser does not support the audio element.
                                    </audio>
                                </div>
                            )}

                            {lesson.content ? (
                                <div
                                    className="prose prose-invert max-w-none prose-p:text-white/72 prose-headings:text-white prose-strong:text-white"
                                    dangerouslySetInnerHTML={{ __html: lesson.content }}
                                />
                            ) : (
                                <div className="rounded-[12px] border border-white/10 bg-white/[0.04] px-5 py-6 text-sm leading-7 text-white/60">
                                    Lesson content will appear here when this learning material includes written guidance.
                                </div>
                            )}

                            {lesson.assessment && (
                                <div className="rounded-[14px] border border-white/10 bg-white/[0.04] px-5 py-5 text-white">
                                    <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                        <div>
                                            <div className="text-base font-semibold text-white">
                                                {assessmentState.title}
                                            </div>
                                            <p className="mt-1 text-sm text-white/70">
                                                {assessmentState.is_completed
                                                    ? 'This assessment has already been completed.'
                                                    : assessmentState.is_unlocked
                                                        ? 'This assessment is ready to start.'
                                                        : 'Assessment unlocks after your lesson watch progress reaches 95%.'}
                                            </p>
                                        </div>

                                        {assessmentState.is_unlocked ? (
                                            <Button asChild className="rounded-full bg-[#DB202C] text-white hover:bg-[#c31c28]">
                                                <Link href={route('assessments.intro', lesson.id)}>
                                                    {assessmentState.current_attempt_id
                                                        ? 'Resume Assessment'
                                                        : assessmentState.is_completed
                                                            ? 'View Assessment Result'
                                                            : 'Open Assessment'}
                                                </Link>
                                            </Button>
                                        ) : (
                                            <StudentStatusBadge status="locked" />
                                        )}
                                    </div>
                                </div>
                            )}

                            <div className="flex flex-wrap items-center gap-3">
                                <Button asChild variant="outline" className="rounded-full border-white/15 bg-white/5 text-white hover:bg-white/10 hover:text-white">
                                    <Link href={route('modules.show', lesson.module.url_slug)}>
                                        Back to module
                                    </Link>
                                </Button>
                                {nextLesson ? (
                                    canOpenNextLesson ? (
                                        <Button asChild className="rounded-full bg-[#DB202C] text-white hover:bg-[#c31c28]">
                                            <Link href={nextLesson.url}>
                                                Next Lesson
                                            </Link>
                                        </Button>
                                    ) : (
                                        <Button
                                            type="button"
                                            onClick={() => setShowLockedDialog(true)}
                                            className="rounded-full bg-[#DB202C] text-white hover:bg-[#c31c28]"
                                        >
                                            Next Lesson
                                        </Button>
                                    )
                                ) : null}
                            </div>
                        </div>
                    </div>
                </section>

                <section className="space-y-5">
                    <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_280px]">
                        <div className="rounded-[14px] border border-white/10 bg-white/[0.04] p-5">
                            <p className="text-xs uppercase tracking-[0.22em] text-white/45">
                                Progress
                            </p>
                            <h2 className="mt-3 text-2xl font-semibold text-white">
                                {moduleState?.completed_lessons ?? 0} of {moduleState?.lesson_count ?? 0} lessons completed
                            </h2>
                            <div className="mt-4 h-2 overflow-hidden rounded-full bg-white/10">
                                <div
                                    className="h-full rounded-full bg-emerald-500"
                                    style={{ width: `${moduleState?.progress_percentage ?? 0}%` }}
                                />
                            </div>
                        </div>

                        <div className="rounded-[14px] border border-white/10 bg-white/[0.04] p-5">
                            <p className="text-xs uppercase tracking-[0.24em] text-white/45">
                                Total Access Time
                            </p>
                            <div className="mt-3 text-3xl font-semibold tracking-[0.08em] text-white">
                                {`${totalAccessParts.hours}:${totalAccessParts.minutes}:${totalAccessParts.seconds}`}
                            </div>
                        </div>
                    </div>

                    <div className="space-y-4">
                        <div>
                            <p className="text-xs uppercase tracking-[0.22em] text-white/45">
                                Lesson Navigation
                            </p>
                            <h2 className="mt-2 text-xl font-semibold text-white">
                                More lessons in this module
                            </h2>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            {navigationItems?.map((item) => (
                                <LessonNavCard
                                    key={item.id}
                                    item={item}
                                    onLockedClick={() => setShowLockedDialog(true)}
                                />
                            ))}
                        </div>
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
