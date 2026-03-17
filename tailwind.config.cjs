module.exports = {
  content: ["./public/**/*.{html,js}", "./server/**/*.{js,html}"],
  theme: {
    extend: {
      animation: {
        "card-in": "card-in 320ms cubic-bezier(0.16, 1, 0.3, 1) both"
      },
      boxShadow: {
        glass: "0 24px 70px rgba(58, 27, 12, 0.12)"
      },
      fontFamily: {
        sans: ['"Plus Jakarta Sans"', "sans-serif"],
        serif: ['"Cormorant Garamond"', "serif"]
      },
      keyframes: {
        "card-in": {
          "0%": {
            opacity: "0",
            transform: "translateY(16px) scale(0.985)"
          },
          "100%": {
            opacity: "1",
            transform: "translateY(0) scale(1)"
          }
        }
      }
    }
  },
  plugins: []
};
