import DeleteConfirmationDialog from '@/Components/DeleteConfirmationDialog';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { Textarea } from '@/Components/ui/textarea';
import StudentProgressStudentLayout from '@/Components/admin/student-progress/StudentProgressStudentLayout';
import { router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const statusMessages = {
    'student-progress-assignment-saved': 'Assignment has been saved.',
    'student-progress-assignment-email-sent': 'Assignment email has been sent.',
    'student-progress-assignment-video-deleted': 'Assignment video has been deleted.',
};

function AssignmentStatusBadge({ status }) {
    const variant =
        status === 'approved'
            ? 'secondary'
            : status === 'rejected'
              ? 'destructive'
              : 'outline';

    return <Badge variant={variant}>{status.replaceAll('_', ' ')}</Badge>;
}

export default function Assignments({
    student,
    assignments,
    assignmentStatuses,
    status,
}) {
    const errors = usePage().props.errors;
    const [assignmentDrafts, setAssignmentDrafts] = useState(() =>
        Object.fromEntries(
            assignments.map((assignment) => [
                assignment.id,
                {
                    assignment_status: assignment.status,
                    assignment_feedback: assignment.feedback ?? '',
                },
            ]),
        ),
    );

    useEffect(() => {
        setAssignmentDrafts(
            Object.fromEntries(
                assignments.map((assignment) => [
                    assignment.id,
                    {
                        assignment_status: assignment.status,
                        assignment_feedback: assignment.feedback ?? '',
                    },
                ]),
            ),
        );
    }, [assignments]);

    const updateAssignmentDraft = (assignmentId, field, value) => {
        setAssignmentDrafts((current) => ({
            ...current,
            [assignmentId]: {
                ...current[assignmentId],
                [field]: value,
            },
        }));
    };

    const saveAssignment = (assignmentId) => {
        router.patch(
            route('admin.student-progress.assignments.update', {
                student: student.id,
                assignmentSubmission: assignmentId,
            }),
            assignmentDrafts[assignmentId],
        );
    };

    const sendAssignmentEmail = (assignmentId) => {
        router.post(
            route('admin.student-progress.assignments.send-email', {
                student: student.id,
                assignmentSubmission: assignmentId,
            }),
        );
    };

    const deleteAssignmentVideo = (assignmentId, onFinish) => {
        router.delete(
            route('admin.student-progress.assignments.delete-video', {
                student: student.id,
                assignmentSubmission: assignmentId,
            }),
            {
                onFinish,
            },
        );
    };

    return (
        <StudentProgressStudentLayout
            title="Assignment"
            description="Review assignment submissions, update status, send email, and manage the submitted video."
            pageTitle="Assignment"
            student={student}
            activeSection="assignment"
        >
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
                {assignments.length === 0 ? (
                    <div className="rounded-lg border border-dashed border-gray-300 px-6 py-10 text-center text-sm text-gray-500">
                        No assignment data is available for this student yet.
                    </div>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Title</TableHead>
                                <TableHead>Video</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Feedback</TableHead>
                                <TableHead className="text-right">Action</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {assignments.map((assignment) => (
                                <TableRow key={assignment.id}>
                                    <TableCell className="align-top font-medium text-gray-900">
                                        <div>{assignment.title}</div>
                                        <div className="mt-1 text-xs text-gray-500">
                                            Submitted {assignment.submitted_at || '-'}
                                        </div>
                                    </TableCell>
                                    <TableCell className="align-top">
                                        {assignment.video ? (
                                            assignment.video.startsWith('http') ? (
                                                <a
                                                    href={assignment.video}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                                >
                                                    Open Video
                                                </a>
                                            ) : (
                                                <span className="text-sm text-gray-700">
                                                    {assignment.video}
                                                </span>
                                            )
                                        ) : (
                                            <span className="text-sm text-gray-500">
                                                No video
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell className="align-top">
                                        <div className="space-y-3">
                                            <AssignmentStatusBadge
                                                status={
                                                    assignmentDrafts[assignment.id]
                                                        ?.assignment_status ??
                                                    assignment.status
                                                }
                                            />
                                            <select
                                                value={
                                                    assignmentDrafts[assignment.id]
                                                        ?.assignment_status ??
                                                    assignment.status
                                                }
                                                onChange={(event) =>
                                                    updateAssignmentDraft(
                                                        assignment.id,
                                                        'assignment_status',
                                                        event.target.value,
                                                    )
                                                }
                                                className="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            >
                                                {assignmentStatuses.map((option) => (
                                                    <option key={option.value} value={option.value}>
                                                        {option.label}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                    </TableCell>
                                    <TableCell className="align-top">
                                        <Textarea
                                            value={
                                                assignmentDrafts[assignment.id]
                                                    ?.assignment_feedback ?? ''
                                            }
                                            onChange={(event) =>
                                                updateAssignmentDraft(
                                                    assignment.id,
                                                    'assignment_feedback',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Feedback is especially useful when assignment is rejected."
                                            className="min-h-24"
                                        />
                                    </TableCell>
                                    <TableCell className="align-top">
                                        <div className="flex flex-col items-end gap-2">
                                            <Button
                                                type="button"
                                                size="sm"
                                                onClick={() => saveAssignment(assignment.id)}
                                            >
                                                Save
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    sendAssignmentEmail(assignment.id)
                                                }
                                            >
                                                Send Email
                                            </Button>
                                            <DeleteConfirmationDialog
                                                title="Delete assignment video?"
                                                description="This will remove the submitted video reference from this assignment."
                                                trigger={
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="destructive"
                                                        disabled={!assignment.video}
                                                    >
                                                        Delete Video
                                                    </Button>
                                                }
                                                onConfirm={({ onFinish }) =>
                                                    deleteAssignmentVideo(
                                                        assignment.id,
                                                        onFinish,
                                                    )
                                                }
                                                confirmLabel="Delete Video"
                                            />
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </div>
        </StudentProgressStudentLayout>
    );
}
