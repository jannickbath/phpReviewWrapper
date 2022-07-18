module.exports = {
  content: ["./src/*.{html,js,php}"],
  important: true,
  theme: {
    extend: {
    "fontSize": {
      "default": "22px"
    },
    "colors": {
      "blue": {
        "dark": "darkblue",
        "DEFAULT": "blue"
      }
    },
    "backgroundImage": {
      // "1": "url('../assets/images/diamond-sunset.svg')",
      // "2": "url('../assets/images/endless-constellation.svg')",
      "3": "url('../assets/images/hollowed-boxes.svg')",
      // "4": "url('../assets/images/liquid-cheese.svg')",
      // "5": "url('../assets/images/protruding-squares.svg')",
      // "6": "url('../assets/images/rainbow-vortex.svg')",
      // "7": "url('../assets/images/spectrum-gradient.svg')"
    }
    },
    "container": {
      "center": true
    }
  },
  plugins: [
    function ({ addVariant }) {
          addVariant("child", "& > *");
          addVariant("child-hover", "& > *:hover");
    },
    require('@tailwindcss/forms')
  ],
}
