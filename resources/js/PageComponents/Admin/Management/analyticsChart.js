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
