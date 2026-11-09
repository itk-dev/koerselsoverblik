<?php

declare(strict_types=1);

namespace App\Tests\Integration\Twig\Components;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;

/**
 * Checks that the components of the prototype draw their parts with classes, not with style
 * attributes, and that each chart has a hidden data table for screen readers.
 */
final class ChartComponentsTest extends KernelTestCase
{
    use InteractsWithTwigComponents;

    public function testSparkline(): void
    {
        $crawler = $this->renderTwigComponent('Sparkline', ['values' => [1.0, 3.0, 2.0], 'cssClass' => 'series-info'])->crawler();

        self::assertSame('spark series-info', $crawler->filter('svg')->attr('class'));
        self::assertSame('true', $crawler->filter('svg')->attr('aria-hidden'));
        self::assertSame('M0.0,29.0 L60.0,3.0 L120.0,16.0', $crawler->filter('path')->attr('d'));
    }

    public function testSparklineWithOneValueIsNotDrawn(): void
    {
        self::assertSame('', trim((string) $this->renderTwigComponent('Sparkline', ['values' => [1.0], 'cssClass' => 'series-info'])));
    }

    public function testLineChart(): void
    {
        $rendered = $this->renderTwigComponent('LineChart', [
            'id' => 'trend',
            'caption' => 'Rettidighed pr. måned',
            'labels' => ['jan', 'feb'],
            'series' => [
                ['name' => 'Afhentning', 'cssClass' => 'series-info', 'values' => [89.6, null]],
                ['name' => 'Afsætning', 'cssClass' => 'series-blue', 'values' => [75.0, 80.0], 'dashed' => true],
            ],
            'max' => 100.0,
            'target' => 90.0,
            'targetLabel' => 'Mål 90 %',
            'unit' => 'percent',
        ]);
        $crawler = $rendered->crawler();

        self::assertSame('trend-caption', $crawler->filter('figure')->attr('aria-labelledby'));
        self::assertSame('Rettidighed pr. måned', $crawler->filter('#trend-caption')->text());
        self::assertSame('true', $crawler->filter('svg')->attr('aria-hidden'));
        self::assertSame(['ch-line series-info', 'ch-line series-blue ch-line--dashed'], $crawler->filter('path.ch-line')->each(static fn ($path): ?string => $path->attr('class')));
        self::assertCount(5, $crawler->filter('line.ch-grid'));
        self::assertSame(['0 %', '25 %', '50 %', '75 %', '100 %'], $crawler->filter('text.ch-ytick')->each(static fn ($text): string => $text->text()));
        self::assertSame(['jan', 'feb'], $crawler->filter('text.ch-xtick')->each(static fn ($text): string => $text->text()));
        self::assertCount(1, $crawler->filter('line.ch-target'));
        self::assertSame('Mål 90 %', $crawler->filter('text.ch-target-label')->text());
        // The legend shows the series, and a dashed series has a dashed swatch.
        self::assertSame(['Afhentning', 'Afsætning'], $crawler->filter('.legend li')->each(static fn ($item): string => $item->text()));
        self::assertCount(1, $crawler->filter('.legend__swatch--dashed'));
        // The hidden data table has each value, and "–" for no value.
        self::assertSame(['Periode', 'Afhentning', 'Afsætning'], $crawler->filter('.sr-only table th[scope="col"]')->each(static fn ($cell): string => $cell->text()));
        self::assertSame(['jan', '89,6 %', '75 %', 'feb', '–', '80 %'], $crawler->filter('.sr-only table tbody th, .sr-only table tbody td')->each(static fn ($cell): string => $cell->text()));
        self::assertStringNotContainsString('style=', (string) $rendered);
    }

    public function testLineChartWithOneSeriesHasNoLegendAndNoTarget(): void
    {
        $crawler = $this->renderTwigComponent('LineChart', [
            'id' => 'one',
            'caption' => 'Ture',
            'labels' => ['jan', 'feb'],
            'series' => [['name' => 'Ture', 'cssClass' => 'series-info', 'values' => [1000.0, 2000.0]]],
            'unit' => 'count',
        ])->crawler();

        self::assertCount(0, $crawler->filter('.legend'));
        self::assertCount(0, $crawler->filter('.ch-target'));
        self::assertSame(['0', '625', '1.250', '1.875', '2.500'], $crawler->filter('text.ch-ytick')->each(static fn ($text): string => $text->text()));
        self::assertSame(['jan', '1.000', 'feb', '2.000'], $crawler->filter('.sr-only table tbody th, .sr-only table tbody td')->each(static fn ($cell): string => $cell->text()));
    }

    public function testColumnChart(): void
    {
        $rendered = $this->renderTwigComponent('ColumnChart', [
            'id' => 'bands',
            'caption' => 'Forsinkede afsætninger',
            'labels' => ['jan', 'feb'],
            'series' => [
                ['name' => '5–15 min', 'cssClass' => 'series-warning', 'values' => [2.0, 0.0]],
                ['name' => 'Over 30 min', 'cssClass' => 'series-danger', 'values' => [1.0, 3.0]],
            ],
        ]);
        $crawler = $rendered->crawler();

        self::assertSame(['ch-bar series-warning', 'ch-bar series-danger', 'ch-bar series-danger'], $crawler->filter('rect.ch-bar')->each(static fn ($bar): ?string => $bar->attr('class')));
        self::assertSame('jan · Over 30 min: 1', $crawler->filter('rect.ch-bar title')->eq(1)->text());
        self::assertSame(['jan', '2', '1', 'feb', '0', '3'], $crawler->filter('.sr-only table tbody th, .sr-only table tbody td')->each(static fn ($cell): string => $cell->text()));
        self::assertCount(2, $crawler->filter('.legend li'));
        self::assertStringNotContainsString('style=', (string) $rendered);
    }

