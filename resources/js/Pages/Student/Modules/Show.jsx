import LockedContentDialog from "@/Components/student/LockedContentDialog";
import StudentBackButton from "@/Components/student/StudentBackButton";
import StudentStatusBadge from "@/Components/student/StudentStatusBadge";
import { Button } from "@/Components/ui/button";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link } from "@inertiajs/react";
import { ArrowRight, FileText, Play } from "lucide-react";
import { useState } from "react";

function LessonCard({ lesson, onLockedClick }) {
    const body = (
        <div className="group block h-full rounded-[14px] border border-white/10 bg-white/[0.04] p-3 transition duration-300 hover:-translate-y-1 hover:border-white/20 hover:bg-white/[0.06] sm:p-3.5">
            <div className="space-y-2.5 sm:space-y-3">
                <div className="relative overflow-hidden rounded-[12px] bg-[#161211]">
                    {lesson.thumbnail_url ? (
                        <img
                            src={lesson.thumbnail_url}
                            alt={lesson.title}
                            className="aspect-video h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]"
                        />
                    ) : (
                        <div className="aspect-video bg-[radial-gradient(circle_at_30%_20%,_rgba(227,120,61,0.4),_transparent_28%),linear-gradient(140deg,_rgba(255,255,255,0.09),_rgba(255,255,255,0.02)),linear-gradient(180deg,_#3a2318_0%,_#17110f_100%)]" />
                    )}
                    <div className="absolute right-3 top-3">
                        <StudentStatusBadge status={lesson.status} />
                    </div>
                </div>

                <div className="space-y-2.5 sm:space-y-3">
                    <div className="space-y-1">
                        <p className="font-['Montserrat'] text-[14px] font-medium tracking-tight text-white/82">
                            Lesson {lesson.sort_order}
                        </p>
                        <h3 className="line-clamp-2 font-['Montserrat'] text-[14px] font-medium tracking-tight text-white">
                            {lesson.title}
                        </h3>
                    </div>
                </div>

                <div className="space-y-1.5 sm:space-y-2">
                    <div className="flex items-center justify-between font-['Montserrat'] text-[14px] font-medium text-white/40">
                        <span>Progress</span>
                        <span>{lesson.progress_percentage}%</span>
                    </div>
                    <div className="h-1.5 overflow-hidden rounded-full bg-white/10">
                        <div
                            className={[
                                "h-full rounded-full",
                                lesson.status === "completed"
                                    ? "bg-emerald-500"
                                    : lesson.status === "locked"
                                      ? "bg-[#DB202C]"
                                      : "bg-white",
                            ].join(" ")}
                            style={{ width: `${lesson.progress_percentage}%` }}
                        />
                    </div>

                    <div className="inline-flex items-center gap-1.5 font-['Montserrat'] text-[14px] font-medium text-white/80">
                        {lesson.is_locked
                            ? "Complete previous lesson"
                            : "Click this to access the lesson"}
                        <ArrowRight className="size-3.5 transition group-hover:translate-x-1" />
                    </div>
                </div>
            </div>
        </div>
    );

    if (lesson.is_locked || !lesson.url) {
        return (
            <button type="button" onClick={onLockedClick} className="text-left">
                {body}
            </button>
        );
    }

    return <Link href={lesson.url}>{body}</Link>;
}

