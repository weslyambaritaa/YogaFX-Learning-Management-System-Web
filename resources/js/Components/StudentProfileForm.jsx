import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';

function SelectField({ id, label, value, onChange, error, options }) {
    return (
        <div>
            <InputLabel htmlFor={id} value={label} />
            <select
                id={id}
                value={value ?? ''}
                onChange={(e) => onChange(e.target.value)}
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
                <option value="">Select an option</option>
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
            <InputError className="mt-2" message={error} />
        </div>
    );
}

function TextAreaField({ id, label, value, onChange, error, rows = 4 }) {
    return (
        <div>
            <InputLabel htmlFor={id} value={label} />
            <textarea
                id={id}
                rows={rows}
                value={value ?? ''}
                onChange={(e) => onChange(e.target.value)}
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            />
            <InputError className="mt-2" message={error} />
        </div>
    );
}

export default function StudentProfileForm({
    data,
    setData,
    errors,
    processing,
    onSubmit,
    submitLabel = 'Save Changes',
}) {
    const genderOptions = [
        { value: 'female', label: 'Female' },
        { value: 'male', label: 'Male' },
        { value: 'non_binary', label: 'Non-binary' },
        { value: 'prefer_not_to_say', label: 'Prefer not to say' },
    ];

    const experienceOptions = [
        { value: 'less_than_1_year', label: 'Less than 1 year' },
        { value: '1-3 years', label: '1-3 years' },
        { value: '3-5 years', label: '3-5 years' },
        { value: '5+ years', label: '5+ years' },
    ];

    const sequenceOptions = [
        { value: 'Beginner', label: 'Beginner' },
        { value: 'Intermediate', label: 'Intermediate' },
        { value: 'Advanced', label: 'Advanced' },
    ];

    const fitnessOptions = [
        { value: 'Beginner', label: 'Beginner' },
        { value: 'Intermediate', label: 'Intermediate' },
        { value: 'Advanced', label: 'Advanced' },
    ];

    const flexibilityOptions = [
        { value: 'Low', label: 'Low' },
        { value: 'Moderate', label: 'Moderate' },
        { value: 'High', label: 'High' },
    ];

    return (
        <form onSubmit={onSubmit} className="space-y-8">
            <section className="space-y-6">
                <div>
                    <h3 className="text-lg font-medium text-gray-900">
                        Personal Identity
                    </h3>
                    <p className="mt-1 text-sm text-gray-600">
                        Complete the student profile data needed for onboarding
                        and future learning operations.
                    </p>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="first_name" value="First Name" />
                        <TextInput
                            id="first_name"
                            className="mt-1 block w-full"
                            value={data.first_name}
                            onChange={(e) => setData('first_name', e.target.value)}
                            isFocused
                        />
                        <InputError className="mt-2" message={errors.first_name} />
                    </div>

                    <div>
                        <InputLabel htmlFor="last_name" value="Last Name" />
                        <TextInput
                            id="last_name"
                            className="mt-1 block w-full"
                            value={data.last_name}
                            onChange={(e) => setData('last_name', e.target.value)}
                        />
                        <InputError className="mt-2" message={errors.last_name} />
                    </div>

                    <div>
                        <InputLabel htmlFor="email" value="Email" />
                        <TextInput
                            id="email"
                            type="email"
                            className="mt-1 block w-full"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                        />
                        <InputError className="mt-2" message={errors.email} />
                    </div>

                    <div>
                        <InputLabel htmlFor="whatsapp" value="WhatsApp" />
                        <TextInput
                            id="whatsapp"
                            className="mt-1 block w-full"
                            value={data.whatsapp}
                            onChange={(e) => setData('whatsapp', e.target.value)}
                        />
                        <InputError className="mt-2" message={errors.whatsapp} />
                    </div>

                    <div>
                        <InputLabel htmlFor="instagram" value="Instagram" />
                        <TextInput
                            id="instagram"
                            className="mt-1 block w-full"
                            value={data.instagram ?? ''}
                            onChange={(e) => setData('instagram', e.target.value)}
                        />
                        <InputError className="mt-2" message={errors.instagram} />
                    </div>

                    <div>
                        <InputLabel htmlFor="country" value="Country" />
                        <TextInput
                            id="country"
                            className="mt-1 block w-full"
                            value={data.country}
                            onChange={(e) => setData('country', e.target.value)}
                        />
                        <InputError className="mt-2" message={errors.country} />
                    </div>

                    <div>
                        <InputLabel htmlFor="birth_date" value="Birth Date" />
                        <TextInput
                            id="birth_date"
                            type="date"
                            className="mt-1 block w-full"
                            value={data.birth_date ?? ''}
                            onChange={(e) => setData('birth_date', e.target.value)}
                        />
                        <InputError className="mt-2" message={errors.birth_date} />
                    </div>

                    <SelectField
                        id="gender"
                        label="Gender"
                        value={data.gender}
                        onChange={(value) => setData('gender', value)}
                        error={errors.gender}
                        options={genderOptions}
                    />

                    <div className="md:col-span-2">
                        <InputLabel
                            htmlFor="profile_photo"
                            value="Profile Photo URL"
                        />
                        <TextInput
                            id="profile_photo"
                            className="mt-1 block w-full"
                            value={data.profile_photo ?? ''}
                            onChange={(e) => setData('profile_photo', e.target.value)}
                        />
                        <InputError
                            className="mt-2"
                            message={errors.profile_photo}
                        />
                    </div>

                    <div className="md:col-span-2">
                        <InputLabel
                            htmlFor="preferred_certificate_picture"
                            value="Preferred Certificate Picture Reference"
                        />
                        <TextInput
                            id="preferred_certificate_picture"
                            className="mt-1 block w-full"
                            value={data.preferred_certificate_picture ?? ''}
                            onChange={(e) =>
                                setData('preferred_certificate_picture', e.target.value)
                            }
                        />
                        <InputError
                            className="mt-2"
                            message={errors.preferred_certificate_picture}
                        />
                    </div>
                </div>
            </section>

            <section className="space-y-6">
                <div>
                    <h3 className="text-lg font-medium text-gray-900">
                        Learning Background
                    </h3>
                    <p className="mt-1 text-sm text-gray-600">
                        This information helps prepare future learning and
                        certificate workflows.
                    </p>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    <SelectField
                        id="practicing_yoga_for"
                        label="Practicing Yoga For"
                        value={data.practicing_yoga_for}
                        onChange={(value) => setData('practicing_yoga_for', value)}
                        error={errors.practicing_yoga_for}
                        options={experienceOptions}
                    />

                    <SelectField
                        id="yoga_sequence_experience"
                        label="Yoga Sequence Experience"
                        value={data.yoga_sequence_experience}
                        onChange={(value) =>
                            setData('yoga_sequence_experience', value)
                        }
                        error={errors.yoga_sequence_experience}
                        options={sequenceOptions}
                    />

                    <div>
                        <InputLabel htmlFor="hours_per_week" value="Hours Per Week" />
                        <TextInput
                            id="hours_per_week"
                            type="number"
                            min="0"
                            max="168"
                            className="mt-1 block w-full"
                            value={data.hours_per_week ?? ''}
                            onChange={(e) => setData('hours_per_week', e.target.value)}
                        />
                        <InputError className="mt-2" message={errors.hours_per_week} />
                    </div>

                    <SelectField
                        id="current_fitness_level"
                        label="Current Fitness Level"
                        value={data.current_fitness_level}
                        onChange={(value) => setData('current_fitness_level', value)}
                        error={errors.current_fitness_level}
                        options={fitnessOptions}
                    />

                    <SelectField
                        id="flexibility_rating"
                        label="Flexibility Rating"
                        value={data.flexibility_rating}
                        onChange={(value) => setData('flexibility_rating', value)}
                        error={errors.flexibility_rating}
                        options={flexibilityOptions}
                    />
                </div>
            </section>

            <section className="space-y-6">
                <div>
                    <h3 className="text-lg font-medium text-gray-900">
                        Motivation
                    </h3>
                    <p className="mt-1 text-sm text-gray-600">
                        Capture the student context required for onboarding and
                        admin visibility.
                    </p>
                </div>

                <div className="grid gap-6">
                    <TextAreaField
                        id="motivation"
                        label="Motivation"
                        value={data.motivation}
                        onChange={(value) => setData('motivation', value)}
                        error={errors.motivation}
                    />

                    <TextAreaField
                        id="why_yogafx"
                        label="Why YogaFX"
                        value={data.why_yogafx}
                        onChange={(value) => setData('why_yogafx', value)}
                        error={errors.why_yogafx}
                    />

                    <div>
                        <InputLabel
                            htmlFor="how_did_you_find_us"
                            value="How Did You Find Us"
                        />
                        <TextInput
                            id="how_did_you_find_us"
                            className="mt-1 block w-full"
                            value={data.how_did_you_find_us}
                            onChange={(e) =>
                                setData('how_did_you_find_us', e.target.value)
                            }
                        />
                        <InputError
                            className="mt-2"
                            message={errors.how_did_you_find_us}
                        />
                    </div>
                </div>
            </section>

            <div className="flex items-center gap-4">
                <PrimaryButton disabled={processing}>{submitLabel}</PrimaryButton>
            </div>
        </form>
    );
}
