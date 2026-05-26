module.exports = {
  content: [
    './index.php',
    './includes/**/*.php',
    './assets/js/**/*.js'
  ],
  theme: {
    extend: {
      colors: {
        colinas: {
          navy: '#262161',
          green: '#6FB43F',
          gray: '#727572',
          ice: '#F5F8FB',
          line: '#DDE6EF'
        }
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
        display: ['Plus Jakarta Sans', 'Inter', 'system-ui', 'sans-serif']
      }
    }
  },
  plugins: []
};

