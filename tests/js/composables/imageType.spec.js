import { describe, it, expect } from 'vitest';
import { imageType } from '@/composables/imageType';

describe('imageType', () => {
    it('uses the type the browser gives', () => {
        expect(imageType(new File(['x'], 'a.bin', { type: 'image/png' }))).toBe('image/png');
    });

    it('falls back to the extension when the browser leaves the type blank', () => {
        expect(imageType(new File(['x'], 'Photo.AVIF'))).toBe('image/avif');
        expect(imageType(new File(['x'], 'photo.jpg'))).toBe('image/jpeg');
    });

    it('falls back to the extension when the browser calls it octet-stream', () => {
        expect(imageType(new File(['x'], 'photo.avif', { type: 'application/octet-stream' }))).toBe('image/avif');
        expect(imageType(new File(['x'], 'notes.txt', { type: 'application/octet-stream' }))).toBe('application/octet-stream');
    });

    it('gives nothing for an unknown blank-type file', () => {
        expect(imageType(new File(['x'], 'notes.txt'))).toBe('');
        expect(imageType(new File(['x'], 'noextension'))).toBe('');
    });
});
