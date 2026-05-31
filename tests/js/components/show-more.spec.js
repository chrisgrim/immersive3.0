/**
 * Specs for GlobalComponents/show-more.vue
 *
 * Covers:
 *  - Short text (<= limit words) shows the full text and NO toggle.
 *  - Long text (> limit words) truncates to `limit` words + "..." and shows a toggle.
 *  - Clicking the toggle expands to the full text and swaps the label to "Show Less".
 *  - The `lessButton` prop controls whether the "Show More" affordance renders
 *    while collapsed.
 *  - blockquote mode renders a <blockquote> with quotes; default mode renders a div/p.
 *  - whiteSpace / bodyClass prop wiring.
 */
import { mount } from '@vue/test-utils';
import ShowMore from '@/GlobalComponents/show-more.vue';

/** Build a string of n space-separated words: "w1 w2 ... wn". */
function words(n) {
    return Array.from({ length: n }, (_, i) => `w${i + 1}`).join(' ');
}

describe('show-more.vue', () => {
    describe('short text (within the word limit)', () => {
        it('renders the full text untruncated', () => {
            const wrapper = mount(ShowMore, { props: { text: words(5), limit: 100 } });
            expect(wrapper.text()).toContain(words(5));
            expect(wrapper.text()).not.toContain('...');
        });

        it('does not render any toggle controls', () => {
            const wrapper = mount(ShowMore, { props: { text: words(5), limit: 100 } });
            expect(wrapper.text()).not.toContain('Show More');
            expect(wrapper.text()).not.toContain('Show Less');
        });

        it('treats text exactly at the limit as not needing show-more', () => {
            const wrapper = mount(ShowMore, { props: { text: words(10), limit: 10 } });
            expect(wrapper.vm.needsShowMore).toBe(false);
            expect(wrapper.vm.adjustedText).toBe(words(10));
        });

        it('handles empty text without truncation or toggle', () => {
            const wrapper = mount(ShowMore, { props: { text: '', limit: 10 } });
            expect(wrapper.vm.needsShowMore).toBeFalsy();
            expect(wrapper.vm.adjustedText).toBe('');
        });
    });

    describe('long text (beyond the word limit)', () => {
        it('truncates to the limit number of words plus an ellipsis', () => {
            const wrapper = mount(ShowMore, { props: { text: words(20), limit: 5 } });
            expect(wrapper.vm.needsShowMore).toBe(true);
            expect(wrapper.vm.adjustedText).toBe('w1 w2 w3 w4 w5...');
        });

        it('shows the "Show More" toggle while collapsed', () => {
            const wrapper = mount(ShowMore, { props: { text: words(20), limit: 5 } });
            const showMore = wrapper
                .findAll('div')
                .find((d) => d.text() === 'Show More' && d.isVisible());
            expect(showMore).toBeTruthy();
        });

        it('expands to the full text and swaps the label when clicked', async () => {
            const wrapper = mount(ShowMore, { props: { text: words(20), limit: 5 } });

            const showMore = wrapper
                .findAll('div')
                .find((d) => d.text() === 'Show More' && d.isVisible());
            await showMore.trigger('click');

            expect(wrapper.vm.showMore).toBe(true);
            expect(wrapper.vm.adjustedText).toBe(words(20));

            const showLess = wrapper
                .findAll('div')
                .find((d) => d.text() === 'Show Less' && d.isVisible());
            expect(showLess).toBeTruthy();
        });

        it('collapses again on a second click', async () => {
            const wrapper = mount(ShowMore, { props: { text: words(20), limit: 5 } });
            await wrapper.vm.toggleShowMore();
            expect(wrapper.vm.showMore).toBe(true);
            await wrapper.vm.toggleShowMore();
            expect(wrapper.vm.showMore).toBe(false);
            expect(wrapper.vm.adjustedText).toBe('w1 w2 w3 w4 w5...');
        });
    });

    describe('lessButton prop', () => {
        it('hides the collapsed "Show More" affordance when lessButton is false', () => {
            const wrapper = mount(ShowMore, {
                props: { text: words(20), limit: 5, lessButton: false },
            });
            const visibleShowMore = wrapper
                .findAll('div')
                .find((d) => d.text() === 'Show More' && d.isVisible());
            expect(visibleShowMore).toBeFalsy();
        });

        it('still shows "Show Less" once expanded even when lessButton is false', async () => {
            const wrapper = mount(ShowMore, {
                props: { text: words(20), limit: 5, lessButton: false },
            });
            await wrapper.vm.toggleShowMore();
            const showLess = wrapper
                .findAll('div')
                .find((d) => d.text() === 'Show Less' && d.isVisible());
            expect(showLess).toBeTruthy();
        });
    });

    describe('blockquote vs div mode', () => {
        it('renders a div (not a blockquote) by default', () => {
            const wrapper = mount(ShowMore, { props: { text: words(5) } });
            expect(wrapper.find('blockquote').exists()).toBe(false);
            expect(wrapper.find('p').exists()).toBe(true);
        });

        it('renders a blockquote with surrounding quotes when blockquote is true', () => {
            const wrapper = mount(ShowMore, { props: { text: words(3), blockquote: true } });
            const bq = wrapper.find('blockquote');
            expect(bq.exists()).toBe(true);
            // Template wraps the text in literal double-quote characters.
            expect(bq.find('span').text()).toBe(`"${words(3)}"`);
        });

        it('truncates inside blockquote mode the same way', () => {
            const wrapper = mount(ShowMore, {
                props: { text: words(20), limit: 5, blockquote: true },
            });
            expect(wrapper.find('blockquote').exists()).toBe(true);
            expect(wrapper.find('blockquote span').text()).toBe('"w1 w2 w3 w4 w5..."');
        });
    });

    describe('prop wiring', () => {
        it('applies bodyClass to the text span', () => {
            const wrapper = mount(ShowMore, { props: { text: words(3), bodyClass: 'italic' } });
            expect(wrapper.find('span').classes()).toContain('italic');
        });

        it('applies the whiteSpace style to the text span', () => {
            const wrapper = mount(ShowMore, { props: { text: words(3), whiteSpace: 'nowrap' } });
            expect(wrapper.find('span').attributes('style')).toContain('white-space: nowrap');
        });

        it('defaults whiteSpace to pre-wrap', () => {
            const wrapper = mount(ShowMore, { props: { text: words(3) } });
            expect(wrapper.find('span').attributes('style')).toContain('white-space: pre-wrap');
        });
    });
});
