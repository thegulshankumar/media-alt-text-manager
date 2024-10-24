jQuery(document).ready(function($) {
    $('.alt-text-input').each(function() {
        var $input = $(this);
        var postId = $input.data('post-id');
        var nonce = $input.data('nonce');
        var previousAltText = $input.val().trim(); // Store the initial value trimmed
        var typingTimer; // Timer identifier
        let currentRequest; // Variable to hold the current fetch request
        let isRequestLocked = false; // Lock to prevent multiple requests

        // Create success and error icons
        var $successIcon = $('<span class="status-icon success-icon" title="Instantly saved as you type">&#10003;</span>').hide().insertAfter($input); // Check mark (green tick)
        var $errorIcon = $('<span class="status-icon error-icon" title="Failed">&#10007;</span>').hide().insertAfter($input); // Cross mark (error)

        // Apply styles for the success icon (green tick)
        $successIcon.css({
            'display': 'inline-block',
            'margin-left': '8px',
            'background-color': 'green', // Green background
            'border-radius': '50%', // Circular background
            'width': '14px',  // Fixed width
            'height': '14px',  // Fixed height
            'text-align': 'center',
            'line-height': '14px',  // Center the icon vertically
            'color': 'white',  // Color for the tick
            'font-size': '10px', // Small font size for tick
            'cursor': 'pointer' // Add pointer cursor to show it's interactive
        });

        // Apply styles for the error icon (cross)
        $errorIcon.css({
            'display': 'inline-block',
            'margin-left': '8px',
            'background-color': 'white', // White background for the error icon
            'border-radius': '50%', // Make it circular
            'width': '14px',
            'height': '14px',
            'text-align': 'center',
            'line-height': '14px', // Center the cross vertically
            'color': 'red', // Red color for the cross
            'font-size': '10px', // Small font size for cross
            'cursor': 'pointer' // Add pointer cursor to show it's interactive
        });

        // Function to check if input is empty and apply yellow placeholder background
        function updatePlaceholderStyle() {
            if (!$input.val()) {
                $input.css('background-color', 'lightyellow'); // Light yellow background for empty alt text
            } else {
                $input.css('background-color', ''); // Reset background if not empty
            }
        }

        // Initial check on page load
        updatePlaceholderStyle();

        // Function to save alt text
        function saveAltText(altText) {
            // Only save if the new alt text is different from the previous one
            if (altText.trim() !== previousAltText && !isRequestLocked) {
                isRequestLocked = true; // Lock the request

                // Abort any previous request
                if (currentRequest) {
                    currentRequest.abort();
                }

                // Send request to save the alt text
                currentRequest = $.ajax({
                    url: `${wpApiSettings.root}matm/v1/save-alt-text`,
                    method: 'POST',
                    contentType: 'application/json',
                    headers: {
                        'X-WP-Nonce': wpApiSettings.nonce
                    },
                    data: JSON.stringify({
                        post_id: postId,
                        alt_text: altText,
                        nonce: nonce
                    }),
                    success: function(response) {
                        previousAltText = altText.trim(); // Update previous alt text
                        isRequestLocked = false; // Unlock the request
                        $successIcon.show(); // Show success icon
                        $errorIcon.hide(); // Hide error icon if it was visible
                        updatePlaceholderStyle(); // Update placeholder style after saving
                    },
                    error: function() {
                        isRequestLocked = false; // Unlock the request
                        $errorIcon.show(); // Show error icon
                        $successIcon.hide(); // Hide success icon if it was visible
                    }
                });
            }
        }

        // Event when user is typing
        $input.on('input', function() {
            clearTimeout(typingTimer); // Clear the timer
            typingTimer = setTimeout(() => {
                var altText = $input.val(); // Get the current input value
                saveAltText(altText); // Call saveAltText
                updatePlaceholderStyle(); // Check placeholder styling during typing
            }, 250);
        });

        // Event when input loses focus
        $input.on('blur', function() {
            clearTimeout(typingTimer); // Clear the timer
            var altText = $input.val(); // Get the current input value
            saveAltText(altText); // Save immediately on focus out
            updatePlaceholderStyle(); // Check placeholder styling on blur
        });

        // Handle the Tab, Shift + Tab, Up Arrow, and Down Arrow keys to navigate between alt-text-input fields
        $input.on('keydown', function(e) {
            if (e.key === 'Tab' && !e.shiftKey) {
                e.preventDefault(); // Prevent the default tab behavior
                var $nextInput = $('.alt-text-input').eq($('.alt-text-input').index(this) + 1); // Get the next alt input field
                if ($nextInput.length) {
                    $nextInput.focus(); // Move to the next alt text input field
                }
            } else if (e.key === 'Tab' && e.shiftKey) {
                e.preventDefault(); // Prevent the default tab behavior
                var $prevInput = $('.alt-text-input').eq($('.alt-text-input').index(this) - 1); // Get the previous alt input field
                if ($prevInput.length) {
                    $prevInput.focus(); // Move to the previous alt text input field
                }
            } else if (e.key === 'ArrowDown') {
                e.preventDefault(); // Prevent scrolling when pressing down
                var $nextInput = $('.alt-text-input').eq($('.alt-text-input').index(this) + 1); // Move to the next input field on down arrow
                if ($nextInput.length) {
                    $nextInput.focus(); // Focus the next input
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault(); // Prevent scrolling when pressing up
                var $prevInput = $('.alt-text-input').eq($('.alt-text-input').index(this) - 1); // Move to the previous input field on up arrow
                if ($prevInput.length) {
                    $prevInput.focus(); // Focus the previous input
                }
            }
        });

        // Initial placeholder style setup
        updatePlaceholderStyle();

        // Hide the icons initially
        $successIcon.hide();
        $errorIcon.hide();
    });
});