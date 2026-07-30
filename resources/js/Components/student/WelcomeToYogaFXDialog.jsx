import { Button } from "@/Components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/Components/ui/dialog";

const STUDENT_LOGO_URL = "https://yogafx.b-cdn.net/content/Logo%20YogAFX.png";

export default function WelcomeToYogaFXDialog({ open, onOpenChange }) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                showCloseButton={false}
                overlayClassName="bg-black/70 backdrop-blur-sm"
                className="border border-white/10 bg-[#141110] p-0 text-white sm:max-w-md"
            >
                <div className="p-8 sm:p-10">
                    <DialogHeader className="items-center space-y-5 text-center">
                        <img
                            src={STUDENT_LOGO_URL}
                            alt="YogaFX"
                            className="h-24 w-auto object-contain sm:h-32"
                        />
                        <div className="space-y-2">
                            <DialogTitle className="text-2xl font-semibold text-white sm:text-3xl">
                                Welcome to YogaFX
                            </DialogTitle>
                        </div>
                    </DialogHeader>
                </div>
                <DialogFooter className="border-t border-white/10 bg-black/20 px-6 pb-6 pt-4 sm:justify-center">
                    <Button
                        type="button"
                        className="w-full sm:w-auto"
                        onClick={() => onOpenChange(false)}
                    >
                        Continue
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
