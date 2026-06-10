import { Button } from '@/Components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Separator } from '@/Components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/Components/ui/sheet';
import { Link, router, usePage } from '@inertiajs/react';
import {
    BookMarked,
    BookOpen,
    BookOpenCheck,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ClipboardList,
    FileSpreadsheet,
    LayoutDashboard,
    Mail,
    Menu,
    PlaySquare,
    Search,
    UserRound,
} from 'lucide-react';
import { useEffect, useState } from 'react';

const ADMIN_SIDEBAR_STORAGE_KEY = 'yogafx-admin-sidebar-collapsed';
const ADMIN_EMAIL_GROUP_STORAGE_KEY = 'yogafx-admin-email-group-open';
const ADMIN_DESKTOP_BREAKPOINT = '(min-width: 1024px)';
const STUDENT_DESKTOP_BREAKPOINT = '(min-width: 768px)';

const adminNavigationItems = [
    {
        label: 'Dashboard',
        route: 'admin.dashboard',
        icon: LayoutDashboard,
        match: ['admin.dashboard'],
    },
    {
        label: 'Modules',
        route: 'admin.modules.index',
        icon: BookOpen,
        match: ['admin.modules.*'],
    },
    {
        label: 'Lessons',
        route: 'admin.lessons.index',
        icon: BookOpenCheck,
        match: ['admin.lessons.*'],
    },
    {
        label: 'Assessment',
        icon: ClipboardList,
        disabled: true,
    },
    {
        label: 'Student Progress',
        route: 'admin.student-progress.index',
        match: ['admin.student-progress.*'],
        icon: FileSpreadsheet,
    },
    {
        label: 'Video Lecture',
        route: 'admin.courses.index',
        icon: PlaySquare,
        match: ['admin.courses.*'],
    },
    {
        label: 'E-Book',
        route: 'admin.ebooks.index',
        icon: BookMarked,
        match: ['admin.ebooks.*'],
    },
    {
        label: 'Email',
        icon: Mail,
        children: [
            {
                label: 'Module Completion',
                icon: Mail,
                route: 'admin.email-notifications.show',
                params: { notificationType: 'module_completion' },
                match: ['admin.email-notifications.show'],
                activeWhen: { notificationType: 'module_completion' },
            },
            {
                label: 'Assignments Review',
                icon: Mail,
                route: 'admin.email-notifications.show',
                params: { notificationType: 'assignment_review' },
                match: ['admin.email-notifications.show'],
                activeWhen: { notificationType: 'assignment_review' },
            },
            {
                label: 'Assignments Approved',
                icon: Mail,
                route: 'admin.email-notifications.show',
                params: { notificationType: 'assignment_approved' },
                match: ['admin.email-notifications.show'],
                activeWhen: { notificationType: 'assignment_approved' },
            },
            {
                label: 'Assignments Rejected',
                icon: Mail,
                route: 'admin.email-notifications.show',
                params: { notificationType: 'assignment_rejected' },
                match: ['admin.email-notifications.show'],
                activeWhen: { notificationType: 'assignment_rejected' },
            },
            {
                label: 'Certificate Created',
                icon: Mail,
                route: 'admin.email-notifications.show',
                params: { notificationType: 'certificate_created' },
                match: ['admin.email-notifications.show'],
                activeWhen: { notificationType: 'certificate_created' },
            },
            {
                label: 'Signup',
                icon: Mail,
                route: 'admin.email-notifications.show',
                params: { notificationType: 'signup' },
                match: ['admin.email-notifications.show'],
                activeWhen: { notificationType: 'signup' },
            },
            {
                label: 'Reset Password',
                icon: Mail,
                route: 'admin.email-notifications.show',
                params: { notificationType: 'reset_password' },
                match: ['admin.email-notifications.show'],
                activeWhen: { notificationType: 'reset_password' },
            },
            {
                label: 'Assessment Complete',
                icon: Mail,
                route: 'admin.email-notifications.show',
                params: { notificationType: 'assessment_complete' },
                match: ['admin.email-notifications.show'],
                activeWhen: { notificationType: 'assessment_complete' },
            },
            {
                label: 'Course Complete',
                icon: Mail,
                route: 'admin.email-notifications.show',
                params: { notificationType: 'course_complete' },
                match: ['admin.email-notifications.show'],
                activeWhen: { notificationType: 'course_complete' },
            },
            {
                label: 'Reminder',
                icon: Mail,
                route: 'admin.email-notifications.show',
                params: { notificationType: 'reminder' },
                match: ['admin.email-notifications.show'],
                activeWhen: { notificationType: 'reminder' },
            },
        ],
    },
];

