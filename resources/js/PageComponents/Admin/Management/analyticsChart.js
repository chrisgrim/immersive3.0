// The Insights page's one-series line chart, as plain SVG geometry: the
// line and area paths, the points to hover, and the axis ticks.

export const niceStep = (raw) => {
    // Works below 1 too (click rates are fractions); never zero.
    const value = raw > 0 ? raw : 1
    const power = 10 ** Math.floor(Math.log10(value))
    const scaled = value / power
    return (scaled <= 1 ? 1 : scaled <= 2 ? 2 : scaled <= 5 ? 5 : 10) * power
}

// Axis labels for counts: 1.5k, 1.2M.
export const shortNumber = (v) => {
    if (v >= 1e6) return `${Math.round(v / 1e5) / 10}M`
    if (v >= 1000) return `${Math.round(v / 100) / 10}k`
    return String(Math.round(v * 100) / 100)
}

export const formatDay = (date) => {
    if (!date) return ''
    const iso = date.length === 10 ? `${date}T00:00:00Z` : `${date.replace(' ', 'T')}Z`
    return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', timeZone: 'UTC' })
}

// points: [{ day, ... }], value: (point) => number
export const lineChart = (points, value) => {
    const width = 1000
    const height = 260
    const left = 44
    const right = 12
    const top = 16
    const bottom = 28
    const max = Math.max(1, ...points.map(value))
    // Whole numbers only: these are counts.
    const step = Math.max(1, niceStep(max / 3))
    const yMax = Math.max(step, Math.ceil(max / step) * step)
    const plotWidth = width - left - right
    const plotHeight = height - top - bottom
    const x = (i) => left + (points.length > 1 ? (i / (points.length - 1)) * plotWidth : plotWidth / 2)
    const y = (v) => top + plotHeight - (v / yMax) * plotHeight

    const coords = points.map((point, i) => ({ x: x(i), y: y(value(point)), point }))
    const line = coords.map((c, i) => `${i ? 'L' : 'M'}${c.x.toFixed(1)},${c.y.toFixed(1)}`).join(' ')
    const area = coords.length
        ? `${line} L${coords[coords.length - 1].x.toFixed(1)},${y(0)} L${coords[0].x.toFixed(1)},${y(0)} Z`
        : ''

    const yTicks = []
    for (let v = 0; v <= yMax; v += step) {
        yTicks.push({ value: v, y: y(v), label: shortNumber(v) })
    }

    const xCount = Math.min(5, points.length)
    const xTicks = xCount > 1
        ? Array.from({ length: xCount }, (_, k) => {
            const i = Math.round((k / (xCount - 1)) * (points.length - 1))
            return { day: points[i].day, x: x(i), label: formatDay(points[i].day), anchor: k === 0 ? 'start' : k === xCount - 1 ? 'end' : 'middle' }
        })
        : []

    return { width, height, left, right, top, bottom, coords, line, area, yTicks, xTicks }
}

// The point nearest the mouse, as an index into chart.coords (null if none).
export const nearestIndex = (chart, event) => {
    const coords = chart.coords
    if (!coords.length) return null
    const box = event.currentTarget.getBoundingClientRect()
    const svgX = ((event.clientX - box.left) / box.width) * chart.width
    let nearest = 0
    coords.forEach((c, i) => {
        if (Math.abs(c.x - svgX) < Math.abs(coords[nearest].x - svgX)) nearest = i
    })
    return nearest
}

// Several series on one chart, the way Google Search Console draws its
// performance chart: each series is scaled to its own range (so clicks and
// impressions both fill the height), and a series marked `invert` (average
// position, where 1 is best) has its best value at the top. The y axis is
// labelled only while a single series is shown; the tooltip carries every
// value. series: [{ key, value: (point) => number|null, invert?, whole? }]
export const multiLineChart = (points, series) => {
    const width = 1000
    const height = 260
    const left = series.length === 1 ? 44 : 12
    const right = 12
    const top = 16
    const bottom = 28
    const plotWidth = width - left - right
    const plotHeight = height - top - bottom
    const x = (i) => left + (points.length > 1 ? (i / (points.length - 1)) * plotWidth : plotWidth / 2)

    const lines = series.map((s) => {
        const values = points.map(s.value).filter((v) => v !== null && v !== undefined)
        const max = values.length ? Math.max(...values) : 1
        const min = values.length ? Math.min(...values) : 0
        // Counts and rates start at 0. Position (best is 1) uses a nice range
        // around its own values, best at the top, so its changes show.
        let step = niceStep((s.invert ? max - min : max) / 3)
        // Counts step in whole numbers (no "0.5 clicks").
        if (s.whole) step = Math.max(1, step)
        let low = 0
        let high = Math.max(step, Math.ceil(max / step) * step)
        if (s.invert) {
            if (max === min) step = 1
            low = Math.max(1, Math.floor(min / step) * step)
            high = Math.max(low + step, Math.ceil(max / step) * step)
        }
        const y = (v) => (s.invert
            ? top + ((v - low) / (high - low)) * plotHeight
            : top + plotHeight - ((v - low) / (high - low)) * plotHeight)
        const coords = points.map((point, i) => {
            const v = s.value(point)
            return v === null || v === undefined ? null : { x: x(i), y: y(v) }
        })
        let path = ''
        coords.forEach((c, i) => {
            if (c) path += `${coords[i - 1] ? 'L' : 'M'}${c.x.toFixed(1)},${c.y.toFixed(1)} `
        })
        // A value with no neighbour draws no line, so it gets a dot.
        const dots = coords.filter((c, i) => c && !coords[i - 1] && !coords[i + 1])
        // Ticks on round multiples of the step inside the range, plus the
        // range's own start when that is not one (position's 1).
        const tickValues = []
        if (Math.abs(low / step - Math.round(low / step)) > 1e-9) tickValues.push(low)
        for (let k = Math.ceil(low / step - 1e-9); k * step <= high + 1e-9; k++) tickValues.push(k * step)
        const ticks = values.length ? tickValues.map((v) => ({ value: v, y: y(v), label: s.format ? s.format(v) : shortNumber(v) })) : []

        return { key: s.key, path: path.trim(), coords, dots, ticks }
    })

    const xCount = Math.min(5, points.length)
    const xTicks = xCount > 1
        ? Array.from({ length: xCount }, (_, k) => {
            const i = Math.round((k / (xCount - 1)) * (points.length - 1))
            return { day: points[i].day, x: x(i), label: formatDay(points[i].day), anchor: k === 0 ? 'start' : k === xCount - 1 ? 'end' : 'middle' }
        })
        : []

    // For hover: the x of every point, so nearestIndex works unchanged.
    const coords = points.map((point, i) => ({ x: x(i), point }))

    return { width, height, left, right, top, bottom, lines, xTicks, coords }
}
