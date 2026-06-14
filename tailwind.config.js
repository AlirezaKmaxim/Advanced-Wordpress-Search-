/** @type {import('tailwindcss').Config} */
module.exports = {
  important: '#hamseda-ajax-search-app',
  content: [
    "./**/*.php",
    "./**/*.js",
    "!./node_modules/**"
  ],
  theme: {
    extend: {
      colors: {
        primaryLight: '#EDF0F8',
        secondaryBlue: '#5977BF',
        deepNavy: '#1F3161',
        darkText: '#242424',
        lavender: '#DDCBFB',
      }
    },
  },
  plugins: [],
}
