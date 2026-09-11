import { Head, Link, router } from "@inertiajs/react";
import { Eye, Pencil, Search, UsersRound } from "lucide-react";
import { useMemo, useState } from "react";
import {
    PageHeader,
    PaginationControls,
    type Pagination,
} from "@/components/admin/shared";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { index, show, update } from "@/routes/admin/pegawai";

type Pegawai = {
    uuid: string;
    name: string;
    email: string | null;
    jabatan: string | null;
    nip: string | null;
    kode_biro: string | null;
    is_active: boolean;
    lemburs_count: number;
};

type Filters = {
    search: string;
    status: string;
};

type EditForm = {
    name: string;
    email: string;
    jabatan: string;
    nip: string;
    is_active: boolean;
    password: string;
};

export default function PegawaiIndex({
    pegawai,
    filters = { search: "", status: "all" },
}: {
    pegawai: Pagination<Pegawai>;
    filters?: Filters;
}) {
    const [values, setValues] = useState<Filters>({
        search: filters.search ?? "",
        status: filters.status ?? "all",
    });
    const [editing, setEditing] = useState<Pegawai | null>(null);
    const [form, setForm] = useState<EditForm>({
        name: "",
        email: "",
        jabatan: "",
        nip: "",
        is_active: true,
        password: "",
    });

    const statusCount = useMemo(() => {
        return {
            active: pegawai.data.filter((item) => item.is_active).length,
            inactive: pegawai.data.filter((item) => !item.is_active).length,
        };
    }, [pegawai]);

    function applyFilters() {
        router.get(
            index.url(),
            {
                search: values.search,
                status: values.status,
            },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                only: ["pegawai", "filters"],
            },
        );
    }

    function resetFilters() {
        const next = { search: "", status: "all" };
        setValues(next);
        router.get(index.url(), next, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            only: ["pegawai", "filters"],
        });
    }

    function openEditor(item: Pegawai) {
        setEditing(item);
        setForm({
            name: item.name,
            email: item.email ?? "",
            jabatan: item.jabatan ?? "",
            nip: item.nip ?? "",
            is_active: item.is_active,
            password: "",
        });
    }

    function submitUpdate() {
        if (!editing) {
            return;
        }

        const payload: Record<string, string | boolean | null> = {
            name: form.name,
            jabatan: form.jabatan || null,
            nip: form.nip || null,
            is_active: form.is_active,
            status: form.is_active ? "active" : "inactive",
        };

        if (form.password.trim().length > 0) {
            payload.password = form.password;
        }

        router.put(update.url(editing.uuid), payload, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setEditing(null),
        });
    }

    return (
        <>
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl bg-linear-to-br from-white to-blue-100 p-4">
                <Head title="Pegawai" />
                <main className="w-full max-w-7xl mx-auto flex-1 flex-col gap-6">
                    <section className="mb-4 bg-card rounded-2xl border border-sky-100 p-4 shadow-sm dark:border-sky-950">
                        <div className="grid gap-3 md:grid-cols-3">
                            <label className="grid gap-1.5 text-sm font-medium">
                                Cari pegawai
                                <Input
                                    value={values.search}
                                    placeholder="Nama, NIP, jabatan…"
                                    onChange={(event) =>
                                        setValues({
                                            ...values,
                                            search: event.target.value,
                                        })
                                    }
                                />
                            </label>
                            <label className="grid gap-1.5 text-sm font-medium">
                                Status
                                <Select
                                    value={values.status}
                                    onValueChange={(value) =>
                                        setValues({ ...values, status: value })
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Semua status
                                        </SelectItem>
                                        <SelectItem value="active">
                                            Aktif
                                        </SelectItem>
                                        <SelectItem value="inactive">
                                            Tidak aktif
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </label>
                            <div className="flex items-end gap-2">
                                <Button onClick={applyFilters}>
                                    <Search className="size-4" /> Filter
                                </Button>
                                <Button
                                    variant="outline"
                                    onClick={resetFilters}
                                >
                                    Reset
                                </Button>
                            </div>
                        </div>
                    </section>

                    <section className="bg-card overflow-hidden rounded-2xl border border-sky-100 shadow-sm dark:border-sky-950">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[760px] text-left text-sm">
                                <thead className="bg-muted/60 text-muted-foreground text-xs tracking-wide uppercase">
                                    <tr>
                                        <th className="px-5 py-3 font-medium">
                                            No
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Pegawai
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Jabatan
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            NIP
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Biro
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Status
                                        </th>
                                        <th className="px-4 py-3 text-center font-medium">
                                            Total lembur
                                        </th>
                                        <th className="w-24 px-4 py-3">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {pegawai.data.map((item, index) => (
                                        <tr
                                            key={item.uuid}
                                            className="hover:bg-muted/35 transition-colors"
                                        >
                                            <td className="px-4 py-4 text-center">
                                                {index + 1}
                                            </td>
                                            <td className="px-5 py-4">
                                                <div className="flex items-center gap-3">
                                                    <div className="bg-primary/10 text-primary inline-flex size-9 items-center justify-center rounded-lg">
                                                        <UsersRound className="size-4" />
                                                    </div>

                                                    <div className="flex flex-col">
                                                        <span className="font-medium text-foreground">
                                                            {item.name}
                                                        </span>
                                                        <span className="text-sm text-muted-foreground">
                                                            {item.email}
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-4">
                                                {item.jabatan ?? "—"}
                                            </td>
                                            <td className="px-4 py-4 font-mono text-xs">
                                                {item.nip ?? "—"}
                                            </td>
                                            <td className="px-4 py-4">
                                                {/* {item.kode_biro ?? "—"} */}
                                                Tata Usaha dan Sumber Daya
                                                Manusia
                                            </td>
                                            <td className="px-4 py-4">
                                                <Badge
                                                    className={
                                                        item.is_active
                                                            ? "border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300"
                                                            : "border-zinc-200 bg-zinc-100 text-zinc-600"
                                                    }
                                                    variant="outline"
                                                >
                                                    {item.is_active
                                                        ? "Aktif"
                                                        : "Tidak aktif"}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-4 text-center font-semibold">
                                                {item.lemburs_count}
                                            </td>
                                            <td className="px-4 py-4">
                                                <div className="flex items-center gap-1">
                                                    <Button
                                                        asChild
                                                        size="icon"
                                                        variant="ghost"
                                                    >
                                                        <Link
                                                            href={show(
                                                                item.uuid,
                                                            )}
                                                            prefetch
                                                            aria-label={`Lihat ${item.name}`}
                                                        >
                                                            <Eye />
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        aria-label={`Edit ${item.name}`}
                                                        onClick={() =>
                                                            openEditor(item)
                                                        }
                                                    >
                                                        <Pencil className="size-4" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {pegawai.data.length === 0 && (
                            <div className="px-5 py-16 text-center">
                                <p className="font-medium">Belum ada pegawai</p>
                            </div>
                        )}
                        <PaginationControls pagination={pegawai} />
                    </section>
                </main>
            </div>

            <Dialog
                open={Boolean(editing)}
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <DialogContent className="sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Edit data pegawai</DialogTitle>
                        <DialogDescription>
                            Ubah profil pegawai tanpa mengisi biro.
                        </DialogDescription>
                    </DialogHeader>
                    {editing && (
                        <div className="grid gap-4 py-2">
                            <label className="grid gap-1.5 text-sm font-medium">
                                Nama
                                <Input
                                    value={form.name}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            name: event.target.value,
                                        })
                                    }
                                />
                            </label>
                            <label className="grid gap-1.5 text-sm font-medium">
                                Jabatan
                                <Input
                                    value={form.jabatan}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            jabatan: event.target.value,
                                        })
                                    }
                                />
                            </label>
                            <label className="grid gap-1.5 text-sm font-medium">
                                NRP/NIP
                                <Input
                                    value={form.nip}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            nip: event.target.value,
                                        })
                                    }
                                />
                            </label>
                            <label className="grid gap-1.5 text-sm font-medium">
                                Status
                                <Select
                                    value={
                                        form.is_active ? "active" : "inactive"
                                    }
                                    onValueChange={(value) =>
                                        setForm({
                                            ...form,
                                            is_active: value === "active",
                                        })
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="active">
                                            Aktif
                                        </SelectItem>
                                        <SelectItem value="inactive">
                                            Tidak aktif
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </label>
                            <label className="grid gap-1.5 text-sm font-medium">
                                Password
                                <Input
                                    type="password"
                                    value={form.password}
                                    placeholder="Kosongkan bila tidak diubah"
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            password: event.target.value,
                                        })
                                    }
                                />
                            </label>
                        </div>
                    )}
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setEditing(null)}
                        >
                            Batal
                        </Button>
                        <Button onClick={submitUpdate}>Simpan</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

PegawaiIndex.layout = { breadcrumbs: [{ title: "Pegawai", href: index() }] };
