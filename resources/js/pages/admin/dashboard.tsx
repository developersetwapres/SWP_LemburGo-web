import { Head, router } from '@inertiajs/react';
import {
    BriefcaseBusiness,
    CalendarDays,
    CircleDollarSign,
    Umbrella,
} from 'lucide-react';
import { PageHeader, Rupiah } from '@/components/admin/shared';
import { Card, CardContent } from '@/components/ui/card';
import { dashboard } from '@/routes/admin';

type Summary = {
    total_lembur: number;
    total_upah: number;
    hari_kerja: number;
    hari_libur: number;
    bulan: string;
    chart: { month: number; total: number }[];
};

const monthNames = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'Mei',
    'Jun',
    'Jul',
    'Agu',
    'Sep',
    'Okt',
    'Nov',
    'Des',
];

export default function AdminDashboard({ summary }: { summary: Summary }) {
    const maximum = Math.max(...summary.chart.map((item) => item.total), 1);
    const metrics = [
        {
            label: 'Total lembur',
            value: summary.total_lembur.toLocaleString('id-ID'),
            icon: BriefcaseBusiness,
            tone: 'bg-sky-500/10 text-sky-600 dark:text-sky-300',
        },
        {
            label: 'Total upah',
            value: <Rupiah value={summary.total_upah} />,
            icon: CircleDollarSign,
            tone: 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-300',
        },
        {
            label: 'Hari kerja',
            value: summary.hari_kerja.toLocaleString('id-ID'),
            icon: CalendarDays,
            tone: 'bg-cyan-500/10 text-cyan-600 dark:text-cyan-300',
        },
        {
            label: 'Hari libur',
            value: summary.hari_libur.toLocaleString('id-ID'),
            icon: Umbrella,
            tone: 'bg-violet-500/10 text-violet-600 dark:text-violet-300',
        },
    ];

    return (
        <>
            <Head title="Dashboard" />
            <main className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Ringkasan operasional"
                    title="Dashboard lembur"
                    description="Pantau lembur yang sudah lengkap dan siap ditinjau pada periode aktif."
                    actions={
                        <label className="border-input bg-background flex h-9 items-center gap-2 rounded-md border px-3 text-sm shadow-xs">
                            <span className="text-muted-foreground">
                                Periode
                            </span>
                            <input
                                type="month"
                                value={summary.bulan}
                                className="bg-transparent outline-none"
                                onChange={(event) =>
                                    router.get(
                                        dashboard.url(),
                                        { bulan: event.target.value },
                                        { preserveState: true, replace: true },
                                    )
                                }
                            />
                        </label>
                    }
                />

                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {metrics.map((metric) => (
                        <Card
                            key={metric.label}
                            className="gap-0 overflow-hidden border-sky-100/80 py-0 dark:border-sky-950"
                        >
                            <CardContent className="flex items-start justify-between p-5">
                                <div>
                                    <p className="text-muted-foreground text-sm">
                                        {metric.label}
                                    </p>
                                    <p className="mt-2 text-2xl font-semibold tracking-tight">
                                        {metric.value}
                                    </p>
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        Bulan berjalan
                                    </p>
                                </div>
                                <div
                                    className={`flex size-10 items-center justify-center rounded-xl ${metric.tone}`}
                                >
                                    <metric.icon className="size-5" />
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </section>

                <section className="bg-card rounded-2xl border border-sky-100 p-5 shadow-sm sm:p-6 dark:border-sky-950">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <h2 className="font-semibold tracking-tight">
                                Tren lembur per bulan
                            </h2>
                            <p className="text-muted-foreground mt-1 text-sm">
                                Jumlah data complete selama tahun{' '}
                                {summary.bulan.slice(0, 4)}.
                            </p>
                        </div>
                        <div className="bg-primary/10 text-primary rounded-full px-3 py-1 text-xs font-medium">
                            Complete
                        </div>
                    </div>
                    <div className="mt-8 flex h-56 items-end gap-2 sm:gap-3">
                        {summary.chart.map((item) => (
                            <div
                                key={item.month}
                                className="group flex h-full min-w-0 flex-1 flex-col justify-end gap-2"
                            >
                                <div className="relative flex flex-1 items-end">
                                    <div className="bg-muted absolute inset-x-0 bottom-0 h-full rounded-t-md" />
                                    <div
                                        className="bg-primary relative w-full rounded-t-md transition-all duration-500 group-hover:bg-sky-400"
                                        style={{
                                            height: `${Math.max((item.total / maximum) * 100, item.total > 0 ? 5 : 0)}%`,
                                        }}
                                        title={`${monthNames[item.month - 1]}: ${item.total}`}
                                    />
                                </div>
                                <div className="text-center">
                                    <p className="text-muted-foreground text-[11px]">
                                        {monthNames[item.month - 1]}
                                    </p>
                                    <p className="text-xs font-medium">
                                        {item.total}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </div>
                </section>
            </main>
        </>
    );
}

AdminDashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
