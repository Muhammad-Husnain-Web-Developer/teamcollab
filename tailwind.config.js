/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        './resources/js/**/*.js',
    ],
    darkMode: 'class',
    theme: {
        extend: {
            colors: {
                brand: {
                    50:  '#f0f4ff',
                    100: '#dbe4ff',
                    200: '#bac8ff',
                    300: '#91a7ff',
                    400: '#748ffc',
                    500: '#5c7cfa',
                    600: '#4c6ef5',
                    700: '#4263eb',
                    800: '#3b5bdb',
                    900: '#364fc7',
                    950: '#2c3e9e',
                },
                dark: {
                    900: '#0f0f10',
                    800: '#161618',
                    750: '#1a1a1d',
                    700: '#1e1e21',
                    650: '#222226',
                    600: '#27272b',
                    550: '#2d2d32',
                    500: '#323237',
                    400: '#3a3a40',
                    300: '#48484f',
                    200: '#6b6b72',
                    100: '#8e8e96',
                    // Higher-contrast additions — dark-300/400 read ~2.3:1 on
                    // dark-800 (fails WCAG AA); use these for readable text.
                    50:  '#c7c7ce',
                },
                aurora: {
                    indigo: '#5c7cfa',
                    violet: '#8b5cf6',
                    teal:   '#2dd4bf',
                },
            },
            fontFamily: {
                sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
                mono: ['JetBrains Mono', 'Fira Code', 'monospace'],
            },
            boxShadow: {
                'glow-brand':  '0 0 20px rgba(92, 124, 250, 0.3)',
                'glow-brand-lg': '0 0 40px rgba(92, 124, 250, 0.35)',
                'glow-success':  '0 0 20px rgba(52, 211, 153, 0.25)',
                'glow-danger':   '0 0 20px rgba(248, 113, 113, 0.25)',
                'dark-lg':    '0 10px 40px rgba(0, 0, 0, 0.5)',
                'dark-xl':    '0 20px 60px rgba(0, 0, 0, 0.6)',
                'glass':      '0 1px 0 0 rgba(255,255,255,0.06) inset, 0 8px 30px rgba(0,0,0,0.35)',
                'glass-lg':   '0 1px 0 0 rgba(255,255,255,0.07) inset, 0 20px 60px rgba(0,0,0,0.5)',
                'card-hover': '0 1px 0 0 rgba(255,255,255,0.08) inset, 0 12px 32px rgba(0,0,0,0.4), 0 0 0 1px rgba(92,124,250,0.15)',
            },
            backgroundImage: {
                'grain': "url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E\")",
            },
            animation: {
                'fade-in':    'fadeIn 0.2s ease-out',
                'fade-up':    'fadeUp 0.3s ease-out',
                'slide-in':   'slideIn 0.25s ease-out',
                'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                'aurora':     'aurora 25s ease-in-out infinite alternate',
            },
            keyframes: {
                fadeIn:  { '0%': { opacity: '0' }, '100%': { opacity: '1' } },
                fadeUp:  { '0%': { opacity: '0', transform: 'translateY(10px)' }, '100%': { opacity: '1', transform: 'translateY(0)' } },
                slideIn: { '0%': { opacity: '0', transform: 'translateX(-10px)' }, '100%': { opacity: '1', transform: 'translateX(0)' } },
                aurora:  {
                    '0%':   { transform: 'translate(0, 0) scale(1)' },
                    '33%':  { transform: 'translate(4%, -6%) scale(1.08)' },
                    '66%':  { transform: 'translate(-6%, 4%) scale(0.96)' },
                    '100%': { transform: 'translate(3%, -3%) scale(1.04)' },
                },
            },
        },
    },
    plugins: [
        require('@tailwindcss/forms')({ strategy: 'class' }),
        require('@tailwindcss/typography'),
    ],
};
