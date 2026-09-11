import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarDays,
    Clock3,
    Expand,
    LockKeyhole,
    MapPin,
    ReceiptText,
    UserRound,
} from 'lucide-react';
import { useState } from 'react';
import {
    DateLabel,
    PageHeader,
    Rupiah,
    StatusBadge,
} from '@/components/admin/shared';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { index, lock } from '@/routes/admin/lemburs';

type Lembur = {
    id: number;
    uuid: string;
    tanggal: string;
    nama_kegiatan: string;
    lokasi_kegiatan: string;
    waktu_pulang: string | null;
    status: string;
    jenis_hari: 'kerja' | 'libur';
    upah: number;
    can_lock: boolean;
    foto_kegiatan: string | null;
    foto_kegiatan_at: string | null;
    foto_pulang: string | null;
    foto_pulang_at: string | null;
    locked_at: string | null;
    locked_by: string | null;
    pegawai: {
        uuid: string;
        name: string;
        nip: string | null;
        jabatan: string | null;
    };
};

export default function LemburShow({ lembur }: { lembur: Lembur }) {
    const [confirmingLock, setConfirmingLock] = useState(false);
    const [image, setImage] = useState<{ url: string; label: string } | null>(
        null,
    );

    return (
        <>
            <Head title={`Lembur ${lembur.pegawai.name}`} />
            <main className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Detail pemeriksaan"
                    title={lembur.nama_kegiatan}
                    description={`${lembur.pegawai.name} · ${lembur.lokasi_kegiatan}`}
                    actions={
                        <>
                            <Button asChild variant="outline">
                                <Link href={index()}>
                                    <ArrowLeft /> Kembali
                                </Link>
                            </Button>
                            {lembur.can_lock && (
                                <Button onClick={() => setConfirmingLock(true)}>
                                    <LockKeyhole /> Kunci data
                                </Button>
                            )}
                        </>
                    }
                />

                <section className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_20rem]">
                    <div className="grid gap-5">
                        <Card className="gap-4 border-sky-100 py-5 dark:border-sky-950">
                            <CardHeader className="flex-row items-center justify-between">
                                <CardTitle>Ringkasan lembur</CardTitle>
                                <StatusBadge status={lembur.status} />
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <Detail
                                    icon={CalendarDays}
                                    label="Tanggal"
                                    value={<DateLabel value={lembur.tanggal} />}
                                />
                                <Detail
                                    icon={Clock3}
                                    label="Waktu pulang"
                                    value={lembur.waktu_pulang ?? '—'}
                                />
                                <Detail
                                    icon={ReceiptText}
                                    label="Jenis hari"
                                    value={
                                        lembur.jenis_hari === 'libur'
                                            ? 'Hari libur'
                                            : 'Hari kerja'
                                    }
                                />
                                <Detail
                                    icon={ReceiptText}
                                    label="Upah lembur"
                                    value={<Rupiah value={lembur.upah} />}
                                    strong
                                />
                                <Detail
                                    icon={MapPin}
                                    label="Lokasi"
                                    value={lembur.lokasi_kegiatan}
                                />
                                {lembur.locked_at && (
                                    <Detail
                                        icon={LockKeyhole}
                                        label="Dikunci"
                                        value={`${lembur.locked_at}${lembur.locked_by ? ` oleh ${lembur.locked_by}` : ''}`}
                                    />
                                )}
                            </CardContent>
                        </Card>

                        <Card className="gap-4 border-sky-100 py-5 dark:border-sky-950">
                            <CardHeader>
                                <CardTitle>Bukti kegiatan</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4 md:grid-cols-2">
                                <PhotoCard
                                    title="Foto kegiatan"
                                    time={lembur.foto_kegiatan_at}
                                    url={lembur.foto_kegiatan}
                                    onOpen={setImage}
                                />
                                <PhotoCard
                                    title="Foto pulang"
                                    time={lembur.foto_pulang_at}
                                    url={lembur.foto_pulang}
                                    onOpen={setImage}
                                />
                            </CardContent>
                        </Card>
                    </div>
                    <Card className="h-fit gap-4 border-sky-100 py-5 dark:border-sky-950">
                        <CardHeader>
                            <CardTitle>Pegawai</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="bg-primary/10 text-primary flex size-12 items-center justify-center rounded-2xl">
                                <UserRound className="size-6" />
                            </div>
                            <div>
                                <p className="font-semibold">
                                    {lembur.pegawai.name}
                                </p>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    {lembur.pegawai.jabatan ??
                                        'Jabatan belum tersedia'}
                                </p>
                            </div>
                            <div className="border-t pt-3">
                                <p className="text-muted-foreground text-xs">
                                    NIP
                                </p>
                                <p className="mt-1 font-medium">
                                    {lembur.pegawai.nip ?? '—'}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </section>
            </main>

            <Dialog open={confirmingLock} onOpenChange={setConfirmingLock}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Kunci data lembur?</DialogTitle>
                        <DialogDescription>
                            Data ini akan menjadi locked dan tidak dapat diedit
                            kembali oleh pegawai.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirmingLock(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            onClick={() =>
                                router.post(
                                    lock.url(lembur.uuid),
                                    {},
                                    {
                                        preserveScroll: true,
                                        onSuccess: () =>
                                            setConfirmingLock(false),
                                    },
                                )
                            }
                        >
                            <LockKeyhole /> Kunci data
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
            <Dialog
                open={image !== null}
                onOpenChange={(open) => !open && setImage(null)}
            >
                <DialogContent className="max-w-4xl p-3">
                    <DialogHeader className="sr-only">
                        <DialogTitle>{image?.label}</DialogTitle>
                        <DialogDescription>
                            Preview bukti kegiatan lembur.
                        </DialogDescription>
                    </DialogHeader>
                    {image && (
                        <img
                            src={image.url}
                            alt={image.label}
                            className="max-h-[80vh] w-full rounded-lg object-contain"
                        />
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

function Detail({
    icon: Icon,
    label,
    value,
    strong = false,
}: {
    icon: typeof CalendarDays;
    label: string;
    value: React.ReactNode;
    strong?: boolean;
}) {
    return (
        <div className="flex gap-3">
            <div className="bg-muted text-muted-foreground flex size-9 shrink-0 items-center justify-center rounded-lg">
                <Icon className="size-4" />
            </div>
            <div>
                <p className="text-muted-foreground text-xs">{label}</p>
                <p
                    className={`mt-1 text-sm ${strong ? 'font-semibold' : 'font-medium'}`}
                >
                    {value}
                </p>
            </div>
        </div>
    );
}

function PhotoCard({
    title,
    time,
    url,
    onOpen,
}: {
    title: string;
    time: string | null;
    url: string | null;
    onOpen: (image: { url: string; label: string }) => void;
}) {
    if (!url)
        return (
            <div className="bg-muted/50 flex min-h-56 flex-col items-center justify-center rounded-xl border border-dashed p-5 text-center">
                <p className="font-medium">{title} belum tersedia</p>
                <p className="text-muted-foreground mt-1 text-sm">
                    Tidak ada bukti foto untuk diperiksa.
                </p>
            </div>
        );
    return (
        <button
            type="button"
            className="group relative overflow-hidden rounded-xl border text-left"
            onClick={() => onOpen({ url, label: title })}
        >
            <img
                src={url}
                alt={title}
                loading="lazy"
                className="h-64 w-full object-cover transition duration-300 group-hover:scale-[1.02]"
            />
            <div className="absolute inset-x-0 bottom-0 flex items-center justify-between bg-linear-to-t from-black/75 to-transparent px-4 pt-10 pb-3 text-white">
                <div>
                    <p className="text-sm font-medium">{title}</p>
                    <p className="text-xs text-white/80">
                        {time ?? 'Waktu tidak tersedia'}
                    </p>
                </div>
                <Expand className="size-4" />
            </div>
        </button>
    );
}

LemburShow.layout = { breadcrumbs: [{ title: 'Data Lembur', href: index() }] };
