<?php

namespace App\Filament\Widgets;

use App\Services\ReportService;
use Carbon\Carbon;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\HtmlString;

/** Daily collections for the last 14 days: one series, so no legend; the heading names it. */
class CollectionsChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    protected ?string $heading = 'Daily collections';

    protected ?string $description = 'Last 14 days';

    protected ?string $maxHeight = '260px';

    /** Validated against light and dark chart surfaces (single-series, teal-600). */
    private const BAR = '#0d9488';

    private function series(): array
    {
        $reports = app(ReportService::class);

        return $reports->collectionsByDay($reports->resolveArea($this->pageFilters['area_id'] ?? null), 14);
    }

    protected function getData(): array
    {
        $series = $this->series();

        return [
            'datasets' => [[
                'label' => 'Collected',
                'data' => array_values($series),
                'backgroundColor' => self::BAR,
                'hoverBackgroundColor' => self::BAR,
                'borderRadius' => ['topLeft' => 4, 'topRight' => 4],
                'borderSkipped' => 'bottom',
                'maxBarThickness' => 24,
            ]],
            'labels' => array_map(fn ($d) => Carbon::parse($d)->format('M j'), array_keys($series)),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        displayColors: false,
                        callbacks: {
                            label: (ctx) => '₱' + ctx.parsed.y.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                        },
                    },
                },
                scales: {
                    x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkipPadding: 12 } },
                    y: {
                        beginAtZero: true,
                        border: { display: false },
                        grid: { color: 'rgba(127, 127, 127, 0.15)' },
                        ticks: { maxTicksLimit: 5, callback: (v) => '₱' + Number(v).toLocaleString('en-PH') },
                    },
                },
            }
        JS);
    }

    /** Table alternative for screen readers. */
    public function getChartAssistiveContent(): HtmlString
    {
        $rows = collect($this->series())
            ->map(fn ($v, $d) => '<tr><th scope="row">'.e(Carbon::parse($d)->format('M j, Y')).'</th><td>₱'.number_format($v, 2).'</td></tr>')
            ->implode('');

        return new HtmlString('<table><caption>Daily collections, last 14 days</caption><thead><tr><th>Date</th><th>Collected</th></tr></thead><tbody>'.$rows.'</tbody></table>');
    }
}
