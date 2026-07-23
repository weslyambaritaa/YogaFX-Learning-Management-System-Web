import CkeditorField from '@/Components/CkeditorField';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useState } from 'react';

function DialogEditorCard({
    title,
    titleLabel,
    contentLabel,
    titleValue,
    contentValue,
    onTitleChange,
    onContentChange,
    titleError,
    contentError,
    open,
    onToggle,
}) {
    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <button
                type="button"
                onClick={onToggle}
                className="flex w-full items-center justify-between gap-4 text-left"
            >
                <div>
                    <h3 className="text-base font-semibold text-slate-900">{title}</h3>
                    <p className="mt-1 text-sm text-slate-500">{contentLabel}</p>
                </div>
                <ChevronDown
                    className={`size-5 text-slate-500 transition-transform ${open ? 'rotate-180' : ''}`}
                />
            </button>

            {open ? (
                <div className="mt-5 space-y-5 border-t border-slate-100 pt-5">
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
            ) : null}
        </section>
    );
}

export default function Edit({ dialogs, status }) {
    const [openSections, setOpenSections] = useState({
        fullStanding: true,
        fullFloor: true,
    });

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
        <AuthenticatedLayout>
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
                            title="Full Standing"
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
                            open={openSections.fullStanding}
                            onToggle={() =>
                                setOpenSections((current) => ({
                                    ...current,
                                    fullStanding: !current.fullStanding,
                                }))
                            }
                        />

                        <DialogEditorCard
                            title="Full Floor"
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
                            open={openSections.fullFloor}
                            onToggle={() =>
                                setOpenSections((current) => ({
                                    ...current,
                                    fullFloor: !current.fullFloor,
                                }))
                            }
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
