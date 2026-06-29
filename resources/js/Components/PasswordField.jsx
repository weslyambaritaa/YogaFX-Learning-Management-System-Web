import { Input } from "@/Components/ui/input";
import { Eye, EyeOff } from "lucide-react";
import { useState } from "react";

export default function PasswordField({
    id,
    value,
    onChange,
    placeholder,
    autoComplete = "current-password",
    className = "",
    inputClassName = "",
    buttonClassName = "",
    ...props
}) {
    const [visible, setVisible] = useState(false);

    return (
        <div className={`relative ${className}`}>
            <Input
                id={id}
                type={visible ? "text" : "password"}
                value={value}
                onChange={onChange}
                placeholder={placeholder}
                autoComplete={autoComplete}
                className={`h-10 rounded-[5px] pr-11 ${inputClassName}`}
                {...props}
            />
            <button
                type="button"
                onClick={() => setVisible((current) => !current)}
                className={`absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 transition hover:text-[#DB202C] ${buttonClassName}`}
                aria-label={visible ? "Hide password" : "Show password"}
            >
                {visible ? (
                    <EyeOff className="size-4" />
                ) : (
                    <Eye className="size-4" />
                )}
            </button>
        </div>
    );
}
