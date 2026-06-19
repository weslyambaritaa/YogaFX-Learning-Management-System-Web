import { Button } from '@/Components/ui/button';
import VideoJsPlayer from '@/Components/VideoJsPlayer';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, PlayCircle } from 'lucide-react';
import { useState } from 'react';

const navigationStatusConfig = {
    current: {
        label: 'Current Lecture',
        className: 'text-[#f15b3a]',
    },
    available: {
        label: 'Available',
        className: 'text-white/70',
    },
};

export default function StudentCourseShow({ course }) {
    const [playerWarning, setPlayerWarning] = useState(null);
    const playbackErrorMessage =
        typeof playerWarning === 'string'
            ? playerWarning
            : playerWarning?.message ?? null;

    return (
        <AuthenticatedLayout
            studentVariant="immersive"
            studentContentClassName="pb-16"
        >
            <Head title={course.title} />

            <div className="mx-auto grid max-w-[1400px] gap-8 px-4 pt-8 sm:px-6 lg:grid-cols-[minmax(0,1.75fr)_360px] lg:px-10">
                <section className="space-y-6">
                    <div className="space-y-4">
                        <p className="text-xs uppercase tracking-[0.28em] text-[#f2d9c8]">
                            Video Lecturer
                        </p>
                        <h1 className="text-4xl font-semibold tracking-[-0.03em] text-white sm:text-5xl">
                            {course.title}
                        </h1>
                        <p className="max-w-3xl text-sm leading-7 text-white/65 sm:text-base">
                            {course.description || 'Premium YogaFX lecturer content stays inside a full lesson-like viewing experience, so playback never feels detached from the platform.'}
                        </p>
                    </div>

                    <div className="overflow-hidden rounded-[16px] border border-white/10 bg-[#110f0f] shadow-[0_24px_90px_rgba(0,0,0,0.35)]">
                        <div className="relative">
                            {course.video?.hls_url ? (
                                <div className="border-b border-white/10 bg-black/20 p-4 sm:p-6">
                                    <VideoJsPlayer
                                        src={course.video.hls_url}
                                        poster={course.thumbnail_url}
                                        className="overflow-hidden rounded-[14px]"
                                        onPlaybackError={setPlayerWarning}
                                    />
                                </div>
                            ) : course.thumbnail_url ? (
                                <>
                                    <img
                                        src={course.thumbnail_url}
                                        alt={course.title}
                                        className="aspect-[16/8] h-full w-full object-cover opacity-70"
                                    />
                                    <div className="absolute inset-0 bg-[linear-gradient(180deg,_rgba(0,0,0,0.16)_0%,_rgba(0,0,0,0.58)_100%)]" />
                                </>
                            ) : (
                                <div className="aspect-[16/8] bg-[radial-gradient(circle_at_30%_20%,_rgba(227,120,61,0.4),_transparent_28%),linear-gradient(140deg,_rgba(255,255,255,0.09),_rgba(255,255,255,0.02)),linear-gradient(180deg,_#3a2318_0%,_#17110f_100%)]" />
                            )}

                            <div className="absolute left-5 top-5 rounded-md border border-white/12 bg-black/30 px-4 py-2 text-xs uppercase tracking-[0.22em] text-white/72 backdrop-blur">
                                {course.video?.is_ready ? 'Lecture ready' : 'Lecture unavailable'}
                            </div>
                        </div>

                        <div className="space-y-6 p-6 sm:p-8">
                            {course.video?.warning_message ? (
                                <div className="rounded-[12px] border border-amber-400/25 bg-amber-500/10 px-5 py-4 text-sm leading-7 text-amber-100">
                                    {course.video.warning_message}
                                </div>
                            ) : null}

                            {playbackErrorMessage ? (
                                <div className="rounded-[12px] border border-amber-400/25 bg-amber-500/10 px-5 py-4 text-sm leading-7 text-amber-100">
                                    {playbackErrorMessage}
                                </div>
                            ) : null}

                            <div className="flex flex-wrap items-center gap-3">
                                <Button
                                    asChild
                                    variant="outline"
                                    className="rounded-lg border-white/15 bg-white/5 text-white hover:bg-white/10 hover:text-white"
                                >
                                    <Link href={route('courses.index')}>Back to Courses</Link>
                                </Button>

                                {course.next_course ? (
                                    <Button
                                        asChild
                                        className="rounded-lg bg-[#d5462f] text-white hover:bg-[#e2553d]"
                                    >
                                        <Link href={course.next_course.url}>
                                            Next Lecture
                                            <ArrowRight className="ml-2 size-4" />
                                        </Link>
                                    </Button>
                                ) : null}
                            </div>
                        </div>
                    </div>
                </section>

                <aside className="space-y-5">
                    <div className="rounded-[14px] border border-white/10 bg-white/[0.04] p-5 backdrop-blur">
                        <p className="text-xs uppercase tracking-[0.22em] text-white/45">
                            Lecture Navigation
                        </p>
                        <h2 className="mt-2 text-xl font-semibold text-white">
                            More video lecturer content
                        </h2>

                        <div className="mt-5 space-y-3">
                            {(course.navigation ?? []).map((item) => {
                                const status = navigationStatusConfig[item.status] ?? navigationStatusConfig.available;

                                return (
                                    <Link
                                        key={item.id}
                                        href={item.url}
                                        className="block rounded-[12px] border border-white/10 bg-black/18 p-4 transition hover:border-white/20 hover:bg-white/[0.05]"
                                    >
                                        <div className="flex items-start gap-4">
                                            <div className="h-20 w-28 shrink-0 overflow-hidden rounded-[12px] bg-white/5">
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
                                                        Lecture {item.index}
                                                    </p>
                                                    <h3 className="text-sm font-medium leading-6 text-white">
                                                        {item.title}
                                                    </h3>
                                                </div>

                                                <span className={`inline-flex items-center gap-2 text-xs font-medium ${status.className}`}>
                                                    <PlayCircle className="size-4" />
                                                    {status.label}
                                                </span>
                                            </div>
                                        </div>
                                    </Link>
                                );
                            })}
                        </div>
                    </div>
                </aside>
            </div>
        </AuthenticatedLayout>
    );
}
