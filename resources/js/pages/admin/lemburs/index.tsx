import { Head, Link, router } from "@inertiajs/react";
import {
    Download,
    Eye,
    LockKeyhole,
    Trash2,
    Search,
    SlidersHorizontal,
    X,
} from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import {
    DateLabel,
    PageHeader,
    PaginationControls,
    Rupiah,
    StatusBadge,
    type Pagination,
} from "@/components/admin/shared";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
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
import {
    bulkLock,
    destroy,
    exportMethod,
    index,
    show,
} from "@/routes/admin/lemburs";
import { Card } from "@/components/ui/card";

type Filters = {
    bulan: string;
    pegawai: string | null;
    status: string;
    jenis_hari: string;
    search: string;
};

type Lembur = {
    id: number;
    uuid: string;
    tanggal: string;
    nama_kegiatan: string;
    lokasi_kegiatan: string;
    waktu_pulang: string | null;
    status: string;
    jenis_hari: "kerja" | "libur";
    upah: number;
    can_lock: boolean;
    can_delete: boolean;
    pegawai: {
        uuid: string;
        name: string;
        nip: string | null;
        jabatan: string | null;
    };
};

type Props = {
    lemburs: Pagination<Lembur>;
    filters: Filters;
    pegawaiOptions: { uuid: string; name: string; nip: string | null }[];
};

