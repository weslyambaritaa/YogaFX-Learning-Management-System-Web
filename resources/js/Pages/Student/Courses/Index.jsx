import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { ArrowUpRight, PlayCircle } from 'lucide-react';

export default function StudentCoursesIndex({ courses }) {
    return (
        <AuthenticatedLayout
            studentVariant="immersive"
            studentContentClassName="pb-16"
        >
            <Head title="Courses" />

            <div className="mx-auto flex max-w-[1400px] flex-col gap-8 px-4 pt-8 sm:px-6 lg:px-10">
                <section className="relative overflow-hidden rounded-[32px] border border-white/10 bg-[#120f0f] px-6 py-8 shadow-[0_24px_90px_rgba(0,0,0,0.35)] sm:px-8 lg:px-10">
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_18%_18%,_rgba(196,91,49,0.28),_transparent_28%),linear-gradient(180deg,_rgba(255,255,255,0.05)_0%,_rgba(255,255,255,0)_36%),linear-gradient(180deg,_rgba(12,10,10,0.22)_0%,_rgba(12,10,10,0.82)_100%)]" />
                    <div className="relative max-w-3xl space-y-4">
                        <p className="text-xs uppercase tracking-[0.28em] text-[#f2d9c8]">
                            Video Lecture
                        </p>
                        <h1 className="text-4xl font-semibold tracking-[-0.03em] text-white sm:text-5xl">
                            Browse premium course content without leaving the YogaFX mood.
                        </h1>
                        <p className="max-w-2xl text-sm leading-7 text-white/68 sm:text-base">
                            Courses should feel like featured premium content, with strong
                            thumbnails, clean hierarchy, and a direct path into the lecture.
                        </p>
                    </div>
                </section>

                <section className="space-y-5">
                    <div>
                        <p className="text-xs uppercase tracking-[0.24em] text-white/40">
                            Course Catalog
                        </p>
                        <h2 className="mt-2 text-2xl font-semibold tracking-tight text-white">
                            Available now for your tier
                        </h2>
                    </div>

                    <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        {courses.map((course) => (
                            <article
                                key={course.id}
                                className="group overflow-hidden rounded-[30px] border border-white/10 bg-white/[0.04] transition duration-300 hover:-translate-y-1 hover:border-white/20 hover:bg-white/[0.06]"
                            >
                                <div className="relative overflow-hidden">
                                    {course.thumbnail_url ? (
                                        <img
                                            src={course.thumbnail_url}
                                            alt={course.title}
                                            className="aspect-[4/3] h-full w-full object-cover transition duration-500 group-hover:scale-[1.04]"
                                        />
                                    ) : (
                                        <div className="aspect-[4/3] bg-[radial-gradient(circle_at_24%_20%,_rgba(223,103,57,0.45),_transparent_28%),linear-gradient(160deg,_#2b1d16_0%,_#120f0e_100%)]" />
                                    )}
                                    <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent" />
                                    <div className="absolute left-5 top-5 rounded-full border border-white/12 bg-black/30 px-3 py-1 text-[11px] uppercase tracking-[0.22em] text-white/70 backdrop-blur">
                                        Course {course.index}
                                    </div>
                                </div>

                                <div className="space-y-4 p-6">
                                    <div className="inline-flex items-center gap-2 text-sm font-medium text-[#f15b3a]">
                                        <PlayCircle className="size-4" />
                                        Ready to watch
                                    </div>

                                    <div>
                                        <h3 className="text-2xl font-semibold tracking-tight text-white">
                                            {course.title}
                                        </h3>
                                        <p className="mt-3 text-sm leading-7 text-white/62">
                                            {course.description || 'Premium YogaFX lecture content ready for viewing.'}
                                        </p>
                                    </div>

                                    <Button
                                        asChild
                                        className="rounded-full bg-[#f15b3a] text-white hover:bg-[#ff6a49]"
                                    >
                                        <a href={course.video} target="_blank" rel="noreferrer">
                                            Open Course Video
                                            <ArrowUpRight className="ml-2 size-4" />
                                        </a>
                                    </Button>
                                </div>
                            </article>
                        ))}
                    </div>

                    {courses.length === 0 && (
                        <div className="rounded-[28px] border border-white/10 bg-white/[0.04] px-6 py-10 text-center text-white/62">
                            No courses are available for your current tier yet.
                        </div>
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
