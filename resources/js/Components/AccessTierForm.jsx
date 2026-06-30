import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';

export default function AccessTierForm({
    data,
    setData,
    errors,
    processing,
    onSubmit,
    submitLabel = 'Save Access Tier',
}) {
    return (
        <form onSubmit={onSubmit} className="space-y-6">
            <div className="grid gap-6 md:grid-cols-2">
                <div>
                    <InputLabel htmlFor="name" value="Tier Name" />
                    <TextInput
                        id="name"
                        className="mt-1 block w-full"
                        value={data.name}
                        onChange={(event) => setData('name', event.target.value)}
                        isFocused
                    />
                    <InputError className="mt-2" message={errors.name} />
                </div>

                <div>
                    <InputLabel htmlFor="level" value="Tier Level" />
                    <TextInput
                        id="level"
                        type="number"
                        min="1"
                        step="1"
                        className="mt-1 block w-full"
                        value={data.level}
                        onChange={(event) => setData('level', event.target.value)}
                    />
                    <p className="mt-2 text-xs text-gray-500">
                        Higher level means higher upgrade hierarchy.
                    </p>
                    <InputError className="mt-2" message={errors.level} />
                </div>
            </div>

            <div>
                <InputLabel htmlFor="description" value="Description" />
                <textarea
                    id="description"
                    rows="4"
                    value={data.description}
                    onChange={(event) => setData('description', event.target.value)}
                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-black focus:ring-black"
                />
                <InputError className="mt-2" message={errors.description} />
            </div>

            <div>
                <InputLabel htmlFor="is_active" value="Status" />
                <select
                    id="is_active"
                    value={data.is_active ? '1' : '0'}
                    onChange={(event) =>
                        setData('is_active', event.target.value === '1')
                    }
                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-black focus:ring-black"
                >
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
                <InputError className="mt-2" message={errors.is_active} />
            </div>

            <div className="rounded-xl border border-slate-200 bg-slate-50 p-4 md:col-span-2">
                <div>
                    <h3 className="text-sm font-semibold text-slate-900">
                        Instant Access
                    </h3>
                    <p className="mt-1 text-xs text-slate-500">
                        Enable dialog shortcuts for students in this tier.
                    </p>
                </div>

                <div className="mt-4 grid gap-3 md:grid-cols-2">
                    <label className="flex items-start gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3">
                        <input
                            type="checkbox"
                            className="mt-1 rounded border-slate-300 text-black shadow-sm focus:ring-black"
                            checked={Boolean(data.has_full_standing_dialog_access)}
                            onChange={(event) =>
                                setData(
                                    'has_full_standing_dialog_access',
                                    event.target.checked,
                                )
                            }
                        />
                        <span>
                            <span className="block text-sm font-medium text-slate-900">
                                Full Standing Dialog
                            </span>
                            <span className="block text-xs text-slate-500">
                                Show and allow access to the standing instant access dialog.
                            </span>
                        </span>
                    </label>

                    <label className="flex items-start gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3">
                        <input
                            type="checkbox"
                            className="mt-1 rounded border-slate-300 text-black shadow-sm focus:ring-black"
                            checked={Boolean(data.has_full_floor_dialog_access)}
                            onChange={(event) =>
                                setData(
                                    'has_full_floor_dialog_access',
                                    event.target.checked,
                                )
                            }
                        />
                        <span>
                            <span className="block text-sm font-medium text-slate-900">
                                Full Floor Dialog
                            </span>
                            <span className="block text-xs text-slate-500">
                                Show and allow access to the floor instant access dialog.
                            </span>
                        </span>
                    </label>
                </div>

                <InputError
                    className="mt-2"
                    message={
                        errors.has_full_standing_dialog_access
                        ?? errors.has_full_floor_dialog_access
                    }
                />
            </div>

            <div className="flex items-center gap-4">
                <PrimaryButton disabled={processing}>{submitLabel}</PrimaryButton>
            </div>
        </form>
    );
}
