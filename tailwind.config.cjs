module.exports = {
  content: [
    "./laravel/public/**/*.js",
    "./laravel/resources/views/**/*.blade.php",
    "./laravel/resources/shortvideo/**/*.css",
    "./laravel/app/**/*.php"
  ],
  safelist: [
    "animate-card-in",
    "aspect-[6/5]",
    "aspect-[4/5]",
    "aspect-[3/4]",
    "aspect-square",
    "h-[92vh]",
    "leading-[1.42]",
    "line-clamp-2",
    "max-h-[920px]",
    "max-w-[430px]",
    "max-w-[1520px]",
    "rounded-[32px]",
    "shadow-[0_28px_80px_rgba(0,0,0,0.42)]",
    "text-[1.2rem]",
    "tracking-[0.02em]",
    "tracking-[0.18em]",
    "xl:max-w-[430px]",
    "xl:w-[430px]",
    "sm:text-[1.35rem]"
  ],
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
