import { Button } from "@/Components/ui/button";
import ImpersonationBanner from "@/Components/ImpersonationBanner";
import StudentAppDownloadDialog from "@/Components/student/StudentAppDownloadDialog";
import TransientStatusBanner from "@/Components/TransientStatusBanner";
import useScrollDirection from "@/lib/useScrollDirection";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import { Separator } from "@/Components/ui/separator";
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from "@/Components/ui/sheet";
import { Link, router, usePage } from "@inertiajs/react";
import {
    BookMarked,
    BookOpen,
    BookOpenCheck,
    Building2,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ClipboardList,
    FileSpreadsheet,
    FileText,
    House,
    LayoutDashboard,
    Mail,
    Menu,
    MessageSquareText,
    PlaySquare,
    Smartphone,
    Shield,
    UserRound,
    Wallet,
} from "lucide-react";
import { useEffect, useState } from "react";

const ADMIN_SIDEBAR_STORAGE_KEY = "yogafx-admin-sidebar-collapsed";
const ADMIN_SIDEBAR_GROUPS_STORAGE_KEY = "yogafx-admin-sidebar-groups-open";
const ADMIN_DESKTOP_BREAKPOINT = "(min-width: 1024px)";
const STUDENT_DESKTOP_BREAKPOINT = "(min-width: 768px)";

const adminNavigationItems = [
    {
        label: "Dashboard",
        route: "admin.dashboard",
        icon: LayoutDashboard,
        match: ["admin.dashboard"],
    },
    {
        label: "Modules",
        route: "admin.modules.index",
        icon: BookOpen,
        match: ["admin.modules.*"],
    },
    {
        label: "Lessons",
        route: "admin.lessons.index",
        icon: BookOpenCheck,
        match: ["admin.lessons.*"],
    },
    {
        label: "Assessment",
        route: "admin.scoreboards.index",
        icon: ClipboardList,
        match: ["admin.scoreboards.*", "admin.assessments.*"],
    },
    {
        label: "Student",
        route: "admin.students.index",
        match: ["admin.students.*", "admin.student-progress.*"],
        icon: FileSpreadsheet,
    },
    {
        label: "Admin",
        route: "admin.admins.index",
        match: ["admin.admins.*"],
        icon: Shield,
    },
    {
        label: "Dialog",
        route: "admin.dialogs.edit",
        match: ["admin.dialogs.*"],
        icon: MessageSquareText,
    },
    {
        label: "Video Lecture",
        route: "admin.courses.index",
        icon: PlaySquare,
        match: ["admin.courses.*"],
    },
    {
        label: "E-Book",
        route: "admin.ebooks.index",
        icon: BookMarked,
        match: ["admin.ebooks.*"],
    },
    {
        label: "Accommodations",
        icon: Building2,
        children: [
            {
                label: "Hotels",
                icon: Building2,
                route: "admin.accommodations.index",
                match: ["admin.accommodations.*"],
            },
            {
                label: "Bookings",
                icon: FileSpreadsheet,
                route: "admin.accommodation-bookings.index",
                match: ["admin.accommodation-bookings.*"],
            },
        ],
    },
    {
        label: "Email",
        icon: Mail,
        children: [
            {
                label: "Branding",
                icon: Mail,
                route: "admin.email-branding.show",
                match: ["admin.email-branding.show"],
            },
            {
                label: "Module Completion",
                icon: Mail,
                route: "admin.email-notifications.show",
                params: { notificationType: "module_completion" },
                match: ["admin.email-notifications.show"],
                activeWhen: { notificationType: "module_completion" },
            },
            {
                label: "Assignments Review",
                icon: Mail,
                route: "admin.email-notifications.show",
                params: { notificationType: "assignment_review" },
                match: ["admin.email-notifications.show"],
                activeWhen: { notificationType: "assignment_review" },
            },
            {
                label: "Assignments Approved",
                icon: Mail,
                route: "admin.email-notifications.show",
                params: { notificationType: "assignment_approved" },
                match: ["admin.email-notifications.show"],
                activeWhen: { notificationType: "assignment_approved" },
            },
            {
                label: "Assignments Rejected",
                icon: Mail,
                route: "admin.email-notifications.show",
                params: { notificationType: "assignment_rejected" },
                match: ["admin.email-notifications.show"],
                activeWhen: { notificationType: "assignment_rejected" },
            },
            {
                label: "Certificate Created",
                icon: Mail,
                route: "admin.email-notifications.show",
                params: { notificationType: "certificate_created" },
                match: ["admin.email-notifications.show"],
                activeWhen: { notificationType: "certificate_created" },
            },
            {
                label: "Payment Success",
                icon: Mail,
                route: "admin.email-notifications.show",
                params: { notificationType: "payment_success" },
                match: ["admin.email-notifications.show"],
                activeWhen: { notificationType: "payment_success" },
            },
            {
                label: "Installment Payment Success",
                icon: Mail,
                route: "admin.email-notifications.show",
                params: { notificationType: "installment_payment_success" },
                match: ["admin.email-notifications.show"],
                activeWhen: { notificationType: "installment_payment_success" },
            },
            {
                label: "Enrollment Success",
                icon: Mail,
                route: "admin.email-notifications.show",
                params: { notificationType: "enrollment_success" },
                match: ["admin.email-notifications.show"],
                activeWhen: { notificationType: "enrollment_success" },
            },
            {
                label: "Signup",
                icon: Mail,
                route: "admin.email-notifications.show",
                params: { notificationType: "signup" },
                match: ["admin.email-notifications.show"],
                activeWhen: { notificationType: "signup" },
            },
            {
                label: "Reset Password",
                icon: Mail,
                route: "admin.email-notifications.show",
                params: { notificationType: "reset_password" },
                match: ["admin.email-notifications.show"],
                activeWhen: { notificationType: "reset_password" },
            },
            {
                label: "Assessment Complete",
                icon: Mail,
                route: "admin.email-notifications.show",
                params: { notificationType: "assessment_complete" },
                match: ["admin.email-notifications.show"],
                activeWhen: { notificationType: "assessment_complete" },
            },
            {
                label: "Course Complete",
                icon: Mail,
                route: "admin.email-notifications.show",
                params: { notificationType: "course_complete" },
                match: ["admin.email-notifications.show"],
                activeWhen: { notificationType: "course_complete" },
            },
            {
                label: "Reminder",
                icon: Mail,
                route: "admin.email-notifications.show",
                params: { notificationType: "reminder" },
                match: ["admin.email-notifications.show"],
                activeWhen: { notificationType: "reminder" },
            },
            {
                label: "Workbook Sent",
                icon: Mail,
                route: "admin.email-notifications.show",
                params: { notificationType: "workbook_sent" },
                match: ["admin.email-notifications.show"],
                activeWhen: { notificationType: "workbook_sent" },
            },
            {
                label: "Irregular Activity Suspended",
                icon: Mail,
                route: "admin.email-notifications.show",
                params: { notificationType: "irregular_activity_suspended" },
                match: ["admin.email-notifications.show"],
                activeWhen: { notificationType: "irregular_activity_suspended" },
            },
            {
                label: "Accommodation",
                icon: Mail,
                route: "admin.email-notifications.show",
                params: { notificationType: "accommodation_booking_confirmed" },
                match: ["admin.email-notifications.show"],
                activeWhen: { notificationType: "accommodation_booking_confirmed" },
            },
        ],
    },
];

