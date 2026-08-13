import { Head, useForm } from "@inertiajs/react";
import {
    BookOpen,
    CalendarDays,
    Check,
    CheckCircle2,
    Clock3,
    Mail,
    RotateCcw,
    Save,
    User,
} from "lucide-react";
import { useEffect } from "react";

const FONT_FAMILY = "'Montserrat', sans-serif";

function statusLabel(status) {
    switch (status) {
        case "submitted":
            return "Submitted";
        case "under_review":
            return "Under Review";
        case "pending_review":
            return "Pending Review";
        case "approved":
            return "Approved";
        case "rejected":
            return "Needs Resubmission";
        default:
            return status ?? "Unknown";
    }
}

function statusClass(status) {
    switch (status) {
        case "approved":
            return "border-emerald-500/60 bg-emerald-500/10 text-emerald-300";
        case "rejected":
            return "border-[#DB202C]/60 bg-[#DB202C]/10 text-red-300";
        case "under_review":
            return "border-amber-400/50 bg-amber-400/10 text-amber-200";
        case "pending_review":
            return "border-sky-400/50 bg-sky-400/10 text-sky-200";
        default:
            return "border-white/20 bg-white/5 text-white/80";
    }
}

function DetailRow({ icon: Icon, label, value }) {
    return (
        <div className="flex items-start gap-3 border-b border-white/10 py-4 last:border-b-0">
            <div className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-white/5">
                <Icon className="size-4 text-white/70" />
            </div>

            <div className="min-w-0">
                <p className="text-xs font-semibold uppercase tracking-[0.14em] text-white/40">
                    {label}
                </p>

                <p className="mt-1 break-words text-sm font-semibold text-white sm:text-base">
                    {value || "-"}
                </p>
            </div>
        </div>
    );
}

