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
      "sunset": "url('../assets/images/diamond-sunset.svg')"
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
    }
  ],
}
