import DeleteConfirmationDialog from '@/Components/DeleteConfirmationDialog';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Textarea } from '@/Components/ui/textarea';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { router, Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const statusMessages = {
    'assignment-submission-updated': 'Assignment submission has been updated.',
    'assignment-submission-video-deleted': 'Assignment submission video has been deleted.',
};

function SubmissionStatusBadge({ status }) {
    const variant =
        status === 'approved'
            ? 'secondary'
            : status === 'rejected'
              ? 'destructive'
              : 'outline';

    return <Badge variant={variant}>{status.replaceAll('_', ' ')}</Badge>;
}

export default function AssignmentShow({
    module,
    assignment,
    submissions,
    submissionStatuses,
    status,
}) {
    const errors = usePage().props.errors;
    const [submissionDrafts, setSubmissionDrafts] = useState(() =>
        Object.fromEntries(
            submissions.map((submission) => [
                submission.id,
                {
                    assignment_status: submission.status,
                    assignment_feedback: submission.feedback ?? '',
                },
            ]),
        ),
    );

    useEffect(() => {
        setSubmissionDrafts(
            Object.fromEntries(
                submissions.map((submission) => [
                    submission.id,
                    {
                        assignment_status: submission.status,
                        assignment_feedback: submission.feedback ?? '',
                    },
                ]),
            ),
        );
    }, [submissions]);

    const updateSubmissionDraft = (submissionId, field, value) => {
        setSubmissionDrafts((current) => ({
            ...current,
            [submissionId]: {
                ...current[submissionId],
                [field]: value,
            },
        }));
    };

    const saveSubmission = (submissionId) => {
        router.patch(
            route('admin.modules.assignments.submissions.update', {
                module: module.id,
                assignment: assignment.id,
                assignmentSubmission: submissionId,
            }),
            submissionDrafts[submissionId],
        );
    };

    const deleteSubmissionVideo = (submissionId, onFinish) => {
        router.delete(
            route('admin.modules.assignments.submissions.delete-video', {
                module: module.id,
                assignment: assignment.id,
                assignmentSubmission: submissionId,
            }),
            {
                onFinish,
            },
        );
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            Review Assignment
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            Review student submissions for {assignment.title} in module {module.title}.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-3">
                        <Link
                            href={route('admin.modules.assignments.edit', {
                                module: module.id,
                                assignment: assignment.id,
                            })}
                            className="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Edit Assignment
                        </Link>
                        <Link
                            href={route('admin.modules.assignments.index', module.id)}
                            className="rounded-md text-sm font-medium text-indigo-600 hover:text-indigo-800"
                        >
                            Back to Assignments
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title="Review Assignment" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {statusMessages[status] && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            {statusMessages[status]}
                        </div>
                    )}
                    {Object.keys(errors).length > 0 && (
                        <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                            {Object.values(errors)[0]}
                        </div>
                    )}

                    <div className="rounded-lg bg-white p-6 shadow-sm">
                        <div className="space-y-2">
                            <div className="text-sm font-semibold text-gray-900">
                                {assignment.title}
                            </div>
                            {assignment.description ? (
                                <div className="max-w-4xl text-sm leading-6 text-gray-600">
                                    {assignment.description}
                                </div>
                            ) : null}
                            <div className="flex flex-wrap gap-4 text-xs uppercase tracking-[0.16em] text-gray-500">
                                <span>Status: {assignment.status}</span>
                                <span>{assignment.is_required ? 'Required' : 'Optional'}</span>
                                <span>Order {assignment.sort_order}</span>
                            </div>
                        </div>
                    </div>

                    <div className="rounded-lg bg-white p-6 shadow-sm">
                        {submissions.length === 0 ? (
                            <div className="rounded-lg border border-dashed border-gray-300 px-6 py-10 text-center text-sm text-gray-500">
                                No student submission has been uploaded for this assignment yet.
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-4 py-3 text-left font-medium text-gray-700">
                                                Student
                                            </th>
                                            <th className="px-4 py-3 text-left font-medium text-gray-700">
                                                Video
                                            </th>
                                            <th className="px-4 py-3 text-left font-medium text-gray-700">
                                                Status
                                            </th>
                                            <th className="px-4 py-3 text-left font-medium text-gray-700">
                                                Feedback
                                            </th>
                                            <th className="px-4 py-3 text-left font-medium text-gray-700">
                                                Action
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100 bg-white">
                                        {submissions.map((submission) => (
                                            <tr key={submission.id}>
                                                <td className="px-4 py-3 align-top">
                                                    <div className="font-medium text-gray-900">
                                                        {submission.student.name}
                                                    </div>
                                                    <div className="text-xs text-gray-500">
                                                        {submission.student.email}
                                                    </div>
                                                    <div className="mt-1 text-xs text-gray-500">
                                                        Submitted {submission.submitted_at || '-'}
                                                    </div>
                                                    <div className="text-xs text-gray-500">
                                                        Reviewed {submission.reviewed_at || '-'}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3 align-top">
                                                    {submission.video_url ? (
                                                        <a
                                                            href={submission.video_url}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                                        >
                                                            Open Video
                                                        </a>
                                                    ) : (
                                                        <span className="text-sm text-gray-500">
                                                            No video
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 align-top">
                                                    <div className="space-y-3">
                                                        <SubmissionStatusBadge
                                                            status={
                                                                submissionDrafts[submission.id]
                                                                    ?.assignment_status ??
                                                                submission.status
                                                            }
                                                        />
                                                        <select
                                                            value={
                                                                submissionDrafts[submission.id]
                                                                    ?.assignment_status ??
                                                                submission.status
                                                            }
                                                            onChange={(event) =>
                                                                updateSubmissionDraft(
                                                                    submission.id,
                                                                    'assignment_status',
                                                                    event.target.value,
                                                                )
                                                            }
                                                            className="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                        >
                                                            {submissionStatuses.map((option) => (
                                                                <option key={option.value} value={option.value}>
                                                                    {option.label}
                                                                </option>
                                                            ))}
                                                        </select>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3 align-top">
                                                    <Textarea
                                                        value={
                                                            submissionDrafts[submission.id]
                                                                ?.assignment_feedback ?? ''
                                                        }
                                                        onChange={(event) =>
                                                            updateSubmissionDraft(
                                                                submission.id,
                                                                'assignment_feedback',
                                                                event.target.value,
                                                            )
                                                        }
                                                        className="min-h-24"
                                                        placeholder="Add review feedback for this student submission."
                                                    />
                                                </td>
                                                <td className="px-4 py-3 align-top">
                                                    <div className="flex flex-col items-start gap-2">
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            onClick={() => saveSubmission(submission.id)}
                                                        >
                                                            Save Review
                                                        </Button>
                                                        <DeleteConfirmationDialog
                                                            title="Delete assignment video?"
                                                            description="This removes the submitted video reference from the student record."
                                                            trigger={
                                                                <Button
                                                                    type="button"
                                                                    size="sm"
                                                                    variant="destructive"
                                                                    disabled={!submission.video_url}
                                                                >
                                                                    Delete Video
                                                                </Button>
                                                            }
                                                            onConfirm={({ onFinish }) =>
                                                                deleteSubmissionVideo(
                                                                    submission.id,
                                                                    onFinish,
                                                                )
                                                            }
                                                            confirmLabel="Delete Video"
                                                        />
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
