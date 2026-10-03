// The Insights page's one-series line chart, as plain SVG geometry: the
// line and area paths, the points to hover, and the axis ticks.

export const niceStep = (raw) => {
    const power = 10 ** Math.floor(Math.log10(Math.max(raw, 1)))
    const scaled = raw / power
    return (scaled <= 1 ? 1 : scaled <= 2 ? 2 : scaled <= 5 ? 5 : 10) * power
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
    const step = niceStep(max / 3)
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
        yTicks.push({ value: v, y: y(v), label: v >= 1000 ? `${Math.round(v / 100) / 10}k` : String(v) })
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
// value. series: [{ key, value: (point) => number|null, invert? }]
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
        const max = Math.max(...values, s.invert ? 1 : 0)
        const step = niceStep((s.invert ? max - 1 : max) / 3 || 1)
        let low = 0
        let high = Math.max(step, Math.ceil(max / step) * step)
        if (s.invert) {
            low = 1
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
        let pen = false
        coords.forEach((c) => {
            if (!c) {
                pen = false
                return
            }
            path += `${pen ? 'L' : 'M'}${c.x.toFixed(1)},${c.y.toFixed(1)} `
            pen = true
        })
        const ticks = []
        for (let v = low; v <= high + 1e-9; v += step) {
            ticks.push({ value: v, y: y(v), label: s.format ? s.format(v) : (v >= 1000 ? `${Math.round(v / 100) / 10}k` : String(Math.round(v * 100) / 100)) })
        }

        return { key: s.key, path: path.trim(), coords, ticks }
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
