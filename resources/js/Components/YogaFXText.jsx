export default function YogaFXText({
    text,
    fxClassName = "text-[#DB202C]",
}) {
    const value = String(text ?? "");
    const parts = value.split(/(YogaFX)/g);

    return parts.map((part, index) => {
        if (part !== "YogaFX") {
            return part;
        }

        return (
            <span key={`yogafx-${index}`}>
                Yoga<span className={fxClassName}>FX</span>
            </span>
        );
    });
}