const adminUtilityItems = [
    {
        label: 'Access Tiers',
        route: 'admin.access-tiers.index',
        icon: FileSpreadsheet,
        match: ['admin.access-tiers.*'],
    },
];

const studentNavigationItems = [
    { label: 'Home', route: 'student.dashboard', match: ['student.dashboard'] },
    { label: 'Modules', route: 'modules.index', match: ['modules.index', 'modules.show', 'lessons.show'] },
    { label: 'Ebooks', route: 'ebooks.index', match: ['ebooks.index', 'ebooks.preview'] },
    { label: 'Courses', route: 'courses.index', match: ['courses.index'] },
    { label: 'Profile', route: 'profile.edit', match: ['profile.edit'] },
];

const adminPageTitles = {
    'admin.dashboard': 'Dashboard',
    'admin.modules.index': 'Modules',
    'admin.modules.create': 'Create Module',
    'admin.modules.edit': 'Edit Module',
    'admin.lessons.index': 'Lessons',
    'admin.lessons.create': 'Create Lesson',
    'admin.lessons.edit': 'Edit Lesson',
    'admin.courses.index': 'Video Lecture',
    'admin.courses.create': 'Create Video Lecture',
    'admin.courses.edit': 'Edit Video Lecture',
    'admin.ebooks.index': 'E-Book',
    'admin.ebooks.create': 'Create E-Book',
    'admin.ebooks.edit': 'Edit E-Book',
    'admin.ebooks.preview': 'E-Book Preview',
    'admin.student-progress.index': 'Student Progress',
    'admin.student-progress.completed-lessons.index': 'Completed Lesson',
    'admin.student-progress.assignments.index': 'Assignment',
    'admin.student-progress.certificates.index': 'Certificate',
    'admin.student-progress.students.edit': 'Student Detail',
    'admin.student-progress.completed-lessons.show': 'Completed Lesson',
    'admin.student-progress.assignments.show': 'Assignment',
    'admin.student-progress.certificates.show': 'Certificate',
    'admin.email-notifications.index': 'Email Notification',
    'admin.email-notifications.show': 'Email Notification',
    'admin.access-tiers.index': 'Access Tiers',
    'admin.access-tiers.create': 'Create Access Tier',
    'admin.access-tiers.edit': 'Edit Access Tier',
};

function UserMenu({ user }) {
    const handleLogout = () => {
        router.post(route('logout'));
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" className="gap-2">
                    <UserRound className="size-4" />
                    <span className="hidden md:inline">{user.name}</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuLabel>
                    <div className="flex flex-col">
                        <span className="font-medium text-foreground">{user.name}</span>
                        <span className="text-xs text-muted-foreground">
                            {user.email}
                        </span>
                    </div>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
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
    );
}

function isItemActive(item) {
    const routeMatches = item.match
        ? item.match.some((pattern) => route().current(pattern))
        : (item.route ? route().current(item.route) : false);

    if (! routeMatches) {
        return false;
    }

    if (! item.activeWhen) {
        return true;
    }

    return Object.entries(item.activeWhen).every(([key, value]) => route().params[key] === value);
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
                    'h-11 w-full justify-start gap-3 rounded-xl px-3 text-muted-foreground',
                    collapsed ? 'px-0 justify-center' : '',
                ].join(' ')}
            >
                {content}
            </Button>
        );
    }

    return (
        <Button
            asChild
            variant={active ? 'secondary' : 'ghost'}
            title={collapsed ? item.label : undefined}
            className={[
                'h-11 w-full justify-start gap-3 rounded-xl px-3',
                collapsed ? 'px-0 justify-center' : '',
            ].join(' ')}
        >
            <Link href={route(item.route)} onClick={onNavigate}>
                {content}
            </Link>
        </Button>
    );
}

