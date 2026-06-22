import LockedContentDialog from "@/Components/student/LockedContentDialog";
import StudentBackButton from "@/Components/student/StudentBackButton";
import StudentStatusBadge from "@/Components/student/StudentStatusBadge";
import { Button } from "@/Components/ui/button";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link } from "@inertiajs/react";
import { ArrowRight, Check, FileText, Play } from "lucide-react";
import { useState } from "react";

function LessonCard({ lesson, onLockedClick }) {
    const body = (
        <div className="group block h-full rounded-[14px] border border-white/10 bg-white/[0.04] p-3.5 transition duration-300 hover:-translate-y-1 hover:border-white/20 hover:bg-white/[0.06]">
            <div className="space-y-3">
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
                </div>

                <div className="space-y-3">
                    <StudentStatusBadge status={lesson.status} />

                    <div className="space-y-1.5">
                        <h3 className="line-clamp-2 text-base font-semibold tracking-tight text-white">
                            {lesson.title}
                        </h3>
                        <p className="text-base font-semibold tracking-tight text-white/82">
                            Lesson {lesson.sort_order}
                        </p>
                        <p className="text-xs leading-6 text-white/62">
                            {[
                                lesson.has_workbook ? "Workbook" : null,
                                lesson.has_video ? "Video" : null,
                                lesson.has_audio ? "Audio" : null,
                                lesson.has_content ? "Content" : null,
                            ]
                                .filter(Boolean)
                                .join(" • ") || "Learning content ready"}
                        </p>
                    </div>
                </div>

                <div className="space-y-2">
                    <div className="flex items-center justify-between text-[11px] uppercase tracking-[0.18em] text-white/40">
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

                    <div className="inline-flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-[0.16em] text-white/80">
                        {lesson.is_locked ? "Complete previous lesson" : null}
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

            <div className="mx-auto flex max-w-[1400px] flex-col gap-8 px-4 pt-8 sm:px-6 lg:px-10">
                <StudentBackButton fallbackHref={route("modules.index")} />

                <section className="relative overflow-hidden rounded-[16px] border border-white/10 bg-[#120f0f] shadow-[0_24px_90px_rgba(0,0,0,0.35)]">
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
                        <div className="max-w-3xl space-y-4">
                            <StudentStatusBadge status={module.status} />
                            <p className="text-xl font-semibold tracking-tight text-white">
                                Module {module.sort_order}
                            </p>
                            <h1 className="text-4xl font-semibold tracking-[-0.03em] text-white sm:text-5xl">
                                {module.title}
                            </h1>
                            {module.description ? (
                                <p className="max-w-2xl text-sm sm:text-base leading-7 font-medium text-white">
                                    {module.description}
                                </p>
                            ) : null}
                        </div>

                        {/* UPDATE: Buttons & Progress badges with Netflix styling */}
                        <div className="flex flex-wrap gap-3 pt-2">
                            {module.continue_last_lesson_url ? (
                                <Button
                                    asChild
                                    className="h-auto rounded-md bg-white px-7 py-3 text-[1.05rem] font-bold text-black transition-colors hover:bg-white/80"
                                >
                                    <Link
                                        href={module.continue_last_lesson_url}
                                        className="flex items-center"
                                    >
                                        <Play className="mr-2.5 size-6 fill-black text-black" />
                                        Continue Last Lesson
                                    </Link>
                                </Button>
                            ) : null}

                            <div className="flex items-center h-auto rounded-md border-0 bg-[#5a5c5f]/80 px-7 py-3 text-[1.05rem] font-bold text-white">
                                {module.completed_lessons} /{" "}
                                {module.lesson_count} completed
                            </div>

                            <div className="flex items-center h-auto rounded-md border-0 bg-[#5a5c5f]/80 px-7 py-3 text-[1.05rem] font-bold text-white">
                                {module.progress_percentage}% module progress
                            </div>
                        </div>
                    </div>
                </section>

                {module.lessons.length ? (
                    <section className="space-y-5">
                        <div>
                            {/* UPDATE: Dihapus "Lesson Access" dan margins disesuaikan */}
                            <h2 className="text-2xl font-semibold tracking-tight text-white">
                                Lessons
                            </h2>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
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

                {module.ebooks?.length ? (
                    <section className="space-y-5">
                        <div>
                            <p className="text-xs uppercase tracking-[0.24em] text-white/40">
                                Ebooks
                            </p>
                            <h2 className="mt-2 text-2xl font-semibold tracking-tight text-white">
                                Browse your ebook library
                            </h2>
                        </div>

                        <div className="grid gap-4 xl:grid-cols-2">
                            {module.ebooks.map((ebook) => (
                                <div
                                    key={ebook.id}
                                    className="rounded-[16px] border border-white/10 bg-white/[0.04] p-5"
                                >
                                    <div className="space-y-3">
                                        <StudentStatusBadge
                                            status="available"
                                            label="Available"
                                        />
                                        <h3 className="text-xl font-semibold tracking-tight text-white">
                                            {ebook.title}
                                        </h3>
                                        <p className="text-xl font-semibold tracking-tight text-white">
                                            Ebook {ebook.sort_order}
                                        </p>
                                        <p className="text-sm leading-7 text-white/62">
                                            {ebook.file_name}
                                        </p>
                                    </div>

                                    {ebook.download_url ? (
                                        <div className="mt-6">
                                            <Button
                                                asChild
                                                variant="outline"
                                                className="rounded-full border-white/15 bg-white/5 text-white hover:bg-white/10 hover:text-white"
                                            >
                                                <a href={ebook.download_url}>
                                                    <FileText className="mr-2 size-4" />
                                                    Access Ebook
                                                </a>
                                            </Button>
                                        </div>
                                    ) : null}
                                </div>
                            ))}
                        </div>
                    </section>
                ) : null}

                {module.certificates?.length ? (
                    <section className="space-y-5">
                        <div>
                            <p className="text-xs uppercase tracking-[0.24em] text-white/40">
                                Certificates
                            </p>
                            <h2 className="mt-2 text-2xl font-semibold tracking-tight text-white">
                                Download your generated certificates
                            </h2>
                        </div>

                        <div className="grid gap-4 xl:grid-cols-2">
                            {module.certificates.map((certificate) => (
                                <a
                                    key={certificate.id}
                                    href={certificate.download_url}
                                    className="rounded-[16px] border border-white/10 bg-white/[0.04] p-5"
                                >
                                    <div className="space-y-3">
                                        <StudentStatusBadge status="completed" />
                                        <h3 className="text-xl font-semibold tracking-tight text-white">
                                            {certificate.type_label}
                                        </h3>
                                        <p className="text-sm leading-7 text-white/62">
                                            Generated {certificate.generated_at}
                                        </p>
                                    </div>
                                </a>
                            ))}
                        </div>
                    </section>
                ) : null}

                {module.assignments?.length ? (
                    <section className="space-y-5">
                        <div>
                            <p className="text-xs uppercase tracking-[0.24em] text-white/40">
                                Assignment Submission
                            </p>
                            <h2 className="mt-2 text-2xl font-semibold tracking-tight text-white">
                                Upload your module assignments
                            </h2>
                        </div>

                        <div className="grid gap-4 xl:grid-cols-2">
                            {module.assignments.map((assignment) => (
                                <Link
                                    key={assignment.id}
                                    href={assignment.url}
                                    className="rounded-[16px] border border-white/10 bg-white/[0.04] p-5 transition hover:border-white/18 hover:bg-white/[0.06]"
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
                                        <h3 className="text-xl font-semibold tracking-tight text-white">
                                            {assignment.title}
                                        </h3>
                                        <p className="text-xl font-semibold tracking-tight text-white">
                                            Assignment {assignment.sort_order}
                                        </p>
                                        <p className="text-sm leading-7 text-white/62">
                                            {assignment.description ||
                                                "Open this assignment to upload your submission."}
                                        </p>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </section>
                ) : null}

                {module.video_lecturers?.length ? (
                    <section className="space-y-5">
                        <div>
                            <p className="text-xs uppercase tracking-[0.24em] text-white/40">
                                Video Lecturer
                            </p>
                            <h2 className="mt-2 text-2xl font-semibold tracking-tight text-white">
                                Browse your lecturer videos
                            </h2>
                        </div>

                        <div className="grid gap-4 xl:grid-cols-2">
                            {module.video_lecturers.map((course) => (
                                <div
                                    key={course.id}
                                    className="overflow-hidden rounded-[16px] border border-white/10 bg-white/[0.04]"
                                >
                                    <div className="relative overflow-hidden">
                                        {course.thumbnail_url ? (
                                            <img
                                                src={course.thumbnail_url}
                                                alt={course.title}
                                                className="aspect-video h-full w-full object-cover"
                                            />
                                        ) : (
                                            <div className="aspect-video bg-[radial-gradient(circle_at_24%_20%,_rgba(223,103,57,0.45),_transparent_28%),linear-gradient(160deg,_#2b1d16_0%,_#120f0e_100%)]" />
                                        )}
                                    </div>

                                    <div className="space-y-4 p-5">
                                        <StudentStatusBadge
                                            status={
                                                course.video?.is_ready
                                                    ? "available"
                                                    : "locked"
                                            }
                                        />
                                        <h3 className="text-xl font-semibold tracking-tight text-white">
                                            {course.title}
                                        </h3>
                                        <p className="text-sm leading-7 text-white/62">
                                            {course.description ||
                                                "Premium YogaFX lecture content ready for viewing."}
                                        </p>

                                        {course.video?.is_ready &&
                                        course.url ? (
                                            <Button
                                                asChild
                                                className="rounded-full bg-[#DB202C] text-white hover:bg-[#c31c28]"
                                            >
                                                <Link href={course.url}>
                                                    Open Video
                                                </Link>
                                            </Button>
                                        ) : (
                                            <div className="rounded-full border border-white/15 bg-white/5 px-4 py-2 text-sm text-white/60">
                                                Video Not Ready
                                            </div>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>
                ) : null}
            </div>
        </AuthenticatedLayout>
    );
}
