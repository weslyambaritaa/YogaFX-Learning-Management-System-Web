import { Button } from '@/Components/ui/button';
import VideoJsPlayer from '@/Components/VideoJsPlayer';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
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

function buildHlsUrl(cdnBaseUrl, videoId) {
    if (!cdnBaseUrl || !videoId) {
        return null;
    }

    return `${cdnBaseUrl.replace(/\/$/, '')}/${encodeURIComponent(videoId)}/playlist.m3u8`;
}

export default function StudentLessonShow({ lesson }) {
    const lessonVideoUrl = buildHlsUrl(lesson.stream_cdn_base_url, lesson.lesson_video_id);
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
                                {lesson.progress?.is_done
                                    ? 'Lesson completed'
                                    : `${lesson.progress?.watch_progress ?? 0}% lesson progress`}
                            </div>
                        </div>

                        <div className="space-y-6 p-6 sm:p-8">
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
                                                {lesson.assessment.title}
                                            </div>
                                            <p className="mt-1 text-sm text-slate-600">
                                                {lesson.assessment.is_unlocked
                                                    ? 'This assessment is ready to start.'
                                                    : 'Assessment unlocks after your lesson watch progress reaches 95%.'}
                                            </p>
                                        </div>

                                        {lesson.assessment.is_unlocked ? (
                                            <Link
                                                href={route('assessments.intro', lesson.id)}
                                                className="inline-flex items-center justify-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
                                            >
                                                {lesson.assessment.current_attempt_id
                                                    ? 'Resume Assessment'
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
                                <Link
                                    href={route('modules.show', lesson.module.url_slug)}
                                    className="inline-flex items-center gap-2 text-sm font-medium text-white/82 transition hover:text-white"
                                >
                                    Back to module
                                    <ChevronRight className="size-4" />
                                </Link>
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
                            You've completed {lesson.module?.completed_lessons ?? 0} of{' '}
                            {lesson.module?.lesson_count ?? 0} lessons
                        </h2>
                        <div className="mt-4 h-2 overflow-hidden rounded-full bg-white/10">
                            <div
                                className="h-full rounded-full bg-[#3DDC84]"
                                style={{ width: `${lesson.module?.progress_percentage ?? 0}%` }}
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
                            {lesson.navigation?.map((item) => {
                                const status =
                                    navigationStatusConfig[item.status] ??
                                    navigationStatusConfig.available;
                                const StatusIcon = status.icon;

                                return (
                                    <Link
                                        key={item.id}
                                        href={item.url}
                                        className="block rounded-[22px] border border-white/10 bg-black/18 p-4 transition hover:border-white/20 hover:bg-white/[0.05]"
                                    >
                                        <div className="flex items-start justify-between gap-4">
                                            <div className="space-y-2">
                                                <p className="text-xs uppercase tracking-[0.22em] text-white/42">
                                                    Lesson {item.sort_order}
                                                </p>
                                                <h3 className="text-sm font-medium leading-6 text-white">
                                                    {item.title}
                                                </h3>
                                            </div>
                                            <span
                                                className={`inline-flex items-center gap-2 text-xs font-medium ${status.className}`}
                                            >
                                                <StatusIcon className="size-4" />
                                                {status.label}
                                            </span>
                                        </div>

                                        <div className="mt-3 h-1.5 overflow-hidden rounded-full bg-white/10">
                                            <div
                                                className="h-full rounded-full bg-[#f15b3a]"
                                                style={{ width: `${item.progress_percentage}%` }}
                                            />
                                        </div>
                                    </Link>
                                );
                            })}
                        </div>
                    </div>

                    <div className="rounded-[28px] border border-white/10 bg-black/25 p-5 shadow-2xl backdrop-blur-md">
                        <div className="space-y-2">
                            <p className="text-xs uppercase tracking-[0.24em] text-white/45">
                                Running Total
                            </p>
                            <div className="text-3xl font-semibold tracking-[0.08em] text-white">
                                132:65:06
                            </div>
                            <p className="text-sm text-white/55">Login Time</p>
                        </div>
                    </div>
                </aside>
            </div>
        </AuthenticatedLayout>
    );
}
