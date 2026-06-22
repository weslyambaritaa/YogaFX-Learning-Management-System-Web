import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Button } from '@/Components/ui/button';
import { usePage } from '@inertiajs/react';
import { CalendarDays, Check } from 'lucide-react';
import { useMemo, useState } from 'react';

const PRACTICING_OPTIONS = [
    { value: 'beginner', label: 'Beginner' },
    { value: '0_to_3_years', label: '0 to 3 years' },
    { value: '4_to_6_years', label: '4 to 6 years' },
    { value: '6_plus_years', label: '6+ years' },
];

const GENDER_OPTIONS = [
    { value: 'male', label: 'Male' },
    { value: 'female', label: 'Female' },
];

const SEQUENCE_OPTIONS = [
    { value: 'bikram', label: 'Bikram' },
    { value: 'hatha', label: 'Hatha' },
    { value: 'astanga', label: 'Astanga' },
    { value: 'vinyasa', label: 'Vinyasa' },
    { value: 'yin', label: 'Yin' },
    { value: 'iyengar', label: 'Iyengar' },
    { value: 'pilates', label: 'Pilates' },
    { value: 'other', label: 'Other' },
];

const HOURS_OPTIONS = [
    { value: '0_3', label: '0-3' },
    { value: '4_7', label: '4-7' },
    { value: '7_10', label: '7-10' },
    { value: '10_plus', label: '10+' },
];

const SIMPLE_LEVEL_OPTIONS = [
    { value: 'poor', label: 'Poor' },
    { value: 'average', label: 'Average' },
    { value: 'good', label: 'Good' },
];

const DISCOVERY_OPTIONS = [
    { value: 'google', label: 'Google' },
    { value: 'facebook', label: 'Facebook' },
    { value: 'instagram', label: 'Instagram' },
    { value: 'chatgpt', label: 'ChatGPT' },
    { value: 'gemini', label: 'Gemini' },
    { value: 'perplexity', label: 'Perplexity' },
    { value: 'youtube', label: 'YouTube' },
    { value: 'yoga_studio', label: 'Yoga Studio' },
    { value: 'word_of_mouth', label: 'Word Of Mouth' },
    { value: 'other', label: 'Other' },
];

function wordsCount(value) {
    return String(value || '')
        .trim()
        .split(/\s+/)
        .filter(Boolean).length;
}

function firstError(errors, field) {
    if (errors?.[field]) {
        return errors[field];
    }

    const nestedKey = Object.keys(errors || {}).find((key) => key.startsWith(`${field}.`));

    return nestedKey ? errors[nestedKey] : null;
}

function ChoiceGrid({
    id,
    label,
    description = null,
    value,
    error,
    options,
    onChange,
    multiple = false,
    immersive = false,
}) {
    const selectedValues = Array.isArray(value) ? value : [];

    return (
        <div className="space-y-3">
            <div>
                <InputLabel
                    htmlFor={id}
                    value={label}
                    className={immersive ? 'text-xs uppercase tracking-[0.18em] text-white/70' : 'text-sm font-medium text-gray-800'}
                />
                {description ? (
                    <p className={immersive ? 'mt-1 text-sm text-white/45' : 'mt-1 text-sm text-gray-500'}>
                        {description}
                    </p>
                ) : null}
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
                {options.map((option) => {
                    const checked = multiple
                        ? selectedValues.includes(option.value)
                        : value === option.value;

                    return (
                        <label
                            key={option.value}
                            className={[
                                'flex cursor-pointer items-center gap-3 rounded-[14px] border px-4 py-3 transition',
                                immersive
                                    ? checked
                                        ? 'border-[#DB202C] bg-[#DB202C]/18 text-white'
                                        : 'border-[#DB202C] bg-white/[0.04] text-white/86 hover:bg-white/[0.07]'
                                    : checked
                                        ? 'border-[#DB202C] bg-rose-50 text-slate-900'
                                        : 'border-[#DB202C] bg-white text-slate-800 hover:bg-rose-50/50',
                            ].join(' ')}
                        >
                            <input
                                id={id}
                                type={multiple ? 'checkbox' : 'radio'}
                                name={id}
                                value={option.value}
                                checked={checked}
                                onChange={() => {
                                    if (multiple) {
                                        const nextValues = checked
                                            ? selectedValues.filter((item) => item !== option.value)
                                            : [...selectedValues, option.value];
                                        onChange(nextValues);
                                        return;
                                    }

                                    onChange(option.value);
                                }}
                                className="sr-only"
                            />
                            <span
                                className={[
                                    'flex size-5 shrink-0 items-center justify-center rounded-full border',
                                    checked
                                        ? 'border-[#DB202C] bg-[#DB202C] text-white'
                                        : immersive
                                            ? 'border-white/25 bg-transparent text-transparent'
                                            : 'border-[#DB202C] bg-transparent text-transparent',
                                ].join(' ')}
                            >
                                <Check className="size-3.5" />
                            </span>
                            <span className="text-sm font-medium">{option.label}</span>
                        </label>
                    );
                })}
            </div>

            <InputError message={error} className={immersive ? 'text-[#ffb4a8]' : ''} />
        </div>
    );
}

