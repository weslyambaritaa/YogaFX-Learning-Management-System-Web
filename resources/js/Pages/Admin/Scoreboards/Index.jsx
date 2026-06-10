import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';

const statusMessages = {
    'scoreboard-created': 'Scoreboard has been created and is ready for building.',
    'scoreboard-updated': 'Scoreboard meta has been updated.',
    'scoreboard-deleted': 'Scoreboard has been deleted.',
};

export default function ScoreboardsIndex({ scoreboards, status }) {
    const errors = usePage().props.errors;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h2 className="text-2xl font-semibold text-slate-900">
                            Scoreboards
                        </h2>
                        <p className="mt-1 text-sm text-slate-500">
                            Create, activate, and manage the scoreboard engine that powers student assessments.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={route('admin.scoreboards.create')}>
                            Create Scoreboard
                        </Link>
                    </Button>
                </div>
            }
        >
            <Head title="Scoreboards" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {statusMessages[status] && (
                        <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            {statusMessages[status]}
                        </div>
                    )}

                    {errors.scoreboard && (
                        <div className="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                            {errors.scoreboard}
                        </div>
                    )}

                    <div className="space-y-4">
                        {scoreboards.map((scoreboard) => (
                            <div
                                key={scoreboard.id}
                                className="rounded-[28px] border border-slate-200 bg-white shadow-sm"
                            >
                                <div className="flex flex-col gap-5 p-5 lg:flex-row lg:items-center">
                                    <div className="flex min-w-0 flex-1 items-start gap-4">
                                        <div className="hidden h-16 w-16 shrink-0 overflow-hidden rounded-2xl border border-slate-200 bg-[#f6f3ec] lg:block">
                                            {scoreboard.thumbnail_url ? (
                                                <img
                                                    src={scoreboard.thumbnail_url}
                                                    alt={scoreboard.title}
                                                    className="h-full w-full object-cover"
                                                />
                                            ) : (
                                                <div className="flex h-full items-center justify-center bg-gradient-to-br from-[#e4ede4] via-white to-[#f3ede4] text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">
                                                    SB
                                                </div>
                                            )}
                                        </div>

                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Badge variant="outline">{scoreboard.status}</Badge>
                                                <Badge
                                                    variant={
                                                        scoreboard.is_active
                                                            ? 'secondary'
                                                            : 'outline'
                                                    }
                                                >
                                                    {scoreboard.is_active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </Badge>
                                                <Badge variant="outline">
                                                    {scoreboard.questions_count} Questions
                                                </Badge>
                                            </div>

                                            <h3 className="mt-3 truncate text-lg font-semibold text-slate-900">
                                                {scoreboard.title}
                                            </h3>
                                            <div className="mt-1 text-sm text-slate-500">
                                                Updated {scoreboard.updated_at ?? 'recently'}
                                            </div>
                                        </div>
                                    </div>

                                    <div className="flex flex-wrap gap-3 lg:justify-end">
                                        <Button asChild>
                                            <Link
                                                href={route(
                                                    'admin.scoreboards.builder',
                                                    scoreboard.id,
                                                )}
                                            >
                                                Open Builder
                                            </Link>
                                        </Button>
                                        <Button asChild variant="outline">
                                            <Link
                                                href={route(
                                                    'admin.scoreboards.edit',
                                                    scoreboard.id,
                                                )}
                                            >
                                                Edit Meta
                                            </Link>
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            asChild
                                        >
                                            <Link
                                                href={route(
                                                    'admin.scoreboards.destroy',
                                                    scoreboard.id,
                                                )}
                                                method="delete"
                                                as="button"
                                            >
                                                Delete
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>

                    {scoreboards.length === 0 && (
                        <div className="rounded-[28px] border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
                            <h3 className="text-lg font-semibold text-slate-900">
                                No scoreboards yet
                            </h3>
                            <p className="mt-2 text-sm text-slate-500">
                                Start with a meta record, then move straight into the builder.
                            </p>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
