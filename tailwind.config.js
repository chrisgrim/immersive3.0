/** @type {import('tailwindcss').Config} */
import scrollbarHide from 'tailwind-scrollbar-hide'

export default {
    content: [
        './resources/views/**/*.blade.php',
        './resources/views/**/**/*.blade.php',
        './resources/js/**/*.js',
        './resources/js/**/*.vue', 
        // Add any other paths that might contain Tailwind CSS classes
    ],
    theme: {
        screens: {
            'lg-air': '950px',
            'xl-air': '1140px',
            '1xl-air': '1300px',
            '2xl-air': '1440px',
            // Keep default Tailwind breakpoints if needed
            'sm': '640px',
            'md': '768px',
            'lg': '1024px',
            'xl': '1280px',
            '2xl': '1536px',
            '3xl': '1600px',
            '4xl': '1920px',
            '5xl': '2500px',
        },
        fontSize: {
            'xs': '.75rem',
            'sm': '.875rem',
            'tiny': '.875rem',
            'md': '1rem',
            'base': '1rem',
            'lg': '1.125rem',
            'xl': '1.25rem',
            '1xl': '1.35rem',
            '2xl': '1.5rem',
            '2.5xl': '1.6rem',
            '3xl': '1.75rem',
            '3.5xl': '2rem',
            '4xl': '2.25rem',
            '4.5xl': '2.5rem',
            '5xl': '3rem',
            '5.5xl': '3.5rem',
            '6xl': '4rem',
            '6.5xl': '4.5rem',
            '7xl': '5rem',
        },
        extend: {
            fontFamily: {
                'montserrat': ['Montserrat', 'sans-serif'],
            },
            zIndex: {
                '40': '40',
                '41': '41',
                '42': '42',
                '43': '43',
                '44': '44',
                '45': '45',
                '46': '46',
                '47': '47',
                '48': '48',
                '49': '49',
                '50': '50',
            },
            spacing: {
                '128': '32rem',
            },
            boxShadow: {
                'custom-1': '0 2px 16px rgb(0 0 0 / 12%)',
                'custom-2': '0 1px 7px rgb(0 0 0 / 50%)',
                'custom-3': '0 1px 2px rgb(0 0 0 / 8%), 0 4px 12px rgb(0 0 0 / 5%)',
                'custom-4': '0 2px 0 0 rgb(0 0 0 / 10%), 0 0 0 0.5px rgb(0 0 0 / 4%)',
                'custom-5': '2px 3px 5px #f3f3f3',
                'custom-6': '0px 6px 16px rgb(0 0 0 / 22%)',
                'custom-7': '0px 5px 16px 2px rgb(0 0 0 / 18%)',
                'light': '0 1px 12px rgba(0, 0, 0, 0.08)',
                'focus-black': '0 0 0 1.5px black, 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)',
                'focus-error': '0 0 0 1.5px #ef4444, 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)'
            },
            colors: {
                'button-red-1': '#e61e4d',
                'button-red-2': '#e31c5f',
                'button-red-3': '#d70466',
                'default-red': '#ff385c',
                'primary': '#ff385c',
            },
            borderRadius: {
                '4xl': '2rem',
                '5xl': '2.5rem',
                '6xl': '3rem',
            },
        }
    },
    plugins: [
        //
    ],
}