function SelectField({
    id,
    label,
    value,
    onChange,
    error,
    options,
    immersive = false,
}) {
    return (
        <div>
            <InputLabel
                htmlFor={id}
                value={label}
                className={immersive ? 'text-xs uppercase tracking-[0.18em] text-white/70' : 'text-sm font-medium text-gray-800'}
            />
            <select
                id={id}
                value={value ?? ''}
                onChange={(event) => onChange(event.target.value)}
                className={[
                    'mt-2 block w-full rounded-[14px] border px-4 py-3 shadow-sm focus:ring-0',
                    immersive
                        ? 'border-[#DB202C] bg-[#171311] text-white [&::-webkit-calendar-picker-indicator]:invert'
                        : 'border-[#DB202C] bg-white text-slate-900',
                ].join(' ')}
            >
                <option value="">{immersive ? 'Select an option' : 'Select an option'}</option>
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.flag ? `${option.flag} ` : ''}
                        {option.label}
                    </option>
                ))}
            </select>
            <InputError message={error} className={immersive ? 'text-[#ffb4a8]' : ''} />
        </div>
    );
}

function TextAreaField({
    id,
    label,
    value,
    onChange,
    error,
    helper = null,
    immersive = false,
}) {
    return (
        <div>
            <InputLabel
                htmlFor={id}
                value={label}
                className={immersive ? 'text-xs uppercase tracking-[0.18em] text-white/70' : 'text-sm font-medium text-gray-800'}
            />
            <textarea
                id={id}
                rows={5}
                value={value ?? ''}
                onChange={(event) => onChange(event.target.value)}
                className={[
                    'mt-2 block w-full rounded-[14px] border px-4 py-3 shadow-sm focus:ring-0',
                    immersive
                        ? 'border-[#DB202C] bg-white/5 text-white placeholder:text-white/30'
                        : 'border-[#DB202C] bg-white text-slate-900',
                ].join(' ')}
            />
            {helper ? (
                <p className={immersive ? 'mt-2 text-xs text-white/45' : 'mt-2 text-xs text-gray-500'}>
                    {helper}
                </p>
            ) : null}
            <InputError message={error} className={immersive ? 'text-[#ffb4a8]' : ''} />
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
    variant = 'default',
    mode = 'profile',
    currentProfilePhotoUrl = null,
}) {
    const { directory = {} } = usePage().props;
    const countryOptions = directory.countries ?? [];
    const phoneCountryCodeOptions = directory.phone_country_codes ?? [];
    const isImmersive = variant === 'immersive' || variant === 'scoreboard';
    const isScoreboard = variant === 'scoreboard';
    const isEnrollment = mode === 'enrollment';
    const [localErrors, setLocalErrors] = useState({});
    const todayLabel = useMemo(
        () =>
            new Intl.DateTimeFormat('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
            }).format(new Date()),
        [],
    );
    const todayIso = useMemo(() => new Date().toISOString().slice(0, 10), []);

    const sectionClassName = isImmersive
        ? 'rounded-[18px] border border-white/10 bg-[linear-gradient(160deg,rgba(255,255,255,0.05),rgba(255,255,255,0.02))] p-5 sm:p-6'
        : 'space-y-6';
    const titleClassName = isImmersive
        ? 'text-2xl font-semibold tracking-tight text-white'
        : 'text-lg font-medium text-gray-900';
    const descriptionClassName = isImmersive
        ? 'mt-1 text-sm leading-6 text-white/58'
        : 'mt-1 text-sm text-gray-600';
    const inputClassName = isImmersive
        ? '!border !border-[#DB202C] bg-white/5 text-white placeholder:text-white/30 focus:!border-[#DB202C] focus:ring-[#DB202C]'
        : '!border !border-[#DB202C] bg-white text-slate-900 focus:!border-[#DB202C] focus:ring-[#DB202C]';

    const handleSubmit = (event) => {
        const nextLocalErrors = {};

        if (isEnrollment && !data.terms_accepted) {
            nextLocalErrors.terms_accepted = 'Please agree to the terms first.';
        }

        if (isEnrollment && !data.recaptcha_confirmed) {
            nextLocalErrors.recaptcha_confirmed = 'Please confirm the reCAPTCHA checkbox.';
        }

        setLocalErrors(nextLocalErrors);

        if (Object.keys(nextLocalErrors).length > 0) {
            event.preventDefault();
            return;
        }

        onSubmit(event);
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-8">
            <section className={sectionClassName}>
                <div>
                    <h3 className={titleClassName}>Personal Information</h3>
                    <p className={descriptionClassName}>Basic account details and your preferred certificate picture.</p>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="first_name" value="First Name" className={isImmersive ? 'text-xs uppercase tracking-[0.18em] text-white/70' : ''} />
                        <TextInput id="first_name" className={`mt-2 block w-full rounded-[14px] ${inputClassName}`} value={data.first_name} onChange={(event) => setData('first_name', event.target.value)} isFocused />
                        <InputError message={firstError(errors, 'first_name')} className={isImmersive ? 'text-[#ffb4a8]' : ''} />
                    </div>

                    <div>
                        <InputLabel htmlFor="last_name" value="Last Name" className={isImmersive ? 'text-xs uppercase tracking-[0.18em] text-white/70' : ''} />
                        <TextInput id="last_name" className={`mt-2 block w-full rounded-[14px] ${inputClassName}`} value={data.last_name} onChange={(event) => setData('last_name', event.target.value)} />
                        <InputError message={firstError(errors, 'last_name')} className={isImmersive ? 'text-[#ffb4a8]' : ''} />
                    </div>

                    <div>
                        <InputLabel htmlFor="email" value="Email" className={isImmersive ? 'text-xs uppercase tracking-[0.18em] text-white/70' : ''} />
                        <TextInput id="email" type="email" className={`mt-2 block w-full rounded-[14px] ${inputClassName}`} value={data.email} onChange={(event) => setData('email', event.target.value)} />
                        <InputError message={firstError(errors, 'email')} className={isImmersive ? 'text-[#ffb4a8]' : ''} />
                    </div>

                    <div>
                        <InputLabel htmlFor="whatsapp_number" value="WhatsApp" className={isImmersive ? 'text-xs uppercase tracking-[0.18em] text-white/70' : ''} />
                        <div className="mt-2 grid gap-3 sm:grid-cols-[210px_minmax(0,1fr)]">
                            <select
                                id="whatsapp_country_code"
                                value={data.whatsapp_country_code ?? '+62'}
                                onChange={(event) => setData('whatsapp_country_code', event.target.value)}
                                className={[
                                    'block w-full rounded-[14px] border px-4 py-3 shadow-sm focus:ring-0',
                                    isImmersive ? 'border-[#DB202C] bg-[#171311] text-white' : 'border-[#DB202C] bg-white text-slate-900',
                                ].join(' ')}
                            >
                                {phoneCountryCodeOptions.map((option) => (
                                    <option key={`${option.value}-${option.label}`} value={option.value}>
                                        {option.flag ? `${option.flag} ` : ''}
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                            <TextInput
                                id="whatsapp_number"
                                className={`block w-full rounded-[14px] ${inputClassName}`}
                                value={data.whatsapp_number ?? ''}
                                onChange={(event) => setData('whatsapp_number', event.target.value)}
                                placeholder="81233456788"
                            />
                        </div>
                        <InputError message={firstError(errors, 'whatsapp_number') ?? firstError(errors, 'whatsapp_country_code') ?? firstError(errors, 'whatsapp')} className={isImmersive ? 'text-[#ffb4a8]' : ''} />
                    </div>

                    <div className="md:col-span-2">
                        <InputLabel htmlFor="profile_photo" value="Please Upload Your Preferred Certificate Picture" className={isImmersive ? 'text-xs uppercase tracking-[0.18em] text-white/70' : ''} />
                        <div className="mt-3 flex flex-col gap-4 md:flex-row md:items-center">
                            <div className="flex h-28 w-24 items-center justify-center overflow-hidden rounded-[44%] border border-[#DB202C] bg-white/5 text-sm text-white/45">
                                {currentProfilePhotoUrl ? (
                                    <img src={currentProfilePhotoUrl} alt="Preferred certificate" className="h-full w-full object-cover" />
                                ) : (
                                    <span className="px-3 text-center">No photo</span>
                                )}
                            </div>
                            <div className="flex-1">
                                <input
                                    id="profile_photo"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                    className={[
                                        'block w-full rounded-[14px] border px-4 py-3 text-sm file:mr-4 file:rounded-[10px] file:border-0 file:bg-[#DB202C] file:px-4 file:py-2 file:font-semibold file:text-white hover:file:bg-[#c31c28]',
                                        isImmersive ? 'border-[#DB202C] bg-white/5 text-white' : 'border-[#DB202C] bg-white text-slate-900',
                                    ].join(' ')}
                                    onChange={(event) => setData('profile_photo', event.target.files?.[0] ?? null)}
                                />
                                <p className={isImmersive ? 'mt-2 text-xs leading-5 text-white/45' : 'mt-2 text-xs leading-5 text-gray-500'}>
                                    Upload one profile photo. You can replace or remove it before submit.
                                </p>
                                <InputError message={firstError(errors, 'profile_photo')} className={isImmersive ? 'text-[#ffb4a8]' : ''} />
                            </div>
                        </div>
                    </div>

                    <div>
                        <InputLabel htmlFor="instagram" value="Instagram (Optional)" className={isImmersive ? 'text-xs uppercase tracking-[0.18em] text-white/70' : ''} />
                        <TextInput id="instagram" className={`mt-2 block w-full rounded-[14px] ${inputClassName}`} value={data.instagram ?? ''} onChange={(event) => setData('instagram', event.target.value)} />
                        <InputError message={firstError(errors, 'instagram')} className={isImmersive ? 'text-[#ffb4a8]' : ''} />
                    </div>

                    <SelectField
                        id="country"
                        label="Country"
                        value={data.country}
                        onChange={(value) => {
                            setData('country', value);
                            const matchedCountry = countryOptions.find((option) => option.value === value);
                            const matchedDialCode = phoneCountryCodeOptions.find((option) => option.label.startsWith(`${matchedCountry?.label ?? ''} (`));
                            if (matchedDialCode && !data.whatsapp_number) {
                                setData('whatsapp_country_code', matchedDialCode.value);
                            }
                        }}
                        error={errors.country}
                        options={countryOptions}
                        immersive={isImmersive}
                    />

                    <div>
                        <InputLabel htmlFor="birth_date" value="Birth Date" className={isImmersive ? 'text-xs uppercase tracking-[0.18em] text-white/70' : ''} />
                        <div className="relative mt-2">
                            <input
                                id="birth_date"
                                name="birth_date"
                                type="date"
                                max={todayIso}
                                value={data.birth_date ? String(data.birth_date).slice(0, 10) : ''}
                                onChange={(event) => setData('birth_date', event.target.value)}
                                style={isImmersive ? { colorScheme: 'dark' } : undefined}
                                className={[
                                    'block w-full appearance-none rounded-[14px] border px-4 py-3 pr-12 shadow-sm focus:ring-0',
                                    isImmersive
                                        ? '!border-[#DB202C] bg-white/5 text-white focus:!border-[#DB202C] focus:ring-[#DB202C] [&::-webkit-calendar-picker-indicator]:cursor-pointer [&::-webkit-calendar-picker-indicator]:opacity-0'
                                        : '!border-[#DB202C] bg-white text-slate-900 focus:!border-[#DB202C] focus:ring-[#DB202C] [&::-webkit-calendar-picker-indicator]:opacity-0',
                                ].join(' ')}
                            />
                            <CalendarDays className={`pointer-events-none absolute right-4 top-1/2 z-10 size-5 -translate-y-1/2 ${isImmersive ? 'text-white' : 'text-slate-700'}`} />
                        </div>
                        <InputError message={firstError(errors, 'birth_date')} className={isImmersive ? 'text-[#ffb4a8]' : ''} />
                    </div>

                    <div className="md:col-span-2">
                        <ChoiceGrid
                            id="gender"
                            label="Gender"
                            value={data.gender}
                            error={firstError(errors, 'gender')}
                            options={GENDER_OPTIONS}
                            onChange={(value) => setData('gender', value)}
                            immersive={isImmersive}
                        />
                    </div>
                </div>
            </section>

            <section className={sectionClassName}>
                <div>
                    <h3 className={titleClassName}>Learning Background</h3>
                    <p className={descriptionClassName}>Your practice background.</p>
                </div>

                <div className="space-y-6">
                    <ChoiceGrid
                        id="practicing_yoga_for"
                        label="Current Yoga Experience"
                        description="Practicing Yoga For (Years & Months)"
                        value={data.practicing_yoga_for}
                        error={firstError(errors, 'practicing_yoga_for')}
                        options={PRACTICING_OPTIONS}
                        onChange={(value) => setData('practicing_yoga_for', value)}
                        immersive={isImmersive}
                    />

                    <ChoiceGrid
                        id="yoga_sequence_experience"
                        label="Yoga Sequence Experience"
                        value={data.yoga_sequence_experience ?? []}
                        error={firstError(errors, 'yoga_sequence_experience')}
                        options={SEQUENCE_OPTIONS}
                        onChange={(value) => setData('yoga_sequence_experience', value)}
                        multiple={true}
                        immersive={isImmersive}
                    />

                    <ChoiceGrid
                        id="hours_per_week"
                        label="How Many Hours P/Week Practicing Yoga?"
                        value={data.hours_per_week}
                        error={firstError(errors, 'hours_per_week')}
                        options={HOURS_OPTIONS}
                        onChange={(value) => setData('hours_per_week', value)}
                        immersive={isImmersive}
                    />

                    <ChoiceGrid
                        id="current_fitness_level"
                        label="Your Current Fitness Level"
                        value={data.current_fitness_level}
                        error={firstError(errors, 'current_fitness_level')}
                        options={SIMPLE_LEVEL_OPTIONS}
                        onChange={(value) => setData('current_fitness_level', value)}
                        immersive={isImmersive}
                    />

                    <ChoiceGrid
                        id="flexibility_rating"
                        label="How would you rate your flexibility"
                        value={data.flexibility_rating}
                        error={firstError(errors, 'flexibility_rating')}
                        options={SIMPLE_LEVEL_OPTIONS}
                        onChange={(value) => setData('flexibility_rating', value)}
                        immersive={isImmersive}
                    />
                </div>
            </section>

            <section className={sectionClassName}>
                <div>
                    <h3 className={titleClassName}>Motivation</h3>
                    <p className={descriptionClassName}>Keep each answer within 50 words.</p>
                </div>

                <div className="space-y-6">
                    <TextAreaField
                        id="motivation"
                        label="What is Your Motivation In Becoming A Yoga Teacher?"
                        value={data.motivation}
                        onChange={(value) => setData('motivation', value)}
                        error={firstError(errors, 'motivation')}
                        helper={`${wordsCount(data.motivation)}/50 words`}
                        immersive={isImmersive}
                    />

                    <TextAreaField
                        id="why_yogafx"
                        label="Please Let Us Know Why You Chose YogaFX"
                        value={data.why_yogafx}
                        onChange={(value) => setData('why_yogafx', value)}
                        error={firstError(errors, 'why_yogafx')}
                        helper={`${wordsCount(data.why_yogafx)}/50 words`}
                        immersive={isImmersive}
                    />

                    <ChoiceGrid
                        id="how_did_you_find_us"
                        label="Please Share How Did You Find Us"
                        value={data.how_did_you_find_us ?? []}
                        error={firstError(errors, 'how_did_you_find_us')}
                        options={DISCOVERY_OPTIONS}
                        onChange={(value) => setData('how_did_you_find_us', value)}
                        multiple={true}
                        immersive={isImmersive}
                    />
                </div>
            </section>

            {isEnrollment ? (
                <section className={sectionClassName}>
                    <div>
                        <h3 className={titleClassName}>Terms & Confirmation</h3>
                        <p className={descriptionClassName}>Confirm your final enrollment details.</p>
                    </div>

                    <div className="space-y-6">
                        <label className="flex items-start gap-3 rounded-[14px] border border-[#DB202C] bg-white/[0.04] px-4 py-4 text-white">
                            <input
                                type="checkbox"
                                checked={Boolean(data.terms_accepted)}
                                onChange={(event) => setData('terms_accepted', event.target.checked)}
                                className="mt-1 size-4 rounded border-[#DB202C] text-[#DB202C] focus:ring-[#DB202C]"
                            />
                            <span className="text-sm">Yes, I agree with Term & Conditions</span>
                        </label>
                        <InputError message={localErrors.terms_accepted} className="text-[#ffb4a8]" />

                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="rounded-[14px] border border-[#DB202C] bg-white/[0.04] px-4 py-4 text-white">
                                <div className="text-xs uppercase tracking-[0.18em] text-white/55">Full Name</div>
                                <div className="mt-2 text-base font-semibold">
                                    {[data.first_name, data.last_name].filter(Boolean).join(' ') || 'Your name'}
                                </div>
                            </div>
                            <div className="rounded-[14px] border border-[#DB202C] bg-white/[0.04] px-4 py-4 text-white">
                                <div className="text-xs uppercase tracking-[0.18em] text-white/55">Date</div>
                                <div className="mt-2 text-base font-semibold">{todayLabel}</div>
                            </div>
                        </div>

                        <label className="flex items-start gap-3 rounded-[14px] border border-[#DB202C] bg-white/[0.04] px-4 py-4 text-white">
                            <input
                                type="checkbox"
                                checked={Boolean(data.recaptcha_confirmed)}
                                onChange={(event) => setData('recaptcha_confirmed', event.target.checked)}
                                className="mt-1 size-4 rounded border-[#DB202C] text-[#DB202C] focus:ring-[#DB202C]"
                            />
                            <span className="text-sm">I'm not a robot (reCAPTCHA)</span>
                        </label>
                        <InputError message={localErrors.recaptcha_confirmed} className="text-[#ffb4a8]" />
                    </div>
                </section>
            ) : null}

            <div className="flex items-center gap-4">
                {isScoreboard ? (
                    <Button type="submit" disabled={processing} className="rounded-[12px] bg-[#DB202C] px-6 text-white hover:bg-[#c01a25]">
                        {submitLabel}
                    </Button>
                ) : (
                    <PrimaryButton disabled={processing} className="rounded-[12px] bg-[#DB202C] px-6 py-3 normal-case tracking-normal text-white hover:bg-[#c01a25]">
                        {submitLabel}
                    </PrimaryButton>
                )}
            </div>
        </form>
    );
}
