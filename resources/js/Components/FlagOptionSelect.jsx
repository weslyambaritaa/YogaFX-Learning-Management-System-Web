import {
    Listbox,
    ListboxButton,
    ListboxOption,
    ListboxOptions,
} from "@headlessui/react";
import { Check, ChevronDown, Globe } from "lucide-react";
import { resolveCountryIso2 } from "@/lib/countryFlags";

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
}) {
    const currentOption =
        selectedOption ?? options.find((option) => option.value === value) ?? null;

    return (
        <Listbox
            value={currentOption}
            by={(left, right) =>
                left?.value === right?.value && left?.label === right?.label
            }
            onChange={onChange}
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
                    <span className="flex min-w-0 items-center gap-3">
                        <FlagVisual
                            option={currentOption}
                            fallbackClassName={fallbackClassName}
                        />
                        <span
                            className={[
                                "block truncate",
                                currentOption
                                    ? buttonTextClassName
                                    : placeholderClassName,
                            ].join(" ")}
                        >
                            {currentOption ? currentOption.label : placeholder}
                        </span>
                    </span>
                    <ChevronDown
                        className={["size-4 shrink-0", chevronClassName].join(" ")}
                        aria-hidden="true"
                    />
                </ListboxButton>

                <ListboxOptions
                    anchor="bottom start"
                    className={[
                        "z-50 mt-2 max-h-72 w-[var(--button-width)] overflow-auto rounded-[5px] border shadow-lg focus:outline-none",
                        panelClassName,
                    ].join(" ")}
                >
                    {options.map((option) => (
                        <ListboxOption
                            key={`${option.value}-${option.label}`}
                            value={option}
                            className={({ focus, selected }) =>
                                [
                                    "cursor-pointer list-none",
                                    optionClassName,
                                    focus ? optionActiveClassName : "",
                                    selected ? optionSelectedClassName : "",
                                ].join(" ")
                            }
                        >
                            {({ selected }) => (
                                <div className="flex items-center justify-between gap-3">
                                    <span className="flex min-w-0 items-center gap-3">
                                        <FlagVisual
                                            option={option}
                                            fallbackClassName={fallbackClassName}
                                        />
                                        <span
                                            className={[
                                                "block truncate",
                                                optionTextClassName,
                                            ].join(" ")}
                                        >
                                            {option.label}
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
                    ))}
                </ListboxOptions>
            </div>
        </Listbox>
    );
}
