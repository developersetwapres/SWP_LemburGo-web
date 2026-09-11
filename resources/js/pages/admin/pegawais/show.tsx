import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, BriefcaseBusiness, MapPin } from 'lucide-react';
import {
    DateLabel,
    PageHeader,
    PaginationControls,
    Rupiah,
    StatusBadge,
    type Pagination,
} from '@/components/admin/shared';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index, show } from '@/routes/admin/pegawai';
import { show as showLembur } from '@/routes/admin/lemburs';

type History = {
    id: number;
    uuid: string;
    tanggal: string;
    nama_kegiatan: string;
    lokasi_kegiatan: string;
    jenis_hari: 'kerja' | 'libur';
    upah: number;
    status: string;
};
type Pegawai = {
    uuid: string;
    name: string;
    jabatan: string | null;
    nip: string | null;
    kode_biro: string | null;
    is_active: boolean;
    image: string | null;
};

export default function PegawaiShow({
    pegawai,
    history,
    filters,
}: {
    pegawai: Pegawai;
    history: Pagination<History>;
    filters: { bulan: string };
}) {
    return (
        <>
            <Head title={pegawai.name} />
            <main className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Profil pegawai"
                    title={pegawai.name}
                    description={`${pegawai.jabatan ?? 'Jabatan belum tersedia'} · Biro ${pegawai.kode_biro ?? '—'}`}
                    actions={
                        <Button asChild variant="outline">
                            <Link href={index()}>
                                <ArrowLeft /> Kembali
                            </Link>
                        </Button>
                    }
                />
                <section className="grid gap-5 xl:grid-cols-[20rem_minmax(0,1fr)]">
                    <div className="bg-card h-fit rounded-2xl border border-sky-100 p-5 shadow-sm dark:border-sky-950">
                        <div className="bg-primary/10 text-primary flex size-14 items-center justify-center rounded-2xl text-lg font-semibold">
                            {pegawai.name.slice(0, 1)}
                        </div>
                        <h2 className="mt-4 font-semibold">{pegawai.name}</h2>
                        <Badge className="mt-2" variant="outline">
                            {pegawai.is_active ? 'Aktif' : 'Tidak aktif'}
                        </Badge>
                        <dl className="mt-5 space-y-4 border-t pt-4 text-sm">
                            <div>
                                <dt className="text-muted-foreground">NIP</dt>
                                <dd className="mt-1 font-medium">
                                    {pegawai.nip ?? '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Jabatan
                                </dt>
                                <dd className="mt-1 font-medium">
                                    {pegawai.jabatan ?? '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Kode biro
                                </dt>
                                <dd className="mt-1 font-medium">
                                    {pegawai.kode_biro ?? '—'}
                                </dd>
                            </div>
                        </dl>
                    </div>
                    <section className="bg-card overflow-hidden rounded-2xl border border-sky-100 shadow-sm dark:border-sky-950">
                        <div className="flex flex-col gap-3 border-b p-5 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 className="font-semibold">
                                    Riwayat lembur
                                </h2>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    Data complete pada periode yang dipilih.
                                </p>
                            </div>
                            <Input
                                type="month"
                                className="w-full sm:w-40"
                                value={filters.bulan}
                                onChange={(event) =>
                                    router.get(
                                        show.url(pegawai.uuid),
                                        { bulan: event.target.value },
                                        {
                                            preserveState: true,
                                            replace: true,
                                            only: ['history', 'filters'],
                                        },
                                    )
                                }
                            />
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[680px] text-left text-sm">
                                <thead className="bg-muted/60 text-muted-foreground text-xs tracking-wide uppercase">
                                    <tr>
                                        <th className="px-5 py-3">Tanggal</th>
                                        <th className="px-4 py-3">Kegiatan</th>
                                        <th className="px-4 py-3">Lokasi</th>
                                        <th className="px-4 py-3">Jenis</th>
                                        <th className="px-4 py-3">Upah</th>
                                        <th className="px-4 py-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {history.data.map((item) => (
                                        <tr
                                            key={item.uuid}
                                            className="hover:bg-muted/35"
                                        >
                                            <td className="px-5 py-4">
                                                <DateLabel
                                                    value={item.tanggal}
                                                />
                                            </td>
                                            <td className="px-4 py-4 font-medium">
                                                <Link
                                                    className="hover:text-primary"
                                                    href={showLembur(item.uuid)}
                                                >
                                                    {item.nama_kegiatan}
                                                </Link>
                                            </td>
                                            <td className="px-4 py-4">
                                                <MapPin className="text-muted-foreground mr-1 inline size-3.5" />
                                                {item.lokasi_kegiatan}
                                            </td>
                                            <td className="px-4 py-4">
                                                {item.jenis_hari === 'libur'
                                                    ? 'Hari libur'
                                                    : 'Hari kerja'}
                                            </td>
                                            <td className="px-4 py-4 font-medium">
                                                <Rupiah value={item.upah} />
                                            </td>
                                            <td className="px-4 py-4">
                                                <StatusBadge
                                                    status={item.status}
                                                />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {history.data.length === 0 && (
                            <div className="px-5 py-12 text-center">
                                <BriefcaseBusiness className="text-muted-foreground mx-auto size-6" />
                                <p className="mt-3 font-medium">
                                    Tidak ada riwayat lembur
                                </p>
                            </div>
                        )}
                        <PaginationControls pagination={history} />
                    </section>
                </section>
            </main>
        </>
    );
}

PegawaiShow.layout = { breadcrumbs: [{ title: 'Pegawai', href: index() }] };
