import Aura from "@primevue/themes/aura";

const TerminiPreset = {
  ...Aura,
  semantic: {
    ...Aura.semantic,
    primary: {
      50: "#fdf9ec",
      100: "#faf0c8",
      200: "#f5e27a",
      300: "#f0d060",
      400: "#d4aa50",
      500: "#c9a84c",
      600: "#b8942a",
      700: "#a87b2e",
      800: "#8b6914",
      900: "#6b4f0a",
      950: "#4a3506",
    },
    colorScheme: {
      ...Aura.semantic?.colorScheme,
      light: {
        ...Aura.semantic?.colorScheme?.light,
        primary: {
          color: "#c9a84c",
          contrastColor: "#0a0a0a",
          hoverColor: "#d4aa50",
          activeColor: "#a87b2e",
        },
      },
      dark: {
        ...Aura.semantic?.colorScheme?.dark,
        primary: {
          color: "#c9a84c",
          contrastColor: "#0a0a0a",
          hoverColor: "#d4aa50",
          activeColor: "#a87b2e",
        },
      },
    },
  },
};

export default TerminiPreset;
