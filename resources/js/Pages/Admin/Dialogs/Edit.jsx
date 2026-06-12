import CkeditorField from '@/Components/CkeditorField';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

function DialogEditorCard({
    titleLabel,
    contentLabel,
    titleValue,
    contentValue,
    onTitleChange,
    onContentChange,
    titleError,
    contentError,
}) {
    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div className="space-y-5">
                <div className="space-y-2">
                    <label className="text-sm font-medium text-slate-700">
                        {titleLabel}
                    </label>
                    <Input value={titleValue} onChange={onTitleChange} />
                    {titleError ? (
                        <div className="text-sm text-rose-600">{titleError}</div>
                    ) : null}
                </div>

                <div className="space-y-2">
                    <label className="text-sm font-medium text-slate-700">
                        {contentLabel}
                    </label>
                    <CkeditorField value={contentValue} onChange={onContentChange} />
                    {contentError ? (
                        <div className="text-sm text-rose-600">{contentError}</div>
                    ) : null}
                </div>
            </div>
        </section>
    );
}

export default function Edit({ dialogs, status }) {
    const form = useForm({
        full_standing_title: dialogs.full_standing.title,
        full_standing_content: dialogs.full_standing.content,
        full_floor_title: dialogs.full_floor.title,
        full_floor_content: dialogs.full_floor.content,
    });

    const submit = (event) => {
        event.preventDefault();
        form.patch(route('admin.dialogs.update'));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="min-w-0">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Dialog
                    </h2>
                    <p className="mt-1 text-sm text-gray-500">
                        Manage the student-facing Full Standing and Full Floor dialog
                        content from one place.
                    </p>
                </div>
            }
        >
            <Head title="Dialog" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {status === 'dialog-content-saved' ? (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Dialog content has been saved.
                        </div>
                    ) : null}

                    <form onSubmit={submit} className="space-y-6">
                        <DialogEditorCard
                            titleLabel="Full Standing Title"
                            contentLabel="Full Standing Dialog"
                            titleValue={form.data.full_standing_title}
                            contentValue={form.data.full_standing_content}
                            onTitleChange={(event) =>
                                form.setData(
                                    'full_standing_title',
                                    event.target.value,
                                )
                            }
                            onContentChange={(value) =>
                                form.setData('full_standing_content', value)
                            }
                            titleError={form.errors.full_standing_title}
                            contentError={form.errors.full_standing_content}
                        />

                        <DialogEditorCard
                            titleLabel="Full Floor Title"
                            contentLabel="Full Floor Dialog"
                            titleValue={form.data.full_floor_title}
                            contentValue={form.data.full_floor_content}
                            onTitleChange={(event) =>
                                form.setData('full_floor_title', event.target.value)
                            }
                            onContentChange={(value) =>
                                form.setData('full_floor_content', value)
                            }
                            titleError={form.errors.full_floor_title}
                            contentError={form.errors.full_floor_content}
                        />

                        <div className="flex justify-end">
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? 'Saving...' : 'Save'}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