const adminUtilityItems = [
    {
        label: "Packages",
        route: "admin.packages.index",
        icon: FileSpreadsheet,
        match: ["admin.packages.*"],
    },
    {
        label: "Invoice",
        route: "admin.invoices.index",
        icon: FileText,
        match: ["admin.invoices.*"],
    },
    {
        label: "Payment",
        route: "admin.payments.index",
        icon: Wallet,
        match: ["admin.payments.*"],
    },
    {
        label: "Access Tiers",
        route: "admin.access-tiers.index",
        icon: FileSpreadsheet,
        match: ["admin.access-tiers.*"],
    },
    {
        label: "Link Control",
        route: "admin.link-control.show",
        icon: Smartphone,
        match: ["admin.link-control.*"],
    },
    {
        label: "Contact Support",
        route: "admin.support-settings.show",
        icon: Mail,
        match: ["admin.support-settings.*"],
    },
];

const studentNavigationItems = [
    { label: "Home", route: "student.dashboard", match: ["student.dashboard"] },
    {
        label: "Modules",
        route: "modules.index",
        match: [
            "modules.index",
            "modules.show",
            "lessons.show",
            "assignments.show",
        ],
    },
];

function studentCanUpgrade(user) {
    const accessTierSlug = user?.access_tier?.slug;

    return ["starter_kit", "online"].includes(String(accessTierSlug ?? ""));
}

function studentInstantAccessItemsForUser(user) {
    const accessTier = user?.access_tier;
    const items = [];

    if (accessTier?.has_full_standing_dialog_access) {
        items.push({
            label: "Full Standing Dialog",
            route: "student.dialogs.standing",
            match: ["student.dialogs.standing"],
        });
    }

    if (accessTier?.has_full_floor_dialog_access) {
        items.push({
            label: "Full Floor Dialog",
            route: "student.dialogs.floor",
            match: ["student.dialogs.floor"],
        });
    }

    return items;
}

