import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Textarea } from '@/Components/ui/textarea';

export default function AssignmentForm({
    data,
    setData,
    errors,
    processing,
    assignmentStatuses,
    onSubmit,
    submitLabel = 'Save Assignment',
}) {
    return (
        <form onSubmit={onSubmit} className="space-y-6">
            <div className="grid gap-6 md:grid-cols-2">
                <div>
                    <InputLabel htmlFor="title" value="Title" />
                    <TextInput
                        id="title"
                        className="mt-1 block w-full"
                        value={data.title}
                        onChange={(event) => setData('title', event.target.value)}
                        isFocused
                    />
                    <InputError className="mt-2" message={errors.title} />
                </div>

                <div>
                    <InputLabel htmlFor="sort_order" value="Sort Order" />
                    <TextInput
                        id="sort_order"
                        type="number"
                        min="1"
                        className="mt-1 block w-full"
                        value={data.sort_order}
                        onChange={(event) => setData('sort_order', event.target.value)}
                    />
                    <InputError className="mt-2" message={errors.sort_order} />
                </div>
            </div>

            <div>
                <InputLabel htmlFor="description" value="Instruction / Description" />
                <Textarea
                    id="description"
                    rows={8}
                    value={data.description ?? ''}
                    onChange={(event) => setData('description', event.target.value)}
                    className="mt-1 block w-full"
                />
                <p className="mt-2 text-xs text-gray-500">
                    Use this field for the submission brief, student instruction, and review expectation.
                </p>
                <InputError className="mt-2" message={errors.description} />
            </div>

            <div className="grid gap-6 md:grid-cols-2">
                <div>
                    <InputLabel htmlFor="status" value="Status" />
                    <select
                        id="status"
                        value={data.status}
                        onChange={(event) => setData('status', event.target.value)}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        {assignmentStatuses.map((status) => (
                            <option key={status.value} value={status.value}>
                                {status.label}
                            </option>
                        ))}
                    </select>
                    <InputError className="mt-2" message={errors.status} />
                </div>

                <div>
                    <InputLabel htmlFor="is_required" value="Requirement" />
                    <label className="mt-3 flex items-center gap-3 text-sm text-gray-700">
                        <input
                            id="is_required"
                            type="checkbox"
                            checked={Boolean(data.is_required)}
                            onChange={(event) => setData('is_required', event.target.checked)}
                            className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                        />
                        Student must submit this assignment
                    </label>
                    <InputError className="mt-2" message={errors.is_required} />
                </div>
            </div>

            <div className="flex items-center gap-4">
                <PrimaryButton disabled={processing}>{submitLabel}</PrimaryButton>
            </div>
        </form>
    );
}
