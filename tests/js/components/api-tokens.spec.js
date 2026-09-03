import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import axios from 'axios';
import ApiTokens from '@/PageComponents/Settings/api-tokens.vue';

vi.mock('axios', () => ({
    default: { get: vi.fn(), post: vi.fn(), delete: vi.fn() },
}));

/**
 * The "AI & API access" settings page: how to connect an assistant, the
 * assistants already connected (with Disconnect), and API keys for scripts
 * (with moderator powers offered to moderators only).
 */
const apps = [{ id: 't1', app: 'Claude Code', scopes: ['mcp:use'], connected_at: '2026-09-01T00:00:00Z', expires_at: null }];
const keys = [{ id: 'k1', name: 'Nightly import', moderate: true, created_at: '2026-08-01T00:00:00Z', expires_at: '2026-10-30T00:00:00Z' }];

const mountPage = () => mount(ApiTokens, { props: { embedded: true } });

beforeEach(() => {
    axios.get.mockImplementation((url) => {
        if (url === '/oauth/connections') return Promise.resolve({ data: { apps } });
        if (url === '/settings/api-tokens/list') return Promise.resolve({ data: { tokens: keys } });
        return Promise.reject(new Error(`unexpected GET ${url}`));
    });
    axios.post.mockResolvedValue({ data: { token: 'eyJ.fresh.token', scopes: ['mcp:use'] } });
    axios.delete.mockResolvedValue({ data: {} });
    window.confirm = vi.fn(() => true);
});

afterEach(() => vi.clearAllMocks());

describe('api-tokens', () => {
    it('tells the user the address to give an assistant', async () => {
        const w = mountPage();
        await flushPromises();

        expect(w.find('[data-test="mcp-url"]').text()).toBe(`${window.location.origin}/mcp`);
        expect(w.text()).toContain('claude mcp add');
    });

    it('lists connected assistants and disconnects one', async () => {
        const w = mountPage();
        await flushPromises();

        expect(w.find('[data-test="connected-apps"]').text()).toContain('Claude Code');

        await w.find('[data-test="connected-apps"] button').trigger('click');
        await flushPromises();

        expect(axios.delete).toHaveBeenCalledWith('/oauth/connections/t1');
        expect(w.find('[data-test="no-apps"]').exists()).toBe(true);
    });

    it('lists API keys, marking one that carries moderator powers', async () => {
        const w = mountPage();
        await flushPromises();

        const list = w.find('[data-test="api-keys"]');
        expect(list.text()).toContain('Nightly import');
        expect(list.text()).toContain('moderator powers');
    });

    it('offers moderator powers to moderators only, and sends the choice', async () => {
        window.Laravel.user.isModerator = false;
        let w = mountPage();
        await flushPromises();
        expect(w.find('[data-test="moderate-option"]').exists()).toBe(false);

        window.Laravel.user.isModerator = true;
        w = mountPage();
        await flushPromises();
        expect(w.find('[data-test="moderate-option"]').exists()).toBe(true);

        await w.find('[data-test="moderate-option"] input').setValue(true);
        await w.find('#token-name').setValue('Ops');
        await w.find('#token-name').trigger('keyup.enter');
        await flushPromises();

        expect(axios.post).toHaveBeenCalledWith('/settings/api-tokens', { name: 'Ops', moderate: true });
        expect(w.find('[data-test="fresh-token"]').text()).toContain('eyJ.fresh.token');
        window.Laravel.user.isModerator = false;
    });
});
