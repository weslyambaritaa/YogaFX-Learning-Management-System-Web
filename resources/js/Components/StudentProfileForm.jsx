import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import TextInput from "@/Components/TextInput";
import { Button } from "@/Components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/Components/ui/dialog";
import { usePage } from "@inertiajs/react";
import { CalendarDays, Check, UploadCloud } from "lucide-react";
import { useMemo, useState, useRef, useCallback } from "react";
import Cropper from "react-easy-crop";

// --- Utility Function untuk Memotong Gambar (Canvas) ---
const createImage = (url) =>
    new Promise((resolve, reject) => {
        const image = new Image();
        image.addEventListener("load", () => resolve(image));
        image.addEventListener("error", (error) => reject(error));
        image.setAttribute("crossOrigin", "anonymous");
        image.src = url;
    });

async function getCroppedImg(imageSrc, pixelCrop) {
    const image = await createImage(imageSrc);
    const canvas = document.createElement("canvas");
    const ctx = canvas.getContext("2d");

    canvas.width = pixelCrop.width;
    canvas.height = pixelCrop.height;

    ctx.drawImage(
        image,
        pixelCrop.x,
        pixelCrop.y,
        pixelCrop.width,
        pixelCrop.height,
        0,
        0,
        pixelCrop.width,
        pixelCrop.height,
    );

    return new Promise((resolve, reject) => {
        canvas.toBlob(
            (blob) => {
                if (blob) {
                    const file = new File([blob], "profile_cropped.jpg", {
                        type: "image/jpeg",
                    });
                    resolve({ file, url: URL.createObjectURL(blob) });
                } else {
                    reject(new Error("Canvas is empty"));
                }
            },
            "image/jpeg",
            0.95,
        );
    });
}
// --------------------------------------------------------

const PRACTICING_OPTIONS = [
    { value: "beginner", label: "Beginner" },
    { value: "0_to_3_years", label: "0 to 3 years" },
    { value: "4_to_6_years", label: "4 to 6 years" },
    { value: "6_plus_years", label: "6+ years" },
];

const GENDER_OPTIONS = [
    { value: "male", label: "Male" },
    { value: "female", label: "Female" },
];

const SEQUENCE_OPTIONS = [
    { value: "bikram", label: "Bikram" },
    { value: "hatha", label: "Hatha" },
    { value: "astanga", label: "Astanga" },
    { value: "vinyasa", label: "Vinyasa" },
    { value: "yin", label: "Yin" },
    { value: "iyengar", label: "Iyengar" },
    { value: "pilates", label: "Pilates" },
    { value: "other", label: "Other" },
];

const HOURS_OPTIONS = [
    { value: "0_3", label: "0-3" },
    { value: "4_7", label: "4-7" },
    { value: "7_10", label: "7-10" },
    { value: "10_plus", label: "10+" },
];

const SIMPLE_LEVEL_OPTIONS = [
    { value: "poor", label: "Poor" },
    { value: "average", label: "Average" },
    { value: "good", label: "Good" },
];

const DISCOVERY_OPTIONS = [
    { value: "google", label: "Google" },
    { value: "facebook", label: "Facebook" },
    { value: "instagram", label: "Instagram" },
    { value: "chatgpt", label: "ChatGPT" },
    { value: "gemini", label: "Gemini" },
    { value: "perplexity", label: "Perplexity" },
    { value: "youtube", label: "YouTube" },
    { value: "yoga_studio", label: "Yoga Studio" },
    { value: "word_of_mouth", label: "Word Of Mouth" },
    { value: "other", label: "Other" },
];

function wordsCount(value) {
    return String(value || "")
        .trim()
        .split(/\s+/)
        .filter(Boolean).length;
}

