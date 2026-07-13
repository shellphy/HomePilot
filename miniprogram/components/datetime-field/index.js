Component({
  properties: {
    label: String,
    required: Boolean,
    date: String,
    time: String,
  },

  methods: {
    onDateChange(event) {
      this.triggerEvent('change', { date: event.detail.value, time: this.data.time });
    },

    onTimeChange(event) {
      this.triggerEvent('change', { date: this.data.date, time: event.detail.value });
    },
  },
});
