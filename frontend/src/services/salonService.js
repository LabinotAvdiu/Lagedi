const BASE_URL = import.meta.env.VITE_API_URL;

export const salonService = {
  async search(city) {
    const response = await fetch(
      `${BASE_URL}/salons?city=${encodeURIComponent(city)}`,
      { headers: { Accept: "application/json" } }
    );

    const data = await response.json();

    if (!response.ok) throw data;

    return data;
  },

  photoUrl(photoRef) {
    return `${BASE_URL}/salons/photo?ref=${encodeURIComponent(photoRef)}`;
  },
};