function firstError(errors, field) {
    if (errors?.[field]) {
        return errors[field];
    }
    const nestedKey = Object.keys(errors || {}).find((key) =>
        key.startsWith(`${field}.`),
    );
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
}) {
    const selectedValues = Array.isArray(value) ? value : [];

    return (
        <div className="space-y-3">
            <div>
                <InputLabel
                    htmlFor={id}
                    value={label}
                    className="text-base font-bold text-white"
                />
                {description ? (
                    <p className="mt-1 text-sm font-semibold text-white/70">
                        {description}
                    </p>
                ) : null}
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                {options.map((option) => {
                    const checked = multiple
                        ? selectedValues.includes(option.value)
                        : value === option.value;

                    return (
                        <label
                            key={option.value}
                            className={[
                                "flex cursor-pointer items-center gap-3 rounded-[12px] border px-5 py-4 transition-colors",
                                checked
                                    ? "border-[#DB202C] bg-[#DB202C]/15 text-white shadow-[0_0_12px_rgba(219,32,44,0.2)]"
                                    : "border-white/20 bg-transparent text-white/80 hover:border-[#DB202C]/50 hover:bg-[#DB202C]/5",
                            ].join(" ")}
                        >
                            <input
                                id={id}
                                type={multiple ? "checkbox" : "radio"}
                                name={id}
                                value={option.value}
                                checked={checked}
                                onChange={() => {
                                    if (multiple) {
                                        const nextValues = checked
                                            ? selectedValues.filter(
                                                  (item) =>
                                                      item !== option.value,
                                              )
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
                                    "flex size-5 shrink-0 items-center justify-center rounded-full border",
                                    checked
                                        ? "border-[#DB202C] bg-[#DB202C] text-white"
                                        : "border-white/40 bg-transparent text-transparent",
                                ].join(" ")}
                            >
                                <Check className="size-3.5" />
                            </span>
                            <span className="text-base font-semibold">
                                {option.label}
                            </span>
                        </label>
                    );
                })}
            </div>
            <InputError
                message={error}
                className="text-[#ffb4a8] font-semibold"
            />
        </div>
    );
}

function SelectField({ id, label, value, onChange, error, options }) {
    return (
        <div>
            <InputLabel
                htmlFor={id}
                value={label}
                className="text-base font-bold text-white mb-2"
            />
            <select
                id={id}
                value={value ?? ""}
                onChange={(event) => onChange(event.target.value)}
                className="block w-full rounded-[12px] border border-[#DB202C] bg-transparent px-4 py-3.5 text-base font-semibold text-white shadow-sm focus:border-[#DB202C] focus:ring-1 focus:ring-[#DB202C] [&::-webkit-calendar-picker-indicator]:invert"
            >
                <option value="" disabled className="text-black font-semibold">
                    Select an option
                </option>
                {options.map((option) => (
                    <option
                        key={option.value}
                        value={option.value}
                        className="text-black font-semibold text-base"
                    >
                        {option.flag ? `${option.flag} ` : ""}
                        {option.label}
                    </option>
                ))}
            </select>
            <InputError
                message={error}
                className="text-[#ffb4a8] font-semibold mt-2"
            />
        </div>
    );
}

function TextAreaField({ id, label, value, onChange, error, helper = null }) {
    return (
        <div>
            <InputLabel
                htmlFor={id}
                value={label}
                className="text-base font-bold text-white mb-2"
            />
            <textarea
                id={id}
                rows={5}
                value={value ?? ""}
                onChange={(event) => onChange(event.target.value)}
                className="block w-full rounded-[12px] border border-[#DB202C] bg-transparent px-4 py-3.5 text-base font-semibold text-white shadow-sm placeholder:text-white/30 focus:border-[#DB202C] focus:ring-1 focus:ring-[#DB202C]"
            />
            {helper ? (
                <p className="mt-2 text-sm font-semibold text-white/60">
                    {helper}
                </p>
            ) : null}
            <InputError
                message={error}
                className="text-[#ffb4a8] font-semibold"
            />
        </div>
    );
}

export default function StudentProfileForm({
    data,
    setData,
    errors,
    processing,
    onSubmit,
    submitLabel = "Save Changes",
    variant = "default",
    mode = "profile",
    currentProfilePhotoUrl = null,
}) {
    const { directory = {} } = usePage().props;
    const countryOptions = directory.countries ?? [];
    const phoneCountryCodeOptions = directory.phone_country_codes ?? [];
    const isScoreboard = variant === "scoreboard";
    const isEnrollment = mode === "enrollment";
    const [localErrors, setLocalErrors] = useState({});

    // --- State Cropper Gambar ---
    const fileInputRef = useRef(null);
    const [photoPreview, setPhotoPreview] = useState(currentProfilePhotoUrl);
    const [rawImageSrc, setRawImageSrc] = useState(null);
    const [isCropModalOpen, setIsCropModalOpen] = useState(false);
    const [crop, setCrop] = useState({ x: 0, y: 0 });
    const [zoom, setZoom] = useState(1);
    const [croppedAreaPixels, setCroppedAreaPixels] = useState(null);

    const todayLabel = useMemo(
        () =>
            new Intl.DateTimeFormat("en-US", {
                month: "short",
                day: "numeric",
                year: "numeric",
            }).format(new Date()),
        [],
    );
    const todayIso = useMemo(() => new Date().toISOString().slice(0, 10), []);

    // Desain Form Tanpa Frame
    const sectionClassName = "space-y-8 pt-8";
    const titleClassName = "text-3xl font-bold tracking-tight text-white mb-2";
    const descriptionClassName =
        "text-base font-semibold leading-6 text-white/70";
    const inputClassName =
        "!border-[#DB202C] bg-transparent text-white text-base font-semibold placeholder:text-white/30 focus:!border-[#DB202C] focus:ring-1 focus:ring-[#DB202C]";

    // Handler untuk File Input Upload
    const onFileChange = async (e) => {
        if (e.target.files && e.target.files.length > 0) {
            const file = e.target.files[0];
            const imageDataUrl = await new Promise((resolve) => {
                const reader = new FileReader();
                reader.readAsDataURL(file);
                reader.onload = () => resolve(reader.result);
            });
            setRawImageSrc(imageDataUrl);
            setIsCropModalOpen(true);
        }
        e.target.value = null; // reset input
    };

    const onCropComplete = useCallback((croppedArea, croppedAreaPixels) => {
        setCroppedAreaPixels(croppedAreaPixels);
    }, []);

    const handleSaveCrop = async () => {
        try {
            const { file, url } = await getCroppedImg(
                rawImageSrc,
                croppedAreaPixels,
            );
            setPhotoPreview(url);
            setData("profile_photo", file);
            setIsCropModalOpen(false);
        } catch (e) {
            console.error(e);
        }
    };

    const handleSubmit = (event) => {
        const nextLocalErrors = {};

        if (isEnrollment && !data.terms_accepted) {
            nextLocalErrors.terms_accepted = "Please agree to the terms first.";
        }

        if (isEnrollment && !data.recaptcha_confirmed) {
            nextLocalErrors.recaptcha_confirmed =
                "Please confirm the reCAPTCHA checkbox.";
        }

        setLocalErrors(nextLocalErrors);

        if (Object.keys(nextLocalErrors).length > 0) {
            event.preventDefault();
            return;
        }

        onSubmit(event);
    };

    return (
        <>
            {/* Modal Cropper */}
            <Dialog open={isCropModalOpen} onOpenChange={setIsCropModalOpen}>
                <DialogContent className="max-w-xl border-white/10 bg-[#141110] text-white">
                    <DialogHeader>
                        <DialogTitle className="text-xl font-bold text-white">
                            Adjust Profile Photo
                        </DialogTitle>
                    </DialogHeader>

                    <div className="relative h-80 w-full sm:h-96 mt-4 rounded-xl overflow-hidden bg-black">
                        {rawImageSrc && (
                            <Cropper
                                image={rawImageSrc}
                                crop={crop}
                                zoom={zoom}
                                aspect={3 / 4} // Membuat lonjong (Portrait)
                                cropShape="round" // Membuat bingkai menjadi oval
                                showGrid={false}
                                onCropChange={setCrop}
                                onCropComplete={onCropComplete}
                                onZoomChange={setZoom}
                            />
                        )}
                    </div>

                    <div className="mt-4 px-2">
                        <label className="text-sm font-semibold text-white/80">
                            Zoom
                        </label>
                        <input
                            type="range"
                            value={zoom}
                            min={1}
                            max={3}
                            step={0.1}
                            aria-labelledby="Zoom"
                            onChange={(e) => setZoom(e.target.value)}
                            className="w-full mt-2 accent-[#DB202C]"
                        />
                    </div>

                    <DialogFooter className="mt-6 border-t border-white/10 pt-4">
                        <Button
                            variant="outline"
                            onClick={() => setIsCropModalOpen(false)}
                            className="border-white/20 bg-transparent text-white hover:bg-white/10"
                        >
                            Cancel
                        </Button>
                        <Button
                            onClick={handleSaveCrop}
                            className="bg-[#DB202C] text-white hover:bg-[#c31c28]"
                        >
                            Crop & Save
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <form onSubmit={handleSubmit} className="space-y-12">
                <section className={sectionClassName}>
                    <div className="mb-6">
                        <h3 className={titleClassName}>Personal Information</h3>
                        <p className={descriptionClassName}>
                            Basic account details and your preferred certificate
                            picture.
                        </p>
                    </div>

                    <div className="grid gap-8 md:grid-cols-2">
                        <div>
                            <InputLabel
                                htmlFor="first_name"
                                value="First Name"
                                className="text-base font-bold text-white"
                            />
                            <TextInput
                                id="first_name"
                                className={`mt-2 block w-full rounded-[12px] py-3.5 px-4 ${inputClassName}`}
                                value={data.first_name}
                                onChange={(event) =>
                                    setData("first_name", event.target.value)
                                }
                                isFocused
                            />
                            <InputError
                                message={firstError(errors, "first_name")}
                                className="text-[#ffb4a8] font-semibold mt-2"
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="last_name"
                                value="Last Name"
                                className="text-base font-bold text-white"
                            />
                            <TextInput
                                id="last_name"
                                className={`mt-2 block w-full rounded-[12px] py-3.5 px-4 ${inputClassName}`}
                                value={data.last_name}
                                onChange={(event) =>
                                    setData("last_name", event.target.value)
                                }
                            />
                            <InputError
                                message={firstError(errors, "last_name")}
                                className="text-[#ffb4a8] font-semibold mt-2"
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="email"
                                value="Email"
                                className="text-base font-bold text-white"
                            />
                            <TextInput
                                id="email"
                                type="email"
                                className={`mt-2 block w-full rounded-[12px] py-3.5 px-4 ${inputClassName}`}
                                value={data.email}
                                onChange={(event) =>
                                    setData("email", event.target.value)
                                }
                            />
                            <InputError
                                message={firstError(errors, "email")}
                                className="text-[#ffb4a8] font-semibold mt-2"
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="whatsapp_number"
                                value="WhatsApp"
                                className="text-base font-bold text-white"
                            />
                            <div className="mt-2 grid gap-3 sm:grid-cols-[140px_minmax(0,1fr)]">
                                <select
                                    id="whatsapp_country_code"
                                    value={data.whatsapp_country_code ?? "+62"}
                                    onChange={(event) =>
                                        setData(
                                            "whatsapp_country_code",
                                            event.target.value,
                                        )
                                    }
                                    className="block w-full rounded-[12px] border border-[#DB202C] bg-transparent px-3 py-3.5 text-base font-semibold text-white shadow-sm focus:border-[#DB202C] focus:ring-1 focus:ring-[#DB202C]"
                                >
                                    {phoneCountryCodeOptions.map((option) => (
                                        <option
                                            key={`${option.value}-${option.label}`}
                                            value={option.value}
                                            className="text-black font-semibold"
                                        >
                                            {option.flag
                                                ? `${option.flag} `
                                                : ""}
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                                <TextInput
                                    id="whatsapp_number"
                                    className={`block w-full rounded-[12px] py-3.5 px-4 ${inputClassName}`}
                                    value={data.whatsapp_number ?? ""}
                                    onChange={(event) =>
                                        setData(
                                            "whatsapp_number",
                                            event.target.value,
                                        )
                                    }
                                    placeholder="81233456788"
                                />
                            </div>
                            <InputError
                                message={
                                    firstError(errors, "whatsapp_number") ??
                                    firstError(
                                        errors,
                                        "whatsapp_country_code",
                                    ) ??
                                    firstError(errors, "whatsapp")
                                }
                                className="text-[#ffb4a8] font-semibold mt-2"
                            />
                        </div>

                        {/* Certificate Photo Section */}
                        <div className="md:col-span-2 mt-2">
                            <InputLabel
                                htmlFor="profile_photo"
                                value="Please Upload Your Preferred Certificate Picture"
                                className="text-base font-bold text-white"
                            />

                            <div className="mt-4 flex flex-col sm:flex-row gap-6 items-start sm:items-center">
                                {/* Area Preview Berbentuk Lonjong (Oval) */}
                                <div className="relative shrink-0 flex h-[160px] w-[120px] items-center justify-center overflow-hidden rounded-[50%] border-[3px] border-[#DB202C] bg-black/40 shadow-[0_0_15px_rgba(219,32,44,0.3)]">
                                    {photoPreview ? (
                                        <img
                                            src={photoPreview}
                                            alt="Preferred certificate"
                                            className="h-full w-full object-cover"
                                        />
                                    ) : (
                                        <div className="flex flex-col items-center justify-center text-white/50">
                                            <UploadCloud className="size-8 mb-2 text-[#DB202C]" />
                                        </div>
                                    )}
                                </div>

                                <div className="flex-1 space-y-4">
                                    <div>
                                        <p className="text-sm font-semibold text-white/70 leading-relaxed">
                                            Upload a clear portrait photo. Click
                                            the button below to upload and
                                            adjust your photo perfectly into the
                                            oval shape.
                                        </p>
                                    </div>

                                    <input
                                        ref={fileInputRef}
                                        id="profile_photo"
                                        type="file"
                                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                        className="hidden"
                                        onChange={onFileChange}
                                    />

                                    <Button
                                        type="button"
                                        onClick={() =>
                                            fileInputRef.current?.click()
                                        }
                                        className="rounded-full bg-white text-black px-8 py-3 text-sm font-bold hover:bg-white/80"
                                    >
                                        Choose Photo
                                    </Button>

                                    <InputError
                                        message={firstError(
                                            errors,
                                            "profile_photo",
                                        )}
                                        className="text-[#ffb4a8] font-semibold"
                                    />
                                </div>
                            </div>
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="instagram"
                                value="Instagram (Optional)"
                                className="text-base font-bold text-white"
                            />
                            <TextInput
                                id="instagram"
                                className={`mt-2 block w-full rounded-[12px] py-3.5 px-4 ${inputClassName}`}
                                value={data.instagram ?? ""}
                                onChange={(event) =>
                                    setData("instagram", event.target.value)
                                }
                            />
                            <InputError
                                message={firstError(errors, "instagram")}
                                className="text-[#ffb4a8] font-semibold mt-2"
                            />
                        </div>

                        <SelectField
                            id="country"
                            label="Country"
                            value={data.country}
                            onChange={(value) => {
                                setData("country", value);
                                const matchedCountry = countryOptions.find(
                                    (option) => option.value === value,
                                );
                                const matchedDialCode =
                                    phoneCountryCodeOptions.find((option) =>
                                        option.label.startsWith(
                                            `${matchedCountry?.label ?? ""} (`,
                                        ),
                                    );
                                if (matchedDialCode && !data.whatsapp_number) {
                                    setData(
                                        "whatsapp_country_code",
                                        matchedDialCode.value,
                                    );
                                }
                            }}
                            error={errors.country}
                            options={countryOptions}
                        />

                        <div>
                            <InputLabel
                                htmlFor="birth_date"
                                value="Birth Date"
                                className="text-base font-bold text-white"
                            />
                            <div className="relative mt-2">
                                <input
                                    id="birth_date"
                                    name="birth_date"
                                    type="date"
                                    max={todayIso}
                                    value={
                                        data.birth_date
                                            ? String(data.birth_date).slice(
                                                  0,
                                                  10,
                                              )
                                            : ""
                                    }
                                    onChange={(event) =>
                                        setData(
                                            "birth_date",
                                            event.target.value,
                                        )
                                    }
                                    style={{ colorScheme: "dark" }}
                                    className="block w-full appearance-none rounded-[12px] border border-[#DB202C] bg-transparent px-4 py-3.5 pr-12 text-base font-semibold text-white shadow-sm focus:border-[#DB202C] focus:ring-1 focus:ring-[#DB202C] [&::-webkit-calendar-picker-indicator]:cursor-pointer [&::-webkit-calendar-picker-indicator]:opacity-0"
                                />
                                <CalendarDays className="pointer-events-none absolute right-4 top-1/2 z-10 size-5 -translate-y-1/2 text-white/70" />
                            </div>
                            <InputError
                                message={firstError(errors, "birth_date")}
                                className="text-[#ffb4a8] font-semibold mt-2"
                            />
                        </div>

                        <div className="md:col-span-2">
                            <ChoiceGrid
                                id="gender"
                                label="Gender"
                                value={data.gender}
                                error={firstError(errors, "gender")}
                                options={GENDER_OPTIONS}
                                onChange={(value) => setData("gender", value)}
                            />
                        </div>
                    </div>
                </section>

                <section className={sectionClassName}>
                    <div className="border-b border-white/20 pb-4 mb-6">
                        <h3 className={titleClassName}>Learning Background</h3>
                        <p className={descriptionClassName}>
                            Your practice background.
                        </p>
                    </div>

                    <div className="space-y-10">
                        <ChoiceGrid
                            id="practicing_yoga_for"
                            label="Current Yoga Experience"
                            description="Practicing Yoga For (Years & Months)"
                            value={data.practicing_yoga_for}
                            error={firstError(errors, "practicing_yoga_for")}
                            options={PRACTICING_OPTIONS}
                            onChange={(value) =>
                                setData("practicing_yoga_for", value)
                            }
                        />

                        <ChoiceGrid
                            id="yoga_sequence_experience"
                            label="Yoga Sequence Experience"
                            value={data.yoga_sequence_experience ?? []}
                            error={firstError(
                                errors,
                                "yoga_sequence_experience",
                            )}
                            options={SEQUENCE_OPTIONS}
                            onChange={(value) =>
                                setData("yoga_sequence_experience", value)
                            }
                            multiple={true}
                        />

                        <ChoiceGrid
                            id="hours_per_week"
                            label="How Many Hours P/Week Practicing Yoga?"
                            value={data.hours_per_week}
                            error={firstError(errors, "hours_per_week")}
                            options={HOURS_OPTIONS}
                            onChange={(value) =>
                                setData("hours_per_week", value)
                            }
                        />

                        <ChoiceGrid
                            id="current_fitness_level"
                            label="Your Current Fitness Level"
                            value={data.current_fitness_level}
                            error={firstError(errors, "current_fitness_level")}
                            options={SIMPLE_LEVEL_OPTIONS}
                            onChange={(value) =>
                                setData("current_fitness_level", value)
                            }
                        />

                        <ChoiceGrid
                            id="flexibility_rating"
                            label="How would you rate your flexibility"
                            value={data.flexibility_rating}
                            error={firstError(errors, "flexibility_rating")}
                            options={SIMPLE_LEVEL_OPTIONS}
                            onChange={(value) =>
                                setData("flexibility_rating", value)
                            }
                        />
                    </div>
                </section>

                <section className={sectionClassName}>
                    <div className="border-b border-white/20 pb-4 mb-6">
                        <h3 className={titleClassName}>Motivation</h3>
                        <p className={descriptionClassName}>
                            Keep each answer within 50 words.
                        </p>
                    </div>

                    <div className="space-y-10">
                        <TextAreaField
                            id="motivation"
                            label="What is Your Motivation In Becoming A Yoga Teacher?"
                            value={data.motivation}
                            onChange={(value) => setData("motivation", value)}
                            error={firstError(errors, "motivation")}
                            helper={`${wordsCount(data.motivation)}/50 words`}
                        />

                        <TextAreaField
                            id="why_yogafx"
                            label="Please Let Us Know Why You Chose YogaFX"
                            value={data.why_yogafx}
                            onChange={(value) => setData("why_yogafx", value)}
                            error={firstError(errors, "why_yogafx")}
                            helper={`${wordsCount(data.why_yogafx)}/50 words`}
                        />

                        <ChoiceGrid
                            id="how_did_you_find_us"
                            label="Please Share How Did You Find Us"
                            value={data.how_did_you_find_us ?? []}
                            error={firstError(errors, "how_did_you_find_us")}
                            options={DISCOVERY_OPTIONS}
                            onChange={(value) =>
                                setData("how_did_you_find_us", value)
                            }
                            multiple={true}
                        />
                    </div>
                </section>

                {isEnrollment ? (
                    <section className={sectionClassName}>
                        <div className="border-b border-white/20 pb-4 mb-6">
                            <h3 className={titleClassName}>
                                Terms & Confirmation
                            </h3>
                            <p className={descriptionClassName}>
                                Confirm your final enrollment details.
                            </p>
                        </div>

                        <div className="space-y-6">
                            <label className="flex cursor-pointer items-start gap-4 rounded-[14px] border border-[#DB202C] bg-transparent px-5 py-5 text-white transition hover:bg-[#DB202C]/10">
                                <input
                                    type="checkbox"
                                    checked={Boolean(data.terms_accepted)}
                                    onChange={(event) =>
                                        setData(
                                            "terms_accepted",
                                            event.target.checked,
                                        )
                                    }
                                    className="mt-1 size-5 rounded border-[#DB202C] text-[#DB202C] focus:ring-[#DB202C] bg-transparent"
                                />
                                <span className="text-lg font-bold">
                                    Yes, I agree with Term & Conditions
                                </span>
                            </label>
                            <InputError
                                message={localErrors.terms_accepted}
                                className="text-[#ffb4a8] font-semibold"
                            />

                            <div className="grid gap-6 md:grid-cols-2">
                                <div className="rounded-[14px] border border-[#DB202C] bg-transparent px-5 py-5 text-white">
                                    <div className="text-sm font-bold text-white/70">
                                        Full Name
                                    </div>
                                    <div className="mt-2 text-xl font-bold">
                                        {[data.first_name, data.last_name]
                                            .filter(Boolean)
                                            .join(" ") || "Your name"}
                                    </div>
                                </div>
                                <div className="rounded-[14px] border border-[#DB202C] bg-transparent px-5 py-5 text-white">
                                    <div className="text-sm font-bold text-white/70">
                                        Date
                                    </div>
                                    <div className="mt-2 text-xl font-bold">
                                        {todayLabel}
                                    </div>
                                </div>
                            </div>

                            <label className="flex cursor-pointer items-start gap-4 rounded-[14px] border border-[#DB202C] bg-transparent px-5 py-5 text-white transition hover:bg-[#DB202C]/10">
                                <input
                                    type="checkbox"
                                    checked={Boolean(data.recaptcha_confirmed)}
                                    onChange={(event) =>
                                        setData(
                                            "recaptcha_confirmed",
                                            event.target.checked,
                                        )
                                    }
                                    className="mt-1 size-5 rounded border-[#DB202C] text-[#DB202C] focus:ring-[#DB202C] bg-transparent"
                                />
                                <span className="text-lg font-bold">
                                    I'm not a robot (reCAPTCHA)
                                </span>
                            </label>
                            <InputError
                                message={localErrors.recaptcha_confirmed}
                                className="text-[#ffb4a8] font-semibold"
                            />
                        </div>
                    </section>
                ) : null}

                <div className="flex items-center pt-8 border-t border-white/20">
                    <Button
                        type="submit"
                        disabled={processing}
                        className="rounded-full bg-white px-10 py-4 text-lg font-bold text-black hover:bg-white/80 transition-all hover:-translate-y-0.5"
                    >
                        {submitLabel}
                    </Button>
                </div>
            </form>
        </>
    );
}
