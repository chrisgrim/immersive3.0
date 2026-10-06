// The saved wheelchair answer ('full' | 'partial' | 'none' | null), read the
// same way as Advisory::wheelchairLevel(): the three-way answer, or the old
// yes/no for an event saved before it existed. When the two disagree the
// yes/no wins (only code from before the change writes it alone).
export const wheelchairLevel = (advisories) => {
    const ready = advisories?.wheelchairReady;
    const access = ['full', 'partial', 'none'].includes(advisories?.wheelchairAccess)
        ? advisories.wheelchairAccess
        : null;

    if (ready === undefined || ready === null) {
        return access;
    }

    if (access !== null && (access === 'full') === Boolean(ready)) {
        return access;
    }

    return ready ? 'full' : 'none';
};
