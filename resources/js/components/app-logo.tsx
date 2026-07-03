export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-md bg-sidebar-primary">
                <img
                    src="/logo.png"
                    alt="Jorge Chavez API SUNAT"
                    className="size-8 object-contain"
                />
            </div>
            <div className="ml-1 grid flex-1 text-left leading-tight">
                <span className="truncate text-sm font-semibold">Jorge Chavez</span>
                <span className="truncate text-[10px] uppercase tracking-widest text-muted-foreground">
                    API SUNAT
                </span>
            </div>
        </>
    );
}
