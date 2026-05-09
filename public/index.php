<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Komodo — Research Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <a class="skip-link" href="#main">Skip to content</a>

    <header class="site-header">
        <div class="wrap">
            <h1 class="site-title">Komodo</h1>
            <p class="site-subtitle">Cybersecurity Event Study Research Platform</p>
            <p class="env-note" role="status">Static placeholder metrics — database not connected (v0.1).</p>
        </div>
    </header>

    <main id="main" class="wrap">
        <section class="panel" id="dataset-summary" aria-labelledby="dataset-summary-heading">
            <h2 id="dataset-summary-heading">Dataset Summary</h2>
            <p class="section-lead">Base tables in <code class="inline-code">gecko_research_database_prod</code> (placeholder row counts).</p>
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">Table</th>
                            <th scope="col" class="num">Rows</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>companies</td><td class="num">68</td></tr>
                        <tr><td>securities</td><td class="num">69</td></tr>
                        <tr><td>cyber_events</td><td class="num">50</td></tr>
                        <tr><td>cyber_event_dates</td><td class="num">101</td></tr>
                        <tr><td>cyber_event_features</td><td class="num">50</td></tr>
                        <tr><td>cyber_event_impacts</td><td class="num">50</td></tr>
                        <tr><td>cyber_event_securities</td><td class="num">50</td></tr>
                        <tr><td>market_calendar</td><td class="num">5,113</td></tr>
                        <tr><td>security_daily_prices</td><td class="num">0</td></tr>
                        <tr><td>index_daily_prices</td><td class="num">0</td></tr>
                        <tr><td>cyber_event_sources</td><td class="num">0</td></tr>
                        <tr><td>event_study_runs</td><td class="num">0</td></tr>
                        <tr><td>event_study_results</td><td class="num">0</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel" id="event-readiness" aria-labelledby="event-readiness-heading">
            <h2 id="event-readiness-heading">Event Readiness</h2>
            <p class="section-lead">Event-study readiness from analytical views (placeholder counts).</p>
            <ul class="kpi-row">
                <li class="kpi">
                    <span class="kpi-label"><code class="inline-code">vw_event_study_event_readiness</code></span>
                    <span class="kpi-value">50</span>
                    <span class="kpi-hint">rows</span>
                </li>
                <li class="kpi">
                    <span class="kpi-label"><code class="inline-code">vw_event_window_boundaries</code></span>
                    <span class="kpi-value">350</span>
                    <span class="kpi-hint">rows</span>
                </li>
            </ul>
        </section>

        <section class="panel" id="market-import" aria-labelledby="market-import-heading">
            <h2 id="market-import-heading">Market Data Import Plan</h2>
            <p class="section-lead">Import targeting and calendar coverage (placeholder counts).</p>
            <ul class="kpi-row">
                <li class="kpi">
                    <span class="kpi-label"><code class="inline-code">vw_market_data_import_plan</code></span>
                    <span class="kpi-value">69</span>
                    <span class="kpi-hint">rows</span>
                </li>
                <li class="kpi">
                    <span class="kpi-label"><code class="inline-code">vw_security_price_import_targets</code></span>
                    <span class="kpi-value">69</span>
                    <span class="kpi-hint">rows</span>
                </li>
                <li class="kpi">
                    <span class="kpi-label"><code class="inline-code">vw_us_trading_days</code></span>
                    <span class="kpi-value">3,520</span>
                    <span class="kpi-hint">rows</span>
                </li>
            </ul>
        </section>

        <section class="panel" id="data-gaps" aria-labelledby="data-gaps-heading">
            <h2 id="data-gaps-heading">Data Gaps</h2>
            <p class="section-lead">Tables and areas with no or minimal loaded data (placeholders).</p>
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">Gap</th>
                            <th scope="col">Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Security daily prices</td>
                            <td><code class="inline-code">security_daily_prices</code> — 0 rows loaded.</td>
                        </tr>
                        <tr>
                            <td>Index daily prices</td>
                            <td><code class="inline-code">index_daily_prices</code> — 0 rows loaded.</td>
                        </tr>
                        <tr>
                            <td>Event sources</td>
                            <td><code class="inline-code">cyber_event_sources</code> — 0 rows.</td>
                        </tr>
                        <tr>
                            <td>Event study outputs</td>
                            <td><code class="inline-code">event_study_runs</code> / <code class="inline-code">event_study_results</code> — 0 rows.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel" id="research-quality" aria-labelledby="research-quality-heading">
            <h2 id="research-quality-heading">Research Quality Flags</h2>
            <p class="section-lead">QA and design diagnostics from views (placeholder row counts).</p>
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">View</th>
                            <th scope="col" class="num">Rows</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code class="inline-code">vw_event_contamination_flags</code></td><td class="num">50</td></tr>
                        <tr><td><code class="inline-code">vw_event_impact_quality_flags</code></td><td class="num">50</td></tr>
                        <tr><td><code class="inline-code">vw_event_research_readiness_flags</code></td><td class="num">50</td></tr>
                        <tr><td><code class="inline-code">vw_event_same_ticker_window_overlaps</code></td><td class="num">13</td></tr>
                        <tr><td><code class="inline-code">vw_event_nearby_cyber_clusters</code></td><td class="num">7</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel panel-muted" id="future-analysis" aria-labelledby="future-analysis-heading">
            <h2 id="future-analysis-heading">Future Analysis / ML</h2>
            <p class="section-lead">Reserved for event-study outputs, model runs, and diagnostics. Not implemented in v0.1.</p>
            <ul class="kpi-row">
                <li class="kpi">
                    <span class="kpi-label"><code class="inline-code">event_study_runs</code></span>
                    <span class="kpi-value">0</span>
                    <span class="kpi-hint">rows</span>
                </li>
                <li class="kpi">
                    <span class="kpi-label"><code class="inline-code">event_study_results</code></span>
                    <span class="kpi-value">0</span>
                    <span class="kpi-hint">rows</span>
                </li>
            </ul>
            <p class="future-note">When connected, this section will summarize run history, key metrics, and model artifacts from the research pipeline.</p>
        </section>
    </main>

    <footer class="site-footer">
        <div class="wrap">
            <p>Komodo v0.1 — local research dashboard. Read-only; no database connection.</p>
        </div>
    </footer>
</body>
</html>