    public function testDistributionBar(): void
    {
        $rendered = $this->renderTwigComponent('DistributionBar', [
            'id' => 'split',
            'caption' => 'Sidste afsætning pr. vognløb',
            'total' => '4 vognløb',
            'segments' => [
                ['name' => 'Til tiden', 'cssClass' => 'series-success', 'value' => 3],
                ['name' => '5–15 min. forsinket', 'cssClass' => 'series-warning', 'value' => 1],
                ['name' => 'Over 30 min. forsinket', 'cssClass' => 'series-danger', 'value' => 0],
            ],
        ]);
        $crawler = $rendered->crawler();

        self::assertSame('Sidste afsætning pr. vognløb', $crawler->filter('#split-caption .dist__label-text')->text());
        self::assertSame('4 vognløb', $crawler->filter('.dist__total')->text());
        // A part with no share is in the legend, but not in the bar.
        self::assertSame(['series-success', 'series-warning'], $crawler->filter('svg rect')->each(static fn ($rect): ?string => $rect->attr('class')));
        self::assertSame('75', $crawler->filter('svg rect')->eq(1)->attr('x'));
        self::assertSame('25', $crawler->filter('svg rect')->eq(1)->attr('width'));
        self::assertSame(
            ['Til tiden 3 75,0 %', '5–15 min. forsinket 1 25,0 %', 'Over 30 min. forsinket 0 0,0 %'],
            $crawler->filter('.dist__item')->each(static fn ($item): string => $item->text()),
        );
        self::assertStringNotContainsString('style=', (string) $rendered);
    }

    public function testTile(): void
    {
        $rendered = $this->renderTwigComponent('Tile', [
            'label' => 'Afhentning pr. borger',
            'value' => '89,6 %',
            'tone' => 'warn',
            'change' => '+2,0 pct.point',
            'changeTone' => 'up',
            'changeIcon' => 'lucide:arrow-up-right',
            'spark' => [80.0, 89.6],
            'sparkClass' => 'series-info',
            'note' => 'Mål 90 %',
            'test' => 'pickup',
        ]);
        $crawler = $rendered->crawler();

        self::assertSame('Afhentning pr. borger', $crawler->filter('.tile__label')->text());
        self::assertSame('89,6 %', $crawler->filter('[data-test="pickup-value"]')->text());
        self::assertSame('tile__value is-warn', $crawler->filter('.tile__value')->attr('class'));
        self::assertSame('+2,0 pct.point', $crawler->filter('[data-test="pickup-change"]')->text());
        self::assertSame('trend trend--up', $crawler->filter('.trend')->attr('class'));
        self::assertCount(1, $crawler->filter('.trend svg'));
        self::assertCount(1, $crawler->filter('svg.spark'));
        self::assertSame('Mål 90 %', $crawler->filter('.tile__note')->text());
        self::assertStringNotContainsString('style=', (string) $rendered);
    }

    public function testTileWithNoChange(): void
    {
        $crawler = $this->renderTwigComponent('Tile', ['label' => 'Over 30 minutter', 'value' => '–', 'test' => 'over-30'])->crawler();

        self::assertSame('tile__value', $crawler->filter('.tile__value')->attr('class'));
        self::assertSame('trend trend--none', $crawler->filter('.trend')->attr('class'));
        self::assertSame('ingen sammenligning', $crawler->filter('.trend')->text());
        self::assertCount(0, $crawler->filter('.tile__note'));
        self::assertCount(0, $crawler->filter('svg.spark'));
    }

    public function testPanel(): void
    {
        $crawler = $this->renderTwigComponent('Panel', ['title' => 'Den valgte periode', 'subtitle' => 'Sidste afsætning'], 'Indhold', ['foot' => 'Kilde'])->crawler();

        self::assertSame('Den valgte periode', $crawler->filter('h2.panel__title')->text());
        self::assertSame('Sidste afsætning', $crawler->filter('.panel__sub')->text());
        self::assertSame('Indhold', $crawler->filter('.panel__body')->text());
        self::assertSame('Kilde', $crawler->filter('.panel__foot')->text());
    }

    public function testPanelWithNoSubtitleAndNoFoot(): void
    {
        $crawler = $this->renderTwigComponent('Panel', ['title' => 'Den valgte periode'], 'Indhold')->crawler();

        self::assertCount(0, $crawler->filter('.panel__sub'));
        self::assertCount(0, $crawler->filter('.panel__foot'));
        self::assertSame('Indhold', $crawler->filter('.panel__body')->text());
    }

    /**
     * The help text opens with the native popover attribute, so it needs no script.
     */
    public function testHelpPopover(): void
    {
        $crawler = $this->renderTwigComponent('HelpPopover', ['id' => 'help-pickup', 'title' => 'Rettidig afhentning'], 'Sådan regnes det ud.')->crawler();

        self::assertSame('help-pickup', $crawler->filter('button')->attr('popovertarget'));
        self::assertSame('Sådan er Rettidig afhentning regnet ud', $crawler->filter('button')->attr('aria-label'));
        self::assertSame('', $crawler->filter('#help-pickup')->attr('popover'));
        self::assertSame('Rettidig afhentning', $crawler->filter('#help-pickup .help__title')->text());
        self::assertSame('Sådan regnes det ud.', $crawler->filter('#help-pickup .help__body')->text());
    }
}
