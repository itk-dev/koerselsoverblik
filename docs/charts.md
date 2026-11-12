# Charts and figures

This document explains each chart, tile and table in the user interface. For each figure, it
tells what the figure counts and which data it leaves out.

**The rule:** each chart, tile and table in the user interface has a section in this document. A
pull request that adds or changes a figure, or a filter of its data, updates the section. See
"Rules for the code" in [AGENTS.md](../AGENTS.md).

Each section has these items:

- **Shows:** what the figure shows, with its Danish label.
- **Source:** the class and the method that give the values.
- **Counts:** the rows that the figure counts, and the calculation.
- **Leaves out:** each filter that removes data from the figure. "Nothing" means no filter.
- **No value:** when the figure shows "–", a gap or "Ingen data i perioden."
- **Tone:** the limits of the colours, if the figure has tones.

## Contents

- [Overview](#overview)
- [Punctuality page](#punctuality-page)
  - [Terms](#terms)
  - [Goal status](#goal-status)
  - [Measure tiles](#measure-tiles)
  - [Late drop-offs tile](#late-drop-offs-tile)
  - [More than 30 minutes tile](#more-than-30-minutes-tile)
  - [Punctuality month by month](#punctuality-month-by-month)
  - [The selected period](#the-selected-period)
  - [Delays in bands](#delays-in-bands)
  - [Punctuality for each supplier](#punctuality-for-each-supplier)
  - [The ten routes with the lowest punctuality](#the-ten-routes-with-the-lowest-punctuality)

## Overview

The overview (`/`, `OverviewController`) has no figures yet.

## Punctuality page

The page is `/punctuality` (`PunctualityController`, `ROLE_VIEW_OVERVIEW`). The user selects a
month with `?month=YYYY-MM`. `PunctualityPageBuilder` calculates the month of the page and the 11
months before it with `PunctualityCalculator::calculate()`. `PunctualityPageDto` gives the
figures to the template, `templates/punctuality/index.html.twig`.

### Terms

- **Month:** all dates from the first to the last date of a calendar month. No value, an invalid
  value and a month after the current month give the current month (`MonthDto`).
- **12 months:** the month of the page and the 11 months before it, the oldest first.
- **Measured stop:** a stop with the status `completed` and an actual time, thus with a
  `delay_seconds`.
- **First pickup:** the pickup of a route run with the earliest planned time that is not
  cancelled (`RouteRunCalculator`).
- **Last drop-off:** the drop-off of a route run with the latest planned time that is not
  cancelled (`RouteRunCalculator`).
- **Measured run:** a route run whose first pickup, or last drop-off, is a measured stop. A run
  can be measured at the last drop-off and not at the first pickup.
- **On time:** a delay of at most `APP_PUNCTUALITY_TOLERANCE_MINUTES` × 60 seconds. An early stop
  has a negative delay, so it is on time.
- **Target:** `APP_PUNCTUALITY_TARGET`, in percent.
- **Delay bands:** the measured last drop-offs in four bands. A delay on the edge of two bands is
  in the lower band.
  - on time
  - more than the tolerance, to 15 minutes
  - more than 15 minutes, to 30 minutes
  - more than 30 minutes
- **Supplier:** the supplier of the sub-agreement of a route today. A change of the supplier
  applies to all months. The routes of a sub-agreement with no supplier count as "Ukendt".

### Goal status

- **Shows:** "{count} af {total} målinger når målet" in the "Servicemål" panel.
- **Source:** `PunctualityPageDto::measuresAtTarget()`.
- **Counts:** the number of the four [measure tiles](#measure-tiles) whose value is at or above
  the target. The total is always 4.
- **Leaves out:**
  - a measure with no value. It does not count as a measure that reaches the target.
  - all data that the measure tiles leave out.
- **No value:** none. With no data, the status is "0 af 4 målinger når målet".
- **Tone:** "ok" when all four measures reach the target. Else no tone.

### Measure tiles

There are four tiles: "Afhentning pr. borger", "Afsætning pr. borger", "Afhentning pr. vognløb"
and "Afsætning pr. vognløb".

- **Shows:** for each measure:
  - the value: the share on time in the month, as a percent
  - the change from the previous month, in percentage points
  - a sparkline of the 12 months
  - a note with the target, and the distance to the target or "Målet er nået"
- **Source:** `PunctualityPageDto::measures()`. The values come from
  `StopRowRepository::countMeasuredStops()` for each citizen, and from
  `RouteRunRepository::countRunFigures()` for each route run.
- **Counts:** on time ÷ measured × 100 (`PunctualityCountDto::percent()`):
  - "Afhentning pr. borger": all measured pickups.
  - "Afsætning pr. borger": all measured drop-offs.
  - "Afhentning pr. vognløb": the first pickup of each route run, if it is measured.
  - "Afsætning pr. vognløb": the last drop-off of each route run, if it is measured.
- **Leaves out:**
  - the stops with the status `cancelled` or `pending` (no registration from the driver)
  - the completed stops with no actual time
  - for each route run: all pickups after the first pickup, and all drop-offs before the last
    drop-off
  - for each route run: a run whose first pickup, or last drop-off, is not measured. A run with
    all stops cancelled has no first pickup and no last drop-off.
  - the stops and the runs of the routes of an ignored sub-agreement
    (`IgnoredSubAgreementFilter::apply()`)
  - the dates outside the month
- **No value:**
  - The value is "–" when the month has no measured rows for the measure. It is not 0 %.
  - The change is "–" when the month or the previous month has no value. A change smaller than
    0.05 points shows as "±0".
  - The sparkline has a gap for a month with no value. It has no line when fewer than two months
    have a value.
  - The sparkline has no axis. Its lowest point is the lowest month, not 0 %.
- **Tone:**
  - The value is "ok" at or above the target, "warn" at or above 70 %, and "danger" below 70 %
    (`PunctualityTone`). A value with no data has no tone.
  - The change is good ("up") when the share goes up, and bad ("down") when it goes down
    (`PunctualityExtension::trendTone()`).
  - A value below the target never shows as the target. For example, 89.6 % with a target of
    90 % shows as "89,6 %" (`PunctualityExtension::percent()`).

### Late drop-offs tile

- **Shows:** "Forsinkede afsætninger": the number of late last drop-offs in the month, the change
  from the previous month, and a sparkline of the 12 months. The note "af {count} vognløb" gives
  the number of measured last drop-offs in the month.
- **Source:** `PunctualityPageDto::lateRuns()`, from `RouteRunRepository::countRunFigures()`.
- **Counts:** the measured last drop-offs with a delay of more than the tolerance. This is the
  sum of the three late [delay bands](#terms). The change is a difference of counts, not of
  shares.
- **Leaves out:**
  - the last drop-offs that are on time
  - the runs whose last drop-off is not measured: cancelled, `pending`, or no actual time
  - the runs with all stops cancelled
  - all stops of a run other than the last drop-off
  - the runs of the routes of an ignored sub-agreement
  - the dates outside the month
- **No value:** "–" when the month has no measured last drop-off. It is not 0. The change is "–"
  when the month or the previous month has no value. The sparkline has a gap for such a month.
- **Tone:** the value has no tone. The change is good ("up") when the count goes down, and bad
  ("down") when it goes up.

### More than 30 minutes tile

- **Shows:** "Over 30 minutter": the number of last drop-offs in the month that are more than
  30 minutes late, the change from the previous month, and a sparkline of the 12 months.
- **Source:** `PunctualityPageDto::over30()`, from `RouteRunRepository::countRunFigures()`.
- **Counts:** the measured last drop-offs with a delay of more than 30 × 60 seconds.
- **Leaves out:**
  - the last drop-offs with a delay of at most 30 minutes
  - the runs whose last drop-off is not measured: cancelled, `pending`, or no actual time
  - the runs with all stops cancelled
  - all stops of a run other than the last drop-off
  - the runs of the routes of an ignored sub-agreement
  - the dates outside the month
- **No value:** "–" when the month has no measured last drop-off. The change is "–" when the
  month or the previous month has no value. The sparkline has a gap for such a month.
- **Tone:** the value is "ok" at 0 and "danger" above 0. The change is good ("up") when the count
  goes down, and bad ("down") when it goes up.

### Punctuality month by month

- **Shows:** "Rettidighed måned for måned": a line chart with one line for each of the four
  measures over the 12 months. The lines for each route run are dashed. A horizontal line shows
  the target. The scale is from 0 to 100 %. A hidden table gives the values to screen readers.
- **Source:** the sparkline values of `PunctualityPageDto::measures()`.
- **Counts:** the value of each [measure tile](#measure-tiles), for each of the 12 months.
- **Leaves out:** the same data as the [measure tiles](#measure-tiles), for each month.
- **No value:** a month with no value for a measure has no point. The line of the measure has a
  gap at that month.
- **Tone:** none. The pickups have the colour `series-info`, and the drop-offs have the colour
  `series-blue`.

### The selected period

- **Shows:** "Den valgte periode": a bar with the four [delay bands](#terms) of the last
  drop-offs in the month. The caption gives the number of measured last drop-offs,
  "{count} vognløb". The legend gives the count and the share of each band.
- **Source:** the total of `PunctualityCalculator::calculate()`, from
  `RouteRunRepository::countRunFigures()`.
- **Counts:** the measured last drop-offs in each band. The share is the count of the band ÷ the
  measured last drop-offs × 100.
- **Leaves out:**
  - the runs whose last drop-off is not measured: cancelled, `pending`, or no actual time
  - the runs with all stops cancelled
  - all stops of a run other than the last drop-off
  - the runs of the routes of an ignored sub-agreement
  - the dates outside the month
- **No value:** a band with no runs is only in the legend, with the count 0 and the share 0. A
  month with no measured last drop-off shows an empty bar and "0 vognløb".
- **Tone:** each band has its colour, from on time to more than 30 minutes: `series-success`,
  `series-warning`, `series-coral` and `series-danger`.

### Delays in bands

- **Shows:** "Forsinkelser fordelt på intervaller": a stacked column chart with the three late
  [delay bands](#terms) for each of the 12 months. The lowest band is at the bottom.
- **Source:** `PunctualityPageDto::lateBandHistory()`, from
  `RouteRunRepository::countRunFigures()`.
- **Counts:** the measured last drop-offs in each late band, for each month. The columns show
  counts, not shares.
- **Leaves out:**
  - the last drop-offs that are on time
  - the runs whose last drop-off is not measured: cancelled, `pending`, or no actual time
  - the runs with all stops cancelled
  - all stops of a run other than the last drop-off
  - the runs of the routes of an ignored sub-agreement
  - the dates outside each month
- **No value:** a month with no late run has no column. The chart does not tell a month with no
  data from a month with no late run. The sparklines of the tiles do.
- **Tone:** each band has its colour: `series-warning`, `series-coral` and `series-danger`.

### Punctuality for each supplier

- **Shows:** "Rettidighed pr. leverandør": a table with one row for each supplier in the month.
  The columns:
  - "Leverandør": the name of the supplier, or "Ukendt".
  - "Vognløb": the number of measured last drop-offs.
  - The four measures, as in the [measure tiles](#measure-tiles).
  - "15–30 min": the measured last drop-offs with a delay of more than 15 minutes, to 30 minutes.
  - "Over 30 min": the measured last drop-offs with a delay of more than 30 minutes.
- **Source:** `PunctualityReportDto::$suppliers`, from `PunctualityCalculator::calculate()`.
- **Counts:** the counts of each route, added for the [supplier](#terms) of the route. The
  suppliers are in the order of the name, with "Ukendt" last. The order is the order of the
  bytes, so a name that starts with "Aa" is first.
- **Leaves out:**
  - all data that the measure tiles leave out
  - a supplier with no route run in the month
  - "Vognløb" counts only the runs with a measured last drop-off. It leaves out the cancelled
    runs and the runs with no measurement.
  - the band from the tolerance to 15 minutes. The table has no column for it.
  - the routes of an ignored sub-agreement
- **No value:** a measure with no measured rows shows "–". With no supplier in the month, the
  table shows "Ingen data i perioden."
- **Tone:** the measures have the tones of the measure tiles. "Over 30 min" is "ok" at 0 and
  "danger" above 0.

### The ten routes with the lowest punctuality

- **Shows:** "De ti vognløb med lavest rettidighed": a table with the 10 routes with the lowest
  punctuality at the last drop-off in the month. The columns:
  - "Vognløb": the route name from the extract.
  - "Leverandør": the [supplier](#terms) of the route, or "Ukendt".
  - "Vognløb kørt": the number of measured last drop-offs of the route.
  - "Rettidighed": the share of the measured last drop-offs that are on time.
  - "Forsinkede": the measured last drop-offs that are not on time.
- **Source:** `PunctualityReportDto::$worstRoutes`, from `PunctualityCalculator::calculate()`.
- **Counts:** the measured last drop-offs of each route in the month. The routes are in the order
  of the punctuality, the lowest first. On the same punctuality, the route with more measured
  runs is first, and then the route name decides.
- **Leaves out:**
  - a route with no measured last drop-off in the month
  - all routes after the first 10
  - the runs whose last drop-off is not measured: cancelled, `pending`, or no actual time
  - "Vognløb kørt" leaves out the cancelled runs and the runs with no measurement
  - all stops of a run other than the last drop-off
  - the routes of an ignored sub-agreement
  - the dates outside the month
- **No value:** with no measured last drop-off in the month, the table shows
  "Ingen data i perioden."
- **Tone:** "Rettidighed" has the tones of the [measure tiles](#measure-tiles).
