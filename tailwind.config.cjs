module.exports = {
  content: ["./public/**/*.{html,js}"],
  theme: {
    extend: {
      animation: {
        "card-in": "card-in 280ms ease-out both"
      },
      boxShadow: {
        glass: "0 18px 60px rgba(43, 26, 12, 0.12)"
      },
      fontFamily: {
        sans: ['"Space Grotesk"', "sans-serif"],
        serif: ['"Newsreader"', "serif"]
      },
      keyframes: {
        "card-in": {
          "0%": {
            opacity: "0",
            transform: "translateY(12px)"
          },
          "100%": {
            opacity: "1",
            transform: "translateY(0)"
          }
        }
      }
    }
  },
  plugins: []
};