export default function StudentModuleShow({ module }) {
    const [lockedDialogOpen, setLockedDialogOpen] = useState(false);

    return (
        <AuthenticatedLayout
            studentVariant="immersive"
            studentContentClassName="pb-16"
        >
            <Head title={module.title} />

            <LockedContentDialog
                open={lockedDialogOpen}
                onOpenChange={setLockedDialogOpen}
                kind="lesson"
            />

            <div className="mx-auto flex max-w-[1400px] flex-col gap-6 px-4 pt-8 sm:gap-8 sm:px-6 lg:px-10">
                <StudentBackButton fallbackHref={route("modules.index")} />

                <section className="relative overflow-hidden rounded-[5px] border border-white/10 bg-[#120f0f] shadow-[0_24px_90px_rgba(0,0,0,0.35)]">
                    <div className="absolute inset-0">
                        {module.thumbnail_url ? (
                            <img
                                src={module.thumbnail_url}
                                alt={module.title}
                                className="h-full w-full object-cover opacity-55"
                            />
                        ) : (
                            <div className="h-full w-full bg-[radial-gradient(circle_at_20%_18%,_rgba(211,101,52,0.45),_transparent_30%),linear-gradient(160deg,_#2f1d16_0%,_#120f0e_100%)]" />
                        )}
                    </div>
                    <div className="absolute inset-0 bg-[linear-gradient(180deg,_rgba(0,0,0,0.22)_0%,_rgba(0,0,0,0.72)_72%,_rgba(0,0,0,0.92)_100%)]" />

                    <div className="relative flex min-h-[420px] flex-col justify-end gap-6 px-6 py-8 sm:px-8 lg:px-10 lg:py-10">
                        <div className="absolute right-6 top-6 sm:right-8 sm:top-8 lg:right-10 lg:top-10">
                            <StudentStatusBadge status={module.status} />
                        </div>
                        <div className="max-w-3xl space-y-4">
                            <p className="font-['Montserrat'] text-[22px] font-medium tracking-tight text-white">
                                Module {module.sort_order}
                            </p>
                            <h1 className="font-['Montserrat'] text-[48px] font-bold tracking-[-0.03em] text-white">
                                {module.title}
                            </h1>
                            {module.description ? (
                                <p className="max-w-2xl font-['Montserrat'] text-sm font-medium leading-7 text-white sm:text-base">
                                    {module.description}
                                </p>
                            ) : null}
                        </div>

                        <div className="flex flex-wrap gap-3 pt-2">
                            {module.continue_last_lesson_url ? (
                                <Button
                                    asChild
                                    className="h-auto rounded-[5px] bg-white px-[10px] py-[8px] font-['Montserrat'] text-[14px] font-medium text-black transition-colors hover:bg-white/80"
                                >
                                    <Link
                                        href={module.continue_last_lesson_url}
                                        className="flex items-center"
                                    >
                                        <Play className="mr-2.5 size-5 fill-black text-black" />
                                        Continue Last Lesson
                                    </Link>
                                </Button>
                            ) : null}

                            <div className="flex h-auto items-center rounded-[5px] border-0 bg-[#5a5c5f]/80 px-[10px] py-[8px] font-['Montserrat'] text-[14px] font-medium text-white">
                                {module.completed_lessons} /{" "}
                                {module.lesson_count} completed
                            </div>

                            <div className="flex h-auto items-center rounded-[5px] border-0 bg-[#5a5c5f]/80 px-[10px] py-[8px] font-['Montserrat'] text-[14px] font-medium text-white">
                                {module.progress_percentage}% module progress
                            </div>
                        </div>
                    </div>
                </section>

                {module.lessons.length ? (
                    <section className="space-y-4 sm:space-y-5">
                        <div>
                            <h2 className="font-['Montserrat'] text-[22px] font-semibold tracking-tight text-white">
                                Lessons
                            </h2>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2 sm:gap-4">
                            {module.lessons.map((lesson) => (
                                <LessonCard
                                    key={lesson.id}
                                    lesson={lesson}
                                    onLockedClick={() =>
                                        setLockedDialogOpen(true)
                                    }
                                />
                            ))}
                        </div>
                    </section>
                ) : null}

                {/* --- EBOOK SECTION --- */}
                {module.ebooks?.length ? (
                    <section className="space-y-4 sm:space-y-5">
                        <div>
                            <h2 className="font-['Montserrat'] text-2xl font-semibold tracking-tight text-white">
                                Ebooks
                            </h2>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2 sm:gap-4">
                            {module.ebooks.map((ebook) => {
                                const cardContent = (
                                    <div className="flex items-center justify-between gap-4">
                                        <div className="space-y-3">
                                            <StudentStatusBadge
                                                status="available"
                                                label="Available"
                                            />
                                            <h3 className="font-['Montserrat'] text-xl font-semibold tracking-tight text-white">
                                                {ebook.title}
                                            </h3>
                                        </div>

                                        <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full border border-white/10 bg-white/5 text-white transition group-hover:scale-110 group-hover:border-white/20 group-hover:bg-white/10 group-hover:text-[#DB202C]">
                                            <FileText className="size-5" />
                                        </div>
                                    </div>
                                );

                                if (!ebook.download_url) {
                                    return (
                                        <div
                                            key={ebook.id}
                                            className="rounded-[14px] border border-white/10 bg-white/[0.04] p-5 opacity-60"
                                        >
                                            {cardContent}
                                        </div>
                                    );
                                }

                                return (
                                    <a
                                        key={ebook.id}
                                        href={ebook.download_url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="group block rounded-[14px] border border-white/10 bg-white/[0.04] p-5 transition duration-300 hover:-translate-y-1 hover:border-white/20 hover:bg-white/[0.06] hover:shadow-[0_8px_30px_rgba(219,32,44,0.15)]"
                                    >
                                        {cardContent}
                                    </a>
                                );
                            })}
                        </div>
                    </section>
                ) : null}

                {/* --- VIDEO LECTURER SECTION --- */}
                {module.video_lecturers?.length ? (
                    <section className="space-y-4 sm:space-y-5">
                        <div>
                            <h2 className="font-['Montserrat'] text-2xl font-semibold tracking-tight text-white">
                                Video Lecturer
                            </h2>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2 sm:gap-4">
                            {module.video_lecturers.map((course) => {
                                const isReady =
                                    course.video?.is_ready && course.url;

                                const cardBody = (
                                    <div className="group block h-full rounded-[14px] border border-white/10 bg-white/[0.04] p-3 transition duration-300 hover:-translate-y-1 hover:border-white/20 hover:bg-white/[0.06] sm:p-3.5">
                                        <div className="space-y-2.5 sm:space-y-3">
                                            <div className="relative overflow-hidden rounded-[12px] bg-[#161211]">
                                                {course.thumbnail_url ? (
                                                    <img
                                                        src={
                                                            course.thumbnail_url
                                                        }
                                                        alt={course.title}
                                                        className="aspect-video h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]"
                                                    />
                                                ) : (
                                                    <div className="aspect-video bg-[radial-gradient(circle_at_30%_20%,_rgba(223,103,57,0.4),_transparent_28%),linear-gradient(140deg,_rgba(255,255,255,0.09),_rgba(255,255,255,0.02)),linear-gradient(180deg,_#3a2318_0%,_#17110f_100%)]" />
                                                )}

                                                {isReady && (
                                                    <div className="absolute inset-0 flex items-center justify-center bg-black/10 opacity-0 transition duration-300 group-hover:opacity-100">
                                                        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-[#DB202C]/90 text-white shadow-[0_0_15px_rgba(219,32,44,0.5)] backdrop-blur-sm">
                                                            <Play className="ml-1 size-5 fill-current" />
                                                        </div>
                                                    </div>
                                                )}
                                            </div>

                                            <div className="space-y-2.5 sm:space-y-3">
                                                <StudentStatusBadge
                                                    status={
                                                        isReady
                                                            ? "available"
                                                            : "locked"
                                                    }
                                                />

                                                <div className="space-y-1.5">
                                                    <h3 className="line-clamp-2 font-['Montserrat'] text-[14px] font-medium tracking-tight text-white">
                                                        {course.title}
                                                    </h3>
                                                </div>

                                                <div className="pt-2">
                                                    <div className="inline-flex items-center gap-1.5 font-['Montserrat'] text-[14px] font-medium text-white/80">
                                                        {isReady
                                                            ? "Watch Video"
                                                            : "Video Not Ready"}
                                                        {isReady && (
                                                            <ArrowRight className="size-3.5 transition group-hover:translate-x-1" />
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                );

                                if (isReady) {
                                    return (
                                        <Link
                                            key={course.id}
                                            href={course.url}
                                            className="block text-left"
                                        >
                                            {cardBody}
                                        </Link>
                                    );
                                }

                                return (
                                    <div
                                        key={course.id}
                                        className="block cursor-not-allowed text-left opacity-70"
                                    >
                                        {cardBody}
                                    </div>
                                );
                            })}
                        </div>
                    </section>
                ) : null}

                {module.certificates?.length ? (
                    <section className="space-y-4 sm:space-y-5">
                        <div>
                            <p className="font-['Montserrat'] text-xs font-medium uppercase tracking-[0.24em] text-white/40">
                                Certificates
                            </p>
                            <h2 className="mt-2 font-['Montserrat'] text-2xl font-semibold tracking-tight text-white">
                                Download your generated certificates
                            </h2>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2 sm:gap-4">
                            {module.certificates.map((certificate) => (
                                <a
                                    key={certificate.id}
                                    href={certificate.download_url}
                                    className="rounded-[5px] border border-white/10 bg-white/[0.04] p-5"
                                >
                                    <div className="space-y-3">
                                        <StudentStatusBadge status="completed" />
                                        <h3 className="font-['Montserrat'] text-xl font-semibold tracking-tight text-white">
                                            {certificate.type_label}
                                        </h3>
                                        <p className="font-['Montserrat'] text-sm font-medium leading-7 text-white/62">
                                            Generated {certificate.generated_at}
                                        </p>
                                    </div>
                                </a>
                            ))}
                        </div>
                    </section>
                ) : null}

                {module.assignments?.length ? (
                    <section className="space-y-4 sm:space-y-5">
                        <div>
                            <p className="font-['Montserrat'] text-xs font-medium uppercase tracking-[0.24em] text-white/40">
                                Assignment Submission
                            </p>
                            <h2 className="mt-2 font-['Montserrat'] text-2xl font-semibold tracking-tight text-white">
                                Upload your module assignments
                            </h2>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2 sm:gap-4">
                            {module.assignments.map((assignment) => (
                                <Link
                                    key={assignment.id}
                                    href={assignment.url}
                                    className="rounded-[5px] border border-white/10 bg-white/[0.04] p-5 transition hover:border-white/18 hover:bg-white/[0.06]"
                                >
                                    <div className="space-y-3">
                                        <StudentStatusBadge
                                            status={
                                                assignment.submission_status ===
                                                "approved"
                                                    ? "completed"
                                                    : "available"
                                            }
                                            label={
                                                assignment.submission_status ===
                                                "approved"
                                                    ? "Completed"
                                                    : "Available"
                                            }
                                        />
                                        <h3 className="font-['Montserrat'] text-xl font-semibold tracking-tight text-white">
                                            {assignment.title}
                                        </h3>
                                        <p className="font-['Montserrat'] text-[14px] font-medium tracking-tight text-white">
                                            Assignment {assignment.sort_order}
                                        </p>
                                        <p className="font-['Montserrat'] text-sm font-medium leading-7 text-white/62">
                                            {assignment.description ||
                                                "Open this assignment to upload your submission."}
                                        </p>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </section>
                ) : null}
            </div>
        </AuthenticatedLayout>
    );
}
