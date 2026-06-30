const COUNTRY_ISO2_MAP = {
    Argentina: "ar",
    Australia: "au",
    Austria: "at",
    Belgium: "be",
    Brazil: "br",
    Canada: "ca",
    China: "cn",
    Denmark: "dk",
    Egypt: "eg",
    Finland: "fi",
    France: "fr",
    Germany: "de",
    "Hong Kong": "hk",
    India: "in",
    Indonesia: "id",
    Ireland: "ie",
    Italy: "it",
    Japan: "jp",
    Malaysia: "my",
    Mexico: "mx",
    Netherlands: "nl",
    "New Zealand": "nz",
    Norway: "no",
    Philippines: "ph",
    Portugal: "pt",
    Qatar: "qa",
    "Saudi Arabia": "sa",
    Singapore: "sg",
    "South Africa": "za",
    "South Korea": "kr",
    Spain: "es",
    Sweden: "se",
    Switzerland: "ch",
    Taiwan: "tw",
    Thailand: "th",
    Turkey: "tr",
    "United Arab Emirates": "ae",
    "United Kingdom": "gb",
    "United States": "us",
    Vietnam: "vn",
};

function normalizeCountryLabel(label) {
    return String(label ?? "")
        .replace(/\s*\(.+\)\s*$/, "")
        .trim();
}

export function resolveCountryIso2(optionOrLabel, fallback = null) {
    if (!optionOrLabel) {
        return fallback;
    }

    if (typeof optionOrLabel === "string") {
        return COUNTRY_ISO2_MAP[normalizeCountryLabel(optionOrLabel)] ?? fallback;
    }

    const normalizedLabel = normalizeCountryLabel(optionOrLabel.label);

    return (
        optionOrLabel.iso2 ??
        COUNTRY_ISO2_MAP[optionOrLabel.value] ??
        COUNTRY_ISO2_MAP[normalizedLabel] ??
        COUNTRY_ISO2_MAP[optionOrLabel.label] ??
        fallback
    );
}

export function enrichCountryOption(option) {
    return {
        ...option,
        iso2: resolveCountryIso2(option),
    };
}

export function enrichCountryOptions(options) {
    return options.map(enrichCountryOption);
}

export function findCountryOptionByDialCode(
    options,
    dialCode,
    preferredCountry = null,
) {
    const normalizedCountry = normalizeCountryLabel(preferredCountry);

    if (normalizedCountry) {
        const preferredMatch = options.find(
            (option) =>
                option.value === dialCode &&
                normalizeCountryLabel(option.label).startsWith(normalizedCountry),
        );

        if (preferredMatch) {
            return preferredMatch;
        }
    }

    return options.find((option) => option.value === dialCode) ?? null;
}