function SidebarGroup({
    item,
    collapsed,
    open,
    setOpen,
    onNavigate,
}) {
    const Icon = item.icon;

    return (
        <div className="space-y-1">
            <Button
                type="button"
                variant="ghost"
                title={collapsed ? item.label : undefined}
                onClick={() => setOpen((current) => !current)}
                className={[
                    'h-11 w-full justify-start gap-3 rounded-xl px-3',
                    collapsed ? 'px-0 justify-center' : '',
                ].join(' ')}
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
                                variant={childActive ? 'secondary' : 'ghost'}
                                className="h-10 w-full justify-start gap-3 rounded-xl px-3"
                            >
                                <Link href={route(child.route, child.params)} onClick={onNavigate}>
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

function AdminSidebar({
    collapsed,
    emailOpen,
    setEmailOpen,
    onNavigate,
}) {
    return (
        <aside
            className={[
                'hidden border-r border-border bg-background lg:flex lg:flex-col',
                collapsed ? 'lg:w-24' : 'lg:w-72',
            ].join(' ')}
        >
            <div className="flex h-16 items-center px-4">
                {!collapsed && (
                    <div>
                        <div className="text-sm font-semibold text-foreground">
                            YogaFX LMS
                        </div>
                        <div className="text-xs text-muted-foreground">Admin Console</div>
                    </div>
                )}
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
                                open={emailOpen}
                                setOpen={setEmailOpen}
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

function AdminMobileSidebar({
    open,
    setOpen,
    emailOpen,
    setEmailOpen,
}) {
    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>
                <Button variant="outline" size="icon" className="lg:hidden">
                    <Menu className="size-4" />
                    <span className="sr-only">Open sidebar</span>
                </Button>
            </SheetTrigger>
            <SheetContent side="left" className="w-[85vw] max-w-80 p-0" showCloseButton={false}>
                <SheetHeader className="border-b border-border">
                    <SheetTitle>YogaFX LMS</SheetTitle>
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
                                    open={emailOpen}
                                    setOpen={setEmailOpen}
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

function StudentTopNavigation({
    user,
    header,
    children,
    variant = 'default',
    contentClassName = '',
}) {
    const [mobileOpen, setMobileOpen] = useState(false);
    const currentRouteName = route().current();
    const isImmersive = variant === 'immersive';

    useEffect(() => {
        const mediaQuery = window.matchMedia(STUDENT_DESKTOP_BREAKPOINT);
        const handleBreakpointChange = (event) => {
            if (event.matches) {
                setMobileOpen(false);
            }
        };

        handleBreakpointChange(mediaQuery);
        mediaQuery.addEventListener('change', handleBreakpointChange);

        return () => {
            mediaQuery.removeEventListener('change', handleBreakpointChange);
        };
    }, []);

    useEffect(() => {
        setMobileOpen(false);
    }, [currentRouteName]);

    return (
        <div
            className={[
                'min-h-screen',
                isImmersive
                    ? 'bg-[radial-gradient(circle_at_top,_rgba(173,76,38,0.28),_transparent_32%),linear-gradient(180deg,_#120f0e_0%,_#0a0908_38%,_#080808_100%)] text-white'
                    : 'bg-slate-50',
            ].join(' ')}
        >
            <nav
                className={[
                    isImmersive
                        ? 'sticky top-0 z-40 border-b border-white/10 bg-black/35 backdrop-blur-xl'
                        : 'border-b border-border bg-background',
                ].join(' ')}
            >
                <div className="mx-auto flex h-20 max-w-[1400px] items-center justify-between gap-3 px-4 sm:px-6 lg:px-10">
                    <div className="flex min-w-0 items-center gap-3">
                        <Sheet open={mobileOpen} onOpenChange={setMobileOpen}>
                            <SheetTrigger asChild>
                                <Button
                                    variant={isImmersive ? 'ghost' : 'outline'}
                                    size="icon"
                                    className={[
                                        'shrink-0 md:hidden',
                                        isImmersive
                                            ? 'border border-white/15 bg-white/5 text-white hover:bg-white/10 hover:text-white'
                                            : '',
                                    ].join(' ')}
                                >
                                    <Menu className="size-4" />
                                    <span className="sr-only">Open navigation</span>
                                </Button>
                            </SheetTrigger>
                            <SheetContent
                                side="left"
                                className={[
                                    'w-[85vw] max-w-72 p-0',
                                    isImmersive
                                        ? 'border-white/10 bg-[#0d0b0a] text-white'
                                        : '',
                                ].join(' ')}
                            >
                                <SheetHeader
                                    className={[
                                        'border-b',
                                        isImmersive
                                            ? 'border-white/10'
                                            : 'border-border',
                                    ].join(' ')}
                                >
                                    <SheetTitle>YogaFX LMS</SheetTitle>
                                    <SheetDescription>Student navigation</SheetDescription>
                                </SheetHeader>
                                <div className="space-y-2 p-4">
                                    {studentNavigationItems.map((item) => (
                                        <Button
                                            key={item.route}
                                            asChild
                                            variant={isItemActive(item) ? 'secondary' : 'ghost'}
                                            className={[
                                                'h-11 w-full justify-start rounded-xl px-3',
                                                isImmersive && !isItemActive(item)
                                                    ? 'text-white/78 hover:bg-white/10 hover:text-white'
                                                    : '',
                                            ].join(' ')}
                                        >
                                            <Link
                                                href={route(item.route)}
                                                onClick={() => setMobileOpen(false)}
                                            >
                                                {item.label}
                                            </Link>
                                        </Button>
                                    ))}
                                </div>
                            </SheetContent>
                        </Sheet>

                        <div className="min-w-0">
                            <div
                                className={[
                                    'truncate text-sm font-semibold',
                                    isImmersive ? 'text-white' : 'text-foreground',
                                ].join(' ')}
                            >
                                YogaFX LMS
                            </div>
                            <div
                                className={[
                                    'truncate text-xs',
                                    isImmersive
                                        ? 'text-white/60'
                                        : 'text-muted-foreground',
                                ].join(' ')}
                            >
                                Student Area
                            </div>
                        </div>
                    </div>

                    <div className="hidden items-center gap-2 overflow-x-auto md:flex">
                        {studentNavigationItems.map((item) => (
                            <Button
                                key={item.route}
                                asChild
                                variant={isItemActive(item) ? 'secondary' : 'ghost'}
                                className={
                                    isImmersive && !isItemActive(item)
                                        ? 'text-white/78 hover:bg-white/10 hover:text-white'
                                        : ''
                                }
                            >
                                <Link href={route(item.route)}>{item.label}</Link>
                            </Button>
                        ))}

                        {isImmersive && (
                            <>
                                <div className="mx-2 hidden h-6 w-px bg-white/10 xl:block" />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    disabled
                                    className="hidden rounded-full border border-white/12 bg-white/5 px-4 text-xs uppercase tracking-[0.18em] text-white/80 opacity-100 hover:bg-white/10 hover:text-white xl:inline-flex"
                                >
                                    Instant Access
                                </Button>
                            </>
                        )}
                    </div>

                    <div className="flex items-center gap-2">
                        {isImmersive && (
                            <>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    disabled
                                    size="icon"
                                    className="hidden rounded-full border border-white/12 bg-white/5 text-white/80 opacity-100 hover:bg-white/10 hover:text-white lg:inline-flex"
                                >
                                    <Search className="size-4" />
                                    <span className="sr-only">Search</span>
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    disabled
                                    className="hidden rounded-full border border-white/12 bg-white/5 px-4 text-xs font-medium text-white/80 opacity-100 hover:bg-white/10 hover:text-white lg:inline-flex"
                                >
                                    Full Standing Dialog
                                    <span className="ml-2 text-[10px] uppercase tracking-[0.18em] text-white/55">
                                        View in Browser
                                    </span>
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    disabled
                                    className="hidden rounded-full border border-white/12 bg-white/5 px-4 text-xs font-medium text-white/80 opacity-100 hover:bg-white/10 hover:text-white xl:inline-flex"
                                >
                                    Full Floor Dialog
                                    <span className="ml-2 text-[10px] uppercase tracking-[0.18em] text-white/55">
                                        View in Browser
                                    </span>
                                </Button>
                            </>
                        )}

                        <div className={isImmersive ? '[&_button]:border-white/15 [&_button]:bg-white/5 [&_button]:text-white [&_button]:hover:bg-white/10 [&_button]:hover:text-white' : ''}>
                            <UserMenu user={user} />
                        </div>
                    </div>
                </div>
            </nav>

            {header && (
                <header
                    className={[
                        isImmersive
                            ? 'border-b border-white/10 bg-black/10'
                            : 'border-b border-border bg-background/90',
                    ].join(' ')}
                >
                    <div className="mx-auto max-w-[1400px] px-4 py-5 sm:px-6 lg:px-10">
                        {header}
                    </div>
                </header>
            )}

            <main className={contentClassName}>{children}</main>
        </div>
    );
}

export default function AuthenticatedLayout({
    header,
    children,
    studentVariant = 'default',
    studentContentClassName = '',
}) {
    const user = usePage().props.auth.user;
    const currentRouteName = route().current();
    const isAdmin = user?.role === 'admin';
    const pageTitle = adminPageTitles[currentRouteName] ?? 'Admin';

    const [collapsed, setCollapsed] = useState(false);
    const [mobileSidebarOpen, setMobileSidebarOpen] = useState(false);
    const [emailOpen, setEmailOpen] = useState(false);

    useEffect(() => {
        if (!isAdmin) {
            return;
        }

        const storedValue = window.localStorage.getItem(ADMIN_SIDEBAR_STORAGE_KEY);
        setCollapsed(storedValue === 'true');
        setEmailOpen(
            window.localStorage.getItem(ADMIN_EMAIL_GROUP_STORAGE_KEY) === 'true',
        );
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
            ADMIN_EMAIL_GROUP_STORAGE_KEY,
            String(emailOpen),
        );
    }, [emailOpen, isAdmin]);

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
        mediaQuery.addEventListener('change', handleBreakpointChange);

        return () => {
            mediaQuery.removeEventListener('change', handleBreakpointChange);
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
            <StudentTopNavigation
                user={user}
                header={header}
                variant={studentVariant}
                contentClassName={studentContentClassName}
            >
                {children}
            </StudentTopNavigation>
        );
    }

    return (
        <div className="min-h-screen bg-slate-50">
            <div className="flex min-h-screen">
                <AdminSidebar
                    collapsed={collapsed}
                    emailOpen={emailOpen}
                    setEmailOpen={setEmailOpen}
                />

                <div className="flex min-h-screen min-w-0 flex-1 flex-col">
                    <header className="sticky top-0 z-30 border-b border-border bg-background/95 backdrop-blur">
                        <div className="flex h-16 items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
                            <div className="flex min-w-0 items-center gap-3">
                                <AdminMobileSidebar
                                    open={mobileSidebarOpen}
                                    setOpen={setMobileSidebarOpen}
                                    emailOpen={emailOpen}
                                    setEmailOpen={setEmailOpen}
                                />

                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    className="hidden lg:inline-flex"
                                    onClick={() => setCollapsed((current) => !current)}
                                >
                                    {collapsed ? (
                                        <ChevronRight className="size-4" />
                                    ) : (
                                        <ChevronLeft className="size-4" />
                                    )}
                                    <span className="sr-only">Toggle sidebar</span>
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
                            <div className="px-4 py-5 sm:px-6 lg:px-8">{header}</div>
                        </div>
                    )}

                    <main className="flex-1">{children}</main>
                </div>
            </div>
        </div>
    );
}
