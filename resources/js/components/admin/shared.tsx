import { Link, router } from "@inertiajs/react";
import { ChevronLeft, ChevronRight } from "lucide-react";
import type { ReactNode } from "react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Pagination<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
};

type PageHeaderProps = {
    eyebrow: string;
    title: string;
    description?: string;
    actions?: ReactNode;
};

export function PageHeader({
    eyebrow,
    title,
    description,
    actions,
}: PageHeaderProps) {
    return (
        <div className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div className="min-w-0 space-y-1.5">
                <p className="text-primary text-[11px] font-semibold tracking-[0.2em] uppercase">
                    {eyebrow}
                </p>

                <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                    {title}
                </h1>

                {description && (
                    <p className="text-muted-foreground max-w-xl text-sm leading-relaxed">
                        {description}
                    </p>
                )}
            </div>

            {actions && (
                <div className="flex shrink-0 flex-wrap items-center gap-2">
                    {actions}
                </div>
            )}
        </div>
    );
}

const statusConfig = {
    draft: {
        label: "Draft",
        className:
            "border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-300",
    },
    complete: {
        label: "Lengkap",
        className:
            "border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/60 dark:bg-sky-950/30 dark:text-sky-300",
    },
    locked: {
        label: "Terkunci",
        className:
            "border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-300",
    },
} as const;

export function StatusBadge({ status }: { status: string }) {
    const config = statusConfig[status as keyof typeof statusConfig] ?? {
        label: status,
        className: "border-border bg-muted text-muted-foreground",
    };

    return (
        <Badge
            variant="outline"
            className={cn("font-medium", config.className)}
        >
            {config.label}
        </Badge>
    );
}

export function PaginationControls({
    pagination,
}: {
    pagination: Pagination<unknown>;
}) {
    if (pagination.last_page <= 1) {
        return null;
    }

    const previous = pagination.links[0];
    const next = pagination.links.at(-1);
    const pageLinks = pagination.links.slice(1, -1);

    const range =
        pagination.from !== null && pagination.to !== null
            ? `${pagination.from}–${pagination.to}`
            : "0";

    return (
        <div className="flex flex-col gap-3 border-t px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-muted-foreground text-sm">
                Menampilkan{" "}
                <span className="text-foreground font-medium">{range}</span>{" "}
                dari{" "}
                <span className="text-foreground font-medium">
                    {pagination.total}
                </span>{" "}
                data
            </p>

            <div className="flex items-center gap-1">
                <Button
                    asChild
                    variant="outline"
                    size="sm"
                    disabled={!previous?.url}
                >
                    <Link
                        href={previous?.url ?? ""}
                        preserveScroll
                        aria-label="Halaman sebelumnya"
                    >
                        <ChevronLeft className="size-4" />
                        <span className="hidden sm:inline">Sebelumnya</span>
                    </Link>
                </Button>

                <div className="hidden items-center gap-1 sm:flex">
                    {pageLinks.map((link, index) => (
                        <Button
                            key={`${link.label}-${index}`}
                            size="sm"
                            variant={link.active ? "default" : "ghost"}
                            disabled={!link.url || link.label === "..."}
                            onClick={() =>
                                link.url &&
                                router.visit(link.url, {
                                    preserveScroll: true,
                                })
                            }
                        >
                            {link.label}
                        </Button>
                    ))}
                </div>

                <Button
                    asChild
                    variant="outline"
                    size="sm"
                    disabled={!next?.url}
                >
                    <Link
                        href={next?.url ?? ""}
                        preserveScroll
                        aria-label="Halaman berikutnya"
                    >
                        <span className="hidden sm:inline">Berikutnya</span>
                        <ChevronRight className="size-4" />
                    </Link>
                </Button>
            </div>
        </div>
    );
}

export function Rupiah({ value }: { value: number }) {
    return <>Rp{new Intl.NumberFormat("id-ID").format(value)}</>;
}

export function DateLabel({ value }: { value: string }) {
    return (
        <>
            {new Intl.DateTimeFormat("id-ID", {
                day: "2-digit",
                month: "short",
                year: "numeric",
            }).format(new Date(`${value}T00:00:00`))}
        </>
    );
}