export default function AssignmentReview({ review, status }) {
    const { data, setData, post, processing, errors } = useForm({
        assignment_status: review.status ?? "submitted",
        assignment_feedback: review.feedback ?? "",
    });

    useEffect(() => {
        setData({
            assignment_status: review.status ?? "submitted",
            assignment_feedback: review.feedback ?? "",
        });
    }, [review.status, review.feedback]);

    const submit = (event) => {
        event.preventDefault();

        post(review.update_url, {
            preserveScroll: true,
        });
    };

    const currentSelectedStatus = data.assignment_status;

    return (
        <>
            <Head title="Assignment Review" />

            <div
                className="relative isolate min-h-screen overflow-x-hidden bg-black text-white"
                style={{ fontFamily: FONT_FAMILY }}
            >
                <div
                    aria-hidden="true"
                    className="pointer-events-none fixed inset-0 z-0 flex items-center justify-center overflow-hidden"
                >
                    <img
                        src="/images/yogafx-white-icon.png"
                        alt=""
                        className="w-[360px] max-w-none select-none opacity-[0.08] sm:w-[520px] lg:w-[680px]"
                    />
                </div>

                <div
                    aria-hidden="true"
                    className="pointer-events-none fixed inset-0 z-0"
                    style={{
                        backgroundImage:
                            "radial-gradient(circle at 50% 20%, rgba(219,32,44,0.10), transparent 28%), radial-gradient(circle at 80% 70%, rgba(16,185,129,0.06), transparent 25%)",
                    }}
                />

                <div className="relative z-10 mx-auto w-full max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-12">
                    <header className="mb-10 flex flex-col items-center text-center">
                        <img
                            src="https://yogafx.b-cdn.net/content/Logo%20YogAFX.png"
                            alt="YogaFX"
                            className="h-16 w-auto object-contain sm:h-20"
                        />

                        <p className="mt-8 text-xs font-bold uppercase tracking-[0.22em] text-[#DB202C]">
                            Instructor Review Portal
                        </p>

                        <h1 className="mt-3 text-3xl font-bold tracking-[-0.04em] text-white sm:text-4xl">
                            Student Assignment Review
                        </h1>

                        <p className="mx-auto mt-4 max-w-2xl text-sm italic leading-7 text-white/60 sm:text-base">
                            Review the student's submitted video and update
                            their assessment status below.
                        </p>
                    </header>

                    {status === "assignment-review-saved" ? (
                        <div className="mb-6 flex items-center gap-3 rounded-xl border border-emerald-500/40 bg-emerald-500/10 px-4 py-4 text-sm font-semibold text-emerald-200">
                            <CheckCircle2 className="size-5 shrink-0" />
                            Review saved successfully. The student's latest
                            assignment status has been updated.
                        </div>
                    ) : null}

                    <div className="grid gap-6 lg:grid-cols-[minmax(0,1.55fr)_minmax(320px,0.85fr)]">
                        <div className="space-y-6">
                            <section className="overflow-hidden rounded-[18px] border border-white/15 bg-[#111111]/95 shadow-[0_24px_80px_rgba(0,0,0,0.35)]">
                                <div className="flex flex-col gap-4 border-b border-white/10 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                                    <div>
                                        <p className="text-xs font-bold uppercase tracking-[0.16em] text-white/40">
                                            Assignment
                                        </p>

                                        <h2 className="mt-1 text-xl font-bold text-white sm:text-2xl">
                                            {review.assignment.title}
                                        </h2>

                                        <p className="mt-1 text-sm text-white/55">
                                            {review.assignment.module_title}
                                        </p>
                                    </div>

                                    <span
                                        className={[
                                            "inline-flex w-fit items-center rounded-full border px-3 py-1.5 text-xs font-bold",
                                            statusClass(review.status),
                                        ].join(" ")}
                                    >
                                        {statusLabel(review.status)}
                                    </span>
                                </div>

                                <div className="p-3 sm:p-5">
                                    {review.video_url ? (
                                        <div className="overflow-hidden rounded-xl border border-white/15 bg-black">
                                            <video
                                                src={review.video_url}
                                                controls
                                                playsInline
                                                preload="metadata"
                                                className="aspect-video w-full bg-black object-contain"
                                            >
                                                Your browser does not support
                                                video playback.
                                            </video>
                                        </div>
                                    ) : (
                                        <div className="flex aspect-video items-center justify-center rounded-xl border border-dashed border-white/20 bg-black/40 px-6 text-center text-sm text-white/50">
                                            No assignment video is available.
                                        </div>
                                    )}
                                </div>
                            </section>

                            <form
                                onSubmit={submit}
                                className="rounded-[18px] border border-white/15 bg-[#111111]/95 p-5 shadow-[0_24px_80px_rgba(0,0,0,0.35)] sm:p-6"
                            >
                                <div>
                                    <p className="text-xs font-bold uppercase tracking-[0.16em] text-[#DB202C]">
                                        Assessment Decision
                                    </p>

                                    <h2 className="mt-2 text-2xl font-bold text-white">
                                        Review Assignment
                                    </h2>

                                    <p className="mt-2 text-sm leading-6 text-white/55">
                                        Choose a final decision or use one of
                                        the internal review statuses.
                                    </p>
                                </div>

                                <div className="mt-6 grid gap-3 sm:grid-cols-2">
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setData(
                                                "assignment_status",
                                                "approved",
                                            )
                                        }
                                        className={[
                                            "flex min-h-[86px] items-center gap-4 rounded-xl border-2 px-4 py-4 text-left transition-all",
                                            currentSelectedStatus === "approved"
                                                ? "border-emerald-500 bg-emerald-500/12 shadow-[0_0_0_2px_rgba(16,185,129,0.14)]"
                                                : "border-white/15 bg-black/25 hover:border-emerald-500/60 hover:bg-emerald-500/5",
                                        ].join(" ")}
                                    >
                                        <span
                                            className={[
                                                "flex size-10 shrink-0 items-center justify-center rounded-full",
                                                currentSelectedStatus ===
                                                "approved"
                                                    ? "bg-emerald-500 text-white"
                                                    : "bg-white/10 text-white/50",
                                            ].join(" ")}
                                        >
                                            <Check className="size-5" />
                                        </span>

                                        <span>
                                            <span className="block font-bold text-white">
                                                Approved
                                            </span>

                                            <span className="mt-1 block text-xs leading-5 text-white/50">
                                                The assignment meets the
                                                required standard.
                                            </span>
                                        </span>
                                    </button>

                                    <button
                                        type="button"
                                        onClick={() =>
                                            setData(
                                                "assignment_status",
                                                "rejected",
                                            )
                                        }
                                        className={[
                                            "flex min-h-[86px] items-center gap-4 rounded-xl border-2 px-4 py-4 text-left transition-all",
                                            currentSelectedStatus === "rejected"
                                                ? "border-[#DB202C] bg-[#DB202C]/12 shadow-[0_0_0_2px_rgba(219,32,44,0.14)]"
                                                : "border-white/15 bg-black/25 hover:border-[#DB202C]/60 hover:bg-[#DB202C]/5",
                                        ].join(" ")}
                                    >
                                        <span
                                            className={[
                                                "flex size-10 shrink-0 items-center justify-center rounded-full",
                                                currentSelectedStatus ===
                                                "rejected"
                                                    ? "bg-[#DB202C] text-white"
                                                    : "bg-white/10 text-white/50",
                                            ].join(" ")}
                                        >
                                            <RotateCcw className="size-5" />
                                        </span>

                                        <span>
                                            <span className="block font-bold text-white">
                                                Needs Resubmission
                                            </span>

                                            <span className="mt-1 block text-xs leading-5 text-white/50">
                                                Ask the student to submit a
                                                revised video.
                                            </span>
                                        </span>
                                    </button>
                                </div>

                                <div className="mt-6">
                                    <label
                                        htmlFor="assignment_status"
                                        className="mb-2 block text-sm font-bold text-white"
                                    >
                                        Review Status
                                    </label>

                                    <select
                                        id="assignment_status"
                                        value={data.assignment_status}
                                        onChange={(event) =>
                                            setData(
                                                "assignment_status",
                                                event.target.value,
                                            )
                                        }
                                        className="block min-h-[52px] w-full rounded-lg border-2 border-white/30 bg-black/70 px-4 py-3 text-sm font-semibold text-white outline-none transition focus:border-[#DB202C] focus:ring-2 focus:ring-[#DB202C]/20"
                                    >
                                        {review.status_options.map(
                                            (option) => (
                                                <option
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </option>
                                            ),
                                        )}
                                    </select>

                                    {errors.assignment_status ? (
                                        <p className="mt-2 text-sm font-semibold text-red-400">
                                            {errors.assignment_status}
                                        </p>
                                    ) : null}
                                </div>

                                <div className="mt-5">
                                    <label
                                        htmlFor="assignment_feedback"
                                        className="mb-2 block text-sm font-bold text-white"
                                    >
                                        Feedback
                                        <span className="ml-2 font-medium italic text-white/40">
                                            Optional
                                        </span>
                                    </label>

                                    <textarea
                                        id="assignment_feedback"
                                        rows={6}
                                        value={data.assignment_feedback}
                                        onChange={(event) =>
                                            setData(
                                                "assignment_feedback",
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Add feedback for the student if needed..."
                                        className="block w-full resize-y rounded-lg border-2 border-white/30 bg-black/70 px-4 py-3 text-sm font-semibold leading-6 text-white placeholder:text-white/30 focus:border-[#DB202C] focus:outline-none focus:ring-2 focus:ring-[#DB202C]/20"
                                    />

                                    {errors.assignment_feedback ? (
                                        <p className="mt-2 text-sm font-semibold text-red-400">
                                            {errors.assignment_feedback}
                                        </p>
                                    ) : null}
                                </div>

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="mt-6 inline-flex min-h-[56px] w-full items-center justify-center gap-2 rounded-lg bg-[#DB202C] px-6 py-4 text-base font-bold text-white shadow-[0_12px_35px_rgba(219,32,44,0.25)] transition-all hover:-translate-y-0.5 hover:bg-[#c01a25] focus:outline-none focus:ring-4 focus:ring-[#DB202C]/30 disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    <Save className="size-5" />

                                    {processing
                                        ? "Saving Review..."
                                        : "Save Review"}
                                </button>
                            </form>
                        </div>

                        <aside className="space-y-6">
                            <section className="rounded-[18px] border border-white/15 bg-[#111111]/95 p-5 shadow-[0_24px_80px_rgba(0,0,0,0.35)] sm:p-6">
                                <div className="flex items-center gap-4 border-b border-white/10 pb-5">
                                    {review.student.profile_photo_url ? (
                                        <img
                                            src={
                                                review.student
                                                    .profile_photo_url
                                            }
                                            alt={review.student.name}
                                            className="size-16 rounded-full border-2 border-white/15 object-cover"
                                        />
                                    ) : (
                                        <div className="flex size-16 items-center justify-center rounded-full border-2 border-white/15 bg-white/10 text-lg font-bold text-white">
                                            {review.student.initials}
                                        </div>
                                    )}

                                    <div className="min-w-0">
                                        <p className="text-xs font-bold uppercase tracking-[0.16em] text-[#DB202C]">
                                            Student
                                        </p>

                                        <h2 className="mt-1 truncate text-xl font-bold text-white">
                                            {review.student.name}
                                        </h2>
                                    </div>
                                </div>

                                <DetailRow
                                    icon={Mail}
                                    label="Email"
                                    value={review.student.email}
                                />

                                <DetailRow
                                    icon={BookOpen}
                                    label="Module"
                                    value={
                                        review.assignment.module_title
                                    }
                                />

                                <DetailRow
                                    icon={CalendarDays}
                                    label="Submitted"
                                    value={review.submitted_at}
                                />

                                <DetailRow
                                    icon={Clock3}
                                    label="Last Reviewed"
                                    value={
                                        review.reviewed_at ??
                                        "Not reviewed yet"
                                    }
                                />
                            </section>

                            {review.feedback ? (
                                <section className="rounded-[18px] border border-white/15 bg-[#111111]/95 p-5 sm:p-6">
                                    <p className="text-xs font-bold uppercase tracking-[0.16em] text-white/40">
                                        Previous Feedback
                                    </p>

                                    <p className="mt-3 whitespace-pre-line text-sm font-medium leading-7 text-white/75">
                                        {review.feedback}
                                    </p>
                                </section>
                            ) : null}

                            <section className="rounded-[18px] border border-white/10 bg-white/[0.035] p-5">
                                <div className="flex items-start gap-3">
                                    <User className="mt-0.5 size-5 shrink-0 text-white/40" />

                                    <p className="text-xs leading-6 text-white/45">
                                        This is a private permanent review
                                        link. No LMS login is required. Keep
                                        this link private because anyone with
                                        the link can access this assignment
                                        review.
                                    </p>
                                </div>
                            </section>
                        </aside>
                    </div>

                    <footer className="mt-10 border-t border-white/10 py-8 text-center">
                        <p className="text-sm font-bold text-white/55">
                            © {new Date().getFullYear()} Yoga
                            <span className="text-[#DB202C]">FX</span>
                        </p>
                    </footer>
                </div>
            </div>
        </>
    );
}