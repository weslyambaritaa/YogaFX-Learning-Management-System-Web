import InputError from "@/Components/InputError";
import FlagOptionSelect from "@/Components/FlagOptionSelect";
import InputLabel from "@/Components/InputLabel";
import TextInput from "@/Components/TextInput";
import { PUBLIC_FORM_FIELD_CLASS } from "@/lib/publicFormStyles";
import { Button } from "@/Components/ui/button";
import {
    enrichCountryOptions,
    findCountryOptionByDialCode,
} from "@/lib/countryFlags";
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/Components/ui/dialog";
import { usePage } from "@inertiajs/react";
import { CalendarDays, Check, UploadCloud } from "lucide-react";
import { useMemo, useState, useRef, useCallback, useEffect } from "react";
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

const YES_NO_OPTIONS = [
    { value: "yes", label: "Yes" },
    { value: "no", label: "No" },
];

const TSHIRT_SIZE_OPTIONS = [
    { value: "XXL", label: "XXL" },
    { value: "XL", label: "XL" },
    { value: "L", label: "L" },
    { value: "M", label: "M" },
    { value: "S", label: "S" },
    { value: "XS", label: "XS" },
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

const COUNTRY_FLAG_MAP = {
    Argentina: "🇦🇷",
    Australia: "🇦🇺",
    Austria: "🇦🇹",
    Belgium: "🇧🇪",
    Brazil: "🇧🇷",
    Canada: "🇨🇦",
    China: "🇨🇳",
    Denmark: "🇩🇰",
    Egypt: "🇪🇬",
    Finland: "🇫🇮",
    France: "🇫🇷",
    Germany: "🇩🇪",
    "Hong Kong": "🇭🇰",
    India: "🇮🇳",
    Indonesia: "🇮🇩",
    Ireland: "🇮🇪",
    Italy: "🇮🇹",
    Japan: "🇯🇵",
    Malaysia: "🇲🇾",
    Mexico: "🇲🇽",
    Netherlands: "🇳🇱",
    "New Zealand": "🇳🇿",
    Norway: "🇳🇴",
    Philippines: "🇵🇭",
    Portugal: "🇵🇹",
    Qatar: "🇶🇦",
    "Saudi Arabia": "🇸🇦",
    Singapore: "🇸🇬",
    "South Africa": "🇿🇦",
    "South Korea": "🇰🇷",
    Spain: "🇪🇸",
    Sweden: "🇸🇪",
    Switzerland: "🇨🇭",
    Taiwan: "🇹🇼",
    Thailand: "🇹🇭",
    Turkey: "🇹🇷",
    "United Arab Emirates": "🇦🇪",
    "United Kingdom": "🇬🇧",
    "United States": "🇺🇸",
    Vietnam: "🇻🇳",
};

const COUNTRY_FLAG_UNICODE_MAP = {
    Argentina: "\uD83C\uDDE6\uD83C\uDDF7",
    Australia: "\uD83C\uDDE6\uD83C\uDDFA",
    Austria: "\uD83C\uDDE6\uD83C\uDDF9",
    Belgium: "\uD83C\uDDE7\uD83C\uDDEA",
    Brazil: "\uD83C\uDDE7\uD83C\uDDF7",
    Canada: "\uD83C\uDDE8\uD83C\uDDE6",
    China: "\uD83C\uDDE8\uD83C\uDDF3",
    Denmark: "\uD83C\uDDE9\uD83C\uDDF0",
    Egypt: "\uD83C\uDDEA\uD83C\uDDEC",
    Finland: "\uD83C\uDDEB\uD83C\uDDEE",
    France: "\uD83C\uDDEB\uD83C\uDDF7",
    Germany: "\uD83C\uDDE9\uD83C\uDDEA",
    "Hong Kong": "\uD83C\uDDED\uD83C\uDDF0",
    India: "\uD83C\uDDEE\uD83C\uDDF3",
    Indonesia: "\uD83C\uDDEE\uD83C\uDDE9",
    Ireland: "\uD83C\uDDEE\uD83C\uDDEA",
    Italy: "\uD83C\uDDEE\uD83C\uDDF9",
    Japan: "\uD83C\uDDEF\uD83C\uDDF5",
    Malaysia: "\uD83C\uDDF2\uD83C\uDDFE",
    Mexico: "\uD83C\uDDF2\uD83C\uDDFD",
    Netherlands: "\uD83C\uDDF3\uD83C\uDDF1",
    "New Zealand": "\uD83C\uDDF3\uD83C\uDDFF",
    Norway: "\uD83C\uDDF3\uD83C\uDDF4",
    Philippines: "\uD83C\uDDF5\uD83C\uDDED",
    Portugal: "\uD83C\uDDF5\uD83C\uDDF9",
    Qatar: "\uD83C\uDDF6\uD83C\uDDE6",
    "Saudi Arabia": "\uD83C\uDDF8\uD83C\uDDE6",
    Singapore: "\uD83C\uDDF8\uD83C\uDDEC",
    "South Africa": "\uD83C\uDDFF\uD83C\uDDE6",
    "South Korea": "\uD83C\uDDF0\uD83C\uDDF7",
    Spain: "\uD83C\uDDEA\uD83C\uDDF8",
    Sweden: "\uD83C\uDDF8\uD83C\uDDEA",
    Switzerland: "\uD83C\uDDE8\uD83C\uDDED",
    Taiwan: "\uD83C\uDDF9\uD83C\uDDFC",
    Thailand: "\uD83C\uDDF9\uD83C\uDDED",
    Turkey: "\uD83C\uDDF9\uD83C\uDDF7",
    "United Arab Emirates": "\uD83C\uDDE6\uD83C\uDDEA",
    "United Kingdom": "\uD83C\uDDEC\uD83C\uDDE7",
    "United States": "\uD83C\uDDFA\uD83C\uDDF8",
    Vietnam: "\uD83C\uDDFB\uD83C\uDDF3",
};

function normalizePhoneNumberInput(value) {
    return String(value ?? "")
        .replace(/^\s+/, "")
        .replace(/^0+/, "");
}

function isBlankString(value) {
    return String(value ?? "").trim() === "";
}

function isValidEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value ?? "").trim());
}

// Single source of truth for the font so it can't be silently
// overridden by an older font-family declared elsewhere in the tree.
const FONT_FAMILY = "'Montserrat', sans-serif";
const PUBLIC_FORM_LABEL_CLASS = "text-sm font-medium text-white/90";
const PUBLIC_FORM_SELECT_PANEL_CLASS =
    "border-white/10 bg-[#161616] text-white";

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

function firstAvailableErrorKey(errors) {
    const priority = [
        "first_name",
        "last_name",
        "email",
        "profile_photo",
        "whatsapp_number",
        "whatsapp_country_code",
        "whatsapp",
        "instagram",
        "country",
        "birth_date",
        "gender",
        "tshirt_size",
        "favorite_song",
        "emergency_contact_name",
        "emergency_contact_relationship",
        "emergency_contact_country_code",
        "emergency_contact_number",
        "emergency_contact_whatsapp",
        "practicing_yoga_for",
        "yoga_sequence_experience",
        "hours_per_week",
        "current_fitness_level",
        "flexibility_rating",
        "motivation",
        "why_yogafx",
        "how_did_you_find_us",
        "has_medical_issues",
        "medical_issues_details",
        "is_taking_medication",
        "medication_details",
        "terms_accepted",
        "recaptcha_confirmed",
    ];

    for (const key of priority) {
        if (firstError(errors, key)) {
            return key;
        }
    }

    return Object.keys(errors || {})[0] ?? null;
}

