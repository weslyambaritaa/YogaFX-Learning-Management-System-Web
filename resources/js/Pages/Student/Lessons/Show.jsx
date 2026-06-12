import { Button } from '@/Components/ui/button';
import VideoJsPlayer from '@/Components/VideoJsPlayer';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import {
    CheckCircle2,
    ChevronRight,
    FileText,
    Lock,
    PlayCircle,
    Volume2,
} from 'lucide-react';

const navigationStatusConfig = {
    completed: {
        icon: CheckCircle2,
        label: 'Completed',
        className: 'text-[#3DDC84]',
    },
    current: {
        icon: PlayCircle,
        label: 'Current Lesson',
        className: 'text-[#f15b3a]',
    },
    available: {
        icon: PlayCircle,
        label: 'Available',
        className: 'text-white/70',
    },
    locked: {
        icon: Lock,
        label: 'Locked',
        className: 'text-white/45',
    },
};

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

export default function StudentLessonShow({ lesson, accessTimeSummary }) {
    const [playerWarning, setPlayerWarning] = useState(null);
    const [watchProgress, setWatchProgress] = useState(lesson.progress?.watch_progress ?? 0);
    const [isLessonDone, setIsLessonDone] = useState(Boolean(lesson.progress?.is_done));
    const [assessmentState, setAssessmentState] = useState(lesson.assessment);
    const [moduleState, setModuleState] = useState(lesson.module);
    const [navigationItems, setNavigationItems] = useState(lesson.navigation ?? []);
    const [nextLesson, setNextLesson] = useState(lesson.next_lesson);
    const [autoNextCountdown, setAutoNextCountdown] = useState(null);
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
    const lessonVideoWarning = lesson.video?.warning_message ?? null;
    const playbackErrorMessage =
        typeof playerWarning === 'string'
            ? playerWarning
            : playerWarning?.message ?? null;
    const playbackWarning =
        playbackErrorMessage &&
        `${playbackErrorMessage} Confirm that the lesson video ID matches an accessible Bunny Stream video in the library used by this environment.`;
    const contentActions = [
        lesson.workbook_url
            ? {
                  label: 'Open Workbook',
                  href: lesson.workbook_url,
                  icon: FileText,
                  external: true,
              }
            : null,
    ].filter(Boolean);

    useEffect(() => {
        setWatchProgress(lesson.progress?.watch_progress ?? 0);
        setIsLessonDone(Boolean(lesson.progress?.is_done));
        setAssessmentState(lesson.assessment);
        setModuleState(lesson.module);
        setNavigationItems(lesson.navigation ?? []);
        setNextLesson(lesson.next_lesson);
        setAutoNextCountdown(null);
        autoNextStartedRef.current = false;
        progressRequestRef.current = {
            inFlight: false,
            latestSent: Number(lesson.progress?.watch_progress ?? 0),
            pending: null,
        };
    }, [lesson.id, lesson.progress?.is_done, lesson.progress?.watch_progress]);

    const resolvedNextLessonUrl = nextLesson?.url
        ?? (nextLesson?.id ? route('lessons.show', nextLesson.id) : null);
    const autoplayNextLessonUrl = nextLesson?.id
        ? route('lessons.show', { lesson: nextLesson.id, autoplay: 1 })
        : null;
    const canOpenNextLesson = Boolean(nextLesson?.is_unlocked && resolvedNextLessonUrl);
    const totalAccessParts = formatDurationParts(totalAccessSeconds);
    const canAutoAdvance = Boolean(
        lesson.lesson_video_id && !assessmentState && nextLesson?.id,
    );

    useEffect(() => {
        if (autoNextCountdown === null || !autoplayNextLessonUrl) {
            return undefined;
        }

        if (autoNextCountdown <= 0) {
            router.visit(autoplayNextLessonUrl);

            return undefined;
        }

        const timeout = window.setTimeout(() => {
            setAutoNextCountdown((current) =>
                current === null ? null : Math.max(0, current - 1),
            );
        }, 1000);

        return () => window.clearTimeout(timeout);
    }, [autoNextCountdown, autoplayNextLessonUrl]);

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
                          is_unlocked:
                              current.is_unlocked || Boolean(result?.assessment_unlocked),
                      }
                    : current,
            );
            setNavigationItems((current) =>
                current.map((item) =>
                    item.id === lesson.id
                        ? {
                              ...item,
                              progress_percentage: persistedProgress,
                              status: completedNow
                                  ? 'completed'
                                  : item.status === 'completed'
                                    ? 'completed'
                                    : 'current',
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
                                      (completedLessons / Number(current.lesson_count)) * 100,
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
                                      item.status === 'locked'
                                          ? 'available'
                                          : item.status,
                                  url:
                                      nextLesson?.url
                                      ?? route('lessons.show', nextLesson.id),
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
                progressRequestRef.current.pending !== null &&
                progressRequestRef.current.pending > progressRequestRef.current.latestSent
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

    return (
        <AuthenticatedLayout
            studentVariant="immersive"
            studentContentClassName="pb-16"
        >
            <Head title={lesson.title} />

            <div className="mx-auto grid max-w-[1400px] gap-8 px-4 pt-8 sm:px-6 lg:grid-cols-[minmax(0,1.75fr)_360px] lg:px-10">
                <section className="space-y-6">
                    <div className="space-y-4">
                        <p className="text-xs uppercase tracking-[0.28em] text-[#f2d9c8]">
                            {lesson.module?.title ?? 'Lesson'}
                        </p>
                        <h1 className="text-4xl font-semibold tracking-[-0.03em] text-white sm:text-5xl">
                            {lesson.title}
                        </h1>
                        <p className="max-w-3xl text-sm leading-7 text-white/65 sm:text-base">
                            Stay focused on the lesson experience. Your content, workbook,
                            media, and progression all live here in one premium learning
                            view.
                        </p>
                    </div>

                    <div className="overflow-hidden rounded-[32px] border border-white/10 bg-[#110f0f] shadow-[0_24px_90px_rgba(0,0,0,0.35)]">
                        <div className="relative">
                            {lessonVideoUrl ? (
                                <div className="border-b border-white/10 bg-black/20 p-4 sm:p-6">
                                    <VideoJsPlayer
                                        src={lessonVideoUrl}
                                        poster={lesson.thumbnail_url}
                                        className="overflow-hidden rounded-[24px]"
                                        autoplay={Boolean(lesson.autoplay)}
                                        onPlaybackError={setPlayerWarning}
                                        onProgressUpdate={handleProgressUpdate}
                                        onTimeUpdate={handlePlayerTimeUpdate}
                                    />
                                </div>
                            ) : lesson.thumbnail_url ? (
                                <>
                                    <img
                                        src={lesson.thumbnail_url}
                                        alt={lesson.title}
                                        className="aspect-[16/8] h-full w-full object-cover opacity-70"
                                    />
                                    <div className="absolute inset-0 bg-[linear-gradient(180deg,_rgba(0,0,0,0.16)_0%,_rgba(0,0,0,0.58)_100%)]" />
                                </>
                            ) : (
                                <div className="aspect-[16/8] bg-[radial-gradient(circle_at_30%_20%,_rgba(227,120,61,0.4),_transparent_28%),linear-gradient(140deg,_rgba(255,255,255,0.09),_rgba(255,255,255,0.02)),linear-gradient(180deg,_#3a2318_0%,_#17110f_100%)]" />
                            )}
                            <div className="absolute left-5 top-5 rounded-full border border-white/12 bg-black/30 px-4 py-2 text-xs uppercase tracking-[0.22em] text-white/72 backdrop-blur">
                                {isLessonDone
                                    ? 'Lesson completed'
                                    : `${watchProgress}% lesson progress`}
                            </div>
                            {autoNextCountdown !== null && nextLesson?.title ? (
                                <div className="absolute inset-x-5 bottom-5 rounded-2xl border border-white/15 bg-black/55 px-5 py-4 backdrop-blur">
                                    <div className="text-xs font-semibold uppercase tracking-[0.2em] text-white/55">
                                        Up Next
                                    </div>
                                    <div className="mt-2 flex items-center justify-between gap-4">
                                        <div>
                                            <div className="text-lg font-semibold text-white">
                                                {nextLesson.title}
                                            </div>
                                            <div className="mt-1 text-sm text-white/70">
                                                Auto continuing in {autoNextCountdown} seconds
                                            </div>
                                        </div>
                                        <div className="rounded-full border border-white/15 bg-white/10 px-4 py-2 text-lg font-semibold text-white">
                                            {autoNextCountdown}
                                        </div>
                                    </div>
                                </div>
                            ) : null}
                        </div>

                        <div className="space-y-6 p-6 sm:p-8">
                            {lessonVideoWarning && (
                                <div className="rounded-[24px] border border-amber-400/25 bg-amber-500/10 px-5 py-4 text-sm leading-7 text-amber-100">
                                    {lessonVideoWarning}
                                </div>
                            )}
                            {playbackWarning && (
                                <div className="rounded-[24px] border border-amber-400/25 bg-amber-500/10 px-5 py-4 text-sm leading-7 text-amber-100">
                                    <p>{playbackWarning}</p>
                                    <div className="mt-3 space-y-1 text-xs leading-6 text-amber-50/90">
                                        <p>
                                            <span className="font-semibold">Requested HLS URL:</span>{' '}
                                            {typeof playerWarning === 'object' && playerWarning?.src
                                                ? playerWarning.src
                                                : lessonVideoUrl ?? '-'}
                                        </p>
                                        <p>
                                            <span className="font-semibold">Lesson Video ID:</span>{' '}
                                            {lesson.lesson_video_id ?? '-'}
                                        </p>
                                        {typeof playerWarning === 'object' && playerWarning?.code !== null && (
                                            <p>
                                                <span className="font-semibold">Player Error Code:</span>{' '}
                                                {playerWarning.code}
                                            </p>
                                        )}
                                        {typeof playerWarning === 'object' && playerWarning?.networkState !== null && (
                                            <p>
                                                <span className="font-semibold">Network State:</span>{' '}
                                                {playerWarning.networkState}
                                            </p>
                                        )}
                                        {typeof playerWarning === 'object' && playerWarning?.readyState !== null && (
                                            <p>
                                                <span className="font-semibold">Ready State:</span>{' '}
                                                {playerWarning.readyState}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            )}

                            <div className="flex flex-wrap gap-3">
                                {contentActions.map((action) => {
                                    const Icon = action.icon;

                                    return (
                                        <Button
                                            key={action.label}
                                            asChild
                                            variant="outline"
                                            className="rounded-full border-white/15 bg-white/5 text-white hover:bg-white/10 hover:text-white"
                                        >
                                            <a
                                                href={action.href}
                                                target={action.external ? '_blank' : undefined}
                                                rel={action.external ? 'noreferrer' : undefined}
                                            >
                                                <Icon className="mr-2 size-4" />
                                                {action.label}
                                            </a>
                                        </Button>
                                    );
                                })}
                            </div>

                            {lesson.audio_url && (
                                <div className="rounded-[24px] border border-white/10 bg-white/[0.04] p-5">
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
                                <div className="rounded-[24px] border border-white/10 bg-white/[0.04] px-5 py-6 text-sm leading-7 text-white/60">
                                    Lesson content will appear here when this learning material
                                    includes written guidance.
                                </div>
                            )}

                            {lesson.assessment && (
                                <div className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-700">
                                    <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                        <div>
                                            <div className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                                Assessment
                                            </div>
                                            <div className="mt-1 text-base font-semibold text-slate-900">
                                                {assessmentState.title}
                                            </div>
                                            <p className="mt-1 text-sm text-slate-600">
                                                {assessmentState.is_completed
                                                    ? 'This assessment has already been completed.'
                                                    : assessmentState.is_unlocked
                                                    ? 'This assessment is ready to start.'
                                                    : 'Assessment unlocks after your lesson watch progress reaches 95%.'}
                                            </p>
                                        </div>

                                        {assessmentState.is_unlocked ? (
                                            <Link
                                                href={route('assessments.intro', lesson.id)}
                                                className="inline-flex items-center justify-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
                                            >
                                                {assessmentState.current_attempt_id
                                                    ? 'Resume Assessment'
                                                    : assessmentState.is_completed
                                                      ? 'View Assessment Result'
                                                      : 'Open Assessment'}
                                            </Link>
                                        ) : (
                                            <div className="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-medium text-amber-800">
                                                Locked
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}

                            {lesson.module && (
                                <div className="flex flex-wrap items-center gap-3">
                                    <Link
                                        href={route('modules.show', lesson.module.url_slug)}
                                        className="inline-flex items-center gap-2 text-sm font-medium text-white/82 transition hover:text-white"
                                    >
                                        Back to module
                                        <ChevronRight className="size-4" />
                                    </Link>
                                    {nextLesson ? (
                                        canOpenNextLesson ? (
                                            <Button
                                                asChild
                                                className="bg-[#e24848] text-white hover:bg-[#f05a5a]"
                                            >
                                                <Link href={resolvedNextLessonUrl}>
                                                    Next Lesson
                                                </Link>
                                            </Button>
                                        ) : (
                                            <div className="rounded-full border border-amber-400/25 bg-amber-500/10 px-4 py-2 text-sm text-amber-100">
                                                {nextLesson.lock_reason ?? 'Finish this lesson before continuing.'}
                                            </div>
                                        )
                                    ) : null}
                                </div>
                            )}

                        </div>
                    </div>
                </section>

                <aside className="space-y-5">
                    <div className="rounded-[28px] border border-white/10 bg-white/[0.04] p-5 backdrop-blur">
                        <p className="text-xs uppercase tracking-[0.22em] text-white/45">
                            Progress
                        </p>
                        <h2 className="mt-3 text-2xl font-semibold text-white">
                            You've completed {moduleState?.completed_lessons ?? 0} of{' '}
                            {moduleState?.lesson_count ?? 0} lessons
                        </h2>
                        <div className="mt-4 h-2 overflow-hidden rounded-full bg-white/10">
                            <div
                                className="h-full rounded-full bg-[#3DDC84]"
                                style={{ width: `${moduleState?.progress_percentage ?? 0}%` }}
                            />
                        </div>
                        <p className="mt-3 text-sm text-white/58">
                            Keep your rhythm steady and move through the module one lesson at
                            a time.
                        </p>
                    </div>

                    <div className="rounded-[28px] border border-white/10 bg-white/[0.04] p-5 backdrop-blur">
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <p className="text-xs uppercase tracking-[0.22em] text-white/45">
                                    Lesson Navigation
                                </p>
                                <h2 className="mt-2 text-xl font-semibold text-white">
                                    More lessons in this module
                                </h2>
                            </div>
                        </div>

                        <div className="mt-5 space-y-3">
                            {navigationItems?.map((item) => {
                                const status =
                                    navigationStatusConfig[item.status] ??
                                    navigationStatusConfig.available;
                                const StatusIcon = status.icon;
                                const NavigationTag = item.url ? Link : 'div';

                                return (
                                    <NavigationTag
                                        key={item.id}
                                        {...(item.url ? { href: item.url } : {})}
                                    className="block rounded-[22px] border border-white/10 bg-black/18 p-4 transition hover:border-white/20 hover:bg-white/[0.05]"
                                    >
                                        <div className="flex items-start gap-4">
                                            <div className="h-20 w-28 shrink-0 overflow-hidden rounded-2xl bg-white/5">
                                                {item.thumbnail_url ? (
                                                    <img
                                                        src={item.thumbnail_url}
                                                        alt={item.title}
                                                        className="h-full w-full object-cover"
                                                    />
                                                ) : null}
                                            </div>
                                            <div className="flex min-w-0 flex-1 items-start justify-between gap-4">
                                                <div className="space-y-2">
                                                    <p className="text-xs uppercase tracking-[0.22em] text-white/42">
                                                        Lesson {item.sort_order}
                                                    </p>
                                                    <h3 className="text-sm font-medium leading-6 text-white">
                                                        {item.title}
                                                    </h3>
                                                    {item.is_locked && item.lock_reason ? (
                                                        <p className="text-xs leading-5 text-amber-200/80">
                                                            {item.lock_reason}
                                                        </p>
                                                    ) : null}
                                                </div>
                                                <span
                                                    className={`inline-flex items-center gap-2 text-xs font-medium ${status.className}`}
                                                >
                                                    <StatusIcon className="size-4" />
                                                    {status.label}
                                                </span>
                                            </div>
                                        </div>

                                        <div className="mt-3 h-1.5 overflow-hidden rounded-full bg-white/10">
                                            <div
                                                className="h-full rounded-full bg-[#f15b3a]"
                                                style={{ width: `${item.progress_percentage}%` }}
                                            />
                                        </div>
                                    </NavigationTag>
                                );
                            })}
                        </div>
                    </div>

                    <div className="rounded-[28px] border border-white/10 bg-black/25 p-5 shadow-2xl backdrop-blur-md">
                        <div className="space-y-5">
                            <div className="space-y-2">
                                <p className="text-xs uppercase tracking-[0.24em] text-white/45">
                                    Total Access Time
                                </p>
                                <div className="text-3xl font-semibold tracking-[0.08em] text-white">
                                    {`${totalAccessParts.hours}:${totalAccessParts.minutes}:${totalAccessParts.seconds}`}
                                </div>
                                <p className="text-sm text-white/55">
                                    Cumulative student access time
                                </p>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </AuthenticatedLayout>
    );
}
