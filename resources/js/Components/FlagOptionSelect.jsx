import {
    Listbox,
    ListboxButton,
    ListboxOption,
    ListboxOptions,
} from "@headlessui/react";
import { Check, ChevronDown, Globe } from "lucide-react";
import { resolveCountryIso2 } from "@/lib/countryFlags";
import { useEffect, useMemo, useState } from "react";

function normalizeOptionValue(value) {
    return String(value ?? "").trim();
}

function FlagVisual({ option, fallbackClassName = "" }) {
    const iso2 = resolveCountryIso2(option);

    if (!iso2) {
        return (
            <span
                className={[
                    "inline-flex h-4 w-5 shrink-0 items-center justify-center rounded-[3px] border border-current/20",
                    fallbackClassName,
                ].join(" ")}
                aria-hidden="true"
            >
                <Globe className="size-3" />
            </span>
        );
    }

    return (
        <span
            className={`fi fi-${iso2} shrink-0 rounded-[3px] shadow-sm`}
            aria-hidden="true"
        />
    );
}

function getOptionSearchTerms(option) {
    return [
        option.label,
        option.value,
        String(option.label ?? "")
            .replace(/\s*\(.+\)\s*$/, "")
            .trim(),
    ]
        .filter(Boolean)
        .map((entry) => String(entry).toLowerCase());
}

function formatOptionLabel(option, displayMode) {
    if (!option) {
        return "";
    }

    if (displayMode === "phone-code") {
        return String(option.value ?? "").trim();
    }

    return String(option.label ?? "").trim();
}

export default function FlagOptionSelect({
    id,
    value,
    selectedOption = null,
    options,
    onChange,
    placeholder = "Select an option",
    disabled = false,
    buttonClassName,
    buttonTextClassName,
    placeholderClassName,
    panelClassName,
    optionClassName,
    optionActiveClassName,
    optionSelectedClassName,
    optionTextClassName,
    chevronClassName,
    fallbackClassName,
    displayMode = "default",
    searchPlaceholder = "Search country or code",
}) {
    const [searchQuery, setSearchQuery] = useState("");
    const currentOption = useMemo(() => {
        const normalizedValue = normalizeOptionValue(value);

        if (
            selectedOption &&
            normalizeOptionValue(selectedOption.value) === normalizedValue
        ) {
            return selectedOption;
        }

        return (
            options.find(
                (option) =>
                    normalizeOptionValue(option.value) === normalizedValue,
            ) ?? null
        );
    }, [options, selectedOption, value]);
    const filteredOptions = useMemo(() => {
        const normalizedQuery = searchQuery.trim().toLowerCase();

        if (!normalizedQuery) {
            return options;
        }

        return options.filter((option) =>
            getOptionSearchTerms(option).some((entry) =>
                entry.includes(normalizedQuery),
            ),
        );
    }, [options, searchQuery]);

    useEffect(() => {
        setSearchQuery("");
    }, [value]);

    return (
        <Listbox
            value={currentOption}
            by={(left, right) =>
                left?.value === right?.value && left?.label === right?.label
            }
            onChange={(option) => {
                if (option) {
                    onChange(option);
                }
            }}
            disabled={disabled}
        >
            <div className="relative">
                <ListboxButton
                    id={id}
                    className={[
                        "flex w-full items-center justify-between gap-3 text-left",
                        buttonClassName,
                    ].join(" ")}
                >
                    <span className="flex min-w-0 flex-1 items-center gap-2">
                        <FlagVisual
                            option={currentOption}
                            fallbackClassName={fallbackClassName}
                        />
                        <span
                            className={[
                                displayMode === "phone-code"
                                    ? "block shrink-0 whitespace-nowrap"
                                    : "block truncate",
                                currentOption
                                    ? buttonTextClassName
                                    : placeholderClassName,
                            ].join(" ")}
                        >
                            {currentOption
                                ? formatOptionLabel(currentOption, displayMode)
                                : placeholder}
                        </span>
                    </span>
                    <ChevronDown
                        className={["size-4 shrink-0", chevronClassName].join(
                            " ",
                        )}
                        aria-hidden="true"
                    />
                </ListboxButton>

                <ListboxOptions
                    anchor="bottom start"
                    className={[
                        "z-50 mt-2 w-[var(--button-width)] overflow-hidden rounded-[5px] border shadow-lg focus:outline-none",
                        panelClassName,
                    ].join(" ")}
                >
                    <div className="border-b border-current/10 p-2">
                        <input
                            type="text"
                            value={searchQuery}
                            onChange={(event) =>
                                setSearchQuery(event.target.value)
                            }
                            onKeyDown={(event) => {
                                event.stopPropagation();
                            }}
                            placeholder={searchPlaceholder}
                            className="h-10 w-full rounded-[5px] border border-current/10 bg-transparent px-3 text-sm outline-none placeholder:text-current/45"
                        />
                    </div>

                    <div
                        className="max-h-72 overflow-y-auto [&::-webkit-scrollbar]:hidden"
                        style={{
                            scrollbarWidth: "none",
                            msOverflowStyle: "none",
                        }}
                    >
                        {filteredOptions.length ? (
                            filteredOptions.map((option) => (
                                <ListboxOption
                                    key={`${option.value}-${option.label}`}
                                    value={option}
                                    className={({ focus, selected }) =>
                                        [
                                            "cursor-pointer list-none",
                                            optionClassName,
                                            focus ? optionActiveClassName : "",
                                            selected
                                                ? optionSelectedClassName
                                                : "",
                                        ].join(" ")
                                    }
                                >
                                    {({ selected }) => (
                                        <div className="flex items-center justify-between gap-3">
                                            <span className="flex min-w-0 flex-1 items-center gap-2">
                                                <FlagVisual
                                                    option={option}
                                                    fallbackClassName={
                                                        fallbackClassName
                                                    }
                                                />
                                                <span
                                                    className={[
                                                        displayMode ===
                                                        "phone-code"
                                                            ? "block shrink-0 whitespace-nowrap"
                                                            : "block truncate",
                                                        optionTextClassName,
                                                    ].join(" ")}
                                                >
                                                    {formatOptionLabel(
                                                        option,
                                                        displayMode,
                                                    )}
                                                </span>
                                            </span>
                                            {selected ? (
                                                <Check
                                                    className="size-4 shrink-0"
                                                    aria-hidden="true"
                                                />
                                            ) : null}
                                        </div>
                                    )}
                                </ListboxOption>
                            ))
                        ) : (
                            <div className="px-3 py-4 text-sm text-current/60">
                                No results found.
                            </div>
                        )}
                    </div>
                </ListboxOptions>
            </div>
        </Listbox>
    );
}
