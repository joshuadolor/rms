/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './index.html',
    './src/**/*.{vue,js,ts,jsx,tsx}',
  ],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        primary: '#ee4b2b',
        'background-light': '#f8f6f6',
        'background-dark': '#221310',
        sage: '#8BA888',
        cream: '#FDFBF7',
        charcoal: '#333333',
        // Template 1 public page (reference: template-1/code.html) — use in public template components only
        't1-primary': '#1152d4',
        't1-bg-light': '#f6f6f8',
        't1-neutral-dark': '#111318',
        't1-neutral-muted': '#616f89',
        't1-border': '#dbdfe6',
        // Template 2 (reference: template-2/code.html)
        'concrete-gray': '#E5E7EB',
        'pale-stone': '#F3F4F6',
        'charcoal-blue': '#1E293B',
        'oxidized-copper': '#B35C38',
        'workshop-border': '#D1D5DB',
      },
      fontFamily: {
        display: ['Plus Jakarta Sans', 'Inter', 'system-ui', 'sans-serif'],
        // Public templates: display (Inter), industrial (Barlow Condensed), mono (JetBrains Mono), body (Inter)
        'font-display': ['Inter', 'sans-serif'],
        industrial: ['Barlow Condensed', 'sans-serif'],
        mono: ['JetBrains Mono', 'monospace'],
        body: ['Inter', 'sans-serif'],
      },
      borderRadius: {
        DEFAULT: '0.25rem',
        lg: '0.5rem',
        xl: '0.75rem',
        full: '9999px',
        // Template 1 reference
        't1': '0.125rem',
        't1-lg': '0.25rem',
        't1-xl': '0.5rem',
        't1-full': '0.75rem',
      },
    },
  },
  plugins: [],
}