function resolveOptionFlag(option) {
    if (!option) {
        return "🌍";
    }

    const normalizedLabel = String(option.label ?? "")
        .replace(/\s*\(.+\)\s*$/, "")
        .trim();

    return (
        COUNTRY_FLAG_MAP[option.value] ??
        COUNTRY_FLAG_MAP[normalizedLabel] ??
        COUNTRY_FLAG_MAP[option.label] ??
        "🌍"
    );
}

function openNativeDatePicker(input) {
    if (!input) {
        return;
    }

    if (typeof input.showPicker === "function") {
        input.showPicker();
        return;
    }

    input.focus();
    input.click();
}

function resolveOptionFlagSafe(option) {
    if (!option) {
        return "\uD83C\uDF0D";
    }

    const normalizedLabel = String(option.label ?? "")
        .replace(/\s*\(.+\)\s*$/, "")
        .trim();

    return (
        COUNTRY_FLAG_UNICODE_MAP[option.value] ??
        COUNTRY_FLAG_UNICODE_MAP[normalizedLabel] ??
        COUNTRY_FLAG_UNICODE_MAP[option.label] ??
        "\uD83C\uDF0D"
    );
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
    theme,
    optionsGridClassName = "grid grid-cols-2 gap-3 sm:gap-4",
}) {
    const selectedValues = Array.isArray(value) ? value : [];

    return (
        <div className="space-y-3" style={{ fontFamily: FONT_FAMILY }}>
            <div>
                <InputLabel
                    htmlFor={id}
                    value={label}
                    className={theme.labelClassName}
                    style={{ fontFamily: FONT_FAMILY }}
                />
                {description ? (
                    <p
                        className={theme.choiceDescriptionClassName}
                        style={{ fontFamily: FONT_FAMILY }}
                    >
                        {description}
                    </p>
                ) : null}
            </div>

            <div className={optionsGridClassName}>
                {options.map((option) => {
                    const checked = multiple
                        ? selectedValues.includes(option.value)
                        : value === option.value;

                    return (
                        <label
                            key={option.value}
                            className={[
                                "flex cursor-pointer items-center gap-3 rounded-[5px] border px-[10px] py-[8px] transition-colors",
                                checked
                                    ? theme.choiceCheckedClassName
                                    : theme.choiceUncheckedClassName,
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
                                        ? theme.choiceIndicatorCheckedClassName
                                        : theme.choiceIndicatorUncheckedClassName,
                                ].join(" ")}
                            >
                                <Check className="size-3.5" />
                            </span>
                            <span
                                className="text-sm font-normal"
                                style={{ fontFamily: FONT_FAMILY }}
                            >
                                {option.label}
                            </span>
                        </label>
                    );
                })}
            </div>
            <InputError message={error} className={theme.errorClassName} />
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
    theme,
    selectedOption = null,
}) {
    return (
        <div style={{ fontFamily: FONT_FAMILY }}>
            <InputLabel
                htmlFor={id}
                value={label}
                className={theme.labelWithSpacingClassName}
                style={{ fontFamily: FONT_FAMILY }}
            />
            <div className="relative">
                <span
                    aria-hidden="true"
                    className="pointer-events-none absolute left-3 top-1/2 z-10 -translate-y-1/2 text-lg leading-none"
                >
                    {resolveCountryFlag(selectedOption)}
                </span>
                <select
                    id={id}
                    value={value ?? ""}
                    onChange={(event) => onChange(event.target.value)}
                    className={`${theme.selectClassName} pl-11`}
                    style={{
                        fontFamily: FONT_FAMILY,
                        color:
                            value && value !== ""
                                ? theme.selectActiveColor
                                : theme.selectPlaceholderColor,
                    }}
                >
                    <option
                        value=""
                        disabled
                        className={theme.selectOptionClassName}
                    >
                        Select an option
                    </option>
                    {options.map((option) => (
                        <option
                            key={option.value}
                            value={option.value}
                            className={theme.selectOptionClassName}
                        >
                            {resolveCountryFlag(option)} {option.label}
                        </option>
                    ))}
                </select>
            </div>
            <InputError
                message={error}
                className={`${theme.errorClassName} mt-2`}
            />
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
    theme,
}) {
    return (
        <div style={{ fontFamily: FONT_FAMILY }}>
            <InputLabel
                htmlFor={id}
                value={label}
                className={theme.labelWithSpacingClassName}
                style={{ fontFamily: FONT_FAMILY }}
            />
            <textarea
                id={id}
                rows={5}
                value={value ?? ""}
                onChange={(event) => onChange(event.target.value)}
                className={theme.textareaClassName}
                style={{ fontFamily: FONT_FAMILY }}
            />
            {helper ? (
                <p
                    className={theme.helperClassName}
                    style={{ fontFamily: FONT_FAMILY }}
                >
                    {helper}
                </p>
            ) : null}
            <InputError message={error} className={theme.errorClassName} />
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
    isMasterClass = false,
}) {
    const { directory = {} } = usePage().props;
    const countryOptions = enrichCountryOptions(directory.countries ?? []);
    const phoneCountryCodeOptions = enrichCountryOptions(
        directory.phone_country_codes ?? [],
    );
    const selectedCountryOption =
        countryOptions.find((option) => option.value === data.country) ?? null;
    const selectedPhoneCountryOption = findCountryOptionByDialCode(
        phoneCountryCodeOptions,
        data.whatsapp_country_code ?? "+62",
        data.country ?? "",
    );
    const selectedEmergencyPhoneCountryOption = findCountryOptionByDialCode(
        phoneCountryCodeOptions,
        data.emergency_contact_country_code ?? "+62",
        data.country ?? "",
    );
    const isScoreboard = variant === "scoreboard";
    const isEnrollment = mode === "enrollment";
    const isAdminMode = mode === "admin";

    const flagSelectButtonTextClassName = isAdminMode
        ? "text-sm font-normal text-slate-900"
        : "text-sm font-normal text-white";

    const flagSelectPlaceholderClassName = isAdminMode
        ? "text-sm font-normal text-slate-400"
        : "text-sm font-normal text-white/50";

    const flagSelectPanelClassName = isAdminMode
        ? "border-slate-200 bg-white text-slate-900"
        : PUBLIC_FORM_SELECT_PANEL_CLASS;

    const flagSelectActiveClassName = isAdminMode
        ? "bg-rose-50"
        : "bg-white/10";

    const flagSelectChevronClassName = isAdminMode
        ? "text-slate-500"
        : "text-white/60";

    const flagSelectFallbackClassName = isAdminMode
        ? "bg-slate-100 text-slate-500"
        : "bg-white/10 text-white/70";

    const [localErrors, setLocalErrors] = useState({});

    // --- State Cropper Gambar ---
    const fileInputRef = useRef(null);
    const [photoPreview, setPhotoPreview] = useState(currentProfilePhotoUrl);
    const [rawImageSrc, setRawImageSrc] = useState(null);
    const [isCropModalOpen, setIsCropModalOpen] = useState(false);
    const [crop, setCrop] = useState({ x: 0, y: 0 });
    const [zoom, setZoom] = useState(1);
    const [croppedAreaPixels, setCroppedAreaPixels] = useState(null);
    const birthDateInputRef = useRef(null);
    const errorSummaryRef = useRef(null);

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
    const backendErrorKey = useMemo(
        () => firstAvailableErrorKey(errors),
        [errors],
    );
    const formErrors = useMemo(
        () => ({ ...(errors ?? {}), ...(localErrors ?? {}) }),
        [errors, localErrors],
    );
    const localErrorKey = useMemo(
        () => firstAvailableErrorKey(localErrors),
        [localErrors],
    );
    const errorSummaryMessage = isEnrollment
        ? "Please complete the highlighted fields before continuing."
        : null;

    useEffect(() => {
        const firstErrorKey = localErrorKey ?? backendErrorKey;

        if (!firstErrorKey) {
            return;
        }

        const target = document.getElementById(firstErrorKey);

        if (target instanceof HTMLElement) {
            target.scrollIntoView({
                behavior: "smooth",
                block: "center",
            });
            target.focus({ preventScroll: true });
            return;
        }

        errorSummaryRef.current?.scrollIntoView({
            behavior: "smooth",
            block: "center",
        });
    }, [backendErrorKey, localErrorKey]);

    // Desain Form Tanpa Frame
    const sectionClassName = "space-y-6";
    const theme = isAdminMode
        ? {
              labelClassName: "text-sm font-medium text-slate-900",
              labelWithSpacingClassName:
                  "mb-2 text-sm font-medium text-slate-900",
              choiceDescriptionClassName:
                  "mt-1 text-sm font-normal text-slate-600",
              choiceCheckedClassName:
                  "border-2 border-[#DB202C] bg-[#DB202C] text-white shadow-[0_0_0_2px_rgba(219,32,44,0.18)]",
              choiceUncheckedClassName:
                  "border border-slate-300 bg-white text-slate-900 shadow-sm transition-all duration-200 hover:border-[#DB202C] hover:bg-rose-50",
              choiceIndicatorCheckedClassName:
                  "border-white bg-white text-[#DB202C]",
              choiceIndicatorUncheckedClassName:
                  "border-slate-400 bg-white text-transparent",
              errorClassName: "font-semibold text-rose-600",
              titleClassName:
                  "mb-2 text-[22px] font-medium tracking-tight text-slate-900",
              descriptionClassName:
                  "text-[12px] font-normal leading-6 text-slate-600",
              inputClassName:
                  "!border-slate-400 bg-white text-slate-900 text-sm font-normal placeholder:text-slate-400 focus:!border-[#DB202C] focus:ring-1 focus:ring-[#DB202C]",
              selectClassName:
                  "block w-full rounded-[5px] border border-slate-400 bg-white px-[10px] py-[8px] text-sm font-normal text-slate-900 shadow-sm focus:border-[#DB202C] focus:ring-1 focus:ring-[#DB202C]",
              selectOptionClassName:
                  "bg-white text-slate-900 text-sm font-normal",
              selectActiveColor: "#0f172a",
              selectPlaceholderColor: "#64748b",
              textareaClassName:
                  "block w-full rounded-[5px] border border-slate-400 bg-white px-[10px] py-[8px] text-sm font-normal text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-[#DB202C] focus:ring-1 focus:ring-[#DB202C]",
              helperClassName: "mt-2 text-sm font-medium text-slate-500",
              sectionDividerClassName: "mb-6 border-b border-slate-200 pb-4",
              footerDividerClassName:
                  "flex items-center border-t border-slate-200 pt-8",
              dialogContentClassName:
                  "max-w-xl border-slate-200 bg-white text-slate-900",
              dialogTitleClassName: "text-xl font-bold text-slate-900",
              dialogZoomLabelClassName: "text-sm font-semibold text-slate-700",
              dialogFooterClassName: "mt-6 border-t border-slate-200 pt-4",
              dialogCancelButtonClassName:
                  "rounded-[5px] border-slate-400 bg-white text-slate-900 hover:bg-slate-100",
              dialogSaveButtonClassName:
                  "rounded-[5px] bg-[#DB202C] text-white hover:bg-[#c31c28]",
              photoPreviewFrameClassName:
                  "relative flex h-[160px] w-[120px] shrink-0 items-center justify-center overflow-hidden rounded-[50%] border-[3px] border-[#DB202C] bg-slate-100 shadow-[0_0_15px_rgba(219,32,44,0.18)]",
              photoFallbackClassName:
                  "flex flex-col items-center justify-center text-slate-400",
              photoHelperClassName:
                  "text-[12px] font-normal leading-relaxed text-slate-600",
              dateInputStyle: undefined,
              dateInputClassName:
                  "block w-full appearance-none rounded-[5px] border border-slate-400 bg-white px-[10px] py-[8px] pr-12 text-sm font-normal text-slate-900 shadow-sm focus:border-[#DB202C] focus:ring-1 focus:ring-[#DB202C] [&::-webkit-calendar-picker-indicator]:cursor-pointer [&::-webkit-calendar-picker-indicator]:opacity-0",
              dateIconClassName:
                  "absolute right-3 top-1/2 z-10 flex size-8 -translate-y-1/2 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-[#DB202C]",
              primaryButtonClassName:
                  "rounded-[5px] bg-[#DB202C] px-[10px] py-[8px] text-[14px] font-medium text-white transition-all hover:-translate-y-0.5 hover:bg-[#c31c28]",
              uploadButtonClassName:
                  "rounded-[5px] bg-[#DB202C] px-[10px] py-[8px] text-[14px] font-medium text-white hover:bg-[#c31c28]",
          }
        : {
              labelClassName: PUBLIC_FORM_LABEL_CLASS,
              labelWithSpacingClassName: `mb-2 ${PUBLIC_FORM_LABEL_CLASS}`,
              choiceDescriptionClassName:
                  "mt-1 text-[12px] font-normal text-white/70",
              choiceCheckedClassName:
                  "border-2 border-[#DB202C] bg-[#DB202C] text-white shadow-[0_0_0_2px_rgba(219,32,44,0.35),0_0_18px_rgba(219,32,44,0.28)]",
              choiceUncheckedClassName:
                  "border-2 border-white/50 bg-black/45 text-white shadow-[0_0_0_1px_rgba(255,255,255,0.14)] transition-all duration-200 hover:border-white hover:bg-white/10",
              choiceIndicatorCheckedClassName:
                  "border-white bg-white text-[#DB202C]",
              choiceIndicatorUncheckedClassName:
                  "border-white bg-transparent text-transparent",
              errorClassName: "font-semibold text-[#ffb4a8]",
              titleClassName:
                  "mb-2 text-[24px] font-bold leading-tight tracking-tight text-white sm:text-[26px]",
              descriptionClassName:
                  "text-[12px] font-normal leading-6 text-white/70",
              inputClassName: PUBLIC_FORM_FIELD_CLASS,
              selectClassName: `block w-full ${PUBLIC_FORM_FIELD_CLASS} [&::-webkit-calendar-picker-indicator]:invert`,
              selectOptionClassName: "text-sm font-normal text-black",
              selectActiveColor: "#DB202C",
              selectPlaceholderColor: "#FFFFFF",
              textareaClassName: `block w-full ${PUBLIC_FORM_FIELD_CLASS}`,
              helperClassName:
                  "mt-2 text-[11px] font-normal italic text-white/65 sm:text-xs",
              sectionDividerClassName: isEnrollment
                  ? "mb-5 border-t-[4px] border-[#DB202C] pt-4"
                  : "mb-6 border-b border-white pb-4",
              footerDividerClassName: isEnrollment
                  ? "flex items-center justify-center pt-8"
                  : "flex items-center border-t border-white pt-8",
              dialogContentClassName:
                  "max-w-xl border-white bg-[#141110] text-white",
              dialogTitleClassName: "text-xl font-bold text-white",
              dialogZoomLabelClassName: "text-sm font-semibold text-white/80",
              dialogFooterClassName: "mt-6 border-t border-white pt-4",
              dialogCancelButtonClassName:
                  "rounded-[5px] border-white bg-transparent text-white hover:bg-white/10",
              dialogSaveButtonClassName:
                  "rounded-[5px] bg-[#DB202C] text-white hover:bg-[#c31c28]",
              photoPreviewFrameClassName:
                  "relative flex h-[160px] w-[120px] shrink-0 items-center justify-center overflow-hidden rounded-[50%] border-[3px] border-white bg-black/40 shadow-[0_0_15px_rgba(219,32,44,0.3)]",
              photoFallbackClassName:
                  "flex flex-col items-center justify-center text-white/50",
              photoHelperClassName:
                  "text-[12px] font-normal leading-relaxed text-white/70",
              dateInputStyle: { colorScheme: "dark" },
              dateInputClassName: `block w-full appearance-none ${PUBLIC_FORM_FIELD_CLASS} pr-12 [&::-webkit-calendar-picker-indicator]:cursor-pointer [&::-webkit-calendar-picker-indicator]:opacity-0`,
              dateIconClassName:
                  "absolute right-3 top-1/2 z-10 flex size-8 -translate-y-1/2 items-center justify-center rounded-full text-white/70 transition hover:bg-white/10 hover:text-white",
              primaryButtonClassName: isEnrollment
                  ? "min-h-[64px] w-full max-w-[360px] rounded-[8px] bg-[#DB202C] px-10 py-6 text-base font-bold italic text-white shadow-[0_12px_35px_rgba(219,32,44,0.3)] transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#c01a25]"
                  : "rounded-[5px] bg-[#DB202C] px-[10px] py-[8px] text-[14px] font-medium text-white transition-all hover:-translate-y-0.5 hover:bg-[#c31c28]",
              uploadButtonClassName:
                  "rounded-[5px] bg-[#DB202C] px-[10px] py-[8px] text-[14px] font-medium text-white hover:bg-[#c31c28]",
          };
    const titleClassName = theme.titleClassName;
    const descriptionClassName = theme.descriptionClassName;
    const inputClassName = theme.inputClassName;

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
            setLocalErrors((current) => ({
                ...current,
                profile_photo: "",
            }));
            setIsCropModalOpen(false);
        } catch (e) {
            console.error(e);
        }
    };

    const handleSubmit = (event) => {
        const nextLocalErrors = {};

        if (isEnrollment) {
            if (isBlankString(data.first_name)) {
                nextLocalErrors.first_name = "First name is required.";
            }

            if (isBlankString(data.last_name)) {
                nextLocalErrors.last_name = "Last name is required.";
            }

            if (isBlankString(data.email)) {
                nextLocalErrors.email = "Email is required.";
            } else if (!isValidEmail(data.email)) {
                nextLocalErrors.email = "Enter a valid email address.";
            }

            if (isBlankString(data.whatsapp_country_code)) {
                nextLocalErrors.whatsapp_country_code =
                    "WhatsApp country code is required.";
            }

            if (isBlankString(data.whatsapp_number)) {
                nextLocalErrors.whatsapp_number =
                    "WhatsApp number is required.";
            }

            if (!photoPreview && !data.profile_photo) {
                nextLocalErrors.profile_photo = "Profile photo is required.";
            }

            if (isBlankString(data.instagram)) {
                nextLocalErrors.instagram = "Instagram Username is required.";
            }

            if (isBlankString(data.country)) {
                nextLocalErrors.country = "Country is required.";
            }

            if (isBlankString(data.birth_date)) {
                nextLocalErrors.birth_date = "Birth date is required.";
            }

            if (isBlankString(data.gender)) {
                nextLocalErrors.gender = "Gender is required.";
            }

            if (isMasterClass) {
                if (isBlankString(data.tshirt_size)) {
                    nextLocalErrors.tshirt_size = "T-shirt size is required.";
                }

                if (isBlankString(data.favorite_song)) {
                    nextLocalErrors.favorite_song =
                        "Favorite song is required.";
                }

                if (isBlankString(data.emergency_contact_name)) {
                    nextLocalErrors.emergency_contact_name =
                        "Emergency contact name is required.";
                }

                if (isBlankString(data.emergency_contact_relationship)) {
                    nextLocalErrors.emergency_contact_relationship =
                        "Relationship is required.";
                }

                if (isBlankString(data.emergency_contact_country_code)) {
                    nextLocalErrors.emergency_contact_country_code =
                        "Emergency WhatsApp country code is required.";
                }

                if (isBlankString(data.emergency_contact_number)) {
                    nextLocalErrors.emergency_contact_number =
                        "Emergency WhatsApp number is required.";
                }
            }

            if (isBlankString(data.practicing_yoga_for)) {
                nextLocalErrors.practicing_yoga_for =
                    "Current yoga experience is required.";
            }

            if (
                !Array.isArray(data.yoga_sequence_experience) ||
                data.yoga_sequence_experience.length === 0
            ) {
                nextLocalErrors.yoga_sequence_experience =
                    "Select at least one yoga sequence experience.";
            }

            if (isBlankString(data.hours_per_week)) {
                nextLocalErrors.hours_per_week = "Hours per week is required.";
            }

            if (isBlankString(data.current_fitness_level)) {
                nextLocalErrors.current_fitness_level =
                    "Current fitness level is required.";
            }

            if (isBlankString(data.flexibility_rating)) {
                nextLocalErrors.flexibility_rating =
                    "Flexibility rating is required.";
            }

            if (isBlankString(data.motivation)) {
                nextLocalErrors.motivation = "Motivation is required.";
            }

            if (isBlankString(data.why_yogafx)) {
                nextLocalErrors.why_yogafx =
                    "Please tell us why you chose YogaFX.";
            }

            if (
                !Array.isArray(data.how_did_you_find_us) ||
                data.how_did_you_find_us.length === 0
            ) {
                nextLocalErrors.how_did_you_find_us =
                    "Select at least one discovery source.";
            }

            if (isMasterClass) {
                if (isBlankString(data.has_medical_issues)) {
                    nextLocalErrors.has_medical_issues =
                        "Please select Yes or No.";
                }

                if (
                    data.has_medical_issues === "yes" &&
                    isBlankString(data.medical_issues_details)
                ) {
                    nextLocalErrors.medical_issues_details =
                        "Brief medical details are required.";
                }

                if (isBlankString(data.is_taking_medication)) {
                    nextLocalErrors.is_taking_medication =
                        "Please select Yes or No.";
                }

                if (
                    data.is_taking_medication === "yes" &&
                    isBlankString(data.medication_details)
                ) {
                    nextLocalErrors.medication_details =
                        "Brief medication details are required.";
                }
            }

            if (!data.terms_accepted) {
                nextLocalErrors.terms_accepted =
                    "Please agree to the terms first.";
            }

            if (!data.recaptcha_confirmed) {
                nextLocalErrors.recaptcha_confirmed =
                    "Please confirm the reCAPTCHA checkbox.";
            }
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
                <DialogContent
                    className={theme.dialogContentClassName}
                    style={{ fontFamily: FONT_FAMILY }}
                >
                    <DialogHeader>
                        <DialogTitle
                            className={theme.dialogTitleClassName}
                            style={{ fontFamily: FONT_FAMILY }}
                        >
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
                        <label
                            className={theme.dialogZoomLabelClassName}
                            style={{ fontFamily: FONT_FAMILY }}
                        >
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

                    <DialogFooter className={theme.dialogFooterClassName}>
                        <Button
                            variant="outline"
                            onClick={() => setIsCropModalOpen(false)}
                            style={{
                                fontFamily: FONT_FAMILY,
                                fontSize: "14px",
                                fontWeight: 500,
                            }}
                            className={theme.dialogCancelButtonClassName}
                        >
                            Cancel
                        </Button>
                        <Button
                            onClick={handleSaveCrop}
                            style={{
                                fontFamily: FONT_FAMILY,
                                fontSize: "14px",
                                fontWeight: 500,
                            }}
                            className={theme.dialogSaveButtonClassName}
                        >
                            Crop & Save
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <form
                onSubmit={handleSubmit}
                className="space-y-6"
                style={{ fontFamily: FONT_FAMILY }}
            >
                {isEnrollment && (backendErrorKey || localErrorKey) ? (
                    <div
                        ref={errorSummaryRef}
                        className="rounded-[5px] border border-[#DB202C]/60 bg-[#DB202C]/10 px-[10px] py-[8px] text-sm text-white"
                        style={{ fontFamily: FONT_FAMILY }}
                    >
                        {errorSummaryMessage}
                    </div>
                ) : null}

                {/* {isEnrollment ? (
                    <div
                        className="rounded-[5px] border border-amber-300/35 bg-amber-500/10 px-[10px] py-[8px] text-sm text-white/90"
                        style={{ fontFamily: FONT_FAMILY }}
                    >
                        All enrollment fields are required, including your
                        profile photo and Instagram.
                    </div>
                ) : null} */}

                <section className={sectionClassName}>
                    <div
                        className={
                            isEnrollment
                                ? "mb-6 border-t-[4px] border-[#DB202C] pt-4"
                                : "mb-6"
                        }
                    >
                        <h3
                            className={titleClassName}
                            style={{ fontFamily: FONT_FAMILY }}
                        >
                            Your Details
                        </h3>
                    </div>

                    <div className="grid gap-6 md:grid-cols-2">
                        <div>
                            <InputLabel
                                htmlFor="first_name"
                                value="First Name"
                                className={theme.labelClassName}
                                style={{ fontFamily: FONT_FAMILY }}
                            />
                            <TextInput
                                id="first_name"
                                className={`mt-2 block w-full ${inputClassName} ${errors.first_name ? "border-red-500 focus:border-red-500 focus:ring-red-500" : ""}`}
                                style={{ fontFamily: FONT_FAMILY }}
                                value={data.first_name}
                                onChange={(event) =>
                                    setData("first_name", event.target.value)
                                }
                            />
                            <InputError
                                message={firstError(formErrors, "first_name")}
                                className={`${theme.errorClassName} mt-2`}
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="last_name"
                                value="Last Name"
                                className={theme.labelClassName}
                                style={{ fontFamily: FONT_FAMILY }}
                            />
                            <TextInput
                                id="last_name"
                                className={`mt-2 block w-full ${inputClassName}`}
                                style={{ fontFamily: FONT_FAMILY }}
                                value={data.last_name}
                                onChange={(event) =>
                                    setData("last_name", event.target.value)
                                }
                            />
                            <InputError
                                message={firstError(formErrors, "last_name")}
                                className={`${theme.errorClassName} mt-2`}
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="email"
                                value="Email"
                                className={theme.labelClassName}
                                style={{ fontFamily: FONT_FAMILY }}
                            />
                            <TextInput
                                id="email"
                                type="email"
                                className={`mt-2 block w-full ${inputClassName}`}
                                style={{ fontFamily: FONT_FAMILY }}
                                value={data.email}
                                onChange={(event) =>
                                    setData("email", event.target.value)
                                }
                            />
                            <InputError
                                message={firstError(formErrors, "email")}
                                className={`${theme.errorClassName} mt-2`}
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="whatsapp_number"
                                value="WhatsApp"
                                className={theme.labelClassName}
                                style={{ fontFamily: FONT_FAMILY }}
                            />
                            <div
                                className={`mt-2 grid items-start gap-3 ${
                                    isEnrollment
                                        ? "grid-cols-[112px_minmax(0,1fr)] sm:grid-cols-[120px_minmax(0,1fr)] md:grid-cols-[128px_minmax(0,1fr)]"
                                        : "grid-cols-[112px_minmax(0,1fr)] sm:grid-cols-[128px_minmax(0,1fr)] md:grid-cols-[136px_minmax(0,1fr)]"
                                }`}
                            >
                                <FlagOptionSelect
                                    id="whatsapp_country_code"
                                    value={data.whatsapp_country_code ?? "+62"}
                                    selectedOption={selectedPhoneCountryOption}
                                    options={phoneCountryCodeOptions}
                                    onChange={(option) =>
                                        setData(
                                            "whatsapp_country_code",
                                            option.value,
                                        )
                                    }
                                    displayMode="phone-code"
                                    searchPlaceholder="Search phone code or country"
                                    buttonClassName={theme.selectClassName}
                                    buttonTextClassName={
                                        flagSelectButtonTextClassName
                                    }
                                    placeholderClassName={
                                        flagSelectPlaceholderClassName
                                    }
                                    panelClassName={flagSelectPanelClassName}
                                    optionClassName="px-4 py-3 text-sm"
                                    optionActiveClassName={
                                        flagSelectActiveClassName
                                    }
                                    optionSelectedClassName="text-[#DB202C]"
                                    optionTextClassName="text-sm font-normal"
                                    chevronClassName={
                                        flagSelectChevronClassName
                                    }
                                    fallbackClassName={
                                        flagSelectFallbackClassName
                                    }
                                />
                                <TextInput
                                    id="whatsapp_number"
                                    className={`block w-full min-w-0 ${inputClassName}`}
                                    style={{ fontFamily: FONT_FAMILY }}
                                    value={data.whatsapp_number ?? ""}
                                    onChange={(event) =>
                                        setData(
                                            "whatsapp_number",
                                            normalizePhoneNumberInput(
                                                event.target.value,
                                            ),
                                        )
                                    }
                                    placeholder="81233456788"
                                />
                            </div>
                            <InputError
                                message={
                                    firstError(formErrors, "whatsapp_number") ??
                                    firstError(
                                        formErrors,
                                        "whatsapp_country_code",
                                    ) ??
                                    firstError(formErrors, "whatsapp")
                                }
                                className={`${theme.errorClassName} mt-2`}
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="instagram"
                                value="Instagram Username"
                                className={theme.labelClassName}
                                style={{ fontFamily: FONT_FAMILY }}
                            />
                            <TextInput
                                id="instagram"
                                className={`mt-2 block w-full ${inputClassName}`}
                                style={{ fontFamily: FONT_FAMILY }}
                                value={data.instagram ?? ""}
                                onChange={(event) =>
                                    setData("instagram", event.target.value)
                                }
                            />
                            <InputError
                                message={firstError(formErrors, "instagram")}
                                className={`${theme.errorClassName} mt-2`}
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="country"
                                value="Country"
                                className={theme.labelWithSpacingClassName}
                                style={{ fontFamily: FONT_FAMILY }}
                            />
                            <FlagOptionSelect
                                id="country"
                                value={data.country}
                                selectedOption={selectedCountryOption}
                                options={countryOptions}
                                onChange={(option) =>
                                    setData("country", option.value)
                                }
                                placeholder="Select a country"
                                buttonClassName={theme.selectClassName}
                                buttonTextClassName={
                                    flagSelectButtonTextClassName
                                }
                                placeholderClassName={
                                    flagSelectPlaceholderClassName
                                }
                                panelClassName={flagSelectPanelClassName}
                                optionClassName="px-4 py-3 text-sm"
                                optionActiveClassName={
                                    flagSelectActiveClassName
                                }
                                optionSelectedClassName="text-[#DB202C]"
                                optionTextClassName="text-sm font-normal"
                                chevronClassName={flagSelectChevronClassName}
                                fallbackClassName={flagSelectFallbackClassName}
                            />
                            <InputError
                                message={firstError(formErrors, "country")}
                                className={`${theme.errorClassName} mt-2`}
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="birth_date"
                                value="Birth Date"
                                className={theme.labelClassName}
                                style={{ fontFamily: FONT_FAMILY }}
                            />
                            <div className="relative mt-2">
                                <input
                                    ref={birthDateInputRef}
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
                                    style={{
                                        ...theme.dateInputStyle,
                                        fontFamily: FONT_FAMILY,
                                    }}
                                    className={theme.dateInputClassName}
                                />
                                <button
                                    type="button"
                                    onClick={() =>
                                        openNativeDatePicker(
                                            birthDateInputRef.current,
                                        )
                                    }
                                    className={theme.dateIconClassName}
                                    aria-label="Open birth date calendar"
                                >
                                    <CalendarDays className="size-5" />
                                </button>
                            </div>
                            <InputError
                                message={firstError(formErrors, "birth_date")}
                                className={`${theme.errorClassName} mt-2`}
                            />
                        </div>

                        <div>
                            <ChoiceGrid
                                id="gender"
                                label="Gender"
                                value={data.gender}
                                error={firstError(formErrors, "gender")}
                                options={GENDER_OPTIONS}
                                onChange={(value) => setData("gender", value)}
                                theme={theme}
                                optionsGridClassName="grid grid-cols-2 gap-4"
                            />
                        </div>

                        {/* Certificate Photo Section */}
                        <div className="md:col-span-2 mt-2">
                            <InputLabel
                                htmlFor="profile_photo"
                                value="Please Upload Your Preferred Certificate Picture"
                                className={theme.labelClassName}
                                style={{
                                    fontFamily: FONT_FAMILY,
                                    fontSize: "14px",
                                    fontWeight: 600,
                                }}
                            />

                            <div className="mt-4 flex flex-row gap-4 items-center">
                                {/* Area Preview Berbentuk Lonjong (Oval) */}
                                <div
                                    className={theme.photoPreviewFrameClassName}
                                >
                                    {photoPreview ? (
                                        <img
                                            src={photoPreview}
                                            alt="Preferred certificate"
                                            className="h-full w-full object-cover"
                                        />
                                    ) : (
                                        <div
                                            className={
                                                theme.photoFallbackClassName
                                            }
                                        >
                                            <UploadCloud className="size-8 mb-2 text-[#DB202C]" />
                                        </div>
                                    )}
                                </div>

                                <div className="flex-1 space-y-4">
                                    <div>
                                        <p
                                            className={
                                                theme.photoHelperClassName
                                            }
                                            style={{
                                                fontFamily: FONT_FAMILY,
                                                fontSize: "12px",
                                                fontWeight: 700,
                                            }}
                                        >
                                            Please Upload Screenshot Picture.
                                            File size 10MB or Less Please. Don't
                                            Worry This Pic Can Be Changed Later
                                            if necessary
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
                                        style={{
                                            fontFamily: FONT_FAMILY,
                                            fontSize: "14px",
                                            fontWeight: 500,
                                        }}
                                        className={theme.uploadButtonClassName}
                                    >
                                        Choose Photo
                                    </Button>

                                    <InputError
                                        message={firstError(
                                            formErrors,
                                            "profile_photo",
                                        )}
                                        className={theme.errorClassName}
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {isMasterClass ? (
                    <section className={sectionClassName}>
                        <div className={theme.sectionDividerClassName}>
                            <h3
                                className={titleClassName}
                                style={{ fontFamily: FONT_FAMILY }}
                            >
                                Emergency Contact
                            </h3>
                        </div>

                        <div className="grid gap-6 md:grid-cols-2">
                            <div>
                                <InputLabel
                                    htmlFor="emergency_contact_name"
                                    value="Emergency Contact Name *"
                                    className={theme.labelClassName}
                                    style={{ fontFamily: FONT_FAMILY }}
                                />

                                <TextInput
                                    id="emergency_contact_name"
                                    className={`mt-2 block w-full ${inputClassName}`}
                                    style={{ fontFamily: FONT_FAMILY }}
                                    value={data.emergency_contact_name ?? ""}
                                    onChange={(event) =>
                                        setData(
                                            "emergency_contact_name",
                                            event.target.value,
                                        )
                                    }
                                />

                                <InputError
                                    message={firstError(
                                        formErrors,
                                        "emergency_contact_name",
                                    )}
                                    className={`${theme.errorClassName} mt-2`}
                                />
                            </div>

                            <div>
                                <InputLabel
                                    htmlFor="emergency_contact_relationship"
                                    value="Relationship *"
                                    className={theme.labelClassName}
                                    style={{ fontFamily: FONT_FAMILY }}
                                />

                                <TextInput
                                    id="emergency_contact_relationship"
                                    className={`mt-2 block w-full ${inputClassName}`}
                                    style={{ fontFamily: FONT_FAMILY }}
                                    value={
                                        data.emergency_contact_relationship ??
                                        ""
                                    }
                                    onChange={(event) =>
                                        setData(
                                            "emergency_contact_relationship",
                                            event.target.value,
                                        )
                                    }
                                />

                                <InputError
                                    message={firstError(
                                        formErrors,
                                        "emergency_contact_relationship",
                                    )}
                                    className={`${theme.errorClassName} mt-2`}
                                />
                            </div>

                            <div className="md:col-span-2">
                                <InputLabel
                                    htmlFor="emergency_contact_number"
                                    value="WhatsApp Number *"
                                    className={theme.labelClassName}
                                    style={{ fontFamily: FONT_FAMILY }}
                                />

                                <div className="mt-2 grid grid-cols-[112px_minmax(0,1fr)] items-start gap-3 sm:grid-cols-[128px_minmax(0,1fr)] md:grid-cols-[136px_minmax(0,1fr)]">
                                    <FlagOptionSelect
                                        id="emergency_contact_country_code"
                                        value={
                                            data.emergency_contact_country_code ??
                                            "+62"
                                        }
                                        selectedOption={
                                            selectedEmergencyPhoneCountryOption
                                        }
                                        options={phoneCountryCodeOptions}
                                        onChange={(option) =>
                                            setData(
                                                "emergency_contact_country_code",
                                                option.value,
                                            )
                                        }
                                        displayMode="phone-code"
                                        searchPlaceholder="Search phone code or country"
                                        buttonClassName={theme.selectClassName}
                                        buttonTextClassName={
                                            flagSelectButtonTextClassName
                                        }
                                        placeholderClassName={
                                            flagSelectPlaceholderClassName
                                        }
                                        panelClassName={
                                            flagSelectPanelClassName
                                        }
                                        optionClassName="px-4 py-3 text-sm"
                                        optionActiveClassName={
                                            flagSelectActiveClassName
                                        }
                                        optionSelectedClassName="text-[#DB202C]"
                                        optionTextClassName="text-sm font-normal"
                                        chevronClassName={
                                            flagSelectChevronClassName
                                        }
                                        fallbackClassName={
                                            flagSelectFallbackClassName
                                        }
                                    />

                                    <TextInput
                                        id="emergency_contact_number"
                                        className={`block w-full min-w-0 ${inputClassName}`}
                                        style={{
                                            fontFamily: FONT_FAMILY,
                                        }}
                                        value={
                                            data.emergency_contact_number ?? ""
                                        }
                                        onChange={(event) =>
                                            setData(
                                                "emergency_contact_number",
                                                normalizePhoneNumberInput(
                                                    event.target.value,
                                                ),
                                            )
                                        }
                                        placeholder="81233456788"
                                    />
                                </div>

                                <InputError
                                    message={
                                        firstError(
                                            formErrors,
                                            "emergency_contact_number",
                                        ) ??
                                        firstError(
                                            formErrors,
                                            "emergency_contact_country_code",
                                        ) ??
                                        firstError(
                                            formErrors,
                                            "emergency_contact_whatsapp",
                                        )
                                    }
                                    className={`${theme.errorClassName} mt-2`}
                                />
                            </div>
                        </div>
                    </section>
                ) : null}

                <section className={sectionClassName}>
                    <div className={theme.sectionDividerClassName}>
                        <h3
                            className={titleClassName}
                            style={{ fontFamily: FONT_FAMILY }}
                        >
                            Current Yoga Experience
                        </h3>
                    </div>

                    <div className="space-y-10">
                        <ChoiceGrid
                            id="practicing_yoga_for"
                            label="Practicing Yoga For (Years & Months)"
                            value={data.practicing_yoga_for}
                            error={firstError(
                                formErrors,
                                "practicing_yoga_for",
                            )}
                            options={PRACTICING_OPTIONS}
                            onChange={(value) =>
                                setData("practicing_yoga_for", value)
                            }
                            theme={theme}
                        />

                        <ChoiceGrid
                            id="yoga_sequence_experience"
                            label="Yoga Sequence Experience"
                            value={data.yoga_sequence_experience ?? []}
                            error={firstError(
                                formErrors,
                                "yoga_sequence_experience",
                            )}
                            options={SEQUENCE_OPTIONS}
                            onChange={(value) =>
                                setData("yoga_sequence_experience", value)
                            }
                            multiple={true}
                            theme={theme}
                        />

                        <ChoiceGrid
                            id="hours_per_week"
                            label="How Many Hours P/Week Practicing Yoga?"
                            value={data.hours_per_week}
                            error={firstError(formErrors, "hours_per_week")}
                            options={HOURS_OPTIONS}
                            onChange={(value) =>
                                setData("hours_per_week", value)
                            }
                            theme={theme}
                        />

                        <ChoiceGrid
                            id="current_fitness_level"
                            label="Your Current Fitness Level"
                            value={data.current_fitness_level}
                            error={firstError(
                                formErrors,
                                "current_fitness_level",
                            )}
                            options={SIMPLE_LEVEL_OPTIONS}
                            onChange={(value) =>
                                setData("current_fitness_level", value)
                            }
                            theme={theme}
                            optionsGridClassName="grid grid-cols-3 gap-3 sm:gap-4"
                        />

                        <ChoiceGrid
                            id="flexibility_rating"
                            label="How would you rate your flexibility"
                            value={data.flexibility_rating}
                            error={firstError(formErrors, "flexibility_rating")}
                            options={SIMPLE_LEVEL_OPTIONS}
                            onChange={(value) =>
                                setData("flexibility_rating", value)
                            }
                            theme={theme}
                            optionsGridClassName="grid grid-cols-3 gap-3 sm:gap-4"
                        />
                    </div>
                </section>

                <section className={sectionClassName}>
                    <div className={theme.sectionDividerClassName}>
                        <h3
                            className={titleClassName}
                            style={{ fontFamily: FONT_FAMILY }}
                        >
                            What is Your Motivation In Becoming A Yoga Teacher?
                        </h3>
                    </div>

                    <TextAreaField
                        id="motivation"
                        label="In 50 Words or Less, Please Share With Us *"
                        value={data.motivation}
                        onChange={(value) => setData("motivation", value)}
                        error={firstError(formErrors, "motivation")}
                        helper={`Maximum of 50 words. Currently Used: ${wordsCount(
                            data.motivation,
                        )} words.`}
                        theme={theme}
                    />
                </section>

                {isMasterClass ? (
                    <section className={sectionClassName}>
                        <div className={theme.sectionDividerClassName}>
                            <h3
                                className={titleClassName}
                                style={{ fontFamily: FONT_FAMILY }}
                            >
                                Medical History
                            </h3>
                        </div>

                        <div className="space-y-8">
                            <ChoiceGrid
                                id="has_medical_issues"
                                label="Any Medical Existing Issues? *"
                                value={data.has_medical_issues ?? ""}
                                error={firstError(
                                    formErrors,
                                    "has_medical_issues",
                                )}
                                options={YES_NO_OPTIONS}
                                onChange={(value) => {
                                    setData("has_medical_issues", value);

                                    if (value === "no") {
                                        setData("medical_issues_details", "");
                                    }
                                }}
                                theme={theme}
                                optionsGridClassName="grid grid-cols-2 gap-4"
                            />

                            {data.has_medical_issues === "yes" ? (
                                <TextAreaField
                                    id="medical_issues_details"
                                    label="Brief Details Please *"
                                    value={data.medical_issues_details ?? ""}
                                    onChange={(value) =>
                                        setData("medical_issues_details", value)
                                    }
                                    error={firstError(
                                        formErrors,
                                        "medical_issues_details",
                                    )}
                                    theme={theme}
                                />
                            ) : null}

                            <ChoiceGrid
                                id="is_taking_medication"
                                label="Are You Taking Any Medication? *"
                                value={data.is_taking_medication ?? ""}
                                error={firstError(
                                    formErrors,
                                    "is_taking_medication",
                                )}
                                options={YES_NO_OPTIONS}
                                onChange={(value) => {
                                    setData("is_taking_medication", value);

                                    if (value === "no") {
                                        setData("medication_details", "");
                                    }
                                }}
                                theme={theme}
                                optionsGridClassName="grid grid-cols-2 gap-4"
                            />

                            {data.is_taking_medication === "yes" ? (
                                <TextAreaField
                                    id="medication_details"
                                    label="Brief Details Please *"
                                    value={data.medication_details ?? ""}
                                    onChange={(value) =>
                                        setData("medication_details", value)
                                    }
                                    error={firstError(
                                        formErrors,
                                        "medication_details",
                                    )}
                                    theme={theme}
                                />
                            ) : null}
                        </div>
                    </section>
                ) : null}

                <section className={sectionClassName}>
                    <div className={theme.sectionDividerClassName}>
                        <h3
                            className={titleClassName}
                            style={{ fontFamily: FONT_FAMILY }}
                        >
                            Please Let Us Know Why You Chose YogaFX
                        </h3>
                    </div>

                    <div className="space-y-8">
                        <TextAreaField
                            id="why_yogafx"
                            label="In 50 Words or Less, Please Share With Us *"
                            value={data.why_yogafx}
                            onChange={(value) => setData("why_yogafx", value)}
                            error={firstError(formErrors, "why_yogafx")}
                            helper={`Maximum of 50 words. Currently Used: ${wordsCount(
                                data.why_yogafx,
                            )} words.`}
                            theme={theme}
                        />

                        {isMasterClass ? (
                            <>
                                <ChoiceGrid
                                    id="tshirt_size"
                                    label="T-Shirt Size *"
                                    value={data.tshirt_size ?? ""}
                                    error={firstError(
                                        formErrors,
                                        "tshirt_size",
                                    )}
                                    options={TSHIRT_SIZE_OPTIONS}
                                    onChange={(value) =>
                                        setData("tshirt_size", value)
                                    }
                                    theme={theme}
                                    optionsGridClassName="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4"
                                />

                                <div>
                                    <InputLabel
                                        htmlFor="favorite_song"
                                        value="Favorite Song *"
                                        className={
                                            theme.labelWithSpacingClassName
                                        }
                                        style={{ fontFamily: FONT_FAMILY }}
                                    />
                                    <TextInput
                                        id="favorite_song"
                                        className={`block w-full ${inputClassName}`}
                                        style={{ fontFamily: FONT_FAMILY }}
                                        value={data.favorite_song ?? ""}
                                        onChange={(event) =>
                                            setData(
                                                "favorite_song",
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Enter your favorite song"
                                    />
                                    <InputError
                                        message={firstError(
                                            formErrors,
                                            "favorite_song",
                                        )}
                                        className={`${theme.errorClassName} mt-2`}
                                    />
                                </div>
                            </>
                        ) : null}

                        <ChoiceGrid
                            id="how_did_you_find_us"
                            label="Please Share How Did You Find Us"
                            value={data.how_did_you_find_us ?? []}
                            error={firstError(
                                formErrors,
                                "how_did_you_find_us",
                            )}
                            options={DISCOVERY_OPTIONS}
                            onChange={(value) =>
                                setData("how_did_you_find_us", value)
                            }
                            multiple={true}
                            theme={theme}
                        />
                    </div>
                </section>

                {isEnrollment ? (
                    <section className={sectionClassName}>
                        <div className={theme.sectionDividerClassName}></div>

                        <div className="space-y-6">
                            <div className="group relative">
                                <label
                                    className={[
                                        "flex cursor-pointer items-start gap-4 rounded-[5px] border px-[10px] py-[8px] text-white transition",
                                        data.terms_accepted
                                            ? "border-[#DB202C] bg-[#DB202C]/12"
                                            : "border-2 border-white/70 bg-black/35 hover:border-white hover:bg-[#DB202C]/10",
                                    ].join(" ")}
                                >
                                    <input
                                        id="terms_accepted"
                                        type="checkbox"
                                        checked={Boolean(data.terms_accepted)}
                                        onChange={(event) =>
                                            setData(
                                                "terms_accepted",
                                                event.target.checked,
                                            )
                                        }
                                        aria-describedby="terms-accepted-tooltip"
                                        className="mt-1 size-5 rounded border-white bg-transparent accent-[#DB202C] focus:ring-[#DB202C]"
                                    />
                                    <span
                                        className="text-sm font-normal"
                                        style={{ fontFamily: FONT_FAMILY }}
                                    >
                                        Yes, I agree with Term & Conditions
                                    </span>
                                </label>

                                <div
                                    id="terms-accepted-tooltip"
                                    role="tooltip"
                                    className="
                                        pointer-events-none
                                        absolute
                                        left-full
                                        top-1/2
                                        z-50
                                        ml-3
                                        hidden
                                        -translate-y-1/2
                                        w-[min(340px,calc(100vw-2rem))]
                                        rounded-[2px]
                                        border
                                        border-[#DB202C]
                                        bg-[#f3f3f3]
                                        px-3
                                        py-3
                                        text-left
                                        text-sm
                                        font-normal
                                        leading-[1.35]
                                        text-[#3f3f3f]
                                        shadow-[0_16px_40px_rgba(0,0,0,0.35)]
                                        group-hover:block
                                        group-focus-within:block
                                    "
                                    style={{ fontFamily: FONT_FAMILY }}
                                >
                                    <span
                                        aria-hidden="true"
                                        className="absolute right-full top-1/2 -translate-y-1/2 border-y-[8px] border-r-[10px] border-y-transparent border-r-[#DB202C]"
                                    />
                                    <span
                                        aria-hidden="true"
                                        className="absolute right-[calc(100%-1px)] top-1/2 -translate-y-1/2 border-y-[7px] border-r-[9px] border-y-transparent border-r-[#f3f3f3]"
                                    />
                                    <strong>Is it your belief</strong> that
                                    should you visit a{" "}
                                    <strong>Registered Medical Doctor</strong>{" "}
                                    for a general health check, that the doctor
                                    would be able to{" "}
                                    <strong>
                                        certify you as a fit and healthy person
                                    </strong>{" "}
                                    capable of participating in a 19 day Yoga
                                    Teacher Training Course, with{" "}
                                    <strong>
                                        up to a possible of 3 hours of Yoga
                                        practice
                                    </strong>{" "}
                                    per day, and there&apos;s no problem
                                    including you in pics and videos in possible
                                    future YogaFX promotions.
                                    <br />
                                    Thank you
                                </div>
                            </div>
                            <InputError
                                message={localErrors.terms_accepted}
                                className={theme.errorClassName}
                            />

                            <div className="grid gap-6 md:grid-cols-2">
                                <div className="rounded-[5px] border border-white bg-transparent px-[10px] py-[8px] text-white">
                                    <div
                                        className="text-sm font-medium text-white/70"
                                        style={{ fontFamily: FONT_FAMILY }}
                                    >
                                        Full Name
                                    </div>
                                    <div
                                        className="mt-2 text-sm font-normal"
                                        style={{ fontFamily: FONT_FAMILY }}
                                    >
                                        {[data.first_name, data.last_name]
                                            .filter(Boolean)
                                            .join(" ") || "Your name"}
                                    </div>
                                </div>
                                <div className="rounded-[5px] border border-white bg-transparent px-[10px] py-[8px] text-white">
                                    <div
                                        className="text-sm font-medium text-white/70"
                                        style={{ fontFamily: FONT_FAMILY }}
                                    >
                                        Today's Date
                                    </div>
                                    <div
                                        className="mt-2 text-sm font-normal"
                                        style={{ fontFamily: FONT_FAMILY }}
                                    >
                                        {todayLabel}
                                    </div>
                                </div>
                            </div>

                            <label
                                className={[
                                    "flex cursor-pointer items-start gap-4 rounded-[5px] border px-[10px] py-[8px] text-white transition",
                                    data.recaptcha_confirmed
                                        ? "border-[#DB202C] bg-[#DB202C]/12"
                                        : "border-2 border-white/70 bg-black/35 hover:border-white hover:bg-[#DB202C]/10",
                                ].join(" ")}
                            >
                                <input
                                    id="recaptcha_confirmed"
                                    type="checkbox"
                                    checked={Boolean(data.recaptcha_confirmed)}
                                    onChange={(event) =>
                                        setData(
                                            "recaptcha_confirmed",
                                            event.target.checked,
                                        )
                                    }
                                    className="mt-1 size-5 rounded border-white bg-transparent accent-[#DB202C] focus:ring-[#DB202C]"
                                />
                                <span
                                    className="text-sm font-normal"
                                    style={{ fontFamily: FONT_FAMILY }}
                                >
                                    I'm not a robot (reCAPTCHA)
                                </span>
                            </label>
                            <InputError
                                message={localErrors.recaptcha_confirmed}
                                className={theme.errorClassName}
                            />
                        </div>
                    </section>
                ) : null}

                <div className={theme.footerDividerClassName}>
                    <Button
                        type="submit"
                        disabled={processing}
                        style={{
                            fontFamily: FONT_FAMILY,
                        }}
                        className={theme.primaryButtonClassName}
                    >
                        {submitLabel}
                    </Button>
                </div>
            </form>
        </>
    );
}
