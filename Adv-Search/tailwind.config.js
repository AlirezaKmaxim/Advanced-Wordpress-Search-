/** @type {import('tailwindcss').Config} */
module.exports = {
  important: '#hamseda-ajax-search-app',
  corePlugins: {
    preflight: false,
  },
  content: [
    "./**/*.php",
    "./**/*.js",
    "!./node_modules/**"
  ],
  theme: {
    extend: {
      fontFamily: {
        yekan: ['YekanBakh', 'IRANSans', 'Tahoma', 'sans-serif'],
      },
      colors: {
        primary: '#FFB3C1',
        secondary: '#FA7993',
        textBlue: '#7BA4F5',
        accent: '#FCE16D',
        deepNavy: '#3A3A4A',
        secondaryText: '#525266',
        mutedText: '#707085',
        lightBg: '#FCFAFA',
        lightBg2: '#F2EFEF',
        lightBg3: '#EFEFF3',
        borderLight: '#E2E2E2',
        badgePink: '#E86681',
      }
    },
  },
  plugins: [],
}