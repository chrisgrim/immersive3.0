/**
 * Specs for PageComponents/Search/Components/show-more-results.vue — the
 * "Show more" control that replaced the numbered pages under search results.
 *
 * The server decides whether more can be shown (`has_more`), because it is
 * also the one that restores `?page=N` on a cold load and will not go past
 * ListingsController::MAX_INITIAL_PAGES; the component must follow that,
 * not compute its own `shown < total`.
 */
import { mount } from '@vue/test-utils';
import ShowMoreResults from '@/PageComponents/Search/Components/show-more-results.vue';

const mountIt = (props = {}) => mount(ShowMoreResults, {
    props: { shown: 20, total: 63, hasMore: true, limitReached: false, loading: false, ...props },
});

describe('show-more-results.vue', () => {
    it('shows how far through the results the page is, and a Show more button', () => {
        const wrapper = mountIt();

        expect(wrapper.text()).toContain('Showing 20 of 63 events');
        expect(wrapper.find('button').text()).toBe('Show more');
    });

    it('emits more when clicked', async () => {
        const wrapper = mountIt();

        await wrapper.find('button').trigger('click');

        expect(wrapper.emitted('more')).toHaveLength(1);
    });

    it("follows the server's has_more rather than its own shown < total", () => {
        const wrapper = mountIt({ shown: 20, total: 63, hasMore: false });

        expect(wrapper.find('button').exists()).toBe(false);
        expect(wrapper.text()).toBe('');
    });

    it('explains the depth cap instead of silently stopping', () => {
        // 200 of 631 shown and the server will not restore a deeper list:
        // no button, but the count stays and the reader is told what to do.
        const wrapper = mountIt({ shown: 200, total: 631, hasMore: false, limitReached: true });

        expect(wrapper.find('button').exists()).toBe(false);
        expect(wrapper.text()).toContain('Showing 200 of 631 events');
        expect(wrapper.text()).toContain('Narrow it down');
    });

    it('disables itself and says so while the next window is loading', () => {
        const wrapper = mountIt({ loading: true });

        const button = wrapper.find('button');
        expect(button.attributes('disabled')).toBeDefined();
        expect(button.text()).toBe('Loading…');
    });
});
