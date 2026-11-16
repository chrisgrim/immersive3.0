import { ref } from 'vue';

/**
 * Airbnb's calendar bar: one fixed line above a scrolling list of months,
 * naming the month being looked at. A month takes over once its own name has
 * slid fully under the bar.
 *
 * The date picker nests and repeats its month name elements, so names are read
 * from their own text ("December2026") rather than counted.
 *
 * @param {import('vue').Ref<HTMLElement|null>} scroller - the scrolling box holding the months
 * @param {import('vue').Ref<HTMLElement|null>} bar - the fixed bar (its bottom edge is the line)
 */
const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

/**
 * @param {import('vue').Ref<HTMLElement|null>} scroller - the scrolling box holding the months
 * @param {import('vue').Ref<HTMLElement|null>} bar - the fixed bar (its bottom edge is the line)
 * @param {() => Date} firstMonth - the first month shown (its year starts the count)
 */
export function useMonthBar(scroller, bar, firstMonth = () => new Date()) {
    const shownMonth = ref('');
    // Every month in order, and which one the bar is on (for two across: the pair).
    const monthLabels = ref([]);
    const shownIndex = ref(0);

    const followMonth = () => {
        const box = scroller.value;
        if (!box) return;
        // In page order, one per month (the picker repeats its name elements).
        let year = firstMonth().getFullYear();
        let last = -1;
        const months = [];
        box.querySelectorAll('.dp__month_year_wrap').forEach((name) => {
            const month = MONTHS.findIndex((each) => name.textContent.includes(each));
            if (month === -1) return;
            if (months.length && months[months.length - 1].name === name.textContent) return;
            if (last !== -1 && month < last) year += 1;
            last = month;
            months.push({ name: name.textContent, element: name, label: `${MONTHS[month]} ${year}` });
        });
        if (!months.length) return;
        const line = (bar.value ?? box).getBoundingClientRect()[bar.value ? 'bottom' : 'top'];
        let shown = 0;
        months.forEach(({ element }, index) => {
            // The name's own text (its wrapper has extra room below it).
            const rect = (element.querySelector('.dp__month_year_select') ?? element).getBoundingClientRect();
            if (rect.height > 0 && rect.bottom <= line) shown = index;
        });
        monthLabels.value = months.map(({ label }) => label);
        shownIndex.value = shown;
        shownMonth.value = months[shown].label;
    };

    // The first month (or the first `count`, two across) is named in the bar,
    // so its own name is hidden; 0 shows them all again.
    const hideFirstName = (count = 1) => {
        scroller.value?.querySelectorAll('.dp--header-wrap').forEach((header, index) => {
            header.style.display = index < count ? 'none' : '';
        });
    };

    return { shownMonth, shownIndex, monthLabels, followMonth, hideFirstName };
}
