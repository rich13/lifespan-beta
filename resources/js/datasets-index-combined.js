/**
 * Combined multi-dataset chart on /datasets (D3 global, jQuery).
 * Scoped under .js-datasets-combined-root; guarded so duplicate script tags cannot double-bind.
 *
 * Verbose logging: localStorage.setItem('debug_datasets_combined', '1') then reload.
 * Always logged: console.info on successful init (with debug hint), console.error on fetch/render failures and fatal aborts (missing D3, root, or URL).
 */
// eslint-disable-next-line no-console
console.warn('[datasets-combined] ES module evaluated (if this is missing, the Vite bundle did not load).');

function runDatasetsCombinedChart($) {
    const log = (...args) => {
        // eslint-disable-next-line no-console
        console.log('[datasets-combined]', ...args);
    };
    const logError = (...args) => {
        // eslint-disable-next-line no-console
        console.error('[datasets-combined]', ...args);
    };
    const debug = () => window.localStorage.getItem('debug_datasets_combined') === '1';
    const dlog = (...args) => {
        if (debug()) {
            log(...args);
        }
    };

    if (typeof window.d3 === 'undefined') {
        logError('abort: window.d3 is undefined (D3 script missing or blocked?)');
        return;
    }

    const $root = $('.js-datasets-combined-root').first();
    if (!$root.length) {
        logError('abort: no .js-datasets-combined-root in DOM');
        return;
    }

    if ($root.data('lifespanCombinedChartInit')) {
        dlog('abort: already initialised on this root');
        return;
    }

    const d3 = window.d3;

    const combinedUrl = String($root.data('combined-url') || '');
    const $chart = $root.find('.js-datasets-combined-chart');
    const $message = $root.find('.js-datasets-combined-message');
    const $form = $root.find('.js-datasets-combined-form');
    const $legend = $root.find('.js-datasets-combined-legend');

    if (!combinedUrl || !$chart.length) {
        logError('abort: missing combinedUrl or chart node', { combinedUrl, chartNodes: $chart.length });
        return;
    }

    $root.data('lifespanCombinedChartInit', true);
    // eslint-disable-next-line no-console
    console.info(
        '[datasets-combined] initialised. Verbose logs: localStorage.setItem("debug_datasets_combined", "1") then reload.'
    );
    dlog('init', { combinedUrl, chartWidth: $chart.innerWidth(), formNodes: $form.length });

    function updateLegend(plotLines, lineColorScale, legendOptions) {
        if (!$legend.length) {
            return;
        }
        if (typeof lineColorScale !== 'function') {
            return;
        }
        const normalised = legendOptions && legendOptions.normalised;
        $legend.empty();
        plotLines.forEach((line) => {
            const stroke = lineColorScale(String(line.slug));
            const $row = $('<span>').addClass('d-inline-flex align-items-center me-3 mb-1');
            $row.append(
                $('<span>')
                    .addClass('rounded-circle d-inline-block me-1 flex-shrink-0')
                    .css({ width: '10px', height: '10px', backgroundColor: stroke })
            );
            let label = line.name || line.slug;
            const unit = String(line.unit || '').trim();
            if (unit) {
                label += ' · ' + unit;
            }
            if (normalised) {
                label += ' (0–1)';
            }
            $row.append($('<span>').text(label));
            $legend.append($row);
        });
    }
    function showMessage(text, isError) {
        if (!$message.length) {
            return;
        }
        $message.removeClass('d-none text-danger text-muted');
        $message.addClass(isError ? 'text-danger' : 'text-muted');
        $message.text(text);
    }

    function selectedSlugs() {
        const slugs = [];
        $root.find('.js-dataset-combined-toggle:checked').each(function () {
            const s = String($(this).data('slug') || '').trim();
            if (s) {
                slugs.push(s);
            }
        });
        return slugs;
    }

    function toFiniteNumber(value) {
        const n = Number(value);
        return Number.isFinite(n) ? n : NaN;
    }

    /**
     * @param {unknown[]} points
     * @returns {{ t_mid: number, value: number, t_start?: number, t_end?: number }[]}
     */
    function sanitiseLinePoints(points) {
        if (!Array.isArray(points)) {
            return [];
        }
        return points
            .map((p) => {
                const tMid = toFiniteNumber(p && p.t_mid);
                const val = toFiniteNumber(p && p.value);
                if (!Number.isFinite(tMid) || !Number.isFinite(val)) {
                    return null;
                }
                const row = { t_mid: tMid, value: val };
                if (p && Number.isFinite(Number(p.t_start))) {
                    row.t_start = Number(p.t_start);
                }
                if (p && Number.isFinite(Number(p.t_end))) {
                    row.t_end = Number(p.t_end);
                }
                return row;
            })
            .filter(Boolean);
    }

    function renderChart(payload) {
        const lines = Array.isArray(payload.lines) ? payload.lines : [];
        const activeLines = lines.filter((line) => {
            if (line.missing_dataset || line.missing_series) {
                return false;
            }
            return Array.isArray(line.points) && line.points.length > 0;
        });

        dlog('renderChart: payload summary', {
            lineCount: lines.length,
            activeLineCount: activeLines.length,
            lines: lines.map((l) => ({
                slug: l.slug,
                missing_dataset: !!l.missing_dataset,
                missing_series: !!l.missing_series,
                pointCount: Array.isArray(l.points) ? l.points.length : null,
            })),
        });

        $chart.empty();

        if (activeLines.length === 0) {
            const anyLine = lines.some((l) => !l.missing_dataset && !l.missing_series);
            dlog('renderChart: no drawable lines', { anyLine });
            showMessage(
                anyLine
                    ? 'No points in this range for the selected series. Try another code or year span.'
                    : 'No data to plot. Check series code exists for each selected dataset.',
                !anyLine
            );
            if ($legend.length) {
                $legend.empty();
            }
            return;
        }

        const normaliseY = $form.find('.js-combined-normalise-y').prop('checked');

        const sanitisedLines = activeLines
            .map((line) => ({
                ...line,
                points: sanitiseLinePoints(line.points),
            }))
            .filter((line) => line.points.length > 0);

        if (sanitisedLines.length === 0) {
            const anyLine = lines.some((l) => !l.missing_dataset && !l.missing_series);
            dlog('renderChart: no drawable lines after sanitise', { anyLine });
            showMessage(
                anyLine
                    ? 'No points in this range for the selected series. Try another code or year span.'
                    : 'No data to plot. Check series code exists for each selected dataset.',
                !anyLine
            );
            if ($legend.length) {
                $legend.empty();
            }
            return;
        }

        showMessage('', false);
        $message.addClass('d-none');

        try {
            const lineColorScale = d3
                .scaleOrdinal(d3.schemeCategory10)
                .domain(sanitisedLines.map((line) => String(line.slug)));

            let plotLines;
            let yValue;

            if (normaliseY) {
                plotLines = sanitisedLines.map((line) => {
                    const vals = line.points.map((p) => p.value);
                    const vmin = Math.min(...vals);
                    const vmax = Math.max(...vals);
                    const span = vmax - vmin || 1;
                    return {
                        ...line,
                        points: line.points.map((p) => ({
                            ...p,
                            normY: (p.value - vmin) / span,
                        })),
                    };
                });
                yValue = (d) => d.normY;
            } else {
                plotLines = sanitisedLines;
                yValue = (d) => d.value;
            }

            const allPoints = plotLines.flatMap((line) =>
                line.points.map((p) => ({ ...p, line }))
            );

            const margin = { top: 16, right: 24, bottom: 44, left: 64 };
            const rawInner = $chart.innerWidth();
            const width = Math.max(320, rawInner || 700);
            const height = 340;
            const innerW = width - margin.left - margin.right;
            const innerH = height - margin.top - margin.bottom;

            dlog('renderChart: dimensions', { rawInner, width, height, pointCount: allPoints.length, normaliseY });
            if (allPoints.length) {
                dlog('renderChart: first point sample', allPoints[0]);
            }

            const svg = d3
                .select($chart.get(0))
                .append('svg')
                .attr('viewBox', `0 0 ${width} ${height}`)
                .attr('width', '100%')
                .attr('height', height);

            const g = svg.append('g').attr('transform', `translate(${margin.left},${margin.top})`);

            const xExtent = d3.extent(allPoints, (d) => d.t_mid);
            const x = d3.scaleLinear().domain(xExtent).nice().range([0, innerW]);

            let yLo = d3.min(allPoints, (d) => yValue(d));
            let yHi = d3.max(allPoints, (d) => yValue(d));
            if (!Number.isFinite(yLo) || !Number.isFinite(yHi)) {
                throw new Error('Could not derive a Y scale from the data.');
            }
            if (yLo === yHi) {
                const pad = normaliseY ? 0.05 : Math.abs(yLo) * 0.05 || 0.5;
                yLo -= pad;
                yHi += pad;
            }

            const y = d3.scaleLinear().domain([yLo, yHi]).nice().range([innerH, 0]);

            dlog('renderChart: scales', {
                xDomain: x.domain(),
                yDomain: y.domain(),
                normaliseY,
            });

            const lineGen = d3
                .line()
                .defined((d) => Number.isFinite(yValue(d)))
                .x((d) => x(d.t_mid))
                .y((d) => y(yValue(d)));

            g.append('g')
                .attr('transform', `translate(0,${innerH})`)
                .call(d3.axisBottom(x).ticks(10).tickFormat(d3.format('d')));

            const yAxis = d3.axisLeft(y).ticks(6);
            if (normaliseY) {
                yAxis.tickFormat(d3.format('.2f'));
            }
            g.append('g').call(yAxis);

            const units = [
                ...new Set(
                    plotLines
                        .map((l) => String(l.unit || '').trim())
                        .filter(Boolean)
                ),
            ];
            let yAxisCaption;
            if (normaliseY) {
                yAxisCaption = 'Normalised value (0–1 per series)';
            } else if (units.length === 1) {
                yAxisCaption = `Value (${units[0]})`;
            } else if (units.length > 1) {
                yAxisCaption = 'Value (mixed units — same numeric scale)';
            } else {
                yAxisCaption = 'Value (shared scale)';
            }

            g.append('text')
                .attr('transform', 'rotate(-90)')
                .attr('y', 0 - margin.left + 14)
                .attr('x', 0 - innerH / 2)
                .attr('dy', '0.8em')
                .style('text-anchor', 'middle')
                .attr('fill', 'currentColor')
                .attr('class', 'small')
                .text(yAxisCaption);

            plotLines.forEach((line) => {
                const stroke = lineColorScale(String(line.slug));
                g.append('path')
                    .datum(line.points)
                    .attr('fill', 'none')
                    .attr('stroke', stroke)
                    .attr('stroke-width', 2)
                    .attr('d', lineGen);
            });

            updateLegend(plotLines, lineColorScale, { normalised: normaliseY });

            const skipped = lines.filter((l) => l.missing_dataset || l.missing_series);
            if (skipped.length) {
                const parts = skipped
                    .map((l) => l.slug + (l.missing_dataset ? ' (missing)' : ' (no series)'))
                    .join(', ');
                showMessage('Some selections were skipped: ' + parts, false);
                $message.removeClass('d-none');
            }

            dlog('renderChart: done');
        } catch (err) {
            logError('renderChart failed', err);
            showMessage(err.message || 'Chart render failed.', true);
            $chart.empty();
            if ($legend.length) {
                $legend.empty();
            }
        }
    }

    function loadChart() {
        const slugs = selectedSlugs();
        if (slugs.length === 0) {
            dlog('loadChart: no slugs selected');
            $chart.empty();
            if ($legend.length) {
                $legend.empty();
            }
            showMessage('Tick at least one dataset to plot.', true);
            return;
        }

        const url = new URL(combinedUrl, window.location.origin);
        url.searchParams.set('datasets', slugs.join(','));
        url.searchParams.set(
            'series_key',
            String($form.find('.js-combined-series-key').val() || '').trim() || 'OWID_WRL'
        );
        const fromVal = $form.find('.js-combined-from-year').val();
        const toVal = $form.find('.js-combined-to-year').val();
        if (fromVal !== '' && fromVal !== null) {
            url.searchParams.set('from', String(fromVal));
        }
        if (toVal !== '' && toVal !== null) {
            url.searchParams.set('to', String(toVal));
        }

        showMessage('Loading…', false);
        $message.removeClass('d-none');

        const requestUrl = url.toString();
        dlog('loadChart: fetching', requestUrl);

        fetch(requestUrl, { headers: { Accept: 'application/json' } })
            .then((response) => {
                dlog('loadChart: response', { status: response.status, ok: response.ok });
                if (!response.ok) {
                    return response.json().then(
                        (body) => {
                            throw new Error(body.message || `Request failed (${response.status})`);
                        },
                        () => {
                            throw new Error(`Request failed (${response.status})`);
                        }
                    );
                }
                return response.json();
            })
            .then((data) => {
                dlog('loadChart: JSON keys', data && typeof data === 'object' ? Object.keys(data) : typeof data);
                renderChart(data);
            })
            .catch((err) => {
                logError('fetch failed', err);
                showMessage(err.message || 'Could not load combined data.', true);
                $chart.empty();
                if ($legend.length) {
                    $legend.empty();
                }
            });
    }

    $form.on('change', '.js-combined-normalise-y', function () {
        loadChart();
    });

    $form.on('submit', function (e) {
        e.preventDefault();
        loadChart();
    });

    $root.on('change', '.js-dataset-combined-toggle', function () {
        loadChart();
    });

    loadChart();
}

function scheduleDatasetsCombinedChart() {
    if (typeof window.jQuery === 'function') {
        window.jQuery(runDatasetsCombinedChart);
        return;
    }
    // eslint-disable-next-line no-console
    console.error(
        '[datasets-combined] window.jQuery is not a function at module run; waiting for DOMContentLoaded.'
    );
    window.addEventListener('DOMContentLoaded', function onDomReady() {
        if (typeof window.jQuery === 'function') {
            window.jQuery(runDatasetsCombinedChart);
        } else {
            // eslint-disable-next-line no-console
            console.error(
                '[datasets-combined] jQuery still unavailable after DOMContentLoaded — check head scripts / CSP / ad blockers.'
            );
        }
    });
}

scheduleDatasetsCombinedChart();
