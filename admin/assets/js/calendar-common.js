/**
 * Global calendar functions for consistent styling across all calendar pages
 */

/**
 * Apply room colors to calendar events
 * @param {Object} info - FullCalendar eventDidMount info object
 * @param {Array} eventTypes - Array of event types to apply colors to (default: ['room_lock'])
 */
function applyRoomColors(info, eventTypes = ['room_lock']) {
    // Check if this event should have room colors applied
    const shouldApplyColors = info.event.backgroundColor && 
        info.event.extendedProps && 
        eventTypes.includes(info.event.extendedProps.type);
    
    if (shouldApplyColors) {
        // Set CSS custom property for the room color
        info.el.style.setProperty('--room-color', info.event.backgroundColor);
        
        // Force apply the color with !important
        info.el.style.setProperty('background-color', info.event.backgroundColor, 'important');
        info.el.style.setProperty('border-color', info.event.backgroundColor, 'important');
        info.el.style.setProperty('background', info.event.backgroundColor, 'important');
        info.el.style.setProperty('color', '#ffffff', 'important');
        
        // Also set the element's dataset for debugging
        info.el.dataset.roomColor = info.event.backgroundColor;
    }
}

/**
 * Apply room colors to booking events (events without type)
 * @param {Object} info - FullCalendar eventDidMount info object
 */
function applyBookingColors(info) {
    if (info.event.backgroundColor && (!info.event.extendedProps || !info.event.extendedProps.type)) {
        // Set CSS custom property for the room color
        info.el.style.setProperty('--room-color', info.event.backgroundColor);
        
        // Force apply the color with !important
        info.el.style.setProperty('background-color', info.event.backgroundColor, 'important');
        info.el.style.setProperty('border-color', info.event.backgroundColor, 'important');
        info.el.style.setProperty('background', info.event.backgroundColor, 'important');
        
        // Also set the element's dataset for debugging
        info.el.dataset.roomColor = info.event.backgroundColor;
    }
}

/**
 * Set data-type attribute for events
 * @param {Object} info - FullCalendar eventDidMount info object
 */
function setEventDataType(info) {
    if (info.event.extendedProps && info.event.extendedProps.type) {
        info.el.setAttribute('data-type', info.event.extendedProps.type);
    }
}
