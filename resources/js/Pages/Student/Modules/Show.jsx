import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link } from "@inertiajs/react";
import {
    ArrowRight,
    CheckCircle2,
    ClipboardCheck,
    Download,
    FileText,
    Lock,
    Play,
    PlayCircle,
} from "lucide-react";

const lessonStatusConfig = {
    completed: {
        icon: CheckCircle2,
        label: "Completed",
        className: "text-[#3DDC84]",
    },
    active: {
        icon: PlayCircle,
        label: "Active Lesson",
        className: "text-[#DB202C]",
    },
    available: {
        icon: PlayCircle,
        label: "Available",
        className: "text-white/72",
    },
    locked: {
        icon: Lock,
        label: "Locked",
        className: "text-white/45",
    },
};

const assignmentStatusConfig = {
    approved: {
        label: "Approved",
        className: "text-[#3DDC84]",
    },
    rejected: {
        label: "Needs Revision",
        className: "text-rose-200",
    },
    under_review: {
        label: "Under Review",
        className: "text-[#f2d9c8]",
    },
    pending_review: {
        label: "Under Review",
        className: "text-[#f2d9c8]",
    },
    submitted: {
        label: "Submitted",
        className: "text-[#f2d9c8]",
    },
    none: {
        label: "Not Submitted",
        className: "text-white/55",
    },
};

