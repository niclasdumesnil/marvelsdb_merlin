/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './src/AppBundle/Resources/ReactViews/**/*.{js,jsx,ts,tsx}',
  ],
  // Prefix all Tailwind classes to avoid conflicts with existing Bootstrap styles
  prefix: 'tw-',
  theme: {
    extend: {
      colors: {
        // Marvel Champions faction colors
        leadership: '#2b80c5',
        protection: '#107116',
        aggression: '#cc3038',
        justice: '#ead51b',
        basic: '#808080',
        determination: '#493f64',
        encounter: {
          DEFAULT: '#9b6007',
          villain: '#31057c',
        },
        hero: '#353b49',
        // UI colors
        'mc-dark': 'rgb(53, 59, 73)',
        'mc-darker': 'rgb(40, 44, 55)',
        'mc-border': 'rgb(70, 76, 92)',
      },
      borderRadius: {
        'card': '12px',
      },
    },
  },
  plugins: [],
};
