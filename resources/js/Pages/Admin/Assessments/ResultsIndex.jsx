import AdminSearchInput from '@/Components/admin/AdminSearchInput';
import DeleteConfirmationDialog from '@/Components/DeleteConfirmationDialog';
import { Button } from '@/Components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useDeferredValue, useState } from 'react';

const statusMessages = {
    'assessment-result-deleted': 'Assessment result has been deleted.',
};

export default function AssessmentResultsIndex({
    assessment,
    results,
    status,
}) {
    const [search, setSearch] = useState('');
    const deferredSearch = useDeferredValue(search);
    const normalizedSearch = deferredSearch.trim().toLowerCase();
    const filteredResults = results.filter((result) => [
        result.name,
        result.email,
        result.correct_answers,
        result.percentage,
        result.completed_at,
    ].join(' ').toLowerCase().includes(normalizedSearch));

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <div className="text-sm text-slate-500">
                            Assessment Results
                        </div>
                        <h2 className="text-2xl font-semibold text-slate-900">
                            {assessment.title}
                        </h2>
                        <p className="mt-1 text-sm text-slate-500">
                            Only completed attempts are shown here.
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-3">
                        <Button asChild variant="outline">
                            <Link
                                href={route('admin.assessments.preview', assessment.id)}
                                data={{ restart: 1 }}
                            >
                                Preview Assessment
                            </Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href={route('admin.scoreboards.index')}>
                                Back to Assessment List
                            </Link>
                        </Button>
                    </div>
                </div>
            }
        >
            <Head title={`${assessment.title} Results`} />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {statusMessages[status] && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            {statusMessages[status]}
                        </div>
                    )}
                    <AdminSearchInput
                        value={search}
                        onChange={setSearch}
                        placeholder="Search results by student name, email, score, or completion time..."
                        resultLabel={`${filteredResults.length} of ${results.length} results`}
                    />

                    <div className="rounded-3xl border border-slate-200 bg-white shadow-sm">
                        {filteredResults.length === 0 ? (
                            <div className="px-6 py-14 text-center">
                                <h3 className="text-lg font-semibold text-slate-900">
                                    {results.length === 0 ? 'No completed results yet' : 'No results match your search'}
                                </h3>
                                <p className="mt-2 text-sm text-slate-500">
                                    {results.length === 0
                                        ? 'Completed attempts will appear here once students finish this assessment.'
                                        : 'Try a different student name, email, or score keyword.'}
                                </p>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Email</TableHead>
                                        <TableHead>Correct Answers</TableHead>
                                        <TableHead>Percentage</TableHead>
                                        <TableHead>Completion Info</TableHead>
                                        <TableHead className="text-right">
                                            Action
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {filteredResults.map((result) => (
                                        <TableRow key={result.id}>
                                            <TableCell className="font-medium text-slate-900">
                                                <Link
                                                    href={route(
                                                        'admin.assessments.results.show',
                                                        {
                                                            assessment: assessment.id,
                                                            attempt: result.id,
                                                        },
                                                    )}
                                                    className="hover:text-slate-700"
                                                >
                                                    {result.name || 'Unknown User'}
                                                </Link>
                                            </TableCell>
                                            <TableCell>{result.email || '-'}</TableCell>
                                            <TableCell>
                                                {result.correct_answers}
                                            </TableCell>
                                            <TableCell>
                                                {result.percentage}%
                                            </TableCell>
                                            <TableCell>
                                                {result.completed_at || '-'}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        asChild
                                                        size="sm"
                                                        variant="outline"
                                                    >
                                                        <Link
                                                            href={route(
                                                                'admin.assessments.results.show',
                                                                {
                                                                    assessment:
                                                                        assessment.id,
                                                                    attempt:
                                                                        result.id,
                                                                },
                                                            )}
                                                        >
                                                            View
                                                        </Link>
                                                    </Button>
                                                    <DeleteConfirmationDialog
                                                        title="Delete this result?"
                                                        description="This deletes the selected assessment attempt and all of its stored answers."
                                                        trigger={
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="destructive"
                                                            >
                                                                Delete
                                                            </Button>
                                                        }
                                                        href={route(
                                                            'admin.assessments.results.destroy',
                                                            {
                                                                assessment:
                                                                    assessment.id,
                                                                attempt:
                                                                    result.id,
                                                            },
                                                        )}
                                                        confirmLabel="Delete Result"
                                                    />
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
