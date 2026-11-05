// "Want to start this as a new run?" A suggestion on saving the Dates or
// Location step of a published listing, when the city changes or a new date
// falls more than 90 days after its last one. It blocks nothing.
const NEW_RUN_GAP_DAYS = 90;

const dayOf = (value) => new Date(String(value).slice(0, 10)).getTime();

export function wantsNewRun(event, step, submitData, savedCity) {
    if (event.status !== 'p') return false;

    if (step === 'Location') {
        const city = submitData?.location?.city;
        return Boolean(city && savedCity && city.trim().toLowerCase() !== savedCity.trim().toLowerCase());
    }

    if (step === 'Dates' && event.closingDate) {
        const limit = dayOf(event.closingDate) + NEW_RUN_GAP_DAYS * 86400000;
        return (submitData?.dateArray ?? []).some((date) => dayOf(date) > limit);
    }

    return false;
}