export default function LemburIndex({
    lemburs,
    filters,
    pegawaiOptions,
}: Props) {
    const [values, setValues] = useState(filters);
    const [selected, setSelected] = useState<string[]>([]);
    const [confirmingLock, setConfirmingLock] = useState(false);
    const [deleting, setDeleting] = useState<Lembur | null>(null);

    useEffect(() => {
        setValues(filters);
        setSelected([]);
    }, [filters]);

    const selectedRows = useMemo(
        () => lemburs.data.filter((item) => selected.includes(item.uuid)),
        [lemburs.data, selected],
    );
    const lockableRows = lemburs.data.filter((item) => item.can_lock);
    const allLockableSelected =
        lockableRows.length > 0 &&
        lockableRows.every((item) => selected.includes(item.uuid));

    function update<K extends keyof Filters>(key: K, value: Filters[K]) {
        setValues((current) => ({ ...current, [key]: value }));
    }

    function applyFilters() {
        router.get(index.url(), values, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            only: ["lemburs", "filters", "pegawaiOptions"],
        });
    }

    function resetFilters() {
        const defaults = {
            ...filters,
            pegawai: null,
            status: "complete",
            jenis_hari: "semua",
            search: "",
        };
        setValues(defaults);
        router.get(index.url(), defaults, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    }

    function toggleRow(item: Lembur, checked: boolean) {
        if (!item.can_lock) {
            return;
        }

        setSelected((current) =>
            checked
                ? [...new Set([...current, item.uuid])]
                : current.filter((uuid) => uuid !== item.uuid),
        );
    }

    function submitBulkLock() {
        router.post(
            bulkLock.url(),
            { ids: selectedRows.map((item) => item.id) },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setConfirmingLock(false);
                    setSelected([]);
                },
            },
        );
    }

    function deleteLembur() {
        if (!deleting) {
            return;
        }

        router.delete(destroy.url(deleting.uuid), {
            preserveScroll: true,
            onSuccess: () => setDeleting(null),
        });
    }

    return (
        <>
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl bg-linear-to-br from-white to-blue-100 p-4">
                <Head title="Data Lembur" />
                <main className="mx-auto py-7 flex w-full max-w-7xl flex-1 flex-col gap-6 rounded-xl bg-white/70 p-4 shadow-sm backdrop-blur-sm md:p-6">
                    <section className="bg-card rounded-2xl border border-sky-100 p-4 shadow-sm dark:border-sky-950">
                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                            <label className="grid gap-1.5 text-sm font-medium">
                                Bulan
                                <Input
                                    type="month"
                                    value={values.bulan}
                                    onChange={(event) =>
                                        update("bulan", event.target.value)
                                    }
                                />
                            </label>
                            <label className="grid gap-1.5 text-sm font-medium">
                                Pegawai
                                <Select
                                    value={values.pegawai ?? "all"}
                                    onValueChange={(value) =>
                                        update(
                                            "pegawai",
                                            value === "all" ? null : value,
                                        )
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Semua pegawai
                                        </SelectItem>
                                        {pegawaiOptions.map((pegawai) => (
                                            <SelectItem
                                                key={pegawai.uuid}
                                                value={pegawai.uuid}
                                            >
                                                {pegawai.name}
                                                {pegawai.nip
                                                    ? ` · ${pegawai.nip}`
                                                    : ""}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </label>
                            <label className="grid gap-1.5 text-sm font-medium">
                                Status
                                <Select
                                    value={values.status}
                                    onValueChange={(value) =>
                                        update("status", value)
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="complete">
                                            Complete
                                        </SelectItem>
                                        <SelectItem value="draft">
                                            Draft
                                        </SelectItem>
                                        <SelectItem value="locked">
                                            Locked
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </label>
                            <label className="grid gap-1.5 text-sm font-medium">
                                Jenis hari
                                <Select
                                    value={values.jenis_hari}
                                    onValueChange={(value) =>
                                        update("jenis_hari", value)
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="semua">
                                            Semua hari
                                        </SelectItem>
                                        <SelectItem value="kerja">
                                            Hari kerja
                                        </SelectItem>
                                        <SelectItem value="libur">
                                            Hari libur
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </label>
                            <label className="grid gap-1.5 text-sm font-medium">
                                Cari kegiatan atau lokasi
                                <div className="relative">
                                    <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                    <Input
                                        className="pl-9"
                                        value={values.search}
                                        placeholder="Mis. rapat, kantor"
                                        onChange={(event) =>
                                            update("search", event.target.value)
                                        }
                                        onKeyDown={(event) =>
                                            event.key === "Enter" &&
                                            applyFilters()
                                        }
                                    />
                                </div>
                            </label>
                        </div>
                        <div className="mt-4 flex flex-wrap justify-end gap-2 border-t pt-4">
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={resetFilters}
                            >
                                <X /> Reset
                            </Button>
                            <Button size="sm" onClick={applyFilters}>
                                <SlidersHorizontal /> Terapkan filter
                            </Button>
                        </div>
                    </section>
                    <div className="mt-3 flex justify-end">
                        <Button
                            asChild
                            className="bg-red-600 text-white hover:bg-red-700"
                        >
                            <a href={exportMethod.url({ query: values })}>
                                <Download />
                                Export PDF
                            </a>
                        </Button>
                    </div>
                    <section className="bg-card overflow-hidden rounded-2xl border border-sky-100 shadow-sm dark:border-sky-950">
                        {selected.length > 0 && (
                            <div className="bg-primary/8 flex flex-col gap-3 border-b border-sky-100 px-5 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-sky-950">
                                <p className="text-sm">
                                    <span className="font-semibold">
                                        {selected.length}
                                    </span>{" "}
                                    data dipilih untuk dikunci.
                                </p>
                                <Button
                                    size="sm"
                                    onClick={() => setConfirmingLock(true)}
                                >
                                    <LockKeyhole /> Kunci data terpilih
                                </Button>
                            </div>
                        )}
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[980px] text-left text-sm">
                                <thead className="bg-muted/60 text-muted-foreground text-xs tracking-wide uppercase">
                                    <tr>
                                        <th className="w-12 px-5 py-3">
                                            <Checkbox
                                                checked={allLockableSelected}
                                                aria-label="Pilih semua data yang dapat dikunci"
                                                onCheckedChange={(checked) =>
                                                    setSelected(
                                                        checked
                                                            ? lockableRows.map(
                                                                  (item) =>
                                                                      item.uuid,
                                                              )
                                                            : [],
                                                    )
                                                }
                                            />
                                        </th>
                                        <th className="px-3 py-3 font-medium">
                                            Pegawai
                                        </th>
                                        <th className="px-3 py-3 font-medium">
                                            Tanggal
                                        </th>
                                        <th className="px-3 py-3 font-medium">
                                            Kegiatan & lokasi
                                        </th>
                                        <th className="px-3 py-3 font-medium">
                                            Pulang
                                        </th>
                                        <th className="px-3 py-3 font-medium">
                                            Jenis hari
                                        </th>
                                        <th className="px-3 py-3 font-medium">
                                            Upah
                                        </th>
                                        <th className="px-3 py-3 font-medium">
                                            Status
                                        </th>
                                        <th className="w-14 px-3 py-3" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {lemburs.data.map((item) => (
                                        <tr
                                            key={item.uuid}
                                            className="hover:bg-muted/35 transition-colors"
                                        >
                                            <td className="px-5 py-4">
                                                <Checkbox
                                                    disabled={!item.can_lock}
                                                    checked={selected.includes(
                                                        item.uuid,
                                                    )}
                                                    aria-label={`Pilih ${item.pegawai.name}`}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        toggleRow(
                                                            item,
                                                            checked === true,
                                                        )
                                                    }
                                                />
                                            </td>
                                            <td className="px-3 py-4">
                                                <p className="font-medium">
                                                    {item.pegawai.name}
                                                </p>
                                                <p className="text-muted-foreground mt-0.5 text-xs">
                                                    {item.pegawai.jabatan ??
                                                        item.pegawai.nip ??
                                                        "—"}
                                                </p>
                                            </td>
                                            <td className="px-3 py-4 whitespace-nowrap">
                                                <DateLabel
                                                    value={item.tanggal}
                                                />
                                            </td>
                                            <td className="max-w-64 px-3 py-4">
                                                <p className="truncate font-medium">
                                                    {item.nama_kegiatan}
                                                </p>
                                                <p className="text-muted-foreground mt-0.5 truncate text-xs">
                                                    {item.lokasi_kegiatan}
                                                </p>
                                            </td>
                                            <td className="px-3 py-4 font-medium">
                                                {item.waktu_pulang ?? "—"}
                                            </td>
                                            <td className="px-3 py-4">
                                                <span
                                                    className={
                                                        item.jenis_hari ===
                                                        "libur"
                                                            ? "text-violet-600 dark:text-violet-300"
                                                            : "text-sky-600 dark:text-sky-300"
                                                    }
                                                >
                                                    {item.jenis_hari === "libur"
                                                        ? "Hari libur"
                                                        : "Hari kerja"}
                                                </span>
                                            </td>
                                            <td className="px-3 py-4 font-medium whitespace-nowrap">
                                                <Rupiah value={item.upah} />
                                            </td>
                                            <td className="px-3 py-4">
                                                <StatusBadge
                                                    status={item.status}
                                                />
                                            </td>
                                            <td className="px-3 py-4">
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
                                                            aria-label={`Lihat ${item.nama_kegiatan}`}
                                                        >
                                                            <Eye />
                                                        </Link>
                                                    </Button>
                                                    {item.can_delete && (
                                                        <Button
                                                            size="icon"
                                                            variant="ghost"
                                                            className="text-destructive hover:text-destructive"
                                                            aria-label={`Hapus ${item.nama_kegiatan}`}
                                                            onClick={() =>
                                                                setDeleting(
                                                                    item,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 />
                                                        </Button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {lemburs.data.length === 0 && (
                            <div className="px-5 py-16 text-center">
                                <p className="font-medium">
                                    Tidak ada data lembur
                                </p>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    Coba ubah filter atau periode yang dipilih.
                                </p>
                            </div>
                        )}
                        <PaginationControls pagination={lemburs} />
                    </section>
                </main>
            </div>

            <Dialog open={confirmingLock} onOpenChange={setConfirmingLock}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Kunci {selected.length} data lembur?
                        </DialogTitle>
                        <DialogDescription>
                            Data complete yang dikunci tidak dapat diedit oleh
                            pegawai. Tindakan ini dicatat atas nama akun Anda.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirmingLock(false)}
                        >
                            Batal
                        </Button>
                        <Button onClick={submitBulkLock}>
                            <LockKeyhole /> Ya, kunci data
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
            <Dialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Hapus data lembur?</DialogTitle>
                        <DialogDescription>
                            Data lembur {deleting?.nama_kegiatan} beserta foto
                            buktinya akan dihapus permanen. Tindakan ini tidak
                            dapat dibatalkan.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeleting(null)}
                        >
                            Batal
                        </Button>
                        <Button variant="destructive" onClick={deleteLembur}>
                            <Trash2 /> Hapus data
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

LemburIndex.layout = { breadcrumbs: [{ title: "Data Lembur", href: index() }] };
