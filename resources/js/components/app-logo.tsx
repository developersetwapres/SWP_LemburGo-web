import AppLogoIcon from "@/components/app-logo-icon";

export default function AppLogo() {
    return (
        <>
            <div className="bg-sidebar-primary text-sidebar-primary-foreground flex aspect-square size-8 items-center justify-center rounded-md">
                <AppLogoIcon />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold tracking-tight">
                    LemburGo
                </span>
                <span className="text-muted-foreground truncate text-[10px] tracking-[0.16em] uppercase">
                    Admin Panel
                </span>
            </div>
        </>
    );
}
