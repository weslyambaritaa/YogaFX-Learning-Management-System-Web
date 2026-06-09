import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { Textarea } from '@/Components/ui/textarea';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const statusMessages = {
    'student-progress-lesson-reset': 'Lesson progress has been reset.',
    'student-progress-assignment-saved': 'Assignment has been saved.',
    'student-progress-assignment-email-sent': 'Assignment email has been sent.',
    'student-progress-assignment-video-deleted': 'Assignment video has been deleted.',
    'student-progress-certificate-generated': 'Certificate has been generated.',
    'student-progress-certificate-recreated': 'Certificate has been recreated.',
    'student-progress-certificate-deleted': 'Certificate has been deleted.',
    'student-progress-graduation-email-sent': 'Graduation email has been sent.',
};

function AssignmentStatusBadge({ status }) {
    const variant =
        status === 'approved'
            ? 'secondary'
            : status === 'rejected'
              ? 'destructive'
              : 'outline';

    return (
        <Badge variant={variant}>
            {status.replaceAll('_', ' ')}
        </Badge>
    );
}

export default function StudentProgress({
    student,
    activeTab,
    completedLessons,
    assignments,
    assignmentStatuses,
    certificates,
    certificateTypes,
    certificateEligibility,
    status,
}) {
    const errors = usePage().props.errors;
    const [currentTab, setCurrentTab] = useState(activeTab);
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

    const certificateForm = useForm({
        certificate_type: certificateTypes[0]?.value ?? '',
    });

    useEffect(() => {
        setCurrentTab(activeTab);
    }, [activeTab]);

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

    const resetLessonProgress = (lessonProgressId) => {
        if (!window.confirm('Reset this completed lesson progress?')) {
            return;
        }

        router.post(
            route('admin.students.progress.lessons.reset', {
                student: student.id,
                lessonProgress: lessonProgressId,
            }),
        );
    };

    const saveAssignment = (assignmentId) => {
        const payload = assignmentDrafts[assignmentId];

        router.patch(
            route('admin.students.progress.assignments.update', {
                student: student.id,
                assignmentSubmission: assignmentId,
            }),
            payload,
        );
    };

    const sendAssignmentEmail = (assignmentId) => {
        router.post(
            route('admin.students.progress.assignments.send-email', {
                student: student.id,
                assignmentSubmission: assignmentId,
            }),
        );
    };

    const deleteAssignmentVideo = (assignmentId) => {
        if (!window.confirm('Delete this assignment video reference?')) {
            return;
        }

        router.delete(
            route('admin.students.progress.assignments.delete-video', {
                student: student.id,
                assignmentSubmission: assignmentId,
            }),
        );
    };

    const recreateCertificate = (certificateId) => {
        router.post(
            route('admin.students.progress.certificates.recreate', {
                student: student.id,
                certificate: certificateId,
            }),
        );
    };

    const deleteCertificate = (certificateId) => {
        if (!window.confirm('Delete this certificate?')) {
            return;
        }

        router.delete(
            route('admin.students.progress.certificates.destroy', {
                student: student.id,
                certificate: certificateId,
            }),
        );
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            Student Progress
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            Review completed lessons, assignment activity, and
                            certificate actions for this student.
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        <Link
                            href={route('admin.students.edit', student.id)}
                            className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                        >
                            Back to Student Detail
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title="Student Progress" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <div className="grid gap-6 md:grid-cols-3">
                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <div className="text-sm text-gray-500">Student</div>
                            <div className="mt-1 text-lg font-semibold text-gray-900">
                                {student.name || 'Unnamed student'}
                            </div>
                            <div className="mt-2 text-sm text-gray-600">
                                {student.email}
                            </div>
                        </div>

                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <div className="text-sm text-gray-500">Access Tier</div>
                            <div className="mt-1 text-lg font-semibold text-gray-900">
                                {student.access_tier || 'Not assigned'}
                            </div>
                        </div>

                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <div className="text-sm text-gray-500">Certificate Eligibility</div>
                            <div className="mt-2">
                                <Badge
                                    variant={
                                        certificateEligibility.eligible
                                            ? 'secondary'
                                            : 'outline'
                                    }
                                >
                                    {certificateEligibility.eligible
                                        ? 'Eligible'
                                        : 'Not Eligible'}
                                </Badge>
                            </div>
                            {certificateEligibility.message && (
                                <div className="mt-3 text-sm text-gray-600">
                                    {certificateEligibility.message}
                                </div>
                            )}
                        </div>
                    </div>

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
                        <Tabs value={currentTab} onValueChange={setCurrentTab}>
                            <TabsList>
                                <TabsTrigger value="completed-lessons">
                                    Completed Lesson
                                </TabsTrigger>
                                <TabsTrigger value="assignment">
                                    Assignment
                                </TabsTrigger>
                                <TabsTrigger value="certificate">
                                    Certificate
                                </TabsTrigger>
                            </TabsList>

                            <TabsContent value="completed-lessons" className="mt-6">
                                {completedLessons.length === 0 ? (
                                    <div className="rounded-lg border border-dashed border-gray-300 px-6 py-10 text-center text-sm text-gray-500">
                                        This student has not completed any lessons yet.
                                    </div>
                                ) : (
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Lesson</TableHead>
                                                <TableHead>Module</TableHead>
                                                <TableHead>Completed At</TableHead>
                                                <TableHead>Watch Progress</TableHead>
                                                <TableHead className="text-right">
                                                    Action
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {completedLessons.map((progress) => (
                                                <TableRow key={progress.id}>
                                                    <TableCell className="font-medium text-gray-900">
                                                        {progress.lesson_title}
                                                    </TableCell>
                                                    <TableCell>
                                                        {progress.module_title}
                                                    </TableCell>
                                                    <TableCell>
                                                        {progress.completed_at || '-'}
                                                    </TableCell>
                                                    <TableCell>
                                                        {progress.watch_progress}%
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        <Button
                                                            type="button"
                                                            variant="destructive"
                                                            size="sm"
                                                            onClick={() =>
                                                                resetLessonProgress(progress.id)
                                                            }
                                                        >
                                                            Reset Progress
                                                        </Button>
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                )}
                            </TabsContent>

                            <TabsContent value="assignment" className="mt-6">
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
                                                <TableHead className="text-right">
                                                    Action
                                                </TableHead>
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
                                                                    assignmentDrafts[
                                                                        assignment.id
                                                                    ]?.assignment_status ??
                                                                    assignment.status
                                                                }
                                                            />
                                                            <select
                                                                value={
                                                                    assignmentDrafts[
                                                                        assignment.id
                                                                    ]?.assignment_status ??
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
                                                                    <option
                                                                        key={option.value}
                                                                        value={option.value}
                                                                    >
                                                                        {option.label}
                                                                    </option>
                                                                ))}
                                                            </select>
                                                        </div>
                                                    </TableCell>
                                                    <TableCell className="align-top">
                                                        <Textarea
                                                            value={
                                                                assignmentDrafts[
                                                                    assignment.id
                                                                ]?.assignment_feedback ?? ''
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
                                                                onClick={() =>
                                                                    saveAssignment(assignment.id)
                                                                }
                                                            >
                                                                Save
                                                            </Button>
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() =>
                                                                    sendAssignmentEmail(
                                                                        assignment.id,
                                                                    )
                                                                }
                                                            >
                                                                Send Email
                                                            </Button>
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="destructive"
                                                                disabled={!assignment.video}
                                                                onClick={() =>
                                                                    deleteAssignmentVideo(
                                                                        assignment.id,
                                                                    )
                                                                }
                                                            >
                                                                Delete Video
                                                            </Button>
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                )}
                            </TabsContent>

                            <TabsContent value="certificate" className="mt-6 space-y-6">
                                <div className="rounded-lg border border-gray-200 p-5">
                                    <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                                        <div className="space-y-2">
                                            <h3 className="text-base font-semibold text-gray-900">
                                                Generate Certificate
                                            </h3>
                                            <p className="text-sm text-gray-500">
                                                Create a new certificate record for the selected
                                                certificate type.
                                            </p>
                                        </div>

                                        <form
                                            onSubmit={(event) => {
                                                event.preventDefault();
                                                certificateForm.post(
                                                    route(
                                                        'admin.students.progress.certificates.store',
                                                        { student: student.id },
                                                    ),
                                                );
                                            }}
                                            className="flex w-full flex-col gap-3 lg:max-w-xl lg:flex-row"
                                        >
                                            <select
                                                value={certificateForm.data.certificate_type}
                                                onChange={(event) =>
                                                    certificateForm.setData(
                                                        'certificate_type',
                                                        event.target.value,
                                                    )
                                                }
                                                className="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            >
                                                {certificateTypes.map((type) => (
                                                    <option key={type.value} value={type.value}>
                                                        {type.label}
                                                    </option>
                                                ))}
                                            </select>
                                            <Button
                                                type="submit"
                                                disabled={
                                                    certificateForm.processing ||
                                                    !certificateEligibility.eligible
                                                }
                                            >
                                                Generate Certificate
                                            </Button>
                                        </form>
                                    </div>

                                    <div className="mt-4">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            disabled={certificates.length === 0}
                                            onClick={() =>
                                                router.post(
                                                    route(
                                                        'admin.students.progress.certificates.send-graduation-email',
                                                        { student: student.id },
                                                    ),
                                                )
                                            }
                                        >
                                            Send Graduation Email
                                        </Button>
                                    </div>
                                </div>

                                {certificates.length === 0 ? (
                                    <div className="rounded-lg border border-dashed border-gray-300 px-6 py-10 text-center text-sm text-gray-500">
                                        No certificate records are available for this student yet.
                                    </div>
                                ) : (
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Certificate Type</TableHead>
                                                <TableHead>Version</TableHead>
                                                <TableHead>Generated At</TableHead>
                                                <TableHead className="text-right">
                                                    Action
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {certificates.map((certificate) => (
                                                <TableRow key={certificate.id}>
                                                    <TableCell className="font-medium text-gray-900">
                                                        {certificate.type_label}
                                                    </TableCell>
                                                    <TableCell>v{certificate.version}</TableCell>
                                                    <TableCell>
                                                        {certificate.generated_at}
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="flex justify-end gap-2">
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="outline"
                                                                disabled={!certificateEligibility.eligible}
                                                                onClick={() =>
                                                                    recreateCertificate(
                                                                        certificate.id,
                                                                    )
                                                                }
                                                            >
                                                                Recreate Certificate
                                                            </Button>
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="outline"
                                                                asChild
                                                            >
                                                                <a
                                                                    href={certificate.download_url}
                                                                >
                                                                    Download Certificate
                                                                </a>
                                                            </Button>
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="destructive"
                                                                onClick={() =>
                                                                    deleteCertificate(
                                                                        certificate.id,
                                                                    )
                                                                }
                                                            >
                                                                Delete Certificate
                                                            </Button>
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                )}
                            </TabsContent>
                        </Tabs>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
