/**
 * Phone Number Validation with Mutation Observer
 * Automatically applies number-only restriction to phone/mobile input fields
 * Mitsubishi Motors Website
 */

document.addEventListener("DOMContentLoaded", () => {
  
  const restrictToNumbers = input => {
    // Only apply if there's no existing input formatter already handling non-numeric input
    // Check if any existing event listeners are already handling this input
    const hasExistingFormatter = hasExistingPhoneFormatter(input);
    if (hasExistingFormatter) {
      return; // Skip if already handled by another script
    }
    
    // Store current cursor position
    const cursorPosition = input.selectionStart;
    const originalValue = input.value;
    
    // Remove all non-numeric characters
    input.value = input.value.replace(/\D/g, "");
    
    // Restore cursor position if value changed
    if (originalValue !== input.value) {
      // Adjust cursor position based on how many characters were removed
      const removedChars = originalValue.length - input.value.length;
      const newPosition = Math.max(0, cursorPosition - removedChars);
      input.setSelectionRange(newPosition, newPosition);
    }
    
    // Trigger input event for any listeners
    input.dispatchEvent(new Event('input', { bubbles: true }));
  };
  
  // Helper function to detect if a phone input is already being handled by another formatter
  const hasExistingPhoneFormatter = input => {
    // Check for phone formatting patterns in the page's scripts
    // This is a heuristic approach to avoid conflicts
    
    // Skip if the input has specific formatting attributes
    if (input.placeholder && input.placeholder.includes('(') && input.placeholder.includes(')')) {
      return true;
    }
    
    // Skip if the input is in a test drive form (we know it has its own formatter)
    const form = input.closest('form');
    if (form && (form.id && form.id.includes('testdrive') || 
        form.action && form.action.includes('test_drive'))) {
      return true;
    }
    
    // Skip if the page has phone formatting scripts
    const scripts = document.querySelectorAll('script');
    for (let script of scripts) {
      if (script.textContent && 
          (script.textContent.includes('replace(/\\D/g') || 
           script.textContent.includes('format.*phone') ||
           script.textContent.includes('auto-format'))) {
        return true;
      }
    }
    
    return false;
  };

  const applyToExisting = () => {
    // Target phone-related input fields
    const selectors = [
      'input[type="text"][name*="phone"]',
      'input[type="text"][name*="mobile"]',
      'input[type="tel"]'
    ];
    
    selectors.forEach(selector => {
      document.querySelectorAll(selector).forEach(input => {
        // Skip if already processed
        if (input.hasAttribute('data-phone-validated')) return;
        
        // Mark as processed
        input.setAttribute('data-phone-validated', 'true');
        
        // Add input event listener
        restrictToNumbers(input);
        
        // Add event listeners for future inputs
        input.addEventListener('input', () => restrictToNumbers(input));
        
        // Handle paste events
        input.addEventListener('paste', (e) => {
          // Use timeout to allow paste to complete before processing
          setTimeout(() => restrictToNumbers(input), 0);
        });
        
        // Handle drop events
        input.addEventListener('drop', (e) => {
          setTimeout(() => restrictToNumbers(input), 0);
        });
        
        // Clean up initial value
        if (input.value) {
          restrictToNumbers(input);
        }
      });
    });
  };

  // Apply to existing elements on page load
  applyToExisting();

  // Set up MutationObserver for dynamically added content
  const observer = new MutationObserver((mutations) => {
    let needsUpdate = false;
    
    mutations.forEach(mutation => {
      if (mutation.type === 'childList') {
        // Check if any added nodes contain relevant inputs
        mutation.addedNodes.forEach(node => {
          if (node.nodeType === Node.ELEMENT_NODE) {
            if (node.matches && node.matches('input')) {
              if (node.matches('input[type="text"][name*="phone"]') ||
                  node.matches('input[type="text"][name*="mobile"]') ||
                  node.matches('input[type="tel"]')) {
                needsUpdate = true;
              }
            }
            
            // Check children recursively
            if (node.querySelectorAll) {
              const phoneInputs = node.querySelectorAll(
                'input[type="text"][name*="phone"], ' +
                'input[type="text"][name*="mobile"], ' +
                'input[type="tel"]'
              );
              if (phoneInputs.length > 0) {
                needsUpdate = true;
              }
            }
          }
        });
      }
    });
    
    if (needsUpdate) {
      // Use timeout to ensure DOM is fully updated
      setTimeout(applyToExisting, 0);
    }
  });

  // Start observing the entire document
  observer.observe(document.body, {
    childList: true,
    subtree: true
  });

  // Expose the function for manual use if needed
  window.phoneValidation = {
    applyToExisting,
    restrictToNumbers
  };
  
});
