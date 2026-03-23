(function ($, Drupal, once) {
  Drupal.behaviors.bookingCalendar = {
    attach: function (context, settings) {
      $(once('bookingCalendar', '#calendar-container', context)).each(function () {
        const container = this;
        const targetInput = document.querySelector('input[name="booking_date_selection"], input[name="admin_date_selection"]');

        if (!targetInput) return;

        const calendar = new FullCalendar.Calendar(container, {
          initialView: 'dayGridMonth',
          locale: 'fr',
          firstDay: 1, 
          headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: ''
          },
          selectable: true,
          unselectAuto: false,
          validRange: {
            start: new Date().toISOString().split('T')[0]
          },
          select: function(info) {
             const newDate = info.startStr;
             // Only trigger AJAX if it's a real user interaction (jsEvent exists)
             // AND the date is actually different.
             if (info.jsEvent && targetInput.value !== newDate) {
               targetInput.value = newDate;
               targetInput.dispatchEvent(new Event('change', { bubbles: true }));
               $(targetInput).trigger('change');
             }
          },
          initialDate: targetInput.value || null,
        });

        calendar.render();

        // Restore visual selection if a value exists
        if (targetInput.value) {
          // calendar.select is fine here because our select: callback has a guard now
          calendar.select(targetInput.value);
        }
      });
    }
  };
})(jQuery, Drupal, once);