const adminPageTitles = {
    "admin.dashboard": "Dashboard",
    "admin.modules.index": "Modules",
    "admin.modules.create": "Create Module",
    "admin.modules.edit": "Edit Module",
    "admin.modules.assignments.index": "Assignments",
    "admin.modules.assignments.create": "Create Assignment",
    "admin.modules.assignments.edit": "Edit Assignment",
    "admin.lessons.index": "Lessons",
    "admin.lessons.create": "Create Lesson",
    "admin.lessons.edit": "Edit Lesson",
    "admin.scoreboards.index": "Assessment",
    "admin.scoreboards.create": "Create Assessment",
    "admin.scoreboards.edit": "Edit Assessment",
    "admin.scoreboards.builder": "Assessment Builder",
    "admin.assessments.preview": "Assessment Preview",
    "admin.assessments.preview.result": "Assessment Preview Result",
    "admin.assessments.results.index": "Assessment Results",
    "admin.assessments.results.show": "Assessment Result Detail",
    "admin.courses.index": "Video Lecture",
    "admin.courses.create": "Create Video Lecture",
    "admin.courses.edit": "Edit Video Lecture",
    "admin.ebooks.index": "E-Book",
    "admin.ebooks.create": "Create E-Book",
    "admin.ebooks.edit": "Edit E-Book",
    "admin.ebooks.preview": "E-Book Preview",
    "admin.accommodations.index": "Accommodations",
    "admin.accommodations.create": "Create Accommodation",
    "admin.accommodations.edit": "Edit Accommodation",
    "admin.accommodations.room-types.index": "Room Types",
    "admin.accommodations.room-types.create": "Create Room Type",
    "admin.accommodations.room-types.edit": "Edit Room Type",
    "admin.accommodation-bookings.index": "Bookings",
    "admin.accommodation-bookings.show": "Booking Detail",
    "admin.student-progress.index": "Student",
    "admin.student-progress.students.show": "Student Detail",
    "admin.student-progress.completed-lessons.index": "Completed Lesson",
    "admin.student-progress.assignments.index": "Assignment",
    "admin.student-progress.certificates.index": "Certificate",
    "admin.student-progress.completed-lessons.show": "Completed Lesson",
    "admin.student-progress.assignments.show": "Assignment",
    "admin.student-progress.certificates.show": "Certificate",
    "admin.students.index": "Students",
    "admin.students.create": "Create Student",
    "admin.students.edit": "Student Detail",
    "admin.admins.index": "Admin",
    "admin.admins.create": "Create Admin",
    "admin.admins.edit": "Edit Admin",
    "admin.profile.edit": "Profile",
    "admin.dialogs.edit": "Dialog",
    "admin.email-notifications.index": "Email Notification",
    "admin.email-notifications.show": "Email Notification",
    "admin.email-branding.show": "Email Branding",
    "admin.link-control.show": "Link Control",
    "admin.support-settings.show": "Contact Support",
    "admin.packages.index": "Packages",
    "admin.packages.create": "Create Package",
    "admin.packages.edit": "Edit Package",
    "admin.invoices.index": "Invoice",
    "admin.payments.index": "Payment",
    "admin.access-tiers.index": "Access Tiers",
    "admin.access-tiers.create": "Create Access Tier",
    "admin.access-tiers.edit": "Edit Access Tier",
};

// Admin logo: https://yogafx.b-cdn.net/content/yogafx.png
// Student logo: https://yogafx.b-cdn.net/content/Logo%20YogAFX.png
const ADMIN_LOGO_URL = "https://yogafx.b-cdn.net/content/yogafx.png";
const STUDENT_LOGO_URL = "https://yogafx.b-cdn.net/content/Logo%20YogAFX.png";

function getUserInitials(user) {
    const baseName =
        [user?.first_name, user?.last_name].filter(Boolean).join(" ").trim() ||
        user?.name ||
        "Student";

    return baseName
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join("");
}