export default function StudentModuleShow({ module }) {
    return (
        <AuthenticatedLayout
            studentVariant="immersive"
            studentContentClassName="pb-16"
        >
            <Head title={module.title} />

            <div className="mx-auto flex max-w-[1400px] flex-col gap-8 px-4 pt-8 sm:px-6 lg:px-10">
                <section className="relative overflow-hidden rounded-[10px] border border-white/10 bg-[#120f0f] shadow-[0_24px_90px_rgba(0,0,0,0.35)]">
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
                            <p className="text-xs uppercase tracking-[0.28em] text-[#f2d9c8]">
                                Module {module.sort_order}
                            </p>
                            <h1 className="text-4xl font-semibold tracking-[-0.03em] text-white sm:text-5xl">
                                {module.title}
                            </h1>
                            {module.description ? (
                                <p className="max-w-2xl text-sm leading-7 text-white/70 sm:text-base">
                                    {module.description}
                                </p>
                            ) : null}
                        </div>

                        {module.show_progress ? (
                            <div className="flex flex-wrap gap-3">
                                <div className="rounded-md border border-white/12 bg-white/5 px-4 py-2 text-sm text-white/78 backdrop-blur">
                                    {module.completed_lessons} /{" "}
                                    {module.lesson_count} completed
                                </div>
                                <div className="rounded-md border border-white/12 bg-white/5 px-4 py-2 text-sm text-white/78 backdrop-blur">
                                    {module.progress_percentage}% module
                                    progress
                                </div>
                            </div>
                        ) : (
                            <div className="flex flex-wrap gap-3">
                                <div className="rounded-md border border-white/12 bg-white/5 px-4 py-2 text-sm text-white/78 backdrop-blur">
                                    {module.assignments?.length
                                        ? "Assignment submission and approval are required before this module can be cleared."
                                        : module.status === "completed"
                                          ? "This module has been opened and marked complete."
                                          : "Open this module once to mark it complete in your path."}
                                </div>
                            </div>
                        )}
                    </div>
                </section>

                {module.lessons.length ? (
                    <section className="space-y-5">
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <p className="text-xs uppercase tracking-[0.24em] text-white/40">
                                    Lesson Access
                                </p>
                                <h2 className="mt-2 text-2xl font-semibold tracking-tight text-white">
                                    Lessons
                                </h2>
                            </div>
                        </div>

                        <div className="space-y-4">
                            {module.lessons.map((lesson) => {
                                const status =
                                    lessonStatusConfig[lesson.status] ??
                                    lessonStatusConfig.available;
                                const StatusIcon = status.icon;
                                const LessonCardTag = lesson.url ? Link : "div";

                                return (
                                    <LessonCardTag
                                        key={lesson.id}
                                        {...(lesson.url
                                            ? { href: lesson.url }
                                            : {})}
                                        className="group block rounded-[10px] border border-white/10 bg-white/[0.04] p-4 transition duration-300 hover:-translate-y-1 hover:border-white/20 hover:bg-white/[0.06]"
                                    >
                                        <div className="grid gap-4 md:grid-cols-[280px_1fr]">
                                            <div className="relative overflow-hidden rounded-[10px] bg-[#161211]">
                                                {lesson.thumbnail_url ? (
                                                    <img
                                                        src={
                                                            lesson.thumbnail_url
                                                        }
                                                        alt={lesson.title}
                                                        className="aspect-[16/9] h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]"
                                                    />
                                                ) : (
                                                    <div className="aspect-[16/9] bg-[radial-gradient(circle_at_30%_20%,_rgba(227,120,61,0.4),_transparent_28%),linear-gradient(140deg,_rgba(255,255,255,0.09),_rgba(255,255,255,0.02)),linear-gradient(180deg,_#3a2318_0%,_#17110f_100%)]" />
                                                )}
                                                <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-black/15 to-transparent" />
                                                <div className="absolute left-4 top-4 rounded-md border border-white/12 bg-black/30 px-3 py-1 text-[11px] uppercase tracking-[0.2em] text-white/70 backdrop-blur">
                                                    #{lesson.sort_order}
                                                </div>
                                            </div>

                                            <div className="flex flex-col justify-between gap-6 p-2">
                                                <div className="space-y-4">
                                                    <div
                                                        className={`inline-flex items-center gap-2 text-sm font-medium ${status.className}`}
                                                    >
                                                        <StatusIcon className="size-4" />
                                                        {status.label}
                                                    </div>

                                                    <div>
                                                        <h3 className="text-2xl font-semibold tracking-tight text-white">
                                                            {lesson.title}
                                                        </h3>
                                                        <p className="mt-3 text-sm leading-7 text-white/62">
                                                            {[
                                                                lesson.has_workbook
                                                                    ? "Workbook"
                                                                    : null,
                                                                lesson.has_video
                                                                    ? "Video"
                                                                    : null,
                                                                lesson.has_audio
                                                                    ? "Audio"
                                                                    : null,
                                                                lesson.has_content
                                                                    ? "Content"
                                                                    : null,
                                                            ]
                                                                .filter(Boolean)
                                                                .join(" • ") ||
                                                                "Learning content ready"}
                                                        </p>
                                                        {lesson.is_locked &&
                                                        lesson.lock_reason ? (
                                                            <p className="mt-3 text-sm leading-6 text-amber-200/80">
                                                                {
                                                                    lesson.lock_reason
                                                                }
                                                            </p>
                                                        ) : null}
                                                    </div>
                                                </div>

                                                <div className="space-y-4">
                                                    <div className="space-y-2">
                                                        <div className="flex items-center justify-between text-xs uppercase tracking-[0.2em] text-white/40">
                                                            <span>
                                                                Lesson progress
                                                            </span>
                                                            <span>
                                                                {
                                                                    lesson.progress_percentage
                                                                }
                                                                %
                                                            </span>
                                                        </div>
                                                        <div className="h-1.5 overflow-hidden rounded-full bg-white/10">
                                                            <div
                                                                className="h-full rounded-full bg-[#DB202C]"
                                                                style={{
                                                                    width: `${lesson.progress_percentage}%`,
                                                                }}
                                                            />
                                                        </div>
                                                    </div>

                                                    <div className="inline-flex items-center gap-2 text-sm font-medium text-white">
                                                        {lesson.is_locked
                                                            ? "Lesson Locked"
                                                            : "Open Lesson"}
                                                        <ArrowRight className="size-4 transition group-hover:translate-x-1" />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </LessonCardTag>
                                );
                            })}
                        </div>
                    </section>
                ) : null}

                {module.assignments?.length ? (
                    <section className="space-y-5">
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <p className="text-xs uppercase tracking-[0.24em] text-white/40">
                                    Assignment Submission
                                </p>
                                <h2 className="mt-2 text-2xl font-semibold tracking-tight text-white">
                                    Upload your module assignments
                                </h2>
                            </div>
                            <div className="hidden text-sm text-white/42 md:block">
                                Separate from lessons, focused on submission
                            </div>
                        </div>

                        <div className="grid gap-4 xl:grid-cols-2">
                            {module.assignments.map((assignment) => {
                                const assignmentStatus =
                                    assignmentStatusConfig[
                                        assignment.submission_status ?? "none"
                                    ] ?? assignmentStatusConfig.none;

                                return (
                                    <Link
                                        key={assignment.id}
                                        href={assignment.url}
                                        className="group rounded-[10px] border border-white/10 bg-white/[0.04] p-5 transition duration-300 hover:-translate-y-1 hover:border-white/18 hover:bg-white/[0.06]"
                                    >
                                        <div className="flex items-start justify-between gap-4">
                                            <div className="space-y-3">
                                                <div className="inline-flex items-center gap-2 rounded-md border border-white/12 bg-black/25 px-3 py-1 text-[11px] uppercase tracking-[0.2em] text-white/60">
                                                    <ClipboardCheck className="size-3.5" />
                                                    #{assignment.sort_order}
                                                </div>
                                                <div>
                                                    <h3 className="text-xl font-semibold tracking-tight text-white">
                                                        {assignment.title}
                                                    </h3>
                                                    <p className="mt-3 text-sm leading-7 text-white/62">
                                                        {assignment.description ||
                                                            "Open this assignment to upload your video submission and track the current review status."}
                                                    </p>
                                                </div>
                                            </div>

                                            <div className="rounded-md border border-white/12 bg-white/5 px-3 py-1 text-xs text-white/68">
                                                Required
                                            </div>
                                        </div>

                                        <div className="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-white/8 pt-4">
                                            <div>
                                                <div
                                                    className={`text-sm font-medium ${assignmentStatus.className}`}
                                                >
                                                    {assignmentStatus.label}
                                                </div>
                                                <div className="mt-1 text-xs text-white/42">
                                                    {assignment.submitted_at
                                                        ? `Last submitted ${assignment.submitted_at}`
                                                        : "No submission has been uploaded yet"}
                                                </div>
                                                {assignment.submission_feedback ? (
                                                    <p className="mt-2 max-w-md text-xs leading-6 text-white/52">
                                                        Latest feedback:{" "}
                                                        {
                                                            assignment.submission_feedback
                                                        }
                                                    </p>
                                                ) : null}
                                            </div>

                                            <div className="inline-flex items-center gap-2 text-sm font-medium text-white">
                                                Open Assignment
                                                <ArrowRight className="size-4 transition group-hover:translate-x-1" />
                                            </div>
                                        </div>
                                    </Link>
                                );
                            })}
                        </div>
                    </section>
                ) : null}

                {module.ebook_enabled ? (
                    <section className="space-y-5">
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <p className="text-xs uppercase tracking-[0.24em] text-white/40">
                                    Ebooks
                                </p>
                                <h2 className="mt-2 text-2xl font-semibold tracking-tight text-white">
                                    Browse your ebook library
                                </h2>
                            </div>
                        </div>

                        {(module.ebooks ?? []).length ? (
                            <div className="grid gap-4 xl:grid-cols-2">
                                {module.ebooks.map((ebook) => (
                                    <div
                                        key={ebook.id}
                                        className="rounded-[10px] border border-white/10 bg-white/[0.04] p-5"
                                    >
                                        <div className="flex items-start justify-between gap-4">
                                            <div className="space-y-3">
                                                <div className="inline-flex items-center gap-2 rounded-md border border-white/12 bg-black/25 px-3 py-1 text-[11px] uppercase tracking-[0.2em] text-white/60">
                                                    <FileText className="size-3.5" />
                                                    Ebook {ebook.sort_order}
                                                </div>
                                                <div>
                                                    <h3 className="text-xl font-semibold tracking-tight text-white">
                                                        {ebook.title}
                                                    </h3>
                                                    <p className="mt-3 text-sm leading-7 text-white/62">
                                                        {ebook.file_name}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <div className="mt-6 flex flex-wrap gap-3 border-t border-white/8 pt-4">
                                            {ebook.download_url ? (
                                                <a
                                                    href={ebook.download_url}
                                                    className="inline-flex items-center gap-2 rounded-lg border border-white/15 bg-white/5 px-4 py-2 text-sm font-medium text-white transition hover:bg-white/10"
                                                >
                                                    Access
                                                    <Download className="size-4" />
                                                </a>
                                            ) : null}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="rounded-[10px] border border-white/10 bg-white/[0.04] p-5 text-sm leading-7 text-white/62">
                                No ebook entries are available for your current
                                access tier in this module yet.
                            </div>
                        )}
                    </section>
                ) : null}

                {module.video_lecturer_enabled ? (
                    <section className="space-y-5">
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <p className="text-xs uppercase tracking-[0.24em] text-white/40">
                                    Video Lecturer
                                </p>
                                <h2 className="mt-2 text-2xl font-semibold tracking-tight text-white">
                                    Browse your lecturer videos
                                </h2>
                            </div>
                        </div>

                        {(module.video_lecturers ?? []).length ? (
                            <div className="grid gap-4 xl:grid-cols-2">
                                {module.video_lecturers.map((course) => (
                                    <div
                                        key={course.id}
                                        className="overflow-hidden rounded-[10px] border border-white/10 bg-white/[0.04]"
                                    >
                                        <div className="relative overflow-hidden">
                                            {course.thumbnail_url ? (
                                                <img
                                                    src={course.thumbnail_url}
                                                    alt={course.title}
                                                    className="aspect-[16/9] h-full w-full object-cover"
                                                />
                                            ) : (
                                                <div className="aspect-[16/9] bg-[radial-gradient(circle_at_24%_20%,_rgba(223,103,57,0.45),_transparent_28%),linear-gradient(160deg,_#2b1d16_0%,_#120f0e_100%)]" />
                                            )}
                                            <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent" />
                                        </div>

                                        <div className="space-y-4 p-5">
                                            <div className="inline-flex items-center gap-2 text-sm font-medium text-[#DB202C]">
                                                <Play className="size-4" />
                                                {course.video?.is_ready
                                                    ? "Ready to watch"
                                                    : "Video unavailable"}
                                            </div>

                                            <div>
                                                <h3 className="text-xl font-semibold tracking-tight text-white">
                                                    {course.title}
                                                </h3>
                                                <p className="mt-3 text-sm leading-7 text-white/62">
                                                    {course.description ||
                                                        "Premium YogaFX lecture content ready for viewing."}
                                                </p>
                                            </div>

                                            {course.video?.warning_message ? (
                                                <p className="text-sm leading-6 text-amber-200/90">
                                                    {
                                                        course.video
                                                            .warning_message
                                                    }
                                                </p>
                                            ) : null}

                                            {course.video?.is_ready &&
                                            course.url ? (
                                                <Link
                                                    href={course.url}
                                                    className="inline-flex items-center gap-2 rounded-lg bg-[#DB202C] px-4 py-2 text-sm font-medium text-white transition hover:bg-[#DB202C]"
                                                >
                                                    Open Video
                                                    <ArrowRight className="size-4" />
                                                </Link>
                                            ) : (
                                                <div className="inline-flex items-center gap-2 rounded-lg border border-white/15 bg-white/5 px-4 py-2 text-sm font-medium text-white/60">
                                                    Video Not Ready
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="rounded-[10px] border border-white/10 bg-white/[0.04] p-5 text-sm leading-7 text-white/62">
                                No video lecturer entries are available for your
                                current access tier in this module yet.
                            </div>
                        )}
                    </section>
                ) : null}

                {module.certificate_enabled ? (
                    <section className="space-y-5">
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <p className="text-xs uppercase tracking-[0.24em] text-white/40">
                                    Certificate
                                </p>
                                <h2 className="mt-2 text-2xl font-semibold tracking-tight text-white">
                                    Download your generated certificates
                                </h2>
                            </div>
                        </div>

                        {(module.certificates ?? []).length ? (
                            <div className="grid gap-4 xl:grid-cols-2">
                                {module.certificates.map((certificate) => (
                                    <a
                                        key={certificate.id}
                                        href={certificate.download_url}
                                        className="group rounded-[10px] border border-white/10 bg-white/[0.04] p-5 transition duration-300 hover:-translate-y-1 hover:border-white/18 hover:bg-white/[0.06]"
                                    >
                                        <div className="flex items-start justify-between gap-4">
                                            <div className="space-y-3">
                                                <div className="inline-flex items-center gap-2 rounded-md border border-white/12 bg-black/25 px-3 py-1 text-[11px] uppercase tracking-[0.2em] text-white/60">
                                                    <Download className="size-3.5" />
                                                    Certificate PDF
                                                </div>
                                                <div>
                                                    <h3 className="text-xl font-semibold tracking-tight text-white">
                                                        {certificate.type_label}
                                                    </h3>
                                                    <p className="mt-3 text-sm leading-7 text-white/62">
                                                        Generated{" "}
                                                        {
                                                            certificate.generated_at
                                                        }
                                                    </p>
                                                </div>
                                            </div>

                                            <div className="inline-flex items-center gap-2 text-sm font-medium text-white">
                                                Download
                                                <ArrowRight className="size-4 transition group-hover:translate-x-1" />
                                            </div>
                                        </div>
                                    </a>
                                ))}
                            </div>
                        ) : (
                            <div className="rounded-[10px] border border-white/10 bg-white/[0.04] p-5 text-sm leading-7 text-white/62">
                                No certificate PDF has been generated for your
                                account yet in this module.
                            </div>
                        )}
                    </section>
                ) : null}
            </div>
        </AuthenticatedLayout>
    );
}
