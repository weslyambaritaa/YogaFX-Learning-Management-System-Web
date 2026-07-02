import { Button } from '@/Components/ui/button';
import StudentBackButton from '@/Components/student/StudentBackButton';
import VideoJsPlayer from '@/Components/VideoJsPlayer';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { useState } from 'react';

export default function StudentCourseShow({ course }) {
    const [playerWarning, setPlayerWarning] = useState(null);
    const playbackErrorMessage =
        typeof playerWarning === 'string'
            ? playerWarning
            : playerWarning?.message ?? null;
    const originModuleLabel = course.origin_module?.sort_order
        ? `Module ${course.origin_module.sort_order}`
        : 'Video Lecturer';
    const backHref = course.origin_module?.url ?? route('courses.index');

    return (
        <AuthenticatedLayout
            studentVariant="immersive"
            studentContentClassName="pb-16"
        >
            <Head title={course.title} />

            <div className="mx-auto flex max-w-[1400px] flex-col gap-5 px-4 pt-4 sm:gap-6 sm:px-6 lg:px-10">
                <StudentBackButton fallbackHref={backHref} />

                <div className="space-y-4 sm:space-y-6">
                    <section className="space-y-4 sm:space-y-6">
                        <div className="overflow-hidden rounded-[5px] border border-white/10 bg-[#110f0f] shadow-[0_24px_90px_rgba(0,0,0,0.35)]">
                            <div className="bg-black">
                                {course.video?.hls_url ? (
                                    <div className="aspect-video w-full">
                                        <VideoJsPlayer
                                            src={course.video.hls_url}
                                            poster={course.thumbnail_url}
                                            className="h-full w-full overflow-hidden"
                                            onPlaybackError={setPlayerWarning}
                                        />
                                    </div>
                                ) : course.thumbnail_url ? (
                                    <div className="aspect-video w-full overflow-hidden">
                                        <img
                                            src={course.thumbnail_url}
                                            alt={course.title}
                                            className="h-full w-full object-cover opacity-70"
                                        />
                                    </div>
                                ) : (
                                    <div className="aspect-video w-full bg-[radial-gradient(circle_at_30%_20%,_rgba(227,120,61,0.4),_transparent_28%),linear-gradient(140deg,_rgba(255,255,255,0.09),_rgba(255,255,255,0.02)),linear-gradient(180deg,_#3a2318_0%,_#17110f_100%)]" />
                                )}
                            </div>

                            <div className="border-t border-white/10 space-y-5 p-5 sm:p-6 lg:p-8">
                                <div className="space-y-4 sm:space-y-5">
                                    <div className="space-y-3">
                                        <p className="font-['Montserrat'] text-[12px] font-medium uppercase tracking-[0.22em] text-white/45">
                                            {originModuleLabel}
                                        </p>
                                        <h1 className="font-['Montserrat'] text-[26px] font-semibold tracking-[-0.03em] text-white sm:text-[32px]">
                                            {course.title}
                                        </h1>
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

                    {(course.navigation ?? []).length ? (
                        <section className="space-y-4 sm:space-y-5">
                            <div className="overflow-hidden rounded-[5px] border border-white/10 bg-[#110f0f] shadow-[0_24px_90px_rgba(0,0,0,0.28)]">
                                <div className="border-b border-white/10 px-5 py-5">
                                    <h2 className="mt-2 font-['Montserrat'] text-[22px] font-medium tracking-tight text-white">
                                        More lecturer videos
                                    </h2>
                                </div>

                                <div className="space-y-2.5 p-3 sm:space-y-3 sm:p-4">
                                    {(course.navigation ?? []).map((item) => {
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

                                                        {item.status === 'current' ? (
                                                            <div className="absolute inset-x-0 bottom-0 h-1 bg-[#DB202C]" />
                                                        ) : null}
                                                    </div>

                                                    <div className="space-y-1.5">
                                                        <h3 className="font-['Montserrat'] text-[14px] font-medium leading-5 text-white">
                                                            {item.title}
                                                        </h3>
                                                    </div>
                                                </div>
                                            </Link>
                                        );
                                    })}
                                </div>
                            </div>
                        </section>
                    ) : null}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
