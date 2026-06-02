import { useMemo, useRef, useEffect, useState } from 'react';
import './views.css';

// Decimal year (e.g. 1994.5 ≈ mid-1994) for proportional placement on the axis.
function decimalYear(dateStr) {
    const d = new Date(dateStr);
    if (isNaN(d)) return null;
    const start = new Date(d.getFullYear(), 0, 1);
    const next  = new Date(d.getFullYear() + 1, 0, 1);
    return d.getFullYear() + (d - start) / (next - start);
}

// Pick a "nice" year step so axis labels land roughly every ~80px.
function niceStep(pxPerYear) {
    const target = 80 / pxPerYear; // desired years between labels
    const steps = [1, 2, 5, 10, 20, 25, 50, 100, 200, 500];
    return steps.find(s => s >= target) ?? 1000;
}

const PAD = 90;          // horizontal padding inside the track (px)
const MIN_SPACING = 158; // min horizontal gap between node centres before stacking
const LANE_GAP = 58;     // vertical distance between stacked lanes

export default function ZoomableTimelineView({ events, onSelect }) {
    const scrollRef = useRef(null);
    const [pxPerYear, setPxPerYear] = useState(60);

    const dated = useMemo(() => {
        return events
            .map(ev => ({ ev, y: decimalYear(ev.event_date) }))
            .filter(d => d.y !== null)
            .sort((a, b) => a.y - b.y);
    }, [events]);

    const { minYear, maxYear } = useMemo(() => {
        if (!dated.length) return { minYear: 0, maxYear: 0 };
        return {
            minYear: Math.floor(dated[0].y),
            maxYear: Math.ceil(dated[dated.length - 1].y),
        };
    }, [dated]);

    const span = Math.max(1, maxYear - minYear);

    // Lay out nodes: greedy lane assignment so labels don't overlap.
    const { nodes, trackWidth, maxLane } = useMemo(() => {
        const laneEnds = []; // last occupied x per lane
        let topLane = 0;
        const placed = dated.map(({ ev, y }) => {
            const x = PAD + (y - minYear) * pxPerYear;
            let lane = laneEnds.findIndex(end => x - end >= MIN_SPACING);
            if (lane === -1) { lane = laneEnds.length; }
            laneEnds[lane] = x;
            topLane = Math.max(topLane, lane);
            return { ev, x, lane };
        });
        return {
            nodes: placed,
            trackWidth: PAD * 2 + span * pxPerYear,
            maxLane: topLane,
        };
    }, [dated, minYear, span, pxPerYear]);

    // Axis sits in the vertical centre; lanes alternate above / below it.
    const sideDepth = Math.ceil((maxLane + 1) / 2);
    const trackHeight = Math.max(300, 120 + sideDepth * LANE_GAP * 2);
    const axisY = trackHeight / 2;

    const ticks = useMemo(() => {
        const step = niceStep(pxPerYear);
        const first = Math.ceil(minYear / step) * step;
        const out = [];
        for (let yr = first; yr <= maxYear; yr += step) {
            out.push({ year: yr, x: PAD + (yr - minYear) * pxPerYear });
        }
        return out;
    }, [minYear, maxYear, pxPerYear]);

    // Start scrolled to the most recent events (right edge).
    useEffect(() => {
        const el = scrollRef.current;
        if (el) el.scrollLeft = el.scrollWidth;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [trackWidth === 0]);

    const zoom = (factor) => setPxPerYear(p => Math.min(800, Math.max(8, Math.round(p * factor))));

    if (!dated.length) {
        return (
            <div className="view-empty">
                <div className="view-empty-icon">📅</div>
                <div>No dated events to plot.</div>
            </div>
        );
    }

    return (
        <div className="ztl">
            <div className="ztl-toolbar">
                <span className="ztl-hint">{minYear}–{maxYear} · {dated.length} events · scroll horizontally</span>
                <div className="ztl-zoom">
                    <button className="ztl-zoom-btn" onClick={() => zoom(1 / 1.6)} disabled={pxPerYear <= 8} aria-label="Zoom out">−</button>
                    <button className="ztl-zoom-btn" onClick={() => zoom(1.6)} disabled={pxPerYear >= 800} aria-label="Zoom in">+</button>
                </div>
            </div>

            <div className="ztl-scroll" ref={scrollRef}>
                <div className="ztl-track" style={{ width: `${trackWidth}px`, height: `${trackHeight}px` }}>
                    {ticks.map(t => (
                        <div key={t.year} className="ztl-tick" style={{ left: `${t.x}px` }}>
                            <span className="ztl-tick-label">{t.year}</span>
                        </div>
                    ))}

                    <div className="ztl-axis" style={{ top: `${axisY}px` }} />

                    {nodes.map(({ ev, x, lane }) => {
                        const above = lane % 2 === 0;
                        const level = Math.floor(lane / 2) + 1;
                        const offset = level * LANE_GAP;
                        const nodeY = above ? axisY - offset : axisY + offset - 28;
                        const connTop = above ? nodeY + 28 : axisY;
                        const connHeight = above ? axisY - (nodeY + 28) : nodeY - axisY;
                        const color = ev.category?.color || 'var(--color-primary)';
                        return (
                            <div key={ev.id}>
                                <div
                                    className="ztl-connector"
                                    style={{ left: `${x}px`, top: `${connTop}px`, height: `${Math.max(0, connHeight)}px` }}
                                />
                                <div className="ztl-dot" style={{ left: `${x}px`, top: `${axisY}px`, background: color }} />
                                <div
                                    className="ztl-node"
                                    style={{ left: `${x}px`, top: `${nodeY}px` }}
                                    onClick={() => onSelect(ev)}
                                >
                                    <div className="ztl-node-card" style={{ borderLeftColor: color }}>
                                        <span className="ztl-node-icon">{ev.category?.icon || '📌'}</span>
                                        <span className="ztl-node-text">
                                            <span className="ztl-node-title">{ev.title}</span>
                                            <span className="ztl-node-date">
                                                {new Date(ev.event_date).toLocaleDateString('en-US', { month: 'short', year: 'numeric' })}
                                            </span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}
