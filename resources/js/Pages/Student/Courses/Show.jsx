import { Button } from '@/Components/ui/button';
import StudentBackButton from '@/Components/student/StudentBackButton';
import StudentStatusBadge from '@/Components/student/StudentStatusBadge';
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
    const currentNavigationItem =
        (course.navigation ?? []).find((item) => item.id === course.id) ?? null;
    const currentStatusLabel =
        currentNavigationItem?.status === 'current'
            ? 'Current Lecture'
            : course.video?.is_ready
              ? 'Available'
              : 'Lecture Unavailable';
    const originModuleLabel = course.origin_module?.sort_order
        ? `Module ${course.origin_module.sort_order}`
        : 'Video Lecturer';
    const backHref = course.origin_module?.url ?? route('courses.index');
    const backLabel = course.origin_module ? 'Back to module' : 'Back to Courses';

    return (
        <AuthenticatedLayout
            studentVariant="immersive"
            studentContentClassName="pb-16"
        >
            <Head title={course.title} />

            <div className="mx-auto flex max-w-[1400px] flex-col gap-6 px-4 pt-4 sm:px-6 lg:px-10">
                <StudentBackButton fallbackHref={backHref} />

                <div className="grid gap-8 lg:grid-cols-[minmax(0,1.75fr)_360px]">
                    <section className="space-y-6">
                        <div className="overflow-hidden rounded-[5px] border border-white/10 bg-[#110f0f] shadow-[0_24px_90px_rgba(0,0,0,0.35)]">
                            <div className="border-b border-white/10 bg-black">
                                {course.video?.hls_url ? (
                                    <div className="p-4 sm:p-6 lg:p-8">
                                        <div className="mx-auto aspect-video w-full max-w-5xl">
                                            <VideoJsPlayer
                                                src={course.video.hls_url}
                                                poster={course.thumbnail_url}
                                                className="h-full w-full overflow-hidden rounded-[5px] shadow-2xl"
                                                onPlaybackError={setPlayerWarning}
                                            />
                                        </div>
                                    </div>
                                ) : course.thumbnail_url ? (
                                    <div className="p-4 sm:p-6 lg:p-8">
                                        <div className="mx-auto aspect-video w-full max-w-5xl overflow-hidden rounded-[5px]">
                                            <img
                                                src={course.thumbnail_url}
                                                alt={course.title}
                                                className="h-full w-full object-cover opacity-70"
                                            />
                                        </div>
                                    </div>
                                ) : (
                                    <div className="p-4 sm:p-6 lg:p-8">
                                        <div className="mx-auto aspect-video w-full max-w-5xl rounded-[5px] bg-[radial-gradient(circle_at_30%_20%,_rgba(227,120,61,0.4),_transparent_28%),linear-gradient(140deg,_rgba(255,255,255,0.09),_rgba(255,255,255,0.02)),linear-gradient(180deg,_#3a2318_0%,_#17110f_100%)]" />
                                    </div>
                                )}

                            </div>

                            <div className="space-y-6 p-6 sm:p-8">
                                <div className="space-y-5">
                                    <div className="space-y-3">
                                        <div className="flex flex-wrap items-center gap-3">
                                            <p className="font-['Montserrat'] text-[12px] font-medium uppercase tracking-[0.22em] text-white/45">
                                                {originModuleLabel}
                                            </p>
                                            <StudentStatusBadge
                                                status={course.video?.is_ready ? 'current' : 'locked'}
                                                label={currentStatusLabel}
                                            />
                                        </div>
                                        <h1 className="font-['Montserrat'] text-[26px] font-semibold tracking-[-0.03em] text-white sm:text-[32px]">
                                            {course.title}
                                        </h1>
                                        {course.index ? (
                                            <div className="rounded-full border border-white/10 bg-white/5 px-4 py-2 font-['Montserrat'] text-sm text-white/65">
                                                Lecture {course.index}
                                            </div>
                                        ) : null}
                                        <p className="max-w-3xl font-['Montserrat'] text-sm leading-7 text-white/65 sm:text-base">
                                            {course.description || 'Premium YogaFX lecturer content stays inside a full lesson-like viewing experience, so playback never feels detached from the platform.'}
                                        </p>
                                    </div>
                                </div>

                                {course.video?.warning_message ? (
                                    <div className="rounded-[5px] border border-amber-400/25 bg-amber-500/10 px-5 py-4 font-['Montserrat'] text-sm leading-7 text-amber-100">
                                        {course.video.warning_message}
                                    </div>
                                ) : null}

                                {playbackErrorMessage ? (
                                    <div className="rounded-[5px] border border-amber-400/25 bg-amber-500/10 px-5 py-4 font-['Montserrat'] text-sm leading-7 text-amber-100">
                                        {playbackErrorMessage}
                                    </div>
                                ) : null}

                                <div className="flex flex-wrap items-center gap-3">
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="h-auto rounded-[5px] border-white/15 bg-white/5 px-[10px] py-[8px] font-['Montserrat'] text-[14px] font-medium text-white hover:bg-white/10 hover:text-white"
                                    >
                                        <Link href={backHref}>{backLabel}</Link>
                                    </Button>

                                    {course.next_course ? (
                                        <Button
                                            asChild
                                            className="h-auto rounded-[5px] bg-[#DB202C] px-[10px] py-[8px] font-['Montserrat'] text-[14px] font-medium text-white hover:bg-[#c31c28]"
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
                        <div className="overflow-hidden rounded-[5px] border border-white/10 bg-[#110f0f] shadow-[0_24px_90px_rgba(0,0,0,0.28)]">
                            <div className="border-b border-white/10 px-5 py-5">
                                <p className="font-['Montserrat'] text-[12px] font-medium uppercase tracking-[0.22em] text-white/40">
                                    Video Lecturer
                                </p>
                                <h2 className="mt-2 font-['Montserrat'] text-[22px] font-medium tracking-tight text-white">
                                    More lecturer videos
                                </h2>
                                <p className="mt-2 font-['Montserrat'] text-sm leading-6 text-white/55">
                                    Pilih salah satu video lecturer yang ingin ditonton.
                                </p>
                            </div>

                            <div className="space-y-3 p-4">
                                {(course.navigation ?? []).map((item) => {
                                    const status = navigationStatusConfig[item.status] ?? navigationStatusConfig.available;

                                    return (
                                        <Link
                                            key={item.id}
                                            href={item.url}
                                            className={[
                                                'group block overflow-hidden rounded-[5px] border p-3 transition',
                                                item.status === 'current'
                                                    ? 'border-[#DB202C]/60 bg-[#DB202C]/10 shadow-[0_10px_30px_rgba(219,32,44,0.16)]'
                                                    : 'border-white/10 bg-white/[0.04] hover:border-white/20 hover:bg-white/[0.06]',
                                            ].join(' ')}
                                        >
                                            <div className="space-y-3">
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
                                                            status={item.status === 'current' ? 'current' : 'available'}
                                                            label={status.label}
                                                            className="origin-top-right scale-[0.72] shadow-none"
                                                        />
                                                    </div>

                                                    {item.status === 'current' ? (
                                                        <div className="absolute inset-x-0 bottom-0 h-1 bg-[#DB202C]" />
                                                    ) : null}
                                                </div>

                                                <div className="space-y-2.5">
                                                    <div className="space-y-1.5">
                                                        <p className="font-['Montserrat'] text-[11px] font-medium uppercase tracking-[0.22em] text-white/40">
                                                            Lecture {item.index}
                                                        </p>
                                                        <h3 className="font-['Montserrat'] text-[14px] font-medium leading-5 text-white">
                                                            {item.title}
                                                        </h3>
                                                    </div>

                                                    <span className={`inline-flex items-center gap-2 font-['Montserrat'] text-[12px] font-medium ${status.className}`}>
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
            </div>
        </AuthenticatedLayout>
    );
}
