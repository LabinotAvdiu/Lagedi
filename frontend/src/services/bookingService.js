import { get, post, patch } from "./api";

export const bookingService = {
  list() {
    return get("/bookings");
  },

  create(payload) {
    return post("/bookings", payload);
  },

  updateStatus(id, status) {
    return patch(`/bookings/${id}/status`, { status });
  },
};