function UserMenu({ user, isImmersive = false }) {
    const { appDownload } = usePage().props;
    const [downloadDialogOpen, setDownloadDialogOpen] = useState(false);
    const [isStudentDesktop, setIsStudentDesktop] = useState(false);

    useEffect(() => {
        if (typeof window === "undefined") {
            return undefined;
        }

        const mediaQuery = window.matchMedia(STUDENT_DESKTOP_BREAKPOINT);
        const syncMatch = () => setIsStudentDesktop(mediaQuery.matches);

        syncMatch();

        if (typeof mediaQuery.addEventListener === "function") {
            mediaQuery.addEventListener("change", syncMatch);

            return () => mediaQuery.removeEventListener("change", syncMatch);
        }

        mediaQuery.addListener(syncMatch);

        return () => mediaQuery.removeListener(syncMatch);
    }, []);

    const handleLogout = () => {
        router.post(route("logout"));
    };

    const handleProfileNavigation = () => {
        if (user?.role === "student") {
            router.visit(route("profile.edit"));
            return;
        }

        if (["admin", "super_admin"].includes(user?.role)) {
            router.visit(route("admin.profile.edit"));
        }
    };

    const isStudent = user?.role === "student";
    const isAdmin = ["admin", "super_admin"].includes(user?.role);
    const showDownloadApplication =
        isStudent &&
        isStudentDesktop &&
        appDownload?.qr_image_url;
    const displayName = user?.first_name || user?.name || "Student";

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="outline"
                        className={[
                            "gap-2 rounded-lg",
                            isImmersive
                                ? "border-transparent bg-transparent px-3 text-white hover:bg-transparent hover:text-white"
                                : "",
                            isImmersive
                                ? "max-md:h-11 max-md:w-11 max-md:rounded-[10px] max-md:border-transparent max-md:bg-transparent max-md:px-0 max-md:hover:bg-transparent"
                                : "",
                        ].join(" ")}
                    >
                        <span
                            className={[
                                "flex size-8 items-center justify-center overflow-hidden bg-white/10 text-xs font-semibold uppercase tracking-[0.12em] text-current",
                                isImmersive
                                    ? "rounded-[8px] max-md:size-9 max-md:border-0 max-md:bg-transparent"
                                    : "rounded-full",
                            ].join(" ")}
                        >
                            {user?.profile_photo ? (
                                <img
                                    src={user.profile_photo}
                                    alt={displayName}
                                    className="h-full w-full object-cover"
                                />
                            ) : (
                                getUserInitials(user) || (
                                    <UserRound className="size-4" />
                                )
                            )}
                        </span>
                        <span className="hidden max-w-32 truncate md:inline">
                            {displayName}
                        </span>
                        <ChevronDown className="hidden size-4 opacity-70 md:inline" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="end"
                    className="w-56 border-gray-200 bg-white text-gray-900"
                >
                    <DropdownMenuLabel>
                        <div className="flex flex-col">
                            <span className="font-medium text-foreground">
                                {user.name}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {user.email}
                            </span>
                        </div>
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    {isStudent && (
                        <DropdownMenuItem
                            onSelect={(event) => {
                                event.preventDefault();
                                handleProfileNavigation();
                            }}
                        >
                            Profile
                        </DropdownMenuItem>
                    )}
                    {isAdmin && (
                        <DropdownMenuItem
                            onSelect={(event) => {
                                event.preventDefault();
                                handleProfileNavigation();
                            }}
                        >
                            Profile
                        </DropdownMenuItem>
                    )}
                    {showDownloadApplication ? (
                        <DropdownMenuItem
                            onSelect={(event) => {
                                event.preventDefault();
                                setDownloadDialogOpen(true);
                            }}
                        >
                            Download Application
                        </DropdownMenuItem>
                    ) : null}
                    <DropdownMenuItem
                        onSelect={(event) => {
                            event.preventDefault();
                            handleLogout();
                        }}
                    >
                        Log Out
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <StudentAppDownloadDialog
                open={downloadDialogOpen}
                onOpenChange={setDownloadDialogOpen}
                qrImageUrl={appDownload?.qr_image_url ?? null}
                maxWidthClassName="sm:max-w-lg"
            />
        </>
    );
}

function isItemActive(item) {
    const routeMatches = item.match
        ? item.match.some((pattern) => route().current(pattern))
        : item.route
          ? route().current(item.route)
          : false;

    if (!routeMatches) {
        return false;
    }

    if (!item.activeWhen) {
        return true;
    }

    return Object.entries(item.activeWhen).every(
        ([key, value]) => route().params[key] === value,
    );
}

function SidebarNavItem({ item, collapsed, onNavigate }) {
    const Icon = item.icon;
    const active = !item.disabled && isItemActive(item);
    const content = (
        <>
            <Icon className="size-4 shrink-0" />
            {!collapsed && <span className="truncate">{item.label}</span>}
        </>
    );

    if (item.disabled) {
        return (
            <Button
                variant="ghost"
                type="button"
                disabled
                title={collapsed ? item.label : undefined}
                className={[
                    "h-11 w-full justify-start gap-3 rounded-xl px-3 text-muted-foreground",
                    collapsed ? "px-0 justify-center" : "",
                ].join(" ")}
            >
                {content}
            </Button>
        );
    }

    return (
        <Button
            asChild
            variant={active ? "secondary" : "ghost"}
            title={collapsed ? item.label : undefined}
            className={[
                "h-11 w-full justify-start gap-3 rounded-xl px-3",
                collapsed ? "px-0 justify-center" : "",
            ].join(" ")}
        >
            <Link href={route(item.route)} onClick={onNavigate}>
                {content}
            </Link>
        </Button>
    );
}

