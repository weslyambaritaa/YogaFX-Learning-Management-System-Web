import InputError from '@/Components/InputError';
import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

const statusMessages = {
    'assignment-submission-saved': 'Your assignment video has been uploaded and is ready for review.',
};

const submissionStatusConfig = {
    submitted: 'Submitted',
    under_review: 'Under Review',
    pending_review: 'Pending Review',
    approved: 'Approved',
    rejected: 'Rejected',
};

export default function StudentAssignmentShow({ assignment, uploadConstraints, status }) {
    const { data, setData, post, processing, errors, progress } = useForm({
        video: null,
    });

    const submit = (event) => {
        event.preventDefault();
        post(route('assignments.submit', assignment.id), {
            forceFormData: true,
        });
    };

    return (
        <AuthenticatedLayout
            studentVariant="immersive"
            studentContentClassName="pb-16"
        >
            <Head title={assignment.title} />

            <div className="mx-auto flex max-w-[1200px] flex-col gap-8 px-4 pt-8 sm:px-6 lg:px-10">
                <section className="rounded-[32px] border border-white/10 bg-[#120f0f] p-8 shadow-[0_24px_90px_rgba(0,0,0,0.35)]">
                    <div className="space-y-4">
                        <p className="text-xs uppercase tracking-[0.28em] text-[#f2d9c8]">
                            {assignment.module?.title ?? 'Module Assignment'}
                        </p>
                        <h1 className="text-4xl font-semibold tracking-[-0.03em] text-white sm:text-5xl">
                            {assignment.title}
                        </h1>
                        <p className="max-w-3xl text-sm leading-7 text-white/68 sm:text-base">
                            {assignment.description ||
                                'Upload your assignment video here. This assignment is kept separate from lessons so your submission workflow stays clean and reviewable.'}
                        </p>
                    </div>

                    <div className="mt-6 flex flex-wrap gap-3">
                        <div className="rounded-full border border-white/12 bg-white/5 px-4 py-2 text-sm text-white/80 backdrop-blur">
                            {assignment.is_required ? 'Required submission' : 'Optional submission'}
                        </div>
                        <div className="rounded-full border border-white/12 bg-white/5 px-4 py-2 text-sm text-white/80 backdrop-blur">
                            Assignment {assignment.sort_order}
                        </div>
                    </div>
                </section>

                {statusMessages[status] && (
                    <div className="rounded-[24px] border border-emerald-400/25 bg-emerald-500/10 px-5 py-4 text-sm leading-7 text-emerald-100">
                        {statusMessages[status]}
                    </div>
                )}

                <div className="grid gap-8 lg:grid-cols-[minmax(0,1.25fr)_360px]">
                    <section className="rounded-[28px] border border-white/10 bg-white/[0.04] p-6 backdrop-blur">
                        <h2 className="text-2xl font-semibold text-white">
                            Submit Assignment Video
                        </h2>
                        <p className="mt-3 text-sm leading-7 text-white/62">
                            Upload a supported video file. The submission will replace your previous file for this assignment and move back into the review queue.
                        </p>

                        <form onSubmit={submit} className="mt-6 space-y-5" encType="multipart/form-data">
                            <div>
                                <label
                                    htmlFor="video"
                                    className="text-sm font-medium text-white"
                                >
                                    Assignment Video
                                </label>
                                <input
                                    id="video"
                                    type="file"
                                    accept=".mp4,.mov,.webm,.avi,.m4v,video/*"
                                    onChange={(event) => setData('video', event.target.files?.[0] ?? null)}
                                    className="mt-2 block w-full rounded-xl border border-white/10 bg-black/20 px-4 py-3 text-sm text-white file:mr-4 file:rounded-full file:border-0 file:bg-[#f15b3a] file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-[#dc4f31]"
                                />
                                <p className="mt-2 text-xs text-white/45">
                                    Maximum file size: {uploadConstraints.video_max_size_label}. Supported formats include MP4, MOV, WEBM, AVI, and M4V.
                                </p>
                                <InputError className="mt-2" message={errors.video} />
                            </div>

                            {progress && (
                                <div className="space-y-2">
                                    <div className="flex items-center justify-between text-xs uppercase tracking-[0.18em] text-white/45">
                                        <span>Upload progress</span>
                                        <span>{progress.percentage}%</span>
                                    </div>
                                    <div className="h-2 overflow-hidden rounded-full bg-white/10">
                                        <div
                                            className="h-full rounded-full bg-[#f15b3a]"
                                            style={{ width: `${progress.percentage}%` }}
                                        />
                                    </div>
                                </div>
                            )}

                            <div className="flex flex-wrap gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Uploading...' : 'Submit Video'}
                                </Button>
                                {assignment.module?.url && (
                                    <Button type="button" variant="outline" asChild>
                                        <Link href={assignment.module.url}>Back to Module</Link>
                                    </Button>
                                )}
                            </div>
                        </form>
                    </section>

                    <aside className="space-y-5">
                        <div className="rounded-[28px] border border-white/10 bg-white/[0.04] p-5 backdrop-blur">
                            <p className="text-xs uppercase tracking-[0.22em] text-white/45">
                                Current Submission
                            </p>
                            <div className="mt-4 space-y-3 text-sm text-white/72">
                                <div>
                                    <div className="text-white/45">Status</div>
                                    <div className="mt-1 font-medium text-white">
                                        {submissionStatusConfig[assignment.submission?.status] ?? 'Not submitted'}
                                    </div>
                                </div>
                                <div>
                                    <div className="text-white/45">Submitted at</div>
                                    <div className="mt-1 font-medium text-white">
                                        {assignment.submission?.submitted_at ?? '-'}
                                    </div>
                                </div>
                                <div>
                                    <div className="text-white/45">Reviewed at</div>
                                    <div className="mt-1 font-medium text-white">
                                        {assignment.submission?.reviewed_at ?? '-'}
                                    </div>
                                </div>
                                {assignment.submission?.video_url && (
                                    <div className="pt-2">
                                        <a
                                            href={assignment.submission.video_url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="text-sm font-medium text-[#f2d9c8] hover:text-white"
                                        >
                                            Open current submission video
                                        </a>
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className="rounded-[28px] border border-white/10 bg-black/25 p-5 shadow-2xl backdrop-blur-md">
                            <p className="text-xs uppercase tracking-[0.22em] text-white/45">
                                Review Feedback
                            </p>
                            <div className="mt-4 text-sm leading-7 text-white/68">
                                {assignment.submission?.feedback ||
                                    'No review feedback has been added yet. Once admin reviews your submission, the latest note will appear here.'}
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
