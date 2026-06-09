import { Button } from '@/Components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import StudentProgressStudentLayout from '@/Components/admin/student-progress/StudentProgressStudentLayout';
import { router } from '@inertiajs/react';

export default function CompletedLessons({ student, completedLessons, status }) {
    const resetLessonProgress = (lessonProgressId) => {
        if (!window.confirm('Reset this completed lesson progress?')) {
            return;
        }

        router.post(
            route('admin.student-progress.completed-lessons.reset', {
                student: student.id,
                lessonProgress: lessonProgressId,
            }),
        );
    };

    return (
        <StudentProgressStudentLayout
            title="Completed Lesson"
            description="Review completed lessons for this student and reset progress when needed."
            pageTitle="Completed Lesson"
            student={student}
            activeSection="completed-lessons"
        >
            {status === 'student-progress-lesson-reset' && (
                <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                    Lesson progress has been reset.
                </div>
            )}

            <div className="rounded-lg bg-white p-6 shadow-sm">
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
                                <TableHead className="text-right">Action</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {completedLessons.map((progress) => (
                                <TableRow key={progress.id}>
                                    <TableCell className="font-medium text-gray-900">
                                        {progress.lesson_title}
                                    </TableCell>
                                    <TableCell>{progress.module_title}</TableCell>
                                    <TableCell>{progress.completed_at || '-'}</TableCell>
                                    <TableCell>{progress.watch_progress}%</TableCell>
                                    <TableCell className="text-right">
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            size="sm"
                                            onClick={() => resetLessonProgress(progress.id)}
                                        >
                                            Reset Progress
                                        </Button>
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
