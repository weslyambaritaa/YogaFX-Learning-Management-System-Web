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

            <div className="flex items-center gap-4">
                <PrimaryButton disabled={processing}>{submitLabel}</PrimaryButton>
            </div>
        </form>
    );
}
