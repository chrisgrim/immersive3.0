/**
 * Specs for GlobalComponents/pagination.vue
 *
 * Covers:
 *  - The pagination <ul> only renders when total > per_page.
 *  - The `pageRange` computed: contiguous windows, edge anchors (1 / last),
 *    and the "..." ellipsis collapsing logic.
 *  - First/prev button disabled on page 1, next/last disabled on last page.
 *  - Clicking a numbered page / prev / next emits 'paginate' with the right page.
 *  - The `limit` prop special cases (0 = all pages, -1 = none).
 */
import { mount } from '@vue/test-utils';
import Pagination from '@/GlobalComponents/pagination.vue';

/** Build a pagination meta object like Laravel's paginator emits. */
function meta({ current_page = 1, last_page = 10, per_page = 10, total = 100 } = {}) {
    return { current_page, last_page, per_page, total };
}

function mountPagination(paginationProps = {}, extra = {}) {
    return mount(Pagination, {
        props: {
            pagination: meta(paginationProps),
            ...extra,
        },
    });
}

describe('pagination.vue', () => {
    describe('visibility', () => {
        it('renders the list when total exceeds per_page', () => {
            const wrapper = mountPagination({ total: 100, per_page: 10 });
            expect(wrapper.find('ul').exists()).toBe(true);
        });

        it('does not render the list when total fits on one page', () => {
            const wrapper = mountPagination({ total: 5, per_page: 10, last_page: 1 });
            expect(wrapper.find('ul').exists()).toBe(false);
        });

        it('does not render the list when total equals per_page', () => {
            const wrapper = mountPagination({ total: 10, per_page: 10, last_page: 1 });
            expect(wrapper.find('ul').exists()).toBe(false);
        });
    });

    describe('pageRange computed', () => {
        it('builds a centered window with both ellipsis anchors in the middle', () => {
            const wrapper = mountPagination({ current_page: 5, last_page: 10 });
            // limit defaults to 2: left=3, right=8 -> [1,3,4,5,6,7,10]
            // collapse: 1->3 gap of 2 fills the 2, 7->10 gap of 3 yields '...'
            expect(wrapper.vm.pageRange).toEqual([1, 2, 3, 4, 5, 6, 7, '...', 10]);
        });

        it('fills a single-page gap instead of showing an ellipsis', () => {
            // current 3 of 10, limit 2: left=1, right=6 -> range [1,2,3,4,5,10]
            // 5 -> 10 gap of 5 => '...'; no leading ellipsis because window touches 1.
            const wrapper = mountPagination({ current_page: 3, last_page: 10 });
            expect(wrapper.vm.pageRange).toEqual([1, 2, 3, 4, 5, '...', 10]);
        });

        it('produces ellipses on both sides when current is in the middle of a long range', () => {
            const wrapper = mountPagination({ current_page: 10, last_page: 20 });
            // limit 2: left=8, right=13 -> range [1,8,9,10,11,12,20]
            expect(wrapper.vm.pageRange).toEqual([1, '...', 8, 9, 10, 11, 12, '...', 20]);
        });

        it('lists every page contiguously when last_page is small', () => {
            const wrapper = mountPagination({ current_page: 1, last_page: 3 });
            expect(wrapper.vm.pageRange).toEqual([1, 2, 3]);
        });

        it('renders a button per entry in pageRange (ellipsis included)', () => {
            const wrapper = mountPagination({ current_page: 5, last_page: 10 });
            const numberButtons = wrapper.findAll('li[key] button, ul > li button');
            // The numbered buttons live in the v-for; count them precisely.
            const pageButtons = wrapper.findAll('ul > li button').filter((b) => {
                const t = b.text().trim();
                return t !== '' && b.find('svg').exists() === false;
            });
            expect(pageButtons.length).toBe(wrapper.vm.pageRange.length);
            expect(numberButtons.length).toBeGreaterThan(0);
        });

        it('marks the current page button as active with the !bg-black class', () => {
            const wrapper = mountPagination({ current_page: 5, last_page: 10 });
            const activeButtons = wrapper
                .findAll('ul > li button')
                .filter((b) => b.classes().includes('!bg-black'));
            expect(activeButtons.length).toBe(1);
            expect(activeButtons[0].text().trim()).toBe('5');
        });
    });

    describe('limit prop special cases', () => {
        it('returns 0 (no numbered pages) when limit is -1', () => {
            const wrapper = mountPagination({ current_page: 5, last_page: 10 }, { limit: -1 });
            expect(wrapper.vm.pageRange).toBe(0);
        });

        it('returns last_page (all pages flat) when limit is 0', () => {
            const wrapper = mountPagination({ current_page: 5, last_page: 10 }, { limit: 0 });
            expect(wrapper.vm.pageRange).toBe(10);
        });
    });

    describe('prev / next button disabled state', () => {
        it('disables the prev button on page 1', () => {
            const wrapper = mountPagination({ current_page: 1, last_page: 10 });
            const prev = wrapper.findAll('ul > li button').find((b) => b.find('svg').exists());
            expect(prev.attributes('disabled')).toBeDefined();
        });

        it('enables the prev button when not on page 1', () => {
            const wrapper = mountPagination({ current_page: 5, last_page: 10 });
            const prev = wrapper.findAll('ul > li button').find((b) => b.find('svg').exists());
            expect(prev.attributes('disabled')).toBeUndefined();
        });

        it('disables the next button on the last page', () => {
            const wrapper = mountPagination({ current_page: 10, last_page: 10 });
            const buttons = wrapper.findAll('ul > li button').filter((b) => b.find('svg').exists());
            const next = buttons[buttons.length - 1];
            expect(next.attributes('disabled')).toBeDefined();
        });

        it('enables the next button when not on the last page', () => {
            const wrapper = mountPagination({ current_page: 5, last_page: 10 });
            const buttons = wrapper.findAll('ul > li button').filter((b) => b.find('svg').exists());
            const next = buttons[buttons.length - 1];
            expect(next.attributes('disabled')).toBeUndefined();
        });
    });

    describe('paginate events', () => {
        it('emits paginate with the clicked page number', async () => {
            const wrapper = mountPagination({ current_page: 5, last_page: 10 });
            const pageButton = wrapper
                .findAll('ul > li button')
                .find((b) => !b.find('svg').exists() && b.text().trim() === '6');
            await pageButton.trigger('click');
            expect(wrapper.emitted('paginate')).toBeTruthy();
            expect(wrapper.emitted('paginate')[0]).toEqual([6]);
        });

        it('emits paginate with current_page - 1 when prev is clicked', async () => {
            const wrapper = mountPagination({ current_page: 5, last_page: 10 });
            const prev = wrapper.findAll('ul > li button').find((b) => b.find('svg').exists());
            await prev.trigger('click');
            expect(wrapper.emitted('paginate')[0]).toEqual([4]);
        });

        it('emits paginate with current_page + 1 when next is clicked', async () => {
            const wrapper = mountPagination({ current_page: 5, last_page: 10 });
            const buttons = wrapper.findAll('ul > li button').filter((b) => b.find('svg').exists());
            const next = buttons[buttons.length - 1];
            await next.trigger('click');
            expect(wrapper.emitted('paginate')[0]).toEqual([6]);
        });

        it('does not emit when clicking the current page button', async () => {
            const wrapper = mountPagination({ current_page: 5, last_page: 10 });
            const current = wrapper
                .findAll('ul > li button')
                .find((b) => b.classes().includes('!bg-black'));
            await current.trigger('click');
            expect(wrapper.emitted('paginate')).toBeFalsy();
        });
    });
});