function SidebarGroup({ item, collapsed, open, setOpen, onNavigate }) {
    const Icon = item.icon;

    return (
        <div className="space-y-1">
            <Button
                type="button"
                variant="ghost"
                title={collapsed ? item.label : undefined}
                onClick={() => setOpen((current) => !current)}
                className={[
                    "h-11 w-full justify-start gap-3 rounded-xl px-3",
                    collapsed ? "px-0 justify-center" : "",
                ].join(" ")}
            >
                <Icon className="size-4 shrink-0" />
                {!collapsed && (
                    <>
                        <span className="truncate">{item.label}</span>
                        {open ? (
                            <ChevronDown className="ml-auto size-4" />
                        ) : (
                            <ChevronRight className="ml-auto size-4" />
                        )}
                    </>
                )}
            </Button>

            {!collapsed && open && (
                <div className="space-y-1 pl-4">
                    {item.children?.map((child) => {
                        const ChildIcon = child.icon;
                        const childActive = isItemActive(child);

                        return (
                            <Button
                                key={child.label}
                                asChild
                                variant={childActive ? "secondary" : "ghost"}
                                className="h-10 w-full justify-start gap-3 rounded-xl px-3"
                            >
                                <Link
                                    href={route(child.route, child.params)}
                                    onClick={onNavigate}
                                >
                                    <ChildIcon className="size-4 shrink-0" />
                                    <span>{child.label}</span>
                                </Link>
                            </Button>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

function AdminSidebar({ collapsed, openGroups, onToggleGroup, onNavigate }) {
    return (
        <aside
            className={[
                "hidden border-r border-border bg-background lg:flex lg:flex-col",
                collapsed ? "lg:w-24" : "lg:w-72",
            ].join(" ")}
        >
            {/* Admin logo — tanpa teks */}
            <div className="flex h-16 items-center justify-center px-4">
                <img
                    src={ADMIN_LOGO_URL}
                    alt="YogaFX Admin"
                    className="h-9 w-auto object-contain"
                />
            </div>

            <Separator />

            <div className="flex-1 overflow-y-auto px-3 py-4">
                <div className="space-y-2">
                    {adminNavigationItems.map((item) =>
                        item.children ? (
                            <SidebarGroup
                                key={item.label}
                                item={item}
                                collapsed={collapsed}
                                open={Boolean(openGroups[item.label])}
                                setOpen={() => onToggleGroup(item.label)}
                                onNavigate={onNavigate}
                            />
                        ) : (
                            <SidebarNavItem
                                key={item.label}
                                item={item}
                                collapsed={collapsed}
                                onNavigate={onNavigate}
                            />
                        ),
                    )}
                </div>

                <Separator className="my-4" />

                <div className="space-y-2">
                    {!collapsed && (
                        <div className="px-3 text-xs font-medium uppercase tracking-[0.18em] text-muted-foreground">
                            Supporting Pages
                        </div>
                    )}
                    {adminUtilityItems.map((item) => (
                        <SidebarNavItem
                            key={item.label}
                            item={item}
                            collapsed={collapsed}
                            onNavigate={onNavigate}
                        />
                    ))}
                </div>
            </div>
        </aside>
    );
}

function AdminMobileSidebar({ open, setOpen, openGroups, onToggleGroup }) {
    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>
                <Button variant="outline" size="icon" className="lg:hidden">
                    <Menu className="size-4" />
                    <span className="sr-only">Open sidebar</span>
                </Button>
            </SheetTrigger>
            <SheetContent
                side="left"
                className="w-[85vw] max-w-80 p-0"
                showCloseButton={false}
            >
                <SheetHeader className="border-b border-border">
                    <SheetTitle>
                        <img
                            src={ADMIN_LOGO_URL}
                            alt="YogaFX Admin"
                            className="h-8 w-auto object-contain"
                        />
                    </SheetTitle>
                    <SheetDescription>Admin navigation</SheetDescription>
                </SheetHeader>
                <div className="flex-1 overflow-y-auto px-3 py-4">
                    <div className="space-y-2">
                        {adminNavigationItems.map((item) =>
                            item.children ? (
                                <SidebarGroup
                                    key={item.label}
                                    item={item}
                                    collapsed={false}
                                    open={Boolean(openGroups[item.label])}
                                    setOpen={() => onToggleGroup(item.label)}
                                    onNavigate={() => setOpen(false)}
                                />
                            ) : (
                                <SidebarNavItem
                                    key={item.label}
                                    item={item}
                                    collapsed={false}
                                    onNavigate={() => setOpen(false)}
                                />
                            ),
                        )}
                    </div>

                    <Separator className="my-4" />

                    <div className="space-y-2">
                        <div className="px-3 text-xs font-medium uppercase tracking-[0.18em] text-muted-foreground">
                            Supporting Pages
                        </div>
                        {adminUtilityItems.map((item) => (
                            <SidebarNavItem
                                key={item.label}
                                item={item}
                                collapsed={false}
                                onNavigate={() => setOpen(false)}
                            />
                        ))}
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    );
}

function StudentSiteHeader({ user, variant = "default" }) {
    const isImmersive = variant === "immersive";
    const studentInstantAccessItems = studentInstantAccessItemsForUser(user);
    const showUpgradeButton = studentCanUpgrade(user);
    const profileUpgradeHref = `${route("profile.edit")}#upgrade-class`;
    const [instantAccessOpen, setInstantAccessOpen] = useState(false);

    return (
        <nav
            className={[
                isImmersive
                    ? "border-b border-white/10 bg-black/35 backdrop-blur-xl"
                    : "border-b border-border bg-background",
            ].join(" ")}
        >
            <div className="mx-auto flex h-20 max-w-[1400px] items-center justify-between gap-3 px-4 sm:px-6 lg:px-10">
                <div className="flex min-w-0 items-center gap-3">
                    <Link href={route("student.dashboard")} className="shrink-0">
                        <img
                            src={STUDENT_LOGO_URL}
                            alt="YogaFX"
                            className="h-7 w-auto shrink-0 cursor-pointer object-contain transition-opacity hover:opacity-80 sm:h-10"
                        />
                    </Link>
                    {user?.access_tier?.description ? (
                        <span
                            className={[
                                "hidden truncate text-sm font-semibold uppercase tracking-wide sm:block",
                                isImmersive
                                    ? "text-white/70"
                                    : "text-muted-foreground",
                            ].join(" ")}
                        >
                            {user.access_tier.description}
                        </span>
                    ) : null}
                </div>
                <div className="min-w-0 md:hidden">
                    {studentInstantAccessItems.length > 0 ? (
                        <Sheet
                            open={instantAccessOpen}
                            onOpenChange={setInstantAccessOpen}
                        >
                            <SheetTrigger asChild>
                                <Button
                                    variant="outline"
                                    className="h-11 max-w-[180px] rounded-full border-white/10 bg-white/5 px-4 text-white hover:bg-white/10 hover:text-white"
                                >
                                    <span className="truncate text-sm font-medium">
                                        Instant Access Dialog
                                    </span>
                                    <ChevronDown className="ml-2 size-4 shrink-0 opacity-70" />
                                </Button>
                            </SheetTrigger>
                            <SheetContent
                                side="bottom"
                                className="rounded-t-[22px] border-white/10 bg-[#171311] px-0 text-white"
                            >
                                <SheetHeader className="px-4 text-left">
                                    <SheetTitle className="text-white">
                                        Instant Access Dialog
                                    </SheetTitle>
                                    <SheetDescription className="text-white/55">
                                        Pick a dialog to open.
                                    </SheetDescription>
                                </SheetHeader>
                                <div className="space-y-2 px-4 pb-6 pt-2">
                                    {studentInstantAccessItems.map(
                                        (item) => (
                                            <Button
                                                key={item.label}
                                                type="button"
                                                variant="ghost"
                                                className="h-12 w-full justify-start rounded-[14px] border border-white/10 bg-white/5 px-4 text-white hover:bg-white/10 hover:text-white"
                                                onClick={() => {
                                                    setInstantAccessOpen(
                                                        false,
                                                    );
                                                    router.visit(
                                                        route(item.route),
                                                    );
                                                }}
                                            >
                                                {item.label}
                                            </Button>
                                        ),
                                    )}
                                </div>
                            </SheetContent>
                        </Sheet>
                    ) : null}
                </div>

                <div className="hidden min-w-0 items-center gap-2 md:flex">
                    {studentNavigationItems.map((item) => (
                        <Button
                            key={item.route}
                            asChild
                            variant="ghost"
                            className={
                                isItemActive(item)
                                    ? "text-[#ff5a3c] hover:text-[#ff5a3c] hover:bg-[#ff5a3c]/10"
                                    : isImmersive
                                      ? "text-white/78 hover:bg-white/10 hover:text-white"
                                      : ""
                            }
                        >
                            <Link href={route(item.route)}>
                                {item.label}
                            </Link>
                        </Button>
                    ))}

                    {studentInstantAccessItems.length > 0 && (
                        <>
                            <div className="mx-2 hidden h-6 w-px bg-white/10 lg:block" />
                            <div className="hidden flex-col items-center gap-1.5 lg:flex">
                                <div
                                    className={[
                                        "text-[11px] font-semibold uppercase tracking-[0.28em]",
                                        isImmersive
                                            ? "text-white/35"
                                            : "text-muted-foreground",
                                    ].join(" ")}
                                >
                                    INSTANT ACCESS
                                </div>
                                <div className="flex items-center gap-2">
                                    {studentInstantAccessItems.map((item) => (
                                        <Button
                                            key={item.label}
                                            asChild
                                            variant="ghost"
                                            className={[
                                                "rounded-lg px-4 text-xs font-medium opacity-100",
                                                isItemActive(item)
                                                    ? "border border-[#ff5a3c]/40 bg-[#ff5a3c]/10 text-[#ff5a3c] hover:bg-[#ff5a3c]/15 hover:text-[#ff5a3c]"
                                                    : isImmersive
                                                      ? "border border-white/12 bg-white/5 text-white/78 hover:bg-white/10 hover:text-white"
                                                      : "",
                                            ].join(" ")}
                                        >
                                            <Link href={route(item.route)}>
                                                {item.label}
                                            </Link>
                                        </Button>
                                    ))}
                                </div>
                            </div>
                        </>
                    )}
                </div>

                <div className="flex items-center gap-2">
                    {showUpgradeButton ? (
                        <Button
                            asChild
                            className="rounded-[10px] bg-[#DB202C] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#c31c28]"
                        >
                            <Link href={profileUpgradeHref}>Upgrade Account</Link>
                        </Button>
                    ) : null}
                    <UserMenu user={user} isImmersive={isImmersive} />
                </div>
            </div>
        </nav>
    );
}

function StudentPageBody({
    header,
    children,
    variant = "default",
    contentClassName = "",
}) {
    const { flash = {} } = usePage().props;
    const isImmersive = variant === "immersive";
    const flashMessage = flash.success ?? flash.error ?? null;
    const flashTone = flash.error ? "error" : "success";
    const mobileStudentNavItems = [
        {
            label: "Home",
            route: "student.dashboard",
            match: ["student.dashboard"],
            icon: House,
        },
        {
            label: "Modules",
            route: "modules.index",
            match: [
                "modules.index",
                "modules.show",
                "lessons.show",
                "assignments.show",
            ],
            icon: BookOpen,
        },
        {
            label: "Profile",
            route: "profile.edit",
            match: ["profile.edit"],
            icon: UserRound,
        },
    ];

    return (
        <>
            {header && (
                <header
                    className={[
                        isImmersive
                            ? "border-b border-white/10 bg-black/10"
                            : "border-b border-border bg-background/90",
                    ].join(" ")}
                >
                    <div className="mx-auto max-w-[1400px] px-4 py-5 sm:px-6 lg:px-10">
                        {header}
                    </div>
                </header>
            )}

            <main className={["pb-20 md:pb-0", contentClassName].join(" ")}>
                {flashMessage ? (
                    <div className="mx-auto max-w-[1400px] px-4 pt-4 sm:px-6 lg:px-10">
                        <TransientStatusBanner
                            message={flashMessage}
                            tone={flashTone}
                        />
                    </div>
                ) : null}

                {children}
            </main>

            <div className="fixed inset-x-0 bottom-0 z-40 border-t border-white/10 bg-[#0b0908]/95 backdrop-blur-xl md:hidden">
                <div className="mx-auto grid max-w-[1400px] grid-cols-3 px-2 py-2">
                    {mobileStudentNavItems.map((item) => {
                        const Icon = item.icon;
                        const active = isItemActive(item);

                        return (
                            <Link
                                key={item.route}
                                href={route(item.route)}
                                className={[
                                    "flex flex-col items-center justify-center gap-1 rounded-[12px] px-2 py-2 text-[11px] font-medium transition",
                                    active
                                        ? "bg-[#db202c]/14 text-white"
                                        : "text-white/58 hover:bg-white/6 hover:text-white",
                                ].join(" ")}
                            >
                                <Icon className="size-4" />
                                <span>{item.label}</span>
                            </Link>
                        );
                    })}
                </div>
            </div>
        </>
    );
}

export default function AuthenticatedLayout({
    header,
    children,
    studentVariant = "default",
    studentContentClassName = "",
}) {
    const { auth, impersonation } = usePage().props;
    const user = auth.user;
    const currentRouteName = route().current();
    const isAdmin = ["admin", "super_admin"].includes(user?.role);
    const isImmersive = studentVariant === "immersive";
    const isImpersonating = Boolean(impersonation?.active);
    const { direction: scrollDirection, isAtTop } = useScrollDirection();
    const isStudentHeaderVisible =
        !isImpersonating || isAtTop || scrollDirection === "up";
    const pageTitle = adminPageTitles[currentRouteName] ?? "Admin";

    const [collapsed, setCollapsed] = useState(false);
    const [mobileSidebarOpen, setMobileSidebarOpen] = useState(false);
    const [openGroups, setOpenGroups] = useState({});

    const toggleGroup = (label) =>
        setOpenGroups((current) => ({
            ...current,
            [label]: !current[label],
        }));

    useEffect(() => {
        if (!isAdmin) {
            return;
        }

        const storedValue = window.localStorage.getItem(
            ADMIN_SIDEBAR_STORAGE_KEY,
        );
        setCollapsed(storedValue === "true");

        try {
            const storedGroups = window.localStorage.getItem(
                ADMIN_SIDEBAR_GROUPS_STORAGE_KEY,
            );
            setOpenGroups(storedGroups ? JSON.parse(storedGroups) : {});
        } catch {
            setOpenGroups({});
        }
    }, [isAdmin]);

    useEffect(() => {
        if (!isAdmin) {
            return;
        }

        window.localStorage.setItem(
            ADMIN_SIDEBAR_STORAGE_KEY,
            String(collapsed),
        );
    }, [collapsed, isAdmin]);

    useEffect(() => {
        if (!isAdmin) {
            return;
        }

        window.localStorage.setItem(
            ADMIN_SIDEBAR_GROUPS_STORAGE_KEY,
            JSON.stringify(openGroups),
        );
    }, [openGroups, isAdmin]);

    useEffect(() => {
        if (!isAdmin) {
            return;
        }

        const mediaQuery = window.matchMedia(ADMIN_DESKTOP_BREAKPOINT);
        const handleBreakpointChange = (event) => {
            if (event.matches) {
                setMobileSidebarOpen(false);
            }
        };

        handleBreakpointChange(mediaQuery);
        mediaQuery.addEventListener("change", handleBreakpointChange);

        return () => {
            mediaQuery.removeEventListener("change", handleBreakpointChange);
        };
    }, [isAdmin]);

    useEffect(() => {
        if (!isAdmin) {
            return;
        }

        setMobileSidebarOpen(false);
    }, [currentRouteName, isAdmin]);

    if (!isAdmin) {
        return (
            <div
                className={[
                    "font-student min-h-screen",
                    isImmersive
                        ? "bg-[radial-gradient(circle_at_top,_rgba(173,76,38,0.28),_transparent_32%),linear-gradient(180deg,_#120f0e_0%,_#0a0908_38%,_#080808_100%)] text-white"
                        : "bg-slate-50",
                ].join(" ")}
            >
                <div
                    className={[
                        "sticky top-0 z-50 transition-transform duration-300 ease-in-out",
                        isStudentHeaderVisible
                            ? "translate-y-0 pointer-events-auto"
                            : "-translate-y-full pointer-events-none",
                    ].join(" ")}
                >
                    <ImpersonationBanner />
                    <StudentSiteHeader user={user} variant={studentVariant} />
                </div>

                <StudentPageBody
                    header={header}
                    variant={studentVariant}
                    contentClassName={studentContentClassName}
                >
                    {children}
                </StudentPageBody>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-slate-50">
            <ImpersonationBanner />
            <div className="flex min-h-screen">
                <AdminSidebar
                    collapsed={collapsed}
                    openGroups={openGroups}
                    onToggleGroup={toggleGroup}
                />

                <div className="flex min-h-screen min-w-0 flex-1 flex-col">
                    <header className="sticky top-0 z-50 border-b border-border bg-background/95 backdrop-blur">
                        <div className="flex h-16 items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
                            <div className="flex min-w-0 items-center gap-3">
                                <AdminMobileSidebar
                                    open={mobileSidebarOpen}
                                    setOpen={setMobileSidebarOpen}
                                    openGroups={openGroups}
                                    onToggleGroup={toggleGroup}
                                />

                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    className="hidden lg:inline-flex"
                                    onClick={() =>
                                        setCollapsed((current) => !current)
                                    }
                                >
                                    {collapsed ? (
                                        <ChevronRight className="size-4" />
                                    ) : (
                                        <ChevronLeft className="size-4" />
                                    )}
                                    <span className="sr-only">
                                        Toggle sidebar
                                    </span>
                                </Button>

                                <div className="min-w-0">
                                    <div className="truncate text-xs uppercase tracking-[0.18em] text-muted-foreground">
                                        Admin
                                    </div>
                                    <h1 className="truncate text-lg font-semibold text-foreground">
                                        {pageTitle}
                                    </h1>
                                </div>
                            </div>

                            <UserMenu user={user} />
                        </div>
                    </header>

                    {header && (
                        <div className="border-b border-border bg-background">
                            <div className="px-4 py-5 sm:px-6 lg:px-8">
                                {header}
                            </div>
                        </div>
                    )}

                    <main className="flex-1">{children}</main>
                </div>
            </div>
        </div>
    );
}
