import InputError from "@/Components/InputError";
import FlagOptionSelect from "@/Components/FlagOptionSelect";
import InputLabel from "@/Components/InputLabel";
import TextInput from "@/Components/TextInput";
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

// Single source of truth for the font so it can't be silently
// overridden by an older font-family declared elsewhere in the tree.
const FONT_FAMILY = "'Montserrat', sans-serif";

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

            <div className="grid gap-4 sm:grid-cols-2">
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
                <p className={theme.helperClassName} style={{ fontFamily: FONT_FAMILY }}>
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
    const isScoreboard = variant === "scoreboard";
    const isEnrollment = mode === "enrollment";
    const isAdminMode = mode === "admin";
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
    const theme = isAdminMode
        ? {
              labelClassName: "text-sm font-medium text-slate-900",
              labelWithSpacingClassName:
                  "mb-2 text-sm font-medium text-slate-900",
              choiceDescriptionClassName:
                  "mt-1 text-sm font-normal text-slate-600",
              choiceCheckedClassName:
                  "border-[#DB202C] bg-[#DB202C] text-white shadow-[0_0_12px_rgba(219,32,44,0.18)]",
              choiceUncheckedClassName:
                  "border-slate-400 bg-white text-slate-700 hover:border-[#DB202C]/50 hover:bg-rose-50/50",
              choiceIndicatorCheckedClassName:
                  "border-[#DB202C] bg-[#DB202C] text-white",
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
              selectOptionClassName: "bg-white text-slate-900 text-sm font-normal",
              selectActiveColor: "#DB202C",
              selectPlaceholderColor: "#0f172a",
              textareaClassName:
                  "block w-full rounded-[5px] border border-slate-400 bg-white px-[10px] py-[8px] text-sm font-normal text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-[#DB202C] focus:ring-1 focus:ring-[#DB202C]",
              helperClassName: "mt-2 text-sm font-medium text-slate-500",
              sectionDividerClassName: "mb-6 border-b border-slate-200 pb-4",
              footerDividerClassName: "flex items-center border-t border-slate-200 pt-8",
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
              labelClassName: "text-[14px] font-medium text-white",
              labelWithSpacingClassName:
                  "mb-2 text-[14px] font-medium text-white",
              choiceDescriptionClassName:
                  "mt-1 text-[12px] font-normal text-white/70",
              choiceCheckedClassName:
                  "border-[#DB202C] bg-[#DB202C] text-white shadow-[0_0_12px_rgba(219,32,44,0.28)]",
              choiceUncheckedClassName:
                  "border-white/40 bg-transparent text-white/80 hover:border-white hover:bg-[#DB202C]/5",
              choiceIndicatorCheckedClassName:
                  "border-white bg-white text-[#DB202C]",
              choiceIndicatorUncheckedClassName:
                  "border-white bg-transparent text-transparent",
              errorClassName: "font-semibold text-[#ffb4a8]",
              titleClassName:
                  "mb-2 text-[22px] font-medium tracking-tight text-white",
              descriptionClassName:
                  "text-[12px] font-normal leading-6 text-white/70",
              inputClassName:
                  "!border-white bg-transparent text-white text-sm font-normal placeholder:text-white/30 focus:!border-white focus:ring-1 focus:ring-white/40",
              selectClassName:
                  "block w-full rounded-[5px] border border-white bg-transparent px-[10px] py-[8px] text-sm font-normal text-white shadow-sm focus:border-white focus:ring-1 focus:ring-white/40 [&::-webkit-calendar-picker-indicator]:invert",
              selectOptionClassName:
                  "text-sm font-normal text-black",
              selectActiveColor: "#DB202C",
              selectPlaceholderColor: "#FFFFFF",
              textareaClassName:
                  "block w-full rounded-[5px] border border-white bg-transparent px-[10px] py-[8px] text-sm font-normal text-white shadow-sm placeholder:text-white/30 focus:border-white focus:ring-1 focus:ring-white/40",
              helperClassName: "mt-2 text-sm font-semibold text-white/60",
              sectionDividerClassName: "mb-6 border-b border-white pb-4",
              footerDividerClassName: "flex items-center border-t border-white pt-8",
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
              dateInputClassName:
                  "block w-full appearance-none rounded-[5px] border border-white bg-transparent px-[10px] py-[8px] pr-12 text-sm font-normal text-white shadow-sm focus:border-white focus:ring-1 focus:ring-white/40 [&::-webkit-calendar-picker-indicator]:cursor-pointer [&::-webkit-calendar-picker-indicator]:opacity-0",
              dateIconClassName:
                  "absolute right-3 top-1/2 z-10 flex size-8 -translate-y-1/2 items-center justify-center rounded-full text-white/70 transition hover:bg-white/10 hover:text-white",
              primaryButtonClassName:
                  "rounded-[5px] bg-[#DB202C] px-[10px] py-[8px] text-[14px] font-medium text-white transition-all hover:-translate-y-0.5 hover:bg-[#c31c28]",
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
                            style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 500 }}
                            className={theme.dialogCancelButtonClassName}
                        >
                            Cancel
                        </Button>
                        <Button
                            onClick={handleSaveCrop}
                            style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 500 }}
                            className={theme.dialogSaveButtonClassName}
                        >
                            Crop & Save
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <form onSubmit={handleSubmit} className="space-y-12" style={{ fontFamily: FONT_FAMILY }}>
                <section className={sectionClassName}>
                    <div className="mb-6">
                        <h3 className={titleClassName} style={{ fontFamily: FONT_FAMILY }}>
                            Personal Information
                        </h3>
                        <p className={descriptionClassName} style={{ fontFamily: FONT_FAMILY }}>
                            Basic account details and your preferred certificate
                            picture.
                        </p>
                    </div>

                    <div className="grid gap-8 md:grid-cols-2">
                        <div>
                            <InputLabel
                                htmlFor="first_name"
                                value="First Name"
                                className={theme.labelClassName}
                                style={{ fontFamily: FONT_FAMILY }}
                            />
                            <TextInput
                                id="first_name"
                                className={`mt-2 block w-full rounded-[5px] py-[8px] px-[10px] ${inputClassName}`}
                                style={{ fontFamily: FONT_FAMILY }}
                                value={data.first_name}
                                onChange={(event) =>
                                    setData("first_name", event.target.value)
                                }
                                isFocused
                            />
                            <InputError
                                message={firstError(errors, "first_name")}
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
                                className={`mt-2 block w-full rounded-[5px] py-[8px] px-[10px] ${inputClassName}`}
                                style={{ fontFamily: FONT_FAMILY }}
                                value={data.last_name}
                                onChange={(event) =>
                                    setData("last_name", event.target.value)
                                }
                            />
                            <InputError
                                message={firstError(errors, "last_name")}
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
                                className={`mt-2 block w-full rounded-[5px] py-[8px] px-[10px] ${inputClassName}`}
                                style={{ fontFamily: FONT_FAMILY }}
                                value={data.email}
                                onChange={(event) =>
                                    setData("email", event.target.value)
                                }
                            />
                            <InputError
                                message={firstError(errors, "email")}
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
                            <div className="mt-2 grid gap-3 sm:grid-cols-[140px_minmax(0,1fr)]">
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
                                    buttonClassName={theme.selectClassName}
                                    buttonTextClassName="text-sm font-normal text-[#DB202C]"
                                    placeholderClassName={
                                        isAdminMode
                                            ? "text-sm font-normal text-slate-400"
                                            : "text-sm font-normal text-white/50"
                                    }
                                    panelClassName={
                                        isAdminMode
                                            ? "border-slate-200 bg-white text-slate-900"
                                            : "border-white/10 bg-[#161616] text-white"
                                    }
                                    optionClassName="px-3 py-2.5 text-sm"
                                    optionActiveClassName={
                                        isAdminMode
                                            ? "bg-rose-50"
                                            : "bg-white/10"
                                    }
                                    optionSelectedClassName="text-[#DB202C]"
                                    optionTextClassName="text-sm font-normal"
                                    chevronClassName={
                                        isAdminMode
                                            ? "text-slate-500"
                                            : "text-white/60"
                                    }
                                    fallbackClassName={
                                        isAdminMode
                                            ? "bg-slate-100 text-slate-500"
                                            : "bg-white/10 text-white/70"
                                    }
                                />
                                <TextInput
                                    id="whatsapp_number"
                                    className={`block w-full rounded-[5px] py-[8px] px-[10px] ${inputClassName}`}
                                    style={{ fontFamily: FONT_FAMILY }}
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
                                className={`${theme.errorClassName} mt-2`}
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

                            <div className="mt-4 flex flex-col sm:flex-row gap-6 items-start sm:items-center">
                                {/* Area Preview Berbentuk Lonjong (Oval) */}
                                <div className={theme.photoPreviewFrameClassName}>
                                    {photoPreview ? (
                                        <img
                                            src={photoPreview}
                                            alt="Preferred certificate"
                                            className="h-full w-full object-cover"
                                        />
                                    ) : (
                                        <div className={theme.photoFallbackClassName}>
                                            <UploadCloud className="size-8 mb-2 text-[#DB202C]" />
                                        </div>
                                    )}
                                </div>

                                <div className="flex-1 space-y-4">
                                    <div>
                                        <p
                                            className={theme.photoHelperClassName}
                                            style={{
                                                fontFamily: FONT_FAMILY,
                                                fontSize: "12px",
                                                fontWeight: 400,
                                            }}
                                        >
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
                                            errors,
                                            "profile_photo",
                                        )}
                                        className={theme.errorClassName}
                                    />
                                </div>
                            </div>
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="instagram"
                                value="Instagram (Optional)"
                                className={theme.labelClassName}
                                style={{ fontFamily: FONT_FAMILY }}
                            />
                            <TextInput
                                id="instagram"
                                className={`mt-2 block w-full rounded-[5px] py-[8px] px-[10px] ${inputClassName}`}
                                style={{ fontFamily: FONT_FAMILY }}
                                value={data.instagram ?? ""}
                                onChange={(event) =>
                                    setData("instagram", event.target.value)
                                }
                            />
                            <InputError
                                message={firstError(errors, "instagram")}
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
                                onChange={(option) => {
                                    setData("country", option.value);
                                    const matchedDialCode =
                                        phoneCountryCodeOptions.find((entry) =>
                                            entry.label.startsWith(
                                                `${option.label} (`,
                                            ),
                                        );
                                    if (matchedDialCode && !data.whatsapp_number) {
                                        setData(
                                            "whatsapp_country_code",
                                            matchedDialCode.value,
                                        );
                                    }
                                }}
                                placeholder="Select a country"
                                buttonClassName={theme.selectClassName}
                                buttonTextClassName="text-sm font-normal text-[#DB202C]"
                                placeholderClassName={
                                    isAdminMode
                                        ? "text-sm font-normal text-slate-400"
                                        : "text-sm font-normal text-white/50"
                                }
                                panelClassName={
                                    isAdminMode
                                        ? "border-slate-200 bg-white text-slate-900"
                                        : "border-white/10 bg-[#161616] text-white"
                                }
                                optionClassName="px-3 py-2.5 text-sm"
                                optionActiveClassName={
                                    isAdminMode
                                        ? "bg-rose-50"
                                        : "bg-white/10"
                                }
                                optionSelectedClassName="text-[#DB202C]"
                                optionTextClassName="text-sm font-normal"
                                chevronClassName={
                                    isAdminMode
                                        ? "text-slate-500"
                                        : "text-white/60"
                                }
                                fallbackClassName={
                                    isAdminMode
                                        ? "bg-slate-100 text-slate-500"
                                        : "bg-white/10 text-white/70"
                                }
                            />
                            <InputError
                                message={errors.country}
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
                                message={firstError(errors, "birth_date")}
                                className={`${theme.errorClassName} mt-2`}
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
                                theme={theme}
                            />
                        </div>
                    </div>
                </section>

                <section className={sectionClassName}>
                    <div className={theme.sectionDividerClassName}>
                        <h3 className={titleClassName} style={{ fontFamily: FONT_FAMILY }}>
                            Learning Background
                        </h3>
                        <p className={descriptionClassName} style={{ fontFamily: FONT_FAMILY }}>
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
                            theme={theme}
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
                            theme={theme}
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
                            theme={theme}
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
                            theme={theme}
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
                            theme={theme}
                        />
                    </div>
                </section>

                <section className={sectionClassName}>
                    <div className={theme.sectionDividerClassName}>
                        <h3 className={titleClassName} style={{ fontFamily: FONT_FAMILY }}>
                            Motivation
                        </h3>
                        <p className={descriptionClassName} style={{ fontFamily: FONT_FAMILY }}>
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
                            theme={theme}
                        />

                        <TextAreaField
                            id="why_yogafx"
                            label="Please Let Us Know Why You Chose YogaFX"
                            value={data.why_yogafx}
                            onChange={(value) => setData("why_yogafx", value)}
                            error={firstError(errors, "why_yogafx")}
                            helper={`${wordsCount(data.why_yogafx)}/50 words`}
                            theme={theme}
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
                            theme={theme}
                        />
                    </div>
                </section>

                {isEnrollment ? (
                    <section className={sectionClassName}>
                        <div className={theme.sectionDividerClassName}>
                            <h3 className={titleClassName} style={{ fontFamily: FONT_FAMILY }}>
                                Terms & Confirmation
                            </h3>
                            <p className={descriptionClassName} style={{ fontFamily: FONT_FAMILY }}>
                                Confirm your final enrollment details.
                            </p>
                        </div>

                        <div className="space-y-6">
                            <label
                                className={[
                                    "flex cursor-pointer items-start gap-4 rounded-[5px] border px-[10px] py-[8px] text-white transition",
                                    data.terms_accepted
                                        ? "border-[#DB202C] bg-[#DB202C]/12"
                                        : "border-white bg-transparent hover:bg-[#DB202C]/10",
                                ].join(" ")}
                            >
                                <input
                                    type="checkbox"
                                    checked={Boolean(data.terms_accepted)}
                                    onChange={(event) =>
                                        setData(
                                            "terms_accepted",
                                            event.target.checked,
                                        )
                                    }
                                    className="mt-1 size-5 rounded border-white bg-transparent accent-[#DB202C] focus:ring-[#DB202C]"
                                />
                                <span
                                    className="text-sm font-normal"
                                    style={{ fontFamily: FONT_FAMILY }}
                                >
                                    Yes, I agree with Term & Conditions
                                </span>
                            </label>
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
                                        Date
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
                                        : "border-white bg-transparent hover:bg-[#DB202C]/10",
                                ].join(" ")}
                            >
                                <input
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
                        style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 500 }}
                        className={theme.primaryButtonClassName}
                    >
                        {submitLabel}
                    </Button>
                </div>
            </form>
        </>
    );
}